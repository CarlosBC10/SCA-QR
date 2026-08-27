<?php
namespace App\Services;

use App\Models\SolicitudExt;
use App\Models\SolicitudExternoDetalle;
use Spatie\Browsershot\Browsershot;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CredencialService
{
    /**
     * Genera el PNG individual de una credencial.
     * Usa Browsershot (Chrome Headless) para renderizar el HTML a PNG.
     * Calidad de impresión (2x device scale factor).
     */
    public static function generarPngIndividual(SolicitudExt $solicitud, SolicitudExternoDetalle $detalle): string
    {
        $logoBase64 = self::generarLogoBase64();

        $html = view('credenciales.credencial_individual', [
            'solicitud'  => $solicitud,
            'externo'    => $detalle->externo,
            'codigo_qr'  => $detalle->codigo_qr,
            'logoBase64' => $logoBase64,
        ])->render();

        // 204x324px = 54mm x 85.6mm a 96 DPI.
        // deviceScaleFactor(2) duplica la resolución a 408x648px (calidad de impresión).
        $chromePath = trim(shell_exec('which google-chrome'));
        if (empty($chromePath)) {
            $chromePath = '/usr/bin/google-chrome'; // Fallback por si no lo encuentra
        }

        return Browsershot::html($html)
            ->setChromePath($chromePath)
            ->windowSize(204, 324)
            ->deviceScaleFactor(2)
            ->screenshot();
    }

    /**
     * Genera el PDF con todas las credenciales en una grilla 3x2.
     * Usa Browsershot para renderizar todo de una sola vez.
     */
    /**
     * Genera el PDF con todas las credenciales en una grilla 3x2.
     * Primero genera los PNGs individuales y luego los embebe en el PDF.
     * Esto asegura que el diseño sea 100% idéntico al PNG individual.
     */
    public static function generarPdfTodas(SolicitudExt $solicitud): string
    {
        $detalles = $solicitud->externos->filter(function($d) {
            return $d->estado_qr === 'autorizado' && $d->codigo_qr;
        });

        $credencialesData = [];
        foreach ($detalles as $detalle) {
            try {
                $pngData = self::generarPngIndividual($solicitud, $detalle);
                $credencialesData[] = base64_encode($pngData);
            } catch (\Exception $e) {
                \App\Models\LogSistema::registrar('ERROR_PNG', 'Error generando PNG para PDF: ' . $e->getMessage());
            }
        }

        // Construir el HTML con las imágenes ya generadas
        $imgsHtml = '';
        foreach ($credencialesData as $base64) {
            $imgsHtml .= '<img src="data:image/png;base64,' . $base64 . '" style="width: 54mm; height: 85.6mm; object-fit: cover; border: 0.2mm dashed #ccc; box-sizing: border-box;">';
        }

        $htmlFinal = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page { size: letter portrait; margin: 8mm; }
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { margin: 0; padding: 0; font-family: sans-serif; }
                .grid { display: flex; flex-wrap: wrap; gap: 3mm; justify-content: center; }
            </style>
        </head>
        <body>
            <div class="grid">' . $imgsHtml . '</div>
        </body>
        </html>';

        $chromePath = trim(shell_exec('which google-chrome'));
        if (empty($chromePath)) {
            $chromePath = '/usr/bin/google-chrome';
        }

        return Browsershot::html($htmlFinal)
            ->setChromePath($chromePath)
            ->format('Letter')
            ->margins(8, 8, 8, 8, 'mm')
            ->pdf();
    }

    /**
     * Convierte el logo a base64 para que Browsershot lo pueda cargar.
    */
    private static function generarLogoBase64(): ?string
    {
        $logoPath = public_path('logo-fruitex.png');
        if (file_exists($logoPath)) {
            return base64_encode(file_get_contents($logoPath));
        }
        return null;
    }
}