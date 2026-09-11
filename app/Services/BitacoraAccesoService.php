<?php

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Externo;
use App\Models\SolicitudExt;
use App\Models\SolicitudInt;
use App\Models\Sitec\Empleado;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use App\Services\BitacoraFiltro;

/**
 * Servicio de contexto histórico de Bitácora.
 *
 * IMPORTANTÍSIMO:
 * Históricamente la BD NO tenía una FK desde bitacora hacia la solicitud
 * que originó el acceso, y este servicio reconstruía el contexto usando
 * las mismas reglas de autorización de SCA:
 *   - Persona + rango de vigencia + planta + movimiento.
 *   - Para grupos internos, solo el integrante aprobado cuenta.
 *   - Para externos, el detalle de la credencial identifica a la persona.
 *
 * ────────────────────────────────────────────────────────────────────────
 * V10.3 · CAMBIO #29: bitacora.id_solicitud YA EXISTE (FK lógico sin
 * restricción física, Script 1 de la Etapa 1 de BD). Este servicio ahora:
 *   1. Resuelve PRIMERO la referencia absoluta por id (clasificar()).
 *   2. Conserva la reconstrucción por reglas como FALLBACK para los
 *      registros históricos (pre-V10.3, id_solicitud NULL).
 *   3. Soporta el nuevo tipo permiso_entrada (filtro + etiqueta + reglas).
 * ────────────────────────────────────────────────────────────────────────
 *
 * Esto permite que Bitácora sea consistente sin romper nada de lo
 * existente. La clasificación NO se guarda en BD; se calcula al consultar.
 */
class BitacoraAccesoService
{
    public const ESTANDAR = 'estandar';
    public const PASE = 'pase_salida';
    public const INTER = 'interplantas';
    public const VISITA = 'visita';
    public const CONTRATISTA = 'contratista';
    // V10.3 · CAMBIO #29: nuevo tipo de acceso interno.
    public const PERMISO_ENTRADA = 'permiso_entrada';

    /**
     * Aplica a la consulta la clasificación de acceso solicitada.
     *
     * Se utilizan EXISTS correlacionados para que el filtro se ejecute en SQL
     * antes de paginate(). Esto evita cargar toda la bitácora en PHP.
     */
    public function aplicarFiltroTipo($query, ?string $clasificacion, ?string $subtipo, BitacoraFiltro $filters): void
    {
        if ($clasificacion === 'internos') {
            $query->where('bitacora.persona_id', 'not like', 'E-%');

            if ($subtipo === self::PASE) {
                $this->whereExistsPase($query, $filters);
                return;
            }

            if ($subtipo === self::INTER) {
                $this->whereExistsInterplantas($query, $filters);
                return;
            }

            // V10.3 · CAMBIO #29: filtro SQL para el nuevo tipo.
            if ($subtipo === self::PERMISO_ENTRADA) {
                $this->whereExistsPermisoEntrada($query, $filters);
                return;
            }

            if ($subtipo === self::ESTANDAR) {
                $this->whereNotExistsPermisoInterno($query, $filters);
                return;
            }

            return;
        }

        if ($clasificacion === 'externos') {
            $query->where('bitacora.persona_id', 'like', 'E-%');

            if ($subtipo === self::VISITA || $subtipo === self::CONTRATISTA) {
                $this->whereExistsExterno($query, $filters, $subtipo);
                return;
            }

            return;
        }

        // Clasificación "todos": no se agrega un filtro de tipo.
        // Los filtros propios de permiso se ignoran si no hay subtipo explícito,
        // evitando que un estado de permiso esconda accesos estándar.
    }

