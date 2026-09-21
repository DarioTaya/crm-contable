<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Muestra el formulario de inicio de sesión.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Procesa la solicitud de inicio de sesión con control de fuerza bruta.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'email' => "Demasiados intentos fallidos. Intente nuevamente en {$seconds} segundos.",
            ])->onlyInput('email');
        }

        $remember = $request->boolean('remember');

        // Verificar si el usuario existe para dar feedback claro si está inactivo
        $user = User::where('email', $credentials['email'])->first();

        if ($user && ! $user->activo) {
            RateLimiter::hit($throttleKey);

            return back()->withErrors([
                'email' => 'Esta cuenta de usuario ha sido desactivada por la administración del estudio.',
            ])->onlyInput('email');
        }

        if (Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'activo' => true], $remember)) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($throttleKey);

        return back()->withErrors([
            'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ])->onlyInput('email');
    }

    /**
     * Cierra la sesión activa del usuario.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
