<?php

namespace App\Services\Navegacion;

use App\Enums\EstadoCliente;
use App\Models\Cliente;
use Illuminate\Http\Request;

/**
 * Empresa que el contador tiene en la mira para el acceso rápido.
 *
 * La ficha abierta manda: si la ruta es un expediente, esa empresa es la que
 * se muestra. En el resto del CRM se recuerda la última empresa elegida en la
 * sesión. El listado nunca sale del alcance del usuario (estudio y, si es
 * asistente, cartera asignada).
 */
class SelectorEmpresa
{
    public const SESSION_KEY = 'empresa_activa_id';

    public const LIMITE = 40;

    /**
     * Rutas en las que el expediente visible pasa a ser la empresa activa.
     * Los accesos a portales no están aquí: abrir SUNAT de otra empresa no
     * debe cambiar la ficha que el contador tiene en pantalla.
     *
     * @var list<string>
     */
    public const RUTAS_EXPEDIENTE = [
        'clientes.show',
        'clientes.notificaciones',
        'clientes.operaciones',
        'clientes.sire',
    ];

    public function actual(Request $request): ?Cliente
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return null;
        }

        $enRuta = $request->route('cliente');

        if ($enRuta instanceof Cliente && $usuario->can('view', $enRuta)) {
            return $enRuta;
        }

        $id = $request->session()->get(self::SESSION_KEY);

        if (! is_numeric($id)) {
            return null;
        }

        $cliente = Cliente::query()->paraUsuario($usuario)->whereKey((int) $id)->first();

        if ($cliente === null) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $cliente;
    }

    public function recordar(Request $request, Cliente $cliente): void
    {
        $request->session()->put(self::SESSION_KEY, $cliente->id);
    }

    /**
     * @return array{empresas: list<array<string, mixed>>, hay_mas: bool}
     */
    public function buscar(string $termino): array
    {
        $termino = trim($termino);

        $consulta = Cliente::query()
            ->paraUsuario()
            ->when($termino !== '', function ($query) use ($termino): void {
                $like = '%'.addcslashes($termino, '%_\\').'%';

                $query->where(function ($inner) use ($like): void {
                    $inner->whereRaw('clientes.razon_social LIKE ? ESCAPE ?', [$like, '\\'])
                        ->orWhereRaw('clientes.ruc LIKE ? ESCAPE ?', [$like, '\\']);
                });
            })
            ->orderBy('razon_social')
            ->orderBy('id');

        $filas = (clone $consulta)->limit(self::LIMITE + 1)->get([
            'id',
            'razon_social',
            'ruc',
            'estado',
        ]);

        $hayMas = $filas->count() > self::LIMITE;

        return [
            'empresas' => $filas
                ->take(self::LIMITE)
                ->map(fn (Cliente $cliente): array => $this->presentar($cliente))
                ->values()
                ->all(),
            'hay_mas' => $hayMas,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     razon_social: string,
     *     ruc: string,
     *     estado_etiqueta: string|null,
     *     sunat: string,
     *     sunafil: string,
     *     carpeta: string,
     *     secciones: array<string, string>
     * }
     */
    public function presentar(Cliente $cliente): array
    {
        $secciones = [];

        foreach (self::RUTAS_EXPEDIENTE as $ruta) {
            $secciones[$ruta] = route($ruta, $cliente);
        }

        return [
            'id' => $cliente->id,
            'razon_social' => $cliente->razon_social,
            'ruc' => $cliente->ruc,
            'estado_etiqueta' => $this->etiquetaEstado($cliente->estado),
            'sunat' => route('clientes.sunat', $cliente),
            'sunafil' => route('clientes.sunafil', $cliente),
            'carpeta' => route('clientes.show', $cliente),
            'secciones' => $secciones,
        ];
    }

    private function etiquetaEstado(EstadoCliente $estado): ?string
    {
        return match ($estado) {
            EstadoCliente::Activo => null,
            EstadoCliente::Suspendido => 'Suspendido',
            EstadoCliente::Retirado => 'Baja',
        };
    }
}