    private function whereExistsPase($query, BitacoraFiltro $f): void
    {
        $query->whereExists(function ($sq) use ($f) {
            $sq->selectRaw('1')
                ->from('solicitudes_int')
                ->where('solicitudes_int.tipo', self::PASE)
                ->where(function ($ref) {
                    $ref->where(function ($direct) {
                        $direct->whereNotNull('bitacora.id_solicitud')
                            ->whereRaw('CAST(solicitudes_int.id_solicitud_int AS CHAR) = bitacora.id_solicitud')
                            // V10.3-fix: la referencia directa debe coincidir
                            // con el beneficiario real de la solicitud.
                            ->where(function ($who) {
                                $who->whereRaw('CAST(bitacora.persona_id AS UNSIGNED) = solicitudes_int.creador_empleado_id')
                                    ->whereNotExists(function ($noGroup) {
                                        $noGroup->selectRaw('1')->from('solicitud_empleados')
                                            ->whereColumn('solicitud_empleados.id_solicitud_int', 'solicitudes_int.id_solicitud_int');
                                    })
                                    ->orWhereExists(function ($se) {
                                        $se->selectRaw('1')->from('solicitud_empleados')
                                            ->whereColumn('solicitud_empleados.id_solicitud_int', 'solicitudes_int.id_solicitud_int')
                                            ->whereRaw('solicitud_empleados.empleado_id = CAST(bitacora.persona_id AS UNSIGNED)');
                                    });
                            });
                    })->orWhere(function ($historico) {
                        $historico->whereNull('bitacora.id_solicitud')
                            // V10.3.2-fix: la inferencia histórica SOLO considera
                            // permisos con estado válido (regresión: se perdió).
                            ->whereIn('solicitudes_int.estado', ['aprobado', 'aprobado_parcial', 'expirado'])
                            ->whereColumn('solicitudes_int.id_planta_origen', 'bitacora.id_planta')
                            ->where('bitacora.tipo_evento', 'salida')
                            ->whereColumn('solicitudes_int.fecha_inicio', '<=', 'bitacora.fecha_hora')
                            ->whereColumn('solicitudes_int.fecha_fin', '>=', 'bitacora.fecha_hora')
                            ->where(function ($who) {
                                $who->whereRaw('CAST(bitacora.persona_id AS UNSIGNED) = solicitudes_int.creador_empleado_id')
                                    ->whereNotExists(function ($noGroup) {
                                        $noGroup->selectRaw('1')->from('solicitud_empleados')
                                            ->whereColumn('solicitud_empleados.id_solicitud_int', 'solicitudes_int.id_solicitud_int');
                                    })
                                    ->orWhereExists(function ($se) {
                                        $se->selectRaw('1')->from('solicitud_empleados')
                                            ->whereColumn('solicitud_empleados.id_solicitud_int', 'solicitudes_int.id_solicitud_int')
                                            ->whereRaw('solicitud_empleados.empleado_id = CAST(bitacora.persona_id AS UNSIGNED)')
                                            ->whereIn('solicitud_empleados.estado', ['aprobado', 'expirado']);
                                    });
                            });
                    });
                });
        });
    }

    private function whereExistsPermisoEntrada($query, BitacoraFiltro $f): void
    {
        // V10.3.2-fix (consistencia filtro-vs-clasificación): se quitó el
        // tipo_evento='entrada' externo. clasificar() marca TAMBIÉN las
        // salidas cubiertas por un permiso_entrada (rama 29b); el filtro
        // debe mostrar exactamente lo mismo que clasifica el listado.
        $query->whereExists(function ($sq) use ($f) {
                $sq->selectRaw('1')
                    ->from('solicitudes_int')
                    ->where('solicitudes_int.tipo', self::PERMISO_ENTRADA)
                    ->where(function ($ref) {
                        $ref->where(function ($direct) {
                            $direct->whereNotNull('bitacora.id_solicitud')
                                ->whereRaw('CAST(solicitudes_int.id_solicitud_int AS CHAR) = bitacora.id_solicitud')
                                // V10.3-fix: la referencia directa debe coincidir
                                // con el beneficiario real de la solicitud.
                                ->where(function ($who) {
                                    $who->whereRaw('CAST(bitacora.persona_id AS UNSIGNED) = solicitudes_int.creador_empleado_id')
                                        ->whereNotExists(function ($noGroup) {
                                            $noGroup->selectRaw('1')->from('solicitud_empleados')
                                                ->whereColumn('solicitud_empleados.id_solicitud_int', 'solicitudes_int.id_solicitud_int');
                                        })
                                        ->orWhereExists(function ($se) {
                                            $se->selectRaw('1')->from('solicitud_empleados')
                                                ->whereColumn('solicitud_empleados.id_solicitud_int', 'solicitudes_int.id_solicitud_int')
                                                ->whereRaw('solicitud_empleados.empleado_id = CAST(bitacora.persona_id AS UNSIGNED)');
                                        });
                                });
                        })->orWhere(function ($historico) {
                            $historico->whereNull('bitacora.id_solicitud')
                                // V10.3.2-fix: la inferencia histórica SOLO considera
                                // permisos con estado válido (regresión: se perdió).
                                ->whereIn('solicitudes_int.estado', ['aprobado', 'aprobado_parcial', 'expirado'])
                                ->whereColumn('solicitudes_int.id_planta_destino', 'bitacora.id_planta')
                                ->whereColumn('solicitudes_int.fecha_inicio', '<=', 'bitacora.fecha_hora')
                                ->whereColumn('solicitudes_int.fecha_fin', '>=', 'bitacora.fecha_hora')
                                ->where(function ($who) {
                                    $who->whereRaw('CAST(bitacora.persona_id AS UNSIGNED) = solicitudes_int.creador_empleado_id')
                                        ->whereNotExists(function ($noGroup) {
                                            $noGroup->selectRaw('1')->from('solicitud_empleados')
                                                ->whereColumn('solicitud_empleados.id_solicitud_int', 'solicitudes_int.id_solicitud_int');
                                        })
                                        ->orWhereExists(function ($se) {
                                            $se->selectRaw('1')->from('solicitud_empleados')
                                                ->whereColumn('solicitud_empleados.id_solicitud_int', 'solicitudes_int.id_solicitud_int')
                                                ->whereRaw('solicitud_empleados.empleado_id = CAST(bitacora.persona_id AS UNSIGNED)')
                                                ->whereIn('solicitud_empleados.estado', ['aprobado', 'expirado']);
                                        });
                                });
                        });
                    });
            });
    }

