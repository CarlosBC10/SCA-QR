<div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f4f6f9; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
    
    {{-- Header --}}
    <div style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); padding: 30px; text-align: center;">
        <h1 style="color: #ffffff; margin: 0; font-size: 26px; letter-spacing: 1px;">FRUITEX DE MÉXICO</h1>
        <p style="color: #c8e6c9; margin: 5px 0 0 0; font-size: 14px; text-transform: uppercase; letter-spacing: 2px;">Sistema SCA</p>
    </div>

    {{-- Body --}}
    <div style="padding: 40px 30px; background-color: #ffffff;">
        <h2 style="color: #1b5e20; margin-top: 0; font-size: 22px; font-weight: 600;">Bienvenido(a), {{ $nombreCompleto }}</h2>
        <p style="font-size: 16px; color: #4a5568; line-height: 1.6; margin-bottom: 25px;">
            Se ha creado su cuenta de acceso al sistema SCA. Para iniciar sesión, utilice las siguientes credenciales temporales:
        </p>

        {{-- Credentials Box --}}
        <div style="background-color: #f0fff4; border-left: 5px solid #2e7d32; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <p style="margin: 0 0 10px 0; color: #2d3748; font-size: 16px;">
                <strong>Usuario:</strong> 
                <span style="background-color: #e6fffa; padding: 4px 10px; border-radius: 4px; font-family: monospace; color: #2c7a7b; font-size: 15px;">{{ $username }}</span>
            </p>
            <p style="margin: 0; color: #2d3748; font-size: 16px;">
                <strong>Contraseña temporal:</strong>
                <span style="background-color: #fffaf0; padding: 4px 10px; border-radius: 4px; font-family: monospace; color: #c05621; font-size: 15px;">{{ $passwordTemporal }}</span>
            </p>
        </div>

        {{-- Warning Box --}}
        <div style="background-color: #fffaf0; border-left: 5px solid #dd6b20; padding: 15px; border-radius: 8px; margin-bottom: 30px;">
            <p style="margin: 0; color: #7c2d12; font-size: 14px; line-height: 1.5;">
                <strong>⚠️ Importante:</strong> Por seguridad, el sistema le pedirá que cambie esta contraseña inmediatamente después de iniciar sesión.
            </p>
        </div>

        {{-- Button --}}
        <div style="text-align: center; margin-top: 30px;">
            <a href="{{ $loginUrl }}" style="background-color: #2e7d32; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 4px 6px rgba(46, 125, 50, 0.2);">
                Iniciar Sesión
            </a>
        </div>
    </div>

    {{-- Footer --}}
    <div style="background-color: #f4f6f9; padding: 20px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
        <p style="color: #718096; font-size: 12px; margin: 0;">
            Este es un mensaje automático. Por favor, no responda a este correo.<br>
            © {{ date('Y') }} Fruitex de México. Todos los derechos reservados.
        </p>
    </div>
</div>