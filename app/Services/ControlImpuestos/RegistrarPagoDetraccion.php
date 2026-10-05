<?php

namespace App\Services\ControlImpuestos;

use App\Models\AbonoDeuda;
use App\Models\CatalogoMedioPago;
use App\Models\Cliente;
use App\Models\DeudaTributaria;
use App\Models\Operacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Si la constancia trae un cargo en cuenta de detracciones, deja el tributo y el pago en el periodo.
 */
final class RegistrarPagoDetraccion
{
    public function __construct(private RegistrarTributoPeriodo $registro) {}

    /**
     * @param  list<array{tributo: string, importe: float, medio_codigo: ?string, fecha: ?string, numero_operacion: ?string}>  $pagos
     * @return array{cantidad: int, alertas: list<array{nivel: string, codigo: string, mensaje: string}>}
     */
    public function registrar(Cliente $cliente, string $periodo, array $pagos): array
    {
        $alertas = [];
        $cantidad = 0;
        $medio = $this->codigoDetracciones($cliente->estudio_id);

        foreach ($pagos as $pago) {
            if (($pago['medio_codigo'] ?? null) !== 'detracciones' || (float) $pago['importe'] <= 0) {
                continue;
            }

            if ($medio === null) {
                $alertas[] = $this->alerta(
                    'El pago de S/ '.number_format((float) $pago['importe'], 2).' es con detracciones, pero ese medio no está en Códigos SUNAT.',
                );

                continue;
            }

            $resultado = DB::transaction(function () use ($cliente, $periodo, $pago, $medio): string {
                $operacion = Operacion::query()->firstOrCreate(
                    [
                        'cliente_id' => $cliente->id,
                        'periodo' => $periodo,
                    ],
                    [
                        'estudio_id' => $cliente->estudio_id,
                    ],
                );

                $deuda = $operacion->deudasTributarias()->where('tributo_codigo', $pago['tributo'])->first();
                $montoDeuda = max((float) $pago['importe'], (float) ($deuda->monto_deuda_inicial ?? 0));

                try {
                    $this->registro->registrar($operacion, [
                        'deuda_id' => $deuda?->id,
                        'tributo_codigo' => $pago['tributo'],
                        'monto_deuda_inicial' => $montoDeuda,
                        'vencimiento_deuda' => $deuda?->vencimiento_deuda?->format('Y-m-d'),
                        'permitir_ajuste_abono' => true,
                    ]);
                } catch (ValidationException $exception) {
                    return 'rechazado';
                }

                $deuda = $operacion->deudasTributarias()->where('tributo_codigo', $pago['tributo'])->first();
                if (! $deuda instanceof DeudaTributaria) {
                    return 'rechazado';
                }

                $marca = $this->marca($pago);
                $yaExiste = $deuda->abonos()->where('observacion', $marca)->exists();
                if ($yaExiste) {
                    return 'repetido';
                }

                AbonoDeuda::query()->create([
                    'estudio_id' => $operacion->estudio_id,
                    'operacion_id' => $operacion->id,
                    'deuda_tributaria_id' => $deuda->id,
                    'monto' => $pago['importe'],
                    'fecha_pago' => $this->fecha($pago['fecha'] ?? null),
                    'medio_pago' => $medio,
                    'observacion' => $marca,
                ]);
                $operacion->refrescarResumenDeudas();

                return 'nuevo';
            });

            if ($resultado === 'nuevo') {
                $cantidad++;
                $alertas[] = $this->alerta(
                    'Registré S/ '.number_format((float) $pago['importe'], 2).' del tributo '.$pago['tributo'].' con cargo en cuenta de detracciones.',
                    'pago_detracciones',
                );
            } elseif ($resultado === 'repetido') {
                $alertas[] = $this->alerta(
                    'El pago con detracciones de S/ '.number_format((float) $pago['importe'], 2).' del tributo '.$pago['tributo'].' ya estaba registrado.',
                    'pago_ya_registrado',
                    'info',
                );
            } else {
                $alertas[] = $this->alerta(
                    'Vi un pago con detracciones del tributo '.$pago['tributo'].', pero no pude registrarlo.',
                );
            }
        }

        return ['cantidad' => $cantidad, 'alertas' => $alertas];
    }

    private function codigoDetracciones(?int $estudioId): ?string
    {
        $consulta = CatalogoMedioPago::query()->where('activo', true);
        if ($estudioId) {
            $consulta->where('estudio_id', $estudioId);
        }

        $exacto = (clone $consulta)->where('codigo', 'detracciones')->value('codigo');
        if (is_string($exacto) && $exacto !== '') {
            return $exacto;
        }

        $parecido = (clone $consulta)->where('denominacion', 'like', '%detracc%')->value('codigo');

        return is_string($parecido) && $parecido !== '' ? $parecido : null;
    }

    /**
     * @param  array{tributo: string, importe: float, numero_operacion: ?string, fecha: ?string}  $pago
     */
    private function marca(array $pago): string
    {
        $numero = trim((string) ($pago['numero_operacion'] ?? ''));
        if ($numero !== '') {
            return 'NPS '.$numero;
        }

        return 'Detracciones '.$pago['tributo'].' '.number_format((float) $pago['importe'], 2, '.', '');
    }

    private function fecha(?string $texto): string
    {
        if (is_string($texto) && preg_match('/(\d{2}\/\d{2}\/\d{4})/', $texto, $coincidencia) === 1) {
            return Carbon::createFromFormat('d/m/Y', $coincidencia[1])->toDateString();
        }

        return now()->toDateString();
    }

    /**
     * @return array{nivel: string, codigo: string, mensaje: string}
     */
    private function alerta(string $mensaje, string $codigo = 'pago_no_registrado', string $nivel = 'advertencia'): array
    {
        return [
            'nivel' => $nivel,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
        ];
    }
}