    private function whereExistsInterplantas($query, BitacoraFiltro $f): void
    {
        $query->whereExists(function ($sq) use ($f) {
            $sq->selectRaw('1')
                ->from('solicitudes_int')
                ->where('solicitudes_int.tipo', self::INTER)
                ->when($f->plantaOrigen, fn($q) => $q->where('solicitudes_int.id_planta_origen', $f->plantaOrigen))
                ->when($f->plantaDestino, fn($q) => $q->where('solicitudes_int.id_planta_destino', $f->plantaDestino))
                ->where(function ($ref) {
                    $ref->where(function ($direct) {
                        $direct->whereNotNull('bitacora.id_solicitud')
                            ->whereRaw('CAST(solicitudes_int.id_solicitud_int AS CHAR) = bitacora.id_solicitud')
                            // V10.3-fix: la referencia directa debe coincidir
                            // con el beneficiario real de la solicitud.
                            ->where(function ($who) {
                                $who->whereRaw('CAST(bitacora.persona_id AS UNSIGNED) = solicitudes_int.creador_empleado_id')
                                    ->whereNotExists(function ($noGroup) {
                                        $noGroup->selectRaw('1')->from('solicitud_empleados')
                                            ->whereColumn('solicitud_empleados.id_solicitud_int', 'solicitudes_int.id_solicitud_int');
                                    })
                                    ->orWhereExists(function ($se) {
                                        $se->selectRaw('1')->from('solicitud_empleados')
                                            ->whereColumn('solicitud_empleados.id_solicitud_int', 'solicitudes_int.id_solicitud_int')
                                            ->whereRaw('solicitud_empleados.empleado_id = CAST(bitacora.persona_id AS UNSIGNED)');
                                    });
                            });
                    })->orWhere(function ($historico) {
                        $historico->whereNull('bitacora.id_solicitud')
                            // V10.3.2-fix: la inferencia histórica SOLO considera
                            // permisos con estado válido (regresión: se perdió).
                            ->whereIn('solicitudes_int.estado', ['aprobado', 'aprobado_parcial', 'expirado'])
                            ->whereColumn('solicitudes_int.fecha_inicio', '<=', 'bitacora.fecha_hora')
                            ->whereColumn('solicitudes_int.fecha_fin', '>=', 'bitacora.fecha_hora')
                            ->where(function ($plantas) {
                                $plantas->whereColumn('solicitudes_int.id_planta_origen', 'bitacora.id_planta')
                                    ->orWhereColumn('solicitudes_int.id_planta_destino', 'bitacora.id_planta');
                            })
                            ->where(function ($who) {
                                $who->whereRaw('CAST(bitacora.persona_id AS UNSIGNED) = solicitudes_int.creador_empleado_id')
                                    ->whereNotExists(function ($noGroup) {
                                        $noGroup->selectRaw('1')->from('solicitud_empleados')
                                            ->whereColumn('solicitud_empleados.id_solicitud_int', 'solicitudes_int.id_solicitud_int');
                                    })
                                    ->orWhereExists(function ($se) {
                                        $se->selectRaw('1')->from('solicitud_empleados')
                                            ->whereColumn('solicitud_empleados.id_solicitud_int', 'solicitudes_int.id_solicitud_int')
                                            ->whereRaw('solicitud_empleados.empleado_id = CAST(bitacora.persona_id AS UNSIGNED)')
                                            ->whereIn('solicitud_empleados.estado', ['aprobado', 'expirado']);
                                    });
                            });
                    });
                });
        });
    }

