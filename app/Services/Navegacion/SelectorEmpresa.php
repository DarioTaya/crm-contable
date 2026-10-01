<?php

namespace App\Services\Navegacion;

use App\Enums\EstadoCliente;
use App\Models\Cliente;
use App\Models\EmpresaFija;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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

    /**
     * Cupo del listado en reposo y de las empresas que el usuario puede fijar.
     */
    public const VISIBLES = 5;

    /**
     * Tope de una búsqueda. El reposo no usa este número: abre solo las cinco.
     */
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
     * Sin texto, devuelve como máximo cinco empresas: primero las fijas del
     * usuario y después las siguientes por razón social. Con texto, busca en
     * toda la cartera visible.
     *
     * @return array{empresas: list<array<string, mixed>>, hay_mas: bool}
     */
    public function buscar(User $usuario, string $termino): array
    {
        $termino = trim($termino);
        $fijas = $this->fijasVisibles($usuario);

        if ($termino === '') {
            return $this->ventana($usuario, $fijas);
        }

        $idsFijas = $fijas->pluck('cliente_id');
        $like = '%'.addcslashes($termino, '%_\\').'%';

        $filas = Cliente::query()
            ->paraUsuario($usuario)
            ->where(function ($inner) use ($like): void {
                $inner->whereRaw('clientes.razon_social LIKE ? ESCAPE ?', [$like, '\\'])
                    ->orWhereRaw('clientes.ruc LIKE ? ESCAPE ?', [$like, '\\']);
            })
            ->orderBy('razon_social')
            ->orderBy('id')
            ->limit(self::LIMITE + 1)
            ->get(['id', 'razon_social', 'ruc', 'estado']);

        $hayMas = $filas->count() > self::LIMITE;

        return [
            'empresas' => $filas
                ->take(self::LIMITE)
                ->map(fn (Cliente $cliente): array => $this->presentar($cliente, $idsFijas->contains($cliente->id)))
                ->values()
                ->all(),
            'hay_mas' => $hayMas,
        ];
    }

    /**
     * Fija o suelta una empresa. El cupo es de cinco; la sexta no desplaza a otra.
     */
    public function alternarFija(User $usuario, Cliente $cliente): bool
    {
        $existente = EmpresaFija::query()
            ->where('user_id', $usuario->id)
            ->where('cliente_id', $cliente->id)
            ->first();

        if ($existente !== null) {
            $existente->delete();

            return false;
        }

        $fijas = $this->fijasVisibles($usuario);

        if ($fijas->count() >= self::VISIBLES) {
            throw ValidationException::withMessages([
                'cliente_id' => 'Ya tienes 5 empresas fijas. Quita una para fijar otra.',
            ]);
        }

        $orden = (int) EmpresaFija::query()->where('user_id', $usuario->id)->max('orden');

        EmpresaFija::query()->create([
            'user_id' => $usuario->id,
            'cliente_id' => $cliente->id,
            'orden' => $orden + 1,
        ]);

        return true;
    }

    public function estaFijada(User $usuario, Cliente $cliente): bool
    {
        return EmpresaFija::query()
            ->where('user_id', $usuario->id)
            ->where('cliente_id', $cliente->id)
            ->exists();
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
     *     secciones: array<string, string>,
     *     fijada: bool
     * }
     */
    public function presentar(Cliente $cliente, bool $fijada = false): array
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
            'fijada' => $fijada,
        ];
    }

    /**
     * @param  Collection<int, EmpresaFija>  $fijas
     * @return array{empresas: list<array<string, mixed>>, hay_mas: bool}
     */
    private function ventana(User $usuario, Collection $fijas): array
    {
        $idsFijas = $fijas->pluck('cliente_id');

        $porId = $idsFijas->isEmpty()
            ? collect()
            : Cliente::query()
                ->paraUsuario($usuario)
                ->whereIn('id', $idsFijas->all())
                ->get(['id', 'razon_social', 'ruc', 'estado'])
                ->keyBy('id');

        $fijadas = $fijas
            ->map(fn (EmpresaFija $fila): ?Cliente => $porId->get($fila->cliente_id))
            ->filter()
            ->take(self::VISIBLES)
            ->values();

        $faltan = self::VISIBLES - $fijadas->count();
        $resto = collect();

        if ($faltan > 0) {
            $resto = Cliente::query()
                ->paraUsuario($usuario)
                ->when(
                    $fijadas->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('id', $fijadas->pluck('id')->all()),
                )
                ->orderBy('razon_social')
                ->orderBy('id')
                ->limit($faltan)
                ->get(['id', 'razon_social', 'ruc', 'estado']);
        }

        $mostradas = $fijadas->concat($resto)->values();

        $hayMas = Cliente::query()
            ->paraUsuario($usuario)
            ->when(
                $mostradas->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $mostradas->pluck('id')->all()),
            )
            ->exists();

        return [
            'empresas' => $mostradas
                ->map(fn (Cliente $cliente): array => $this->presentar($cliente, $idsFijas->contains($cliente->id)))
                ->all(),
            'hay_mas' => $hayMas,
        ];
    }

    /**
     * @return Collection<int, EmpresaFija>
     */
    private function fijasVisibles(User $usuario): Collection
    {
        $filas = $usuario->empresasFijas()->get();

        if ($filas->isEmpty()) {
            return $filas;
        }

        $visibles = Cliente::query()
            ->paraUsuario($usuario)
            ->whereIn('id', $filas->pluck('cliente_id')->all())
            ->pluck('id');

        $huerfanas = $filas->reject(
            fn (EmpresaFija $fila): bool => $visibles->contains($fila->cliente_id),
        );

        if ($huerfanas->isNotEmpty()) {
            EmpresaFija::query()->whereKey($huerfanas->modelKeys())->delete();
            $filas = $filas->reject(
                fn (EmpresaFija $fila): bool => $huerfanas->contains(
                    fn (EmpresaFija $huerfana): bool => $huerfana->is($fila),
                ),
            )->values();
        }

        return $filas;
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
