<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    /**
     * Muestra la lista de usuarios (equipo) del estudio actual.
     */
    public function index(): View
    {
        $estudioId = Auth::user()->estudio_id ?? 1;

        $usuarios = User::where('estudio_id', $estudioId)
            ->withCount('clientes')
            ->orderByRaw("CASE WHEN rol = 'admin' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        return view('usuarios.index', compact('usuarios'));
    }

    /**
     * Muestra el formulario para registrar un nuevo miembro del equipo.
     */
    public function create(): View
    {
        $estudioId = Auth::user()->estudio_id ?? 1;
        $clientes = Cliente::where('estudio_id', $estudioId)->orderBy('razon_social')->get();

        return view('usuarios.create', compact('clientes'));
    }

    /**
     * Almacena un nuevo usuario en el estudio.
     */
    public function store(Request $request): RedirectResponse
    {
        $estudioId = Auth::user()->estudio_id ?? 1;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:6'],
            'rol' => ['required', Rule::in(['admin', 'asistente'])],
            'clientes' => ['nullable', 'array'],
            'clientes.*' => ['integer', Rule::exists('clientes', 'id')->where('estudio_id', $estudioId)],
        ]);

        $user = User::create([
            'estudio_id' => $estudioId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'password' => Hash::make($validated['password']),
            'password_plain' => $validated['password'],
            'rol' => $validated['rol'],
            'activo' => true,
        ]);

        if (! empty($validated['clientes'])) {
            $user->clientes()->sync($validated['clientes']);
        }

        return redirect()->route('usuarios.index')->with('success', "Usuario '{$user->name}' registrado exitosamente con rol de ".ucfirst($user->rol).'.');
    }

    /**
     * Muestra la ficha y perfil detallado del usuario.
     */
    public function show(User $usuario): View
    {
        $currentEstudioId = Auth::user()->estudio_id ?? 1;

        if ($usuario->estudio_id !== $currentEstudioId) {
            abort(403, 'No tiene autorización para ver este usuario.');
        }

        $clientes = Cliente::where('estudio_id', $currentEstudioId)->orderBy('razon_social')->get();
        $assignedClientIds = $usuario->clientes()->pluck('clientes.id')->toArray();
        $modoEdicion = false;

        return view('usuarios.show', compact('usuario', 'clientes', 'assignedClientIds', 'modoEdicion'));
    }

    /**
     * Formulario para editar un usuario (perfil en modo edición).
     */
    public function edit(User $usuario): View
    {
        $currentEstudioId = Auth::user()->estudio_id ?? 1;

        if ($usuario->estudio_id !== $currentEstudioId) {
            abort(403, 'No tiene autorización para gestionar este usuario.');
        }

        $clientes = Cliente::where('estudio_id', $currentEstudioId)->orderBy('razon_social')->get();
        $assignedClientIds = $usuario->clientes()->pluck('clientes.id')->toArray();
        $modoEdicion = true;

        return view('usuarios.show', compact('usuario', 'clientes', 'assignedClientIds', 'modoEdicion'));
    }

    /**
     * Actualiza los datos y asignación de clientes del usuario.
     */
    public function update(Request $request, User $usuario): RedirectResponse
    {
        $currentEstudioId = Auth::user()->estudio_id ?? 1;

        if ($usuario->estudio_id !== $currentEstudioId) {
            abort(403, 'No tiene autorización para gestionar este usuario.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'telefono' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:6'],
            'rol' => ['required', Rule::in(['admin', 'asistente'])],
            'activo' => ['required', 'boolean'],
            'clientes' => ['nullable', 'array'],
            'clientes.*' => ['integer', Rule::exists('clientes', 'id')->where('estudio_id', $currentEstudioId)],
        ]);

        // Evitar que el administrador se desactive a sí mismo o se quite el rol admin
        if ($usuario->id === Auth::id()) {
            if (! $validated['activo']) {
                return back()->withErrors(['activo' => 'No puedes desactivar tu propia cuenta activa de administrador.']);
            }
            if ($validated['rol'] !== 'admin') {
                return back()->withErrors(['rol' => 'No puedes remover tu propio rol de administrador.']);
            }
        }

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'rol' => $validated['rol'],
            'activo' => (bool) $validated['activo'],
        ];

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
            $data['password_plain'] = $validated['password'];
        }

        $usuario->update($data);

        // Sincronizar clientes asignados
        $usuario->clientes()->sync($validated['clientes'] ?? []);

        return redirect()->route('usuarios.show', $usuario)->with('success', "Perfil de '{$usuario->name}' actualizado correctamente.");
    }

    /**
     * Alterna el estado activo/inactivo de un usuario.
     */
    public function toggleActivo(User $usuario): RedirectResponse
    {
        $currentEstudioId = Auth::user()->estudio_id ?? 1;

        if ($usuario->estudio_id !== $currentEstudioId) {
            abort(403, 'No tiene autorización para gestionar este usuario.');
        }

        if ($usuario->id === Auth::id()) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta activa de administrador.');
        }

        $usuario->update(['activo' => ! $usuario->activo]);

        $estadoTxt = $usuario->activo ? 'activado' : 'desactivado';

        return redirect()->route('usuarios.index')->with('success', "Usuario '{$usuario->name}' {$estadoTxt} correctamente.");
    }
}