    private function whereNotExistsPermisoInterno($query, BitacoraFiltro $f): void
    {
        // Una referencia directa jamás se interpreta como estándar.
        $query->whereNull('bitacora.id_solicitud')
            ->whereNotExists(function ($sq) use ($f) {
                $sq->selectRaw('1')->from('solicitudes_int')
                    ->whereIn('solicitudes_int.tipo', [self::PASE, self::INTER, self::PERMISO_ENTRADA])
                    ->whereIn('solicitudes_int.estado', ['aprobado', 'aprobado_parcial', 'expirado'])
                    ->whereColumn('solicitudes_int.fecha_inicio', '<=', 'bitacora.fecha_hora')
                    ->whereColumn('solicitudes_int.fecha_fin', '>=', 'bitacora.fecha_hora')
                    ->where(function ($plantas) {
                        $plantas->where(function ($q) {
                            $q->where('solicitudes_int.tipo', self::PASE)
                                ->whereColumn('solicitudes_int.id_planta_origen', 'bitacora.id_planta')
                                ->where('bitacora.tipo_evento', 'salida');
                        })->orWhere(function ($q) {
                            $q->where('solicitudes_int.tipo', self::INTER)
                                ->where(function ($q2) {
                                    $q2->whereColumn('solicitudes_int.id_planta_origen', 'bitacora.id_planta')
                                        ->orWhereColumn('solicitudes_int.id_planta_destino', 'bitacora.id_planta');
                                });
                        })->orWhere(function ($q) {
                            $q->where('solicitudes_int.tipo', self::PERMISO_ENTRADA)
                                ->where('bitacora.tipo_evento', 'entrada')
                                ->whereColumn('solicitudes_int.id_planta_destino', 'bitacora.id_planta');
                        });
                    })
                    ->where(function ($who) {
                        $who->whereRaw('CAST(bitacora.persona_id AS UNSIGNED) = solicitudes_int.creador_empleado_id')
                            ->whereNotExists(function ($noGroup) {
                                $noGroup->selectRaw('1')->from('solicitud_empleados')
                                    ->whereColumn('solicitud_empleados.id_solicitud_int', 'solicitudes_int.id_solicitud_int');
                            })
                            ->orWhereExists(function ($se) {
                                $se->selectRaw('1')->from('solicitud_empleados')
                                    ->whereColumn('solicitud_empleados.id_solicitud_int', 'solicitudes_int.id_solicitud_int')
                                    ->whereRaw('solicitud_empleados.empleado_id = CAST(bitacora.persona_id AS UNSIGNED)')
                                    ->whereIn('solicitud_empleados.estado', ['aprobado', 'expirado']);
                            });
                    });
            });
    }

