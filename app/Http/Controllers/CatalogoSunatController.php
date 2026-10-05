<?php

namespace App\Http\Controllers;

use App\Models\CatalogoMedioPago;
use App\Models\CatalogoTributo;
use App\Services\Catalogos\CatalogoSunat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogoSunatController extends Controller
{
    public function index(CatalogoSunat $catalogo): View
    {
        $catalogo->asegurar();

        return view('ajustes.codigos-sunat', [
            'tributos' => $catalogo->tributos(),
            'medios' => $catalogo->medios(),
            'roles' => CatalogoSunat::ROLES,
            'formularios' => CatalogoSunat::FORMULARIOS,
            'regimenes' => CatalogoSunat::REGIMENES_RENTA,
        ]);
    }

    public function storeTributo(Request $request, CatalogoSunat $catalogo): RedirectResponse
    {
        $catalogo->crearTributo($request->only(['codigo', 'denominacion', 'nombre_corto', 'formulario', 'rol', 'regimenes']));

        return redirect()->route('ajustes.codigos')->with('success', 'El tributo quedó disponible en Control de impuestos.');
    }

    public function updateTributo(Request $request, CatalogoTributo $catalogoTributo, CatalogoSunat $catalogo): RedirectResponse
    {
        $datos = $request->only(['codigo', 'denominacion', 'nombre_corto', 'formulario', 'rol', 'regimenes']);
        $datos['activo'] = $request->boolean('activo');
        $catalogo->actualizarTributo($catalogoTributo, $datos);

        return redirect()->route('ajustes.codigos')->with('success', 'El tributo '.$catalogoTributo->codigo.' quedó actualizado.');
    }

    public function destroyTributo(CatalogoTributo $catalogoTributo, CatalogoSunat $catalogo): RedirectResponse
    {
        $codigo = $catalogoTributo->codigo;
        $catalogo->eliminarTributo($catalogoTributo);

        return redirect()->route('ajustes.codigos')->with('success', 'Se eliminó el tributo '.$codigo.'.');
    }

    public function storeMedio(Request $request, CatalogoSunat $catalogo): RedirectResponse
    {
        $catalogo->crearMedio($request->only(['codigo', 'denominacion']));

        return redirect()->route('ajustes.codigos')->with('success', 'El medio de pago quedó disponible al registrar un pago.');
    }

    public function updateMedio(Request $request, CatalogoMedioPago $catalogoMedioPago, CatalogoSunat $catalogo): RedirectResponse
    {
        $catalogo->actualizarMedio($catalogoMedioPago, [
            'codigo' => $request->input('codigo'),
            'denominacion' => $request->input('denominacion'),
            'activo' => $request->boolean('activo'),
        ]);

        return redirect()->route('ajustes.codigos')->with('success', 'El medio de pago quedó actualizado.');
    }

    public function destroyMedio(CatalogoMedioPago $catalogoMedioPago, CatalogoSunat $catalogo): RedirectResponse
    {
        $nombre = $catalogoMedioPago->denominacion;
        $catalogo->eliminarMedio($catalogoMedioPago);

        return redirect()->route('ajustes.codigos')->with('success', 'Se eliminó el medio de pago '.$nombre.'.');
    }
}
