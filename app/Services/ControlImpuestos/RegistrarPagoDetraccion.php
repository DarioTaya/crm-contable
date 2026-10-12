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
                $nps = trim((string) ($pago['numero_operacion'] ?? ''));
                $fechaPago = $this->fecha($pago['fecha'] ?? null);
                $montoPago = (float) $pago['importe'];

                // Buscar si la deuda ya tiene un abono registrado para este pago
                $abonosExistentes = $deuda->abonos()->get();

                // Criterio A: Coincidencia por NPS
                $abonoCoincidente = null;
                if ($nps !== '') {
                    $abonoCoincidente = $abonosExistentes->first(function (AbonoDeuda $a) use ($nps, $marca): bool {
                        $obs = (string) $a->observacion;

                        return $obs === $marca || str_contains($obs, $nps);
                    });
                }

                // Criterio B: Coincidencia por medio de detracciones y monto
                if (! $abonoCoincidente) {
                    $abonoCoincidente = $abonosExistentes->first(function (AbonoDeuda $a) use ($montoPago): bool {
                        return $a->esPagoDetracciones() && abs((float) $a->monto - $montoPago) < 0.01;
                    });
                }

                // Criterio C: Si la deuda tiene un único abono de detracciones
                if (! $abonoCoincidente) {
                    $abonosDetracciones = $abonosExistentes->filter(fn (AbonoDeuda $a): bool => $a->esPagoDetracciones());
                    if ($abonosDetracciones->count() === 1) {
                        $abonoCoincidente = $abonosDetracciones->first();
                    }
                }

                if ($abonoCoincidente instanceof AbonoDeuda) {
                    $mismoMonto = abs((float) $abonoCoincidente->monto - $montoPago) < 0.01;
                    $mismaObs = (string) $abonoCoincidente->observacion === $marca;
                    $mismoMedio = (string) ($abonoCoincidente->medio_pago ?? '') === (string) $medio;
                    $mismaFecha = $abonoCoincidente->fecha_pago?->toDateString() === $fechaPago;

                    if ($mismoMonto && $mismaObs && $mismoMedio && $mismaFecha) {
                        // Limpiar posibles duplicados redundantes generados previamente
                        $deuda->abonos()
                            ->whereKeyNot($abonoCoincidente->id)
                            ->where(function ($q) use ($nps, $montoPago, $marca): void {
                                if ($nps !== '') {
                                    $q->where('observacion', 'like', "%{$nps}%");
                                }
                                $q->orWhere('observacion', $marca)
                                    ->orWhere(function ($sub) use ($montoPago): void {
                                        $sub->where('monto', $montoPago)
                                            ->where('medio_pago', 'detracciones');
                                    });
                            })
                            ->get()
                            ->each(function (AbonoDeuda $duplicado): void {
                                $duplicado->delete();
                            });

                        $operacion->refrescarResumenDeudas();

                        return 'repetido';
                    }

                    // En lugar de duplicar, se actualiza/reemplaza con la información oficial de la constancia
                    $abonoCoincidente->update([
                        'monto' => $montoPago,
                        'fecha_pago' => $fechaPago,
                        'medio_pago' => $medio,
                        'observacion' => $marca,
                    ]);

                    // Eliminar cualquier duplicado adicional para esta misma deuda y pago
                    $deuda->abonos()
                        ->whereKeyNot($abonoCoincidente->id)
                        ->where(function ($q) use ($nps, $montoPago, $marca): void {
                            if ($nps !== '') {
                                $q->where('observacion', 'like', "%{$nps}%");
                            }
                            $q->orWhere('observacion', $marca)
                                ->orWhere(function ($sub) use ($montoPago): void {
                                    $sub->where('monto', $montoPago)
                                        ->where('medio_pago', 'detracciones');
                                });
                        })
                        ->get()
                        ->each(function (AbonoDeuda $duplicado): void {
                            $duplicado->delete();
                        });

                    $operacion->refrescarResumenDeudas();

                    return 'reemplazado';
                }

                AbonoDeuda::query()->create([
                    'estudio_id' => $operacion->estudio_id,
                    'operacion_id' => $operacion->id,
                    'deuda_tributaria_id' => $deuda->id,
                    'monto' => $montoPago,
                    'fecha_pago' => $fechaPago,
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
            } elseif ($resultado === 'reemplazado') {
                $cantidad++;
                $alertas[] = $this->alerta(
                    'Se actualizó y vinculó el pago con detracciones de S/ '.number_format((float) $pago['importe'], 2).' del tributo '.$pago['tributo'].' ('.$this->marca($pago).') reemplazando el registro previo sin duplicar.',
                    'pago_actualizado',
                    'info',
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
