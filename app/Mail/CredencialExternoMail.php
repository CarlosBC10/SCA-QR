<?php
namespace App\Mail;

use App\Models\SolicitudExt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Services\CredencialService;

class CredencialExternoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SolicitudExt $solicitud,
        public string $nombreDestinatario,
        public ?string $motivo = null,
        public ?array $aprobados = null,
        public ?array $rechazados = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Credenciales de acceso - Solicitud #' . $this->solicitud->id_solicitud_ext,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.credencial_externo',
            with: [
                'nombreDestinatario' => $this->nombreDestinatario,
                'solicitud'          => $this->solicitud,
                'motivo'             => $this->motivo,
                'aprobados'          => $this->aprobados,
                'rechazados'         => $this->rechazados,
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        // 1. Adjuntar PNGs individuales
        foreach ($this->solicitud->externos as $detalle) {
            if ($detalle->estado_qr === 'autorizado' && $detalle->codigo_qr) {
                try {
                    $pngData = CredencialService::generarPngIndividual($this->solicitud, $detalle);
                    $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromData(
                        fn () => $pngData,
                        'credencial_' . \Str::slug($detalle->externo?->nombre_completo ?? 'externo') . '.png'
                    )->withMime('image/png');
                } catch (\Exception $e) {
                    \App\Models\LogSistema::registrar('ERROR_NOTIF', 'Error al generar PNG: ' . $e->getMessage());
                }
            }
        }

        // 2. Adjuntar PDF con todas
        try {
            $pdfData = CredencialService::generarPdfTodas($this->solicitud);
            $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromData(
                fn () => $pdfData,
                'credenciales_solicitud_' . $this->solicitud->id_solicitud_ext . '.pdf'
            )->withMime('application/pdf');
        } catch (\Exception $e) {
            \App\Models\LogSistema::registrar('ERROR_NOTIF', 'Error al generar PDF: ' . $e->getMessage());
        }

        return $attachments;
    }
}