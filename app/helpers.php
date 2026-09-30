<?php

/**
 * ============================================================
 * helpers.php — Funciones globales del sistema SCA-QR
 * ============================================================
 *
 * Este archivo contiene funciones helper que se usan en toda
 * la aplicación (controllers, views, etc.).
 *
 * Para que Laravel cargue este archivo automáticamente,
 * se agregó la entrada "files" en composer.json (ver M2 Parte 2).
 *
 * Funciones disponibles:
 *   sca_modal_flash($tipo, $titulo, $mensaje, $detalles = null)
 * ============================================================
 */

if (!function_exists('sca_modal_flash')) {

    /**
     * Construye el array estructurado para el flash 'sca_modal'.
     *
     * Uso típico desde un controller:
     *
     *   return redirect()->route('solicitudes.bandeja')
     *       ->with('sca_modal', sca_modal_flash('success', 'Solicitud creada', 'La solicitud #X fue creada.'));
     *
     * Tipos soportados (deben coincidir con la API de sca-modal.js):
     *   - 'success'  → modal verde (check)
     *   - 'error'    → modal rojo (X)
     *   - 'warning'  → modal ámbar (triángulo)
     *   - 'info'     → modal azul (i)
     *   - 'loading'  → modal bloqueante (raro usarlo como flash, pero soportado)
     *
     * @param string      $tipo    Uno de: success, error, warning, info, loading
     * @param string      $titulo  Título corto del modal (se muestra en negrita)
     * @param string      $mensaje Mensaje del cuerpo (HTML permitido: <strong>, <ul>, etc.)
     * @param array|null  $detalles Opcional. Array de bloques de detalle, cada uno con:
     *                              [
     *                                  'titulo' => 'Aprobados (2)',
     *                                  'tipo'   => 'success' | 'error',
     *                                  'items'  => ['Nombre 1', 'Nombre 2']
     *                              ]
     *
     * @return array Array listo para pasar a ->with('sca_modal', ...)
     */
    function sca_modal_flash($tipo, $titulo, $mensaje, $detalles = null)
    {
        $flash = [
            'tipo'    => $tipo,
            'titulo'  => $titulo,
            'mensaje' => $mensaje,
        ];

        // Solo agregamos 'detalles' si se pasó (para no mandar null al JS)
        if (!empty($detalles)) {
            $flash['detalles'] = $detalles;
        }

        return $flash;
    }
}