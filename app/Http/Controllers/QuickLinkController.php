<?php

namespace App\Http\Controllers;

use App\Models\QuickLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class QuickLinkController extends Controller
{
    /**
     * Guarda un nuevo acceso rápido / enlace oficial.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:1000'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $orden = $validated['orden'] ?? ((int) QuickLink::max('orden') + 1);

        $quickLink = QuickLink::create([
            'label' => $validated['label'],
            'url' => $validated['url'],
            'orden' => $orden,
            'activo' => $request->boolean('activo', true),
        ]);

        Cache::forget('quick_links_activos');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Acceso rápido guardado correctamente.',
                'link' => $quickLink,
            ]);
        }

        return redirect()->route('ajustes.index')->with('success', 'Acceso rápido guardado correctamente.');
    }

    /**
     * Actualiza un acceso rápido existente.
     */
    public function update(Request $request, QuickLink $quickLink): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:1000'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $quickLink->update([
            'label' => $validated['label'],
            'url' => $validated['url'],
            'orden' => $validated['orden'] ?? $quickLink->orden,
            'activo' => $request->has('activo') ? $request->boolean('activo') : $quickLink->activo,
        ]);

        Cache::forget('quick_links_activos');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Acceso rápido actualizado correctamente.',
                'link' => $quickLink,
            ]);
        }

        return redirect()->route('ajustes.index')->with('success', 'Acceso rápido actualizado correctamente.');
    }

    /**
     * Elimina un acceso rápido.
     */
    public function destroy(Request $request, QuickLink $quickLink): JsonResponse|RedirectResponse
    {
        $quickLink->delete();

        Cache::forget('quick_links_activos');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Acceso rápido eliminado correctamente.',
            ]);
        }

        return redirect()->route('ajustes.index')->with('success', 'Acceso rápido eliminado correctamente.');
    }

    /**
     * Reordena la lista de accesos rápidos.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:quick_links,id'],
        ]);

        foreach ($validated['ids'] as $index => $id) {
            QuickLink::where('id', $id)->update(['orden' => $index + 1]);
        }

        Cache::forget('quick_links_activos');

        return response()->json([
            'success' => true,
            'message' => 'Orden de accesos rápidos actualizado.',
        ]);
    }
}
