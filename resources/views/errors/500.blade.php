{{-- resources/views/errors/500.blade.php --}}
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error 500 · Sistema de Control de Accesos mediante QR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/sca-ui.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    </style>
</head>
<body>
    <div class="text-center">
        <i class="bi bi-server text-danger" style="font-size: 5rem;"></i>
        <h1 class="display-1 fw-bold text-danger mt-3">500</h1>
        <h3 class="fw-bold mb-2">Error del Servidor</h3>
        <p class="text-muted mb-4">Ocurrió un problema interno contacte al departamento de TI (verifique su conexion a internet). Inténtalo de nuevo más tarde.</p>
        <a href="/" class="btn btn-success">
            <i class="bi bi-arrow-left"></i> Volver al Inicio
        </a>
    </div>
</body>
</html>