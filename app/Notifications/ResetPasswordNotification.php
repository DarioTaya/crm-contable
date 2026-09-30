<?php

namespace App\Notifications;

use App\Models\Estudio;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * Crea una nueva instancia de la notificación.
     */
    public function __construct(public string $token) {}

    /**
     * Obtiene los canales de entrega de la notificación.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Obtiene la representación del correo de la notificación.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $estudio = $notifiable->estudio ?? Estudio::first();
        $estudioNombre = $estudio ? $estudio->nombreParaMostrar() : 'DeContador';

        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject("Restablecer contraseña — {$estudioNombre}")
            ->greeting("¡Hola, {$notifiable->name}!")
            ->line("Recibes este correo porque se solicitó un restablecimiento de contraseña para tu cuenta de usuario en {$estudioNombre}.")
            ->action('Restablecer mi Contraseña', $url)
            ->line('Este enlace de recuperación expirará en 60 minutos por motivos de seguridad.')
            ->line('Si no solicitaste este cambio, puedes ignorar este mensaje de forma segura. Tu cuenta y accesos permanecen protegidos.')
            ->salutation("Atentamente,\nEquipo de Seguridad — {$estudioNombre}");
    }
}
