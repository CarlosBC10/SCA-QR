<?php
namespace App\Mail;

use App\Models\SolicitudInt;
use App\Models\SolicitudExt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificacionSolicitudMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SolicitudInt|SolicitudExt $solicitud,
        public string $nombreDestinatario,
        public string $asunto,
        public string $titulo,
        public string $mensaje,
        public ?string $motivo = null,
        public ?array $aprobados = null,
        public ?array $rechazados = null,
        public ?array $personas = null,
        public ?string $creadorNombre = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notificacion_solicitud',
            with: [
                'nombreDestinatario' => $this->nombreDestinatario,
                'titulo'             => $this->titulo,
                'mensaje'            => $this->mensaje,
                'motivo'             => $this->motivo,
                'aprobados'          => $this->aprobados,
                'rechazados'         => $this->rechazados,
                'personas'           => $this->personas,
                'creadorNombre'      => $this->creadorNombre,
                'solicitud'          => $this->solicitud,
            ],
        );
    }
}