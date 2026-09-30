<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PerfilController extends Controller
{
    /**
     * Muestra la vista de perfil del usuario en sesión.
     */
    public function edit(): View
    {
        $user = Auth::user();

        return view('perfil.edit', compact('user'));
    }

    /**
     * Actualiza los datos personales del usuario en sesión.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'telefono' => ['nullable', 'string', 'max:50'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
        ]);

        return redirect()->route('perfil.edit')->with('success', 'Tus datos de perfil han sido actualizados.');
    }

    /**
     * Cambia la contraseña del usuario en sesión.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = Auth::user();
        $user->update([
            'password' => Hash::make($validated['password']),
            'password_plain' => $validated['password'],
        ]);

        return redirect()->route('perfil.edit')->with('success', 'Tu contraseña ha sido modificada correctamente.');
    }
}
