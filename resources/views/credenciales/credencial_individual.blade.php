{{-- resources/views/credenciales/credencial_individual.blade.php --}}

@php
    $tipoLabel = $solicitud->tipo === 'contratista'
        ? 'CONTRATISTA'
        : 'VISITANTE';

    $tipoColor = $solicitud->tipo === 'contratista'
        ? '#6f42c1'
        : '#dc3545';
@endphp

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 204px;
            height: 324px;
            margin: 0;
            padding: 0;
            background: #ffffff;
            font-family: Arial, Helvetica, sans-serif;
        }

        /* =====================================================
           CREDENCIAL
           ===================================================== */

        .credencial {
            position: relative;

            width: 204px;
            height: 324px;

            overflow: hidden;

            background: #fffdf5;

            border: 1px solid #d9ddd7;
            border-radius: 8px;
        }


        /* =====================================================
           FONDO DECORATIVO
           ===================================================== */

        .fondo-fruta {
            position: absolute;
            z-index: 1;

            pointer-events: none;
        }

        .manzana {
            width: 57px;

            left: -18px;
            top: 70px;

            opacity: .92;
        }

        .mango {
            width: 62px;

            right: -19px;
            top: 78px;

            opacity: .90;
        }

        .pina {
            width: 60px;

            left: -17px;
            bottom: 39px;

            opacity: .90;
        }

        .fruta-derecha {
            width: 55px;

            right: -18px;
            bottom: 49px;

            opacity: .88;
        }


        /* =====================================================
           HOJAS DECORATIVAS
           ===================================================== */

        .hoja {
            position: absolute;

            z-index: 1;

            width: 38px;

            opacity: .12;

            pointer-events: none;
        }

        .hoja-1 {
            top: 95px;
            left: 18px;

            transform: rotate(-20deg);
        }

        .hoja-2 {
            top: 150px;
            right: 8px;

            transform: rotate(30deg);
        }

        .hoja-3 {
            bottom: 95px;
            left: 9px;

            transform: rotate(25deg);
        }


        /* =====================================================
           CONTENIDO
           ===================================================== */

        .contenido-principal {
            position: relative;

            z-index: 5;

            width: 100%;
            height: 100%;
        }


        /* =====================================================
           HEADER
           ===================================================== */

        .header {
            height: 76px;

            background: rgba(255,255,255,.96);

            text-align: center;

            padding-top: 4px;

            border-bottom: 3px solid #2e7d32;
        }

        .logo {
            display: block;

            height: 49px;

            max-width: 175px;

            width: auto;

            margin: 0 auto;
        }

        .titulo {
            margin-top: 1px;

            color: #2e7d32;

            font-size: 6.5px;

            font-weight: bold;

            letter-spacing: .6px;
        }


        /* =====================================================
           CONTENIDO CENTRAL
           ===================================================== */

        .contenido {
            height: 220px;

            padding: 5px 9px 0 9px;

            text-align: center;
        }


        /* =====================================================
           TIPO
           ===================================================== */

        .tipo {
            display: inline-block;

            padding: 3px 13px;

            border-radius: 20px;

            color: #ffffff;

            font-size: 7px;

            font-weight: bold;

            letter-spacing: .5px;

            margin-bottom: 3px;
        }


        /* =====================================================
           NOMBRE
           ===================================================== */

        .nombre {
            width: 100%;

            color: #162d1d;

            font-size: 11px;

            font-weight: bold;

            line-height: 12px;

            margin-bottom: 4px;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;
        }


        /* =====================================================
           INFORMACIÓN
           ===================================================== */

        .informacion {
            width: 100%;

            background: rgba(255,255,255,.96);

            border: 1px solid #e2e5e0;

            border-radius: 5px;

            padding: 3px 6px;

            text-align: left;

            margin-bottom: 4px;
        }

        .fila {
            font-size: 6.5px;

            line-height: 8px;

            color: #444;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;
        }

        .label {
            color: #2e7d32;

            font-weight: bold;
        }


        /* =====================================================
           VIGENCIA
           ===================================================== */

        .vigencia {
            width: 100%;

            background: rgba(239,247,237,.97);

            border: 1px solid #b6cfad;

            border-radius: 5px;

            padding: 3px;

            margin-bottom: 3px;
        }

        .vigencia-titulo {
            color: #2e7d32;

            font-size: 6px;

            font-weight: bold;

            text-transform: uppercase;
        }

        .vigencia-fecha {
            color: #222;

            font-size: 6.3px;

            font-weight: bold;

            line-height: 8px;
        }


        /* =====================================================
           QR
           ===================================================== */

        .qr-wrapper {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            width: 86px;
            height: 86px;

            padding: 4px;

            background: #ffffff;

            border: 2px solid #2e7d32;

            border-radius: 6px;

            margin-top: 1px;
        }

        .qr-wrapper svg {
            display: block;

            width: 76px;
            height: 76px;
        }

        .qr-label {
            color: #2e7d32;

            font-size: 5.5px;

            font-weight: bold;

            margin-top: 1px;

            text-transform: uppercase;

            letter-spacing: .3px;
        }


        /* =====================================================
           MOTIVO
           ===================================================== */

        .motivo {
            margin-top: 2px;

            color: #777;

            font-size: 5.8px;

            line-height: 7px;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;
        }


        /* =====================================================
           FOOTER VERDE
           ===================================================== */

        .footer {
            position: absolute;

            left: 0;
            right: 0;
            bottom: 0;

            z-index: 10;

            height: 28px;

            background: #075b2c;

            color: #ffffff;

            text-align: center;

            padding: 4px 7px 3px;
        }

        .footer-texto {
            font-size: 5.7px;

            font-weight: bold;

            line-height: 7px;
        }

        .footer-direccion {
            color: #d5ead6;

            font-size: 5px;

            line-height: 6px;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;
        }

    </style>