    private function whereExistsExterno($query, BitacoraFiltro $f, string $subtipo): void
    {
        $tipoBd = $subtipo === self::VISITA ? 'visitante' : 'contratista';

        $query->whereExists(function ($sq) use ($f, $tipoBd) {
            $sq->selectRaw('1')
                ->from('solicitudes_ext')
                ->join('solicitud_externo_detalle', 'solicitud_externo_detalle.id_solicitud_ext', '=', 'solicitudes_ext.id_solicitud_ext')
                ->where('solicitudes_ext.tipo', $tipoBd)
                ->where(function ($ref) use ($f) {
                    $ref->where(function ($direct) use ($f) {
                        $direct->whereNotNull('bitacora.id_solicitud')
                            ->whereRaw('CAST(solicitudes_ext.id_solicitud_ext AS CHAR) = bitacora.id_solicitud')
                            ->whereRaw("CONCAT('E-', solicitud_externo_detalle.id_externos) = bitacora.persona_id")
                            // V10.3-fix: los filtros de empresa/anfitrión
                            // también aplican cuando la referencia es directa.
                            ->when($f->empresa, fn($q) => $q->where('solicitudes_ext.empresa', 'like', '%' . $f->empresa . '%'))
                            ->when($f->anfitrion, function ($q) use ($f) {
                                $ids = Empleado::where('nombre_completo', 'like', '%' . $f->anfitrion . '%')->pluck('id_empleado');
                                $q->whereIn('solicitudes_ext.anfitrion_empleado_id', $ids->isEmpty() ? [-1] : $ids);
                            })
                    })->orWhere(function ($historico) use ($f) {
                        $historico->whereNull('bitacora.id_solicitud')
                            ->whereIn('solicitudes_ext.estado', ['aprobado', 'aprobado_parcial', 'expirado'])
                            ->whereColumn('solicitudes_ext.id_planta_destino', 'bitacora.id_planta')
                            ->whereColumn('solicitudes_ext.fecha_inicio', '<=', 'bitacora.fecha_hora')
                            ->whereColumn('solicitudes_ext.fecha_fin', '>=', 'bitacora.fecha_hora')
                            ->whereRaw("CONCAT('E-', solicitud_externo_detalle.id_externos) = bitacora.persona_id")
                            ->when($f->empresa, fn($q) => $q->where('solicitudes_ext.empresa', 'like', '%' . $f->empresa . '%'))
                            ->when($f->anfitrion, function ($q) use ($f) {
                                $ids = Empleado::where('nombre_completo', 'like', '%' . $f->anfitrion . '%')->pluck('id_empleado');
                                $q->whereIn('solicitudes_ext.anfitrion_empleado_id', $ids->isEmpty() ? [-1] : $ids);
                            })
                    });
                });
        });
    }

    // V10.3.6-fix: filtro "Estado permiso" ELIMINADO por completo (vista,
    // BitacoraFiltro, BitacoraController y este service, incluida la ayuda
    // estadoPermisoCallback y sus 6 puntos de uso). Motivo: no está en los
    // requisitos y no aporta información — la bitácora solo vincula
    // id_solicitud cuando el permiso AUTORIZÓ el acceso (aprobado o
    // aprobado_parcial; pase expirado únicamente en salidas, regla
    // "la salida nunca se bloquea"). Una solicitud pendiente/rechazada
    // jamás deja registro vinculado, y las aprobadas ya se ven con el
    // filtro "Tipo de acceso" (Pase de Salida / Interplantas /
    // Permiso de Entrada / Visita / Contratista).

