<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #1b5e20, #2e7d32); padding: 25px; border-radius: 12px 12px 0 0; text-align: center;">
        <h1 style="color: #fff; margin: 0; font-size: 22px;">Fruitex de México</h1>
        <p style="color: #c8e6c9; margin: 5px 0 0 0; font-size: 14px;">Sistema SCA-QR</p>
    </div>

    <div style="background: #f9f9f9; padding: 25px; border: 1px solid #ddd; border-radius: 0 0 12px 12px;">
        <p style="margin: 0 0 15px 0; color: #333;">
            Hola <strong>{{ $nombreDestinatario }}</strong>,
        </p>
        
        <h2 style="margin: 0 0 15px 0; color: #1b5e20; font-size: 18px;">{{ $titulo }}</h2>
        
        <p style="margin: 0 0 20px 0; color: #555; line-height: 1.5;">
            {{ $mensaje }}
        </p>
        
        @if($motivo)
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <strong style="color: #856404;">Motivo:</strong><br>
            <span style="color: #333;">{{ $motivo }}</span>
        </div>
        @endif

        {{-- Lista neutral de personas (para solicitudes nuevas) --}}
        @if($personas && !$aprobados && !$rechazados)
        <div style="background: #fff; border: 1px solid #eee; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #2e7d32;">👥 Personas en la solicitud ({{ count($personas) }}):</h3>
            <ul style="margin: 0 0 0 20px; padding: 0; color: #333; font-size: 13px;">
                @foreach($personas as $nombre)
                    <li>{{ $nombre }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Listas de aprobados y rechazados --}}
        @if($aprobados || $rechazados)
        <div style="background: #fff; border: 1px solid #eee; border-radius: 8px; padding: 15px; margin: 20px 0;">
            @if($aprobados)
                <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #2e7d32;">✅ Personas aprobadas ({{ count($aprobados) }}):</h3>
                <ul style="margin: 0 0 15px 20px; padding: 0; color: #333; font-size: 13px;">
                    @foreach($aprobados as $nombre)
                        <li>{{ $nombre }}</li>
                    @endforeach
                </ul>
            @endif

            @if($rechazados)
                <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #dc3545;">❌ Personas rechazadas ({{ count($rechazados) }}):</h3>
                <ul style="margin: 0 0 0 20px; padding: 0; color: #333; font-size: 13px;">
                    @foreach($rechazados as $nombre)
                        <li>{{ $nombre }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        @endif

        {{-- Detalles de la solicitud --}}
        <div style="background: #e8f5e9; border-left: 4px solid #2e7d32; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <strong style="color: #1b5e20;">Detalles de la solicitud:</strong><br>
            <span style="color: #333; line-height: 1.6;">
                @php
                    $id = $solicitud instanceof \App\Models\SolicitudInt ? $solicitud->id_solicitud_int : $solicitud->id_solicitud_ext;
                    $tipo = $solicitud instanceof \App\Models\SolicitudInt 
                        ? ($solicitud->tipo === 'pase_salida' ? 'Pase de Salida' : 'Interplantas')
                        : ($solicitud->tipo === 'visitante' ? 'Visita' : 'Contratista');
                @endphp
                Solicitud #{{ $id }}<br>
                Tipo: {{ $tipo }}<br>
                @if($solicitud instanceof \App\Models\SolicitudExt && $solicitud->empresa)
                    Empresa: {{ $solicitud->empresa }}<br>
                @endif
                @if($creadorNombre)
                    Creada por: {{ $creadorNombre }}<br>
                @endif
                Vigencia: {{ $solicitud->fecha_inicio->format('d/m/Y H:i') }} a {{ $solicitud->fecha_fin->format('d/m/Y H:i') }}<br>
                Motivo: {{ $solicitud->motivo }}
            </span>
        </div>

        <p style="margin: 20px 0 0 0; color: #888; font-size: 12px;">
            Este es un correo automático, no responda a esta dirección.
        </p>
    </div>
</div>