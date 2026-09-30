<?php

namespace App\Http\Controllers;

use App\Models\AlertaBuzon;
use App\Models\Cliente;
use App\Models\Estudio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Muestra el formulario de inicio de sesión.
     */
    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $rememberedEmail = $request->cookie((string) config('auth.device_remember.email_cookie'));
        $rememberedPassword = $request->cookie((string) config('auth.device_remember.secret_cookie'));

        $estudio = Estudio::first();
        $totalClientes = Cliente::count();
        $totalAlertas = AlertaBuzon::pendientes()->count();
        $totalRiesgo = AlertaBuzon::altaPrioridad()->pendientes()->count();

        return view('auth.login', [
            'rememberedEmail' => is_string($rememberedEmail) ? $rememberedEmail : '',
            'rememberedPassword' => is_string($rememberedPassword) ? $rememberedPassword : '',
            'rememberDevice' => filled($rememberedEmail),
            'estudio' => $estudio,
            'totalClientes' => $totalClientes,
            'totalAlertas' => $totalAlertas,
            'totalRiesgo' => $totalRiesgo,
        ]);
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

            if ($remember) {
                $this->queueDeviceRememberCookies($credentials['email'], $credentials['password']);
            } elseif ($this->hasDeviceRememberCookies($request)) {
                $this->forgetDeviceRememberCookies();
            }

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

    /**
     * Guarda el correo y la contraseña de este equipo en cookies cifradas.
     */
    private function queueDeviceRememberCookies(string $email, string $password): void
    {
        $minutes = (int) config('auth.device_remember.minutes');

        Cookie::queue(cookie(
            (string) config('auth.device_remember.email_cookie'),
            $email,
            $minutes,
        ));

        Cookie::queue(cookie(
            (string) config('auth.device_remember.secret_cookie'),
            $password,
            $minutes,
        ));
    }

    /**
     * Determina si el equipo ya tiene credenciales recordadas.
     */
    private function hasDeviceRememberCookies(Request $request): bool
    {
        return $request->hasCookie((string) config('auth.device_remember.email_cookie'))
            || $request->hasCookie((string) config('auth.device_remember.secret_cookie'));
    }

    /**
     * Elimina las credenciales recordadas en este equipo.
     */
    private function forgetDeviceRememberCookies(): void
    {
        Cookie::queue(Cookie::forget((string) config('auth.device_remember.email_cookie')));
        Cookie::queue(Cookie::forget((string) config('auth.device_remember.secret_cookie')));
    }
}
