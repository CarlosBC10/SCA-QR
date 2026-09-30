{{-- resources/views/credenciales/credenciales_grid_pdf.blade.php --}}
@php
    // Hoja Carta: 216mm x 279mm
    // Margen: 8mm cada lado -> área útil: 200mm x 263mm
    // 3 columnas de 54mm = 162mm + 2 gaps de 19mm = 200mm (perfecto)
    // 3 filas de 85.6mm = 256.8mm + 2 gaps de 3mm = 262.8mm (cabe en 263mm)
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: letter portrait;
            margin: 8mm;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: sans-serif;
        }
        .grid {
            display: flex;
            flex-wrap: wrap;
            gap: 3mm;
        }
        .credencial-wrapper {
            width: 54mm;
            height: 85.6mm;
            overflow: hidden; /* Evita que la imagen se salga */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .credencial-wrapper img {
            width: 54mm;
            height: 85.6mm;
            object-fit: cover; /* Ajusta la imagen al tamaño exacto sin deformar */
        }
    </style>
</head>
<body>
    <div class="grid">
        @foreach($credenciales as $cred)
            <div class="credencial-wrapper">
                <img src="data:image/png;base64,{{ $cred['pngBase64'] }}" alt="Credencial">
            </div>
        @endforeach
    </div>
</body>
</html>