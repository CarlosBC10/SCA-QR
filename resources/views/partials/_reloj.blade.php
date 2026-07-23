{{-- resources/views/partials/_reloj.blade.php --}}
{{--
  Reloj en tiempo real reutilizable.
  Muestra hora (HH:MM:SS) y fecha (Lun, 15 Ene 2025) actualizándose cada segundo.
  Se incrusta en cualquier vista con: @include('partials._reloj', ['id' => 'topbar'])
--}}
<div class="d-flex align-items-center gap-2" id="reloj-{{ $id ?? 'default' }}">
    <i class="bi bi-clock-history text-success"></i>
    <div>
        <div class="fw-bold" id="reloj-hora-{{ $id ?? 'default' }}" style="font-size: 1.1rem; line-height: 1;">--:--:--</div>
        <small class="text-muted" id="reloj-fecha-{{ $id ?? 'default' }}" style="font-size: 0.7rem;">Cargando...</small>
    </div>
</div>

@push('scripts')
<script>
(function() {
    const id = '{{ $id ?? "default" }}';
    const horaEl = document.getElementById('reloj-hora-' + id);
    const fechaEl = document.getElementById('reloj-fecha-' + id);

    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    function actualizar() {
        const ahora = new Date();
        const h = String(ahora.getHours()).padStart(2, '0');
        const m = String(ahora.getMinutes()).padStart(2, '0');
        const s = String(ahora.getSeconds()).padStart(2, '0');
        horaEl.textContent = `${h}:${m}:${s}`;
        
        const dia = dias[ahora.getDay()];
        const d = ahora.getDate();
        const mes = meses[ahora.getMonth()];
        const a = ahora.getFullYear();
        fechaEl.textContent = `${dia}, ${d} ${mes} ${a}`;
    }

    actualizar();
    setInterval(actualizar, 1000);
})();
</script>
@endpush