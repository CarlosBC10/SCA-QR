<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #1b5e20, #2e7d32); padding: 25px; border-radius: 12px 12px 0 0; text-align: center;">
        <h1 style="color: #fff; margin: 0; font-size: 22px;">Fruitex de México</h1>
        <p style="color: #c8e6c9; margin: 5px 0 0 0; font-size: 14px;">Sistema SCA</p>
    </div>

    <div style="background: #f9f9f9; padding: 25px; border: 1px solid #ddd; border-radius: 0 0 12px 12px;">
        <p style="margin: 0 0 15px 0; color: #333;">
            Hola <strong>{{ $nombreDestinatario }}</strong>,
        </p>
        
        <p style="margin: 0 0 20px 0; color: #555;">
            Se ha aprobado una solicitud de acceso para la planta <strong>{{ $solicitud->plantaDestino?->nombre ?? 'N/A' }}</strong>.
            A continuación encontrará las credenciales adjuntas (PDF y PNG) para cada persona autorizada.
        </p>

        @if($motivo)
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <strong style="color: #856404;">Motivo de rechazos:</strong><br>
            <span style="color: #333;">{{ $motivo }}</span>
        </div>
        @endif

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

        <p style="margin: 20px 0 0 0; color: #888; font-size: 12px;">
            Este es un correo automático, no responda a esta dirección.
        </p>
    </div>
</div>