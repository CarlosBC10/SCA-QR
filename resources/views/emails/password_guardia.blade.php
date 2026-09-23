<div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f4f6f9; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">

    {{-- Header --}}
    <div style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); padding: 30px; text-align: center;">
        <h1 style="color: #ffffff; margin: 0; font-size: 26px; letter-spacing: 1px;">FRUITEX DE MÉXICO</h1>
        <p style="color: #c8e6c9; margin: 5px 0 0 0; font-size: 14px; text-transform: uppercase; letter-spacing: 2px;">Sistema SCA</p>
    </div>

    {{-- Body --}}
    <div style="padding: 40px 30px; background-color: #ffffff;">
        <h2 style="color: #1b5e20; margin-top: 0; font-size: 22px; font-weight: 600;">Hola, {{ $nombreOperador }}</h2>
        <p style="font-size: 16px; color: #4a5568; line-height: 1.6; margin-bottom: 25px;">
            @if($contexto === 'crear')
                Usted acaba de <strong>crear la cuenta</strong> del guardia <strong>{{ $nombreGuardia }}</strong>.
                A continuación encontrará sus credenciales de acceso. Entréguelas al guardia por un medio seguro.
            @else
                Usted acaba de <strong>resetear la contraseña</strong> del guardia <strong>{{ $nombreGuardia }}</strong>.
                A continuación encontrará la nueva contraseña temporal. Entréguela al guardia por un medio seguro.
            @endif
        </p>

        {{-- Credentials Box --}}
        <div style="background-color: #f0fff4; border-left: 5px solid #2e7d32; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <p style="margin: 0 0 10px 0; color: #2d3748; font-size: 16px;">
                <strong>Usuario del guardia:</strong>
                <span style="background-color: #e6fffa; padding: 4px 10px; border-radius: 4px; font-family: monospace; color: #2c7a7b; font-size: 15px;">{{ $usernameGuardia }}</span>
            </p>
            <p style="margin: 0; color: #2d3748; font-size: 16px;">
                <strong>Contraseña temporal:</strong>
                <span style="background-color: #fffaf0; padding: 4px 10px; border-radius: 4px; font-family: monospace; color: #c05621; font-size: 15px;">{{ $passwordTemporal }}</span>
            </p>
        </div>

        {{-- Instructions Box: flujo M3 paso a paso --}}
        <div style="background-color: #ebf8ff; border-left: 5px solid #2b6cb0; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <p style="margin: 0 0 10px 0; color: #2a4365; font-size: 15px; font-weight: bold;">
                Instrucciones para el guardia:
            </p>
            <ol style="margin: 0; padding-left: 20px; color: #2a4365; font-size: 14px; line-height: 1.8;">
                <li>Iniciar sesión en el <strong>panel web</strong> (botón de abajo) con el usuario y la contraseña temporal.</li>
                <li>El sistema le pedirá <strong>cambiar la contraseña</strong> inmediatamente (mínimo 8 caracteres, una mayúscula, una minúscula y un número).</li>
                <li>Con la nueva contraseña ya podrá <strong>activar el kiosko</strong> de su planta con normalidad.</li>
            </ol>
        </div>

        {{-- Warning Box: kiosko no acepta temporal --}}
        <div style="background-color: #fffaf0; border-left: 5px solid #dd6b20; padding: 15px; border-radius: 8px; margin-bottom: 30px;">
            <p style="margin: 0; color: #7c2d12; font-size: 14px; line-height: 1.5;">
                <strong>Importante:</strong> La contraseña temporal solo permite el primer acceso al panel web. El guardia debe cambiarla antes de utilizar el kiosko.
            </p>
        </div>

        {{-- Button --}}
        <div style="text-align: center; margin-top: 30px;">
            <a href="{{ $loginUrl }}" style="background-color: #2e7d32; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 4px 6px rgba(46, 125, 50, 0.2);">
                Ir al Panel Web
            </a>
        </div>
    </div>

    {{-- Footer --}}
    <div style="background-color: #f4f6f9; padding: 20px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
        <p style="color: #718096; font-size: 12px; margin: 0;">
            Este es un mensaje automático. Por favor, no responda a este correo.<br>
            Por seguridad, el sistema no volverá a mostrar esta contraseña. Guárdela o imprímala si la necesita.<br>
            © {{ date('Y') }} Fruitex de México. Todos los derechos reservados.
        </p>
    </div>
</div>