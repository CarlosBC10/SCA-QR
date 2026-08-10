<?php
// app/Mail/PasswordTemporalMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordTemporalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nombreCompleto,
        public string $username,
        public string $passwordTemporal,
        public string $loginUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Credenciales de acceso - SCA-QR (Fruitex de México)',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password_temporal',
        );
    }
}