<?php

namespace App\Http\Middleware;

use App\Models\Cliente;
use App\Services\Navegacion\SelectorEmpresa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Al abrir un expediente, esa empresa queda como la activa del acceso rápido.
 */
class RecordarEmpresaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $cliente = $request->route('cliente');
        $ruta = $request->route()?->getName();

        if (
            $cliente instanceof Cliente
            && is_string($ruta)
            && in_array($ruta, SelectorEmpresa::RUTAS_EXPEDIENTE, true)
            && $request->user()?->can('view', $cliente)
        ) {
            $request->session()->put(SelectorEmpresa::SESSION_KEY, $cliente->id);
        }

        return $next($request);
    }
}
