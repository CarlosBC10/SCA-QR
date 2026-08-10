<?php
// app/Http/Controllers/NotificacionController.php
//
// CAMBIO V4: 'usuario_id' → 'id_usuario_sca' (FK a usuarios_sca).
// El scope noLeidas() ya existe en el modelo; recientes() se eliminó del modelo
// (no estaba en el V4 que te di), así que lo reemplazo con orderBy directo.

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificacionController extends Controller
{
    /**
     * API: devuelve notificaciones del usuario (no leídas + últimas 10).
     */
    public function api()
    {
        $user = Auth::user();

        $noLeidas = Notificacion::where('id_usuario_sca', $user->id_usuario_sca)
            ->noLeidas()
            ->count();

        $ultimas = Notificacion::where('id_usuario_sca', $user->id_usuario_sca)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($n) {
                return [
                    'id'      => $n->id_notificacion,
                    'tipo'    => $n->tipo,
                    'titulo'  => $n->titulo,
                    'mensaje' => $n->mensaje,
                    'url'     => $n->url,
                    'leida'   => $n->leida,
                    'tiempo'  => $n->created_at->diffForHumans(),
                ];
            });

        return response()->json(['no_leidas' => $noLeidas, 'notificaciones' => $ultimas]);
    }

    /**
     * Marca una notificación como leída.
     */
    public function marcarLeida(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        $notif = Notificacion::find($request->id);
        if (!$notif || $notif->id_usuario_sca !== Auth::id()) {
            return response()->json(['success' => false], 403);
        }

        $notif->update(['leida' => true]);
        return response()->json(['success' => true]);
    }

    /**
     * Marca todas las notificaciones del usuario como leídas.
     */
    public function marcarTodasLeidas()
    {
        Notificacion::where('id_usuario_sca', Auth::id())
            ->noLeidas()
            ->update(['leida' => true]);

        return response()->json(['success' => true]);
    }
}