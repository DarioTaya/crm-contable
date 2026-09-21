<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\RedirectResponse;

class NotificacionController extends Controller
{
    /**
     * Alterna el estado de lectura de la notificación (leído / pendiente).
     */
    public function toggleLeido(Notificacion $notificacion): RedirectResponse
    {
        $notificacion->update([
            'leido' => ! $notificacion->leido,
        ]);

        $mensaje = $notificacion->leido
            ? 'Notificación marcada como leída.'
            : 'Notificación marcada como pendiente.';

        return back()->with('success', $mensaje);
    }
}
