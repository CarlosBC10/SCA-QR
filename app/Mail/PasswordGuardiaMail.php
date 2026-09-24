<?php
// app/Mail/PasswordGuardiaMail.php
//
// M3: Correo con las credenciales TEMPORALES de un GUARDIA.
//
// A diferencia de PasswordTemporalMail (que va al propio usuario), este
// correo va al OPERADOR que crea o resetea al guardia (los guardias no
// tienen correo institucional propio). El operador entrega la contraseña
// al guardia por un medio seguro.
//
// El correo incluye las instrucciones del flujo M3:
//   1. El guardia inicia sesión en el PANEL WEB (no kiosko).
//   2. El sistema le exige cambiar la contraseña (DebeCambiarPassword).
//   3. Solo con la contraseña definitiva podrá activar el kiosko —
//      la temporal NO funciona en el kiosko (password_verify directo
//      rechaza el prefijo 'temp:' por diseño).

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordGuardiaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nombreGuardia,      // Nombre del guardia
        public string $usernameGuardia,    // Username asignado (G-XXXX)
        public string $passwordTemporal,   // Contraseña temporal (sin hash)
        public string $loginUrl,           // URL del login del panel web
        public string $nombreOperador,     // Quién crea/resetea (destinatario)
        public string $contexto            // 'crear' | 'resetear'
    ) {}

    public function envelope(): Envelope
    {
        $asunto = $this->contexto === 'crear'
            ? 'Credenciales del guardia ' . $this->nombreGuardia . 'SCA'
            : 'Nueva contraseña temporal del guardia ' . $this->nombreGuardia;
        return new Envelope(
            subject: $asunto,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password_guardia',
        );
    }
}