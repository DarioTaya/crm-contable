<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            'dni' => ['nullable', 'string', 'max:15'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'foto_perfil' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp,gif,avif', 'max:5120'],
            'eliminar_foto' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('eliminar_foto')) {
            if ($user->foto_perfil && Storage::disk('public')->exists($user->foto_perfil)) {
                Storage::disk('public')->delete($user->foto_perfil);
            }
            $user->foto_perfil = null;
        } elseif ($request->hasFile('foto_perfil')) {
            if ($user->foto_perfil && Storage::disk('public')->exists($user->foto_perfil)) {
                Storage::disk('public')->delete($user->foto_perfil);
            }
            $user->foto_perfil = $request->file('foto_perfil')->store('perfiles/'.$user->id, 'public');
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'dni' => $validated['dni'] ?? null,
            'cargo' => $validated['cargo'] ?? null,
            'telefono' => $validated['telefono'] ?? null,
            'fecha_nacimiento' => $validated['fecha_nacimiento'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'foto_perfil' => $user->foto_perfil,
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