</head>

<body>

<div class="credencial">

    {{-- =====================================================
         FRUTAS DECORATIVAS
         ===================================================== --}}

    <img
        src="{{ public_path('images/credencial/manzana.png') }}"
        class="fondo-fruta manzana"
        alt=""
    >

    <img
        src="{{ public_path('images/credencial/mango.png') }}"
        class="fondo-fruta mango"
        alt=""
    >

    <img
        src="{{ public_path('images/credencial/pina.png') }}"
        class="fondo-fruta pina"
        alt=""
    >


    {{-- =====================================================
         CONTENIDO PRINCIPAL
         ===================================================== --}}

    <div class="contenido-principal">

        {{-- HEADER --}}
        <div class="header">

            @if($logoBase64)
                <img
                    src="data:image/png;base64,{{ $logoBase64 }}"
                    class="logo"
                    alt="FRUITEX DE MÉXICO"
                >
            @endif

            <div class="titulo">
                CREDENCIAL DE ACCESO · SCA-QR
            </div>

        </div>


        {{-- CONTENIDO --}}
        <div class="contenido">

            {{-- TIPO --}}
            <div
                class="tipo"
                style="background: {{ $tipoColor }};"
            >
                {{ $tipoLabel }}
            </div>


            {{-- NOMBRE --}}
            <div class="nombre">
                {{ $externo?->nombre_completo ?? 'Nombre' }}
            </div>


            {{-- INFORMACIÓN --}}
            <div class="informacion">

                @if($solicitud->empresa)
                    <div class="fila">
                        <span class="label">EMPRESA:</span>
                        {{ $solicitud->empresa }}
                    </div>
                @endif

                <div class="fila">
                    <span class="label">PLANTA:</span>
                    {{ $solicitud->plantaDestino?->nombre ?? 'N/A' }}
                </div>
                <div class="fila">
                    <span class="label">Anfitrión:</span>
                    {{ $solicitud->anfitrion?->nombre_completo ?? 'N/A' }}
                </div>

            </div>


            {{-- VIGENCIA --}}
            <div class="vigencia">

                <div class="vigencia-titulo">
                    VIGENCIA DE ACCESO
                </div>

                <div class="vigencia-fecha">
                    {{ $solicitud->fecha_inicio->format('d/m/Y H:i') }}
                    &nbsp;—&nbsp;
                    {{ $solicitud->fecha_fin->format('d/m/Y H:i') }}
                </div>

            </div>


            {{-- QR --}}
            <div class="qr-wrapper">
                {!! QrCode::size(76)->margin(0)->generate($codigo_qr) !!}
            </div>

            <div class="qr-label">
                ESCANEAR PARA VALIDAR ACCESO
            </div>


            {{-- MOTIVO --}}
            <div class="motivo">
                Motivo: {{ $solicitud->motivo ?? 'N/A' }}
            </div>

        </div>


        {{-- FOOTER --}}
        <div class="footer">

            <div class="footer-texto">
                PRESENTE ESTA CREDENCIAL PARA SU ACCESO.
            </div>

            <div class="footer-direccion">
                {{ $solicitud->plantaDestino?->direccion ?? 'N/A' }}
            </div>

        </div>

    </div>

</div>

</body>
</html>