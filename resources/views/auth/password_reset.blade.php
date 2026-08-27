@extends('layouts.app')

@section('title', 'Recuperar Contraseña')

@section('content')
<div class="row justify-content-center align-items-center" style="min-height: 70vh;">
    <div class="col-md-5">
        <div class="card border-0 shadow-lg" style="border-radius: 20px;">
            <div class="card-header bg-transparent text-center py-4 border-0">
                <div class="mb-3">
                    <i class="bi bi-key-fill text-success" style="font-size: 3rem;"></i>
                </div>
                <h3 class="fw-bold mb-1 text-dark">¿Olvidó su contraseña?</h3>
                <p class="text-muted mb-0">No se preocupe, le enviaremos una temporal.</p>
            </div>
            <div class="card-body px-4 pb-4">
                @if($errors->any())
                    <div class="alert alert-danger" style="border-radius: 12px;">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" id="formResetPassword">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label text-muted small text-uppercase fw-bold">Usuario</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-person text-success"></i>
                            </span>
                            <input type="text" name="username" class="form-control border-start-0 ps-0" required autofocus value="{{ old('username') }}" placeholder="Número de empleado o G-XXXX">
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg fw-bold" style="border-radius: 12px;" id="btnResetPassword">
                            <i class="bi bi-send-fill me-2"></i> Enviar Correo
                        </button>
                    </div>
                </form>
                <div class="text-center mt-4">
                    <a href="{{ route('login') }}" class="text-muted small text-decoration-none">
                        <i class="bi bi-arrow-left-circle"></i> Volver al Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
    // ============================================================
    // M2 [ScaModal] — Intercept form submit for AJAX flow
    //
    // FLUJO:
    //   1. Usuario ingresa username y clic en "Enviar Correo"
    //   2. JS intercepta el submit (e.preventDefault())
    //   3. Muestra modal loading ("Enviando correo...")
    //   4. Hace fetch() al controller (POST con FormData)
    //   5. Controller valida usuario, genera password temporal,
    //      envía correo y devuelve JSON
    //   6. Cierra loading, muestra success
    //   7. Al clic en "Aceptar" → redirect al login
    //      O después de 3 segundos → auto-redirect al login
    // ============================================================
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('formResetPassword');
        if (!form) return;

        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn = document.getElementById('btnResetPassword');
            const originalBtnHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Enviando...';

            // Mostrar loading
            ScaModal.loading('Enviando correo', 'Generando contraseña temporal y enviando correo...');

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: new FormData(form),
                });

                const data = await response.json();

                if (data.success) {
                    // Option E: NO cerramos el loading. Se queda visible durante la navegación.
                    // El success se mostrará en el login vía flash sca_modal.
                    window.location.href = data.redirect;
                } else {
                    // En caso de error, cerrar el loading y mostrar error
                    ScaModal.close();
                    if (data.errors) {
                        const errores = Object.values(data.errors).flat();
                        ScaModal.error('Error de validación', errores.join('<br>'));
                    } else {
                        ScaModal.error('Error', data.mensaje || 'No se pudo enviar el correo.');
                    }
                    btn.disabled = false;
                    btn.innerHTML = originalBtnHtml;
                }
            } catch (err) {
                ScaModal.close();
                ScaModal.error('Error de conexión', 'No se pudo contactar con el servidor. Inténtalo de nuevo.');
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            }
        });
    });
</script>
@endpush

@endsection