    /**
     * Clasifica únicamente los registros que se van a mostrar/exportar.
     * Se cargan permisos de forma masiva para evitar una consulta por fila.
     */
    public function clasificar(Collection $bitacoras): Collection
    {
        if ($bitacoras->isEmpty()) {
            return $bitacoras;
        }

        $minFecha = $bitacoras->min('fecha_hora');
        $maxFecha = $bitacoras->max('fecha_hora');
        $idsInternos = $bitacoras->filter(fn($b) => !str_starts_with((string)$b->persona_id, 'E-'))
            ->pluck('persona_id')->map(fn($id) => (int)$id)->unique()->values();
        $idsExternos = $bitacoras->filter(fn($b) => str_starts_with((string)$b->persona_id, 'E-'))
            ->pluck('persona_id')->map(fn($id) => (int)str_replace('E-', '', $id))->unique()->values();

        $internas = SolicitudInt::with('empleados')
            ->whereIn('estado', ['aprobado', 'aprobado_parcial', 'expirado'])
            ->where('fecha_inicio', '<=', $maxFecha)
            ->where('fecha_fin', '>=', $minFecha)
            ->when($idsInternos->isNotEmpty(), function ($q) use ($idsInternos) {
                $q->where(function ($who) use ($idsInternos) {
                    $who->whereIn('creador_empleado_id', $idsInternos->all())
                        ->orWhereHas('empleados', fn($se) => $se->whereIn('empleado_id', $idsInternos->all()));
                });
            }, function ($q) {
                $q->whereRaw('1=0');
            })
            ->get();

        $externas = SolicitudExt::with('externos')
            ->whereIn('estado', ['aprobado', 'aprobado_parcial', 'expirado'])
            ->where('fecha_inicio', '<=', $maxFecha)
            ->where('fecha_fin', '>=', $minFecha)
            ->when($idsExternos->isNotEmpty(), function ($q) use ($idsExternos) {
                $q->whereHas('externos', fn($detalle) => $detalle->whereIn('id_externos', $idsExternos->all()));
            }, function ($q) {
                $q->whereRaw('1=0');
            })
            ->get();

        // Indexar permisos por persona para reducir el costo del recorrido.
        $permisosInternosPersona = [];
        foreach ($internas as $sol) {
            foreach ($this->personasSolicitudInterna($sol) as $personaId) {
                $permisosInternosPersona[$personaId][] = $sol;
            }
        }

        $permisosExternosPersona = [];
        foreach ($externas as $sol) {
            foreach ($sol->externos as $detalle) {
                $permisosExternosPersona[(int)$detalle->id_externos][] = $sol;
            }
        }

        // ────────────────────────────────────────────────────────
        // V10.3 · CAMBIO #29a: REFERENCIA ABSOLUTA (id_solicitud).
        // Las bitácoras nuevas ya traen el id de la solicitud que
        // autorizó el acceso (lo grabó el escáner). Se cargan en lote
        // y tienen PRIORIDAD sobre la reconstrucción por reglas:
        // el dato directo es confiable; las reglas solo adivinan.
        // Si el id ya no existe (huérfano) → bandera
        // $b->solicitud_inconsistente = true (NUNCA se oculta).
        // ────────────────────────────────────────────────────────
        $directasInt = collect();
        $directasExt = collect();
        $idsDirectosInt = $bitacoras
            ->filter(fn($b) => !empty($b->id_solicitud) && !str_starts_with((string)$b->persona_id, 'E-'))
            ->pluck('id_solicitud')->map(fn($v) => (int)$v)->unique()->values();
        $idsDirectosExt = $bitacoras
            ->filter(fn($b) => !empty($b->id_solicitud) && str_starts_with((string)$b->persona_id, 'E-'))
            ->pluck('id_solicitud')->map(fn($v) => (int)$v)->unique()->values();
        if ($idsDirectosInt->isNotEmpty()) {
            $directasInt = SolicitudInt::with('empleados')
                ->whereIn('id_solicitud_int', $idsDirectosInt->all())->get()
                ->keyBy('id_solicitud_int');
        }
        if ($idsDirectosExt->isNotEmpty()) {
            $directasExt = SolicitudExt::with('externos')
                ->whereIn('id_solicitud_ext', $idsDirectosExt->all())->get()
                ->keyBy('id_solicitud_ext');
        }

        $tipos = [self::ESTANDAR, self::PASE, self::INTER, self::VISITA, self::CONTRATISTA];

        foreach ($bitacoras as $b) {
            $tipo = self::ESTANDAR;
            $contexto = null;
            $b->solicitud_inconsistente = false;

            // V10.3 · CAMBIO #29a: referencia absoluta manda sobre reglas.
            $referenciaDirecta = !empty($b->id_solicitud);
            if ($referenciaDirecta) {
                $idRef = trim((string) $b->id_solicitud);
                $refDirecta = preg_match('/^\d+$/', $idRef)
                    ? (str_starts_with((string)$b->persona_id, 'E-')
                        ? $directasExt->get((int)$idRef)
                        : $directasInt->get((int)$idRef))
                    : null;

                if ($refDirecta) {
                    $esRuteoConsistente = false;
                    if ($refDirecta instanceof SolicitudInt && !str_starts_with((string)$b->persona_id, 'E-')) {
                        $empIdRef = (int) $b->persona_id;
                        $esRuteoConsistente = ($refDirecta->empleados->isEmpty() && (int)$refDirecta->creador_empleado_id === $empIdRef)
                            || $refDirecta->empleados->contains(fn ($se) => (int)$se->empleado_id === $empIdRef);
                    } elseif ($refDirecta instanceof SolicitudExt && str_starts_with((string)$b->persona_id, 'E-')) {
                        $extIdRef = (int) str_replace('E-', '', (string)$b->persona_id);
                        $esRuteoConsistente = $refDirecta->externos->contains(fn ($detalle) => (int)$detalle->id_externos === $extIdRef);
                    }

                    if ($esRuteoConsistente) {
                        $tipo = $this->tipoDesdeSolicitud($refDirecta);
                        $contexto = $refDirecta;
                    } else {
                        $b->solicitud_inconsistente = true;
                        $tipo = 'inconsistente';
                    }
                } else {
                    $b->solicitud_inconsistente = true;
                    $tipo = 'inconsistente';
                }
            }

            if (!$referenciaDirecta && $contexto === null && !str_starts_with((string)$b->persona_id, 'E-')) {
                $id = (int)$b->persona_id;
                foreach (($permisosInternosPersona[$id] ?? []) as $sol) {
                    if (!$this->fechaDentro($b->fecha_hora, $sol->fecha_inicio, $sol->fecha_fin)) {
                        continue;
                    }

                    if ($sol->tipo === self::PASE
                        && $b->tipo_evento === 'salida'
                        && (int)$b->id_planta === (int)$sol->id_planta_origen) {
                        $tipo = self::PASE;
                        $contexto = $sol;
                        break;
                    }

                    if ($sol->tipo === self::INTER
                        && in_array((int)$b->id_planta, [(int)$sol->id_planta_origen, (int)$sol->id_planta_destino], true)) {
                        $tipo = self::INTER;
                        $contexto = $sol;
                        // Interplantas tiene prioridad sobre Pase si ambos
                        // coincidieran por datos históricos ambiguos.
                        break;
                    }

                    // V10.3 · CAMBIO #29b: clasificación por reglas para
                    // permiso_entrada (aplica sobre todo a bitácoras sin
                    // referencia directa; las nuevas ya la traen).
                    // En permiso_entrada origen = destino = planta propia.
                    if ($sol->tipo === self::PERMISO_ENTRADA
                        && (int)$b->id_planta === (int)$sol->id_planta_destino) {
                        $tipo = self::PERMISO_ENTRADA;
                        $contexto = $sol;
                        break;
                    }
                }
            } elseif (!$referenciaDirecta && $contexto === null) {
                $id = (int)str_replace('E-', '', $b->persona_id);
                foreach (($permisosExternosPersona[$id] ?? []) as $sol) {
                    if (!$this->fechaDentro($b->fecha_hora, $sol->fecha_inicio, $sol->fecha_fin)) {
                        continue;
                    }
                    if ((int)$b->id_planta !== (int)$sol->id_planta_destino) {
                        continue;
                    }
                    $tipo = $sol->tipo === 'contratista' ? self::CONTRATISTA : self::VISITA;
                    $contexto = $sol;
                    break;
                }
            }

            $b->tipo_acceso = $tipo;
            $b->permiso_contexto = $contexto;
        }

        return $bitacoras;
    }

