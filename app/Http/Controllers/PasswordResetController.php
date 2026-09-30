<?php

namespace App\Http\Controllers;

use App\Models\Estudio;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /**
     * Muestra el formulario para solicitar el enlace de recuperación de contraseña.
     */
    public function showForgotPasswordForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $estudio = Estudio::first();

        return view('auth.forgot-password', [
            'estudio' => $estudio,
        ]);
    }

    /**
     * Procesa la solicitud y despacha el correo con el token de recuperación.
     */
    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Por favor ingrese un correo electrónico válido.',
        ]);

        $throttleKey = 'forgot-password|'.Str::lower((string) $request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'email' => "Demasiados intentos recientes. Por favor espere {$seconds} segundos antes de volver a solicitar.",
            ])->onlyInput('email');
        }

        RateLimiter::hit($throttleKey, 60);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            return back()->withErrors([
                'email' => 'No encontramos ningún usuario activo registrado con ese correo electrónico.',
            ])->onlyInput('email');
        }

        if (! $user->activo) {
            return back()->withErrors([
                'email' => 'Esta cuenta se encuentra desactivada por la administración del estudio contable.',
            ])->onlyInput('email');
        }

        $status = Password::broker()->sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            RateLimiter::clear($throttleKey);

            return back()->with('status', 'Hemos enviado un enlace seguro para restablecer tu contraseña. Revisa tu bandeja de entrada o spam.');
        }

        return back()->withErrors([
            'email' => 'No fue posible enviar el correo de recuperación en este momento. Por favor inténtalo más tarde.',
        ])->onlyInput('email');
    }

    /**
     * Muestra el formulario para ingresar la nueva contraseña usando el token recibido.
     */
    public function showResetForm(Request $request, string $token): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $estudio = Estudio::first();

        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'estudio' => $estudio,
        ]);
    }

    /**
     * Valida el token y actualiza la contraseña del usuario de forma sincronizada.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'token.required' => 'El token de seguridad es requerido.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'password.required' => 'Debe ingresar una nueva contraseña.',
            'password.min' => 'La contraseña debe contener al menos 6 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'password_plain' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with(
                'status',
                '¡Tu contraseña ha sido restablecida exitosamente! Ya puedes iniciar sesión con tu nueva contraseña.'
            );
        }

        return back()->withErrors([
            'email' => 'El enlace para restablecer la contraseña es inválido o ha caducado por seguridad. Por favor solicita uno nuevo.',
        ])->onlyInput('email');
    }
}