    public function coincideTipo($bitacora, string $tipo): bool
    {
        return ($bitacora->tipo_acceso ?? self::ESTANDAR) === $tipo;
    }

    // V10.3 · CAMBIO #29a: mapa solicitud → tipo de acceso, para la
    // resolución por referencia absoluta (id_solicitud directo).
    private function tipoDesdeSolicitud($sol): string
    {
        if ($sol instanceof SolicitudInt) {
            return match ($sol->tipo) {
                self::PASE            => self::PASE,
                self::PERMISO_ENTRADA => self::PERMISO_ENTRADA,
                default               => self::INTER,
            };
        }
        return $sol->tipo === 'contratista' ? self::CONTRATISTA : self::VISITA;
    }

    private function personasSolicitudInterna(SolicitudInt $sol): array
    {
        $ids = [];
        if ($sol->empleados->isEmpty()) {
            $ids[] = (int) $sol->creador_empleado_id;
        }
        foreach ($sol->empleados as $se) {
            if (in_array($se->estado, ['aprobado', 'expirado'], true)) {
                $ids[] = (int)$se->empleado_id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function fechaDentro($fecha, $inicio, $fin): bool
    {
        return $fecha >= $inicio && $fecha <= $fin;
    }

    /**
     * Devuelve un contexto textual para la tabla/detalle.
     */
    public function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            'inconsistente' => 'Inconsistente',
            self::PASE => 'Pase de Salida',
            self::INTER => 'Interplantas',
            // V10.3 · CAMBIO #29: etiqueta del nuevo tipo.
            self::PERMISO_ENTRADA => 'Permiso de Entrada',
            self::VISITA => 'Visita',
            self::CONTRATISTA => 'Contratista',
            default => 'Estándar',
        };
    }
}
