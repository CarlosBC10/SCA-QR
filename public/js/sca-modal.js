/* ============================================================
 * sca-modal.js — Sistema de Modales Estandarizados SCA-QR
 * ============================================================
 *
 * PROPOSITO:
 *   Wrapper JS sobre Bootstrap 5.3 Modal que estandariza la
 *   apariencia y la API de todos los modales del sistema.
 *
 * USO (desde cualquier vista blade):
 *   ScaModal.success('Título', 'Mensaje');
 *   ScaModal.error('Título', 'Mensaje');
 *   ScaModal.warning('Título', 'Mensaje');
 *   ScaModal.info('Título', 'Mensaje');
 *   ScaModal.loading('Procesando', 'Espere...');   // bloquea, sin botones
 *   ScaModal.confirm({ titulo, mensaje, tipo, requiereInput,
 *                     textoConfirmar, onConfirm, onCancel });
 *   ScaModal.close();                               // cierra el modal actual
 *
 * OPCIONES AVANZADAS (success/error/warning/info):
 *   ScaModal.success('Título', 'Mensaje', {
 *       onOk: () => { ... },        // callback al clic en "Aceptar"
 *       autoClose: 3000,            // auto-cerrar después de 3 segundos (ms)
 *       detalles: [...]             // bloques de detalles
 *   });
 *
 * DEPENDENCIAS:
 *   - Bootstrap 5.3 (bundle)
 *   - Bootstrap Icons 1.11.3
 *
 * EXCLUSIONES:
 *   Este archivo NO se carga en el kiosko.
 * ============================================================ */

(function () {
    'use strict';

    // ===== Referencias al DOM del modal container =====
    const modalEl = document.getElementById('scaModal');
    if (!modalEl) {
        console.warn('[ScaModal] Container #scaModal no encontrado. El modal no estará disponible en esta página.');
        return;
    }

    const bodyEl    = document.getElementById('scaModalBody');
    const footerEl  = document.getElementById('scaModalFooter');
    const contentEl = document.getElementById('scaModalContent');

    const modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true });

    // ===== Mapas de configuración por tipo =====
    const ICONS = {
        success: 'bi-check-circle-fill',
        error:   'bi-x-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        info:    'bi-info-circle-fill',
        danger:  'bi-exclamation-triangle-fill',
        loading: ''
    };

    const BTN_TEXT = {
        success: 'Aceptar',
        error:   'Aceptar',
        warning: 'Entendido',
        info:    'Entendido'
    };

    const BTN_CLASS = {
        success: 'btn-success',
        error:   'btn-danger',
        warning: 'btn-warning',
        info:    'btn-info'
    };

    // ===== Estado interno =====
    let onConfirmCallback  = null;
    let onCancelCallback   = null;
    let onOkCallback       = null;
    let confirmPromiseResolver = null;

    let wasConfirmed = false;
    let confirmValue = null;
    let isConfirmMode = false;

    let autoCloseTimer = null;

    let isHiding = false;
    let isHandlingHidden = false;
    let pendingRender = null;

    // ============================================================
    // Listeners GLOBALES (agregados una sola vez)
    // ============================================================

    modalEl.addEventListener('hide.bs.modal', () => {
        isHiding = true;
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        isHandlingHidden = true;
        isHiding = false;

        // Limpiar autoCloseTimer si el usuario cerró antes
        if (autoCloseTimer) {
            clearTimeout(autoCloseTimer);
            autoCloseTimer = null;
        }

        // Guardar referencias antes de resetear
        const confirmCb = onConfirmCallback;
        const cancelCb  = onCancelCallback;
        const okCb      = onOkCallback;
        const resolver  = confirmPromiseResolver;
        const confirmed = wasConfirmed;
        const value     = confirmValue;
        const wasConfirm = isConfirmMode;

        // Resetear estado PRIMERO
        wasConfirmed = false;
        confirmValue = null;
        onConfirmCallback = null;
        onCancelCallback = null;
        onOkCallback = null;
        confirmPromiseResolver = null;
        isConfirmMode = false;

        // Limpiar barra de progreso
        const bar = contentEl.querySelector('.progress-bar-wrap');
        if (bar) bar.remove();

        // Disparar callbacks según el modo
        if (wasConfirm) {
            if (confirmed) {
                if (typeof confirmCb === 'function') confirmCb(value);
            } else {
                if (typeof cancelCb === 'function') cancelCb();
            }
            if (resolver) resolver(confirmed);
        } else {
            if (typeof okCb === 'function') okCb();
        }

        // Procesar render pendiente
        if (pendingRender) {
            const pending = pendingRender;
            pendingRender = null;
            render(pending);
        }

        isHandlingHidden = false;
    });

    /**
     * Renderiza el contenido del modal según el tipo.
     */
    function render(opts) {
        const { tipo, titulo, mensaje, detalles, input, botones, onOk, autoClose } = opts;

        // FIX v4: Si el modal se está cerrando, encolar este render
        if (isHiding) {
            pendingRender = opts;
            return modal;
        }

        // FIX v4: Limpiar autoCloseTimer anterior
        if (autoCloseTimer) {
            clearTimeout(autoCloseTimer);
            autoCloseTimer = null;
        }

        // FIX v4: Asignar onOk y autoClose AQUÍ (cuando render realmente se ejecuta),
        // no en la API pública. Esto evita que onOk se dispare cuando el modal
        // anterior (loading) termina de cerrarse.
        onOkCallback = onOk || null;
        if (autoClose) {
            autoCloseTimer = setTimeout(() => { modal.hide(); }, autoClose);
        }

        // Configurar backdrop y keyboard según el tipo
        modal._config.backdrop = (tipo === 'loading') ? 'static' : true;
        modal._config.keyboard  = (tipo === 'loading') ? false : true;

        // 1. Aplica la clase de tipo
        contentEl.className = 'modal-content sca-modal-' + tipo;

        // 2. Construye el HTML del body
        let html = `
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            <div class="sca-icon">
                ${tipo === 'loading'
                    ? `<div class="spinner-border" role="status"></div>`
                    : `<i class="bi ${ICONS[tipo]}"></i>`}
            </div>
            <div class="sca-title">${titulo}</div>
            <div class="sca-message">${mensaje}</div>
        `;

        // 3. En loading, agrega 3 dots animados
        if (tipo === 'loading') {
            html += `<div class="sca-steps"><div class="step"></div><div class="step"></div><div class="step"></div></div>`;
        }

        // 4. Bloques de detalles
        if (detalles && detalles.length) {
            html += '<div class="sca-details">';
            detalles.forEach(d => {
                const blockClass = d.tipo === 'success' ? 'success-block'
                                 : (d.tipo === 'error' ? 'error-block' : '');
                const headerIcon = d.tipo === 'success' ? 'bi-check-circle-fill'
                                 : (d.tipo === 'error' ? 'bi-x-circle-fill'
                                 : (d.icono || 'bi-list-ul'));
                const itemIcon = d.tipo === 'success' ? 'bi-check2'
                               : (d.tipo === 'error' ? 'bi-x-lg'
                               : (d.itemIcono || 'bi-dot'));
                const itemColor = d.color
                                || (d.tipo === 'success' ? 'var(--sca-success)'
                                : (d.tipo === 'error' ? 'var(--sca-error)'
                                : 'inherit'));
                html += `<div class="detail-block ${blockClass}">
                    <div class="detail-title">
                        <i class="bi ${headerIcon}"></i>
                        ${d.titulo}
                    </div>
                    ${d.items.map(i => `<div class="detail-item"><i class="bi ${itemIcon}" style="color:${itemColor}"></i><span>${i}</span></div>`).join('')}
                </div>`;
            });
            html += '</div>';
        }

        // 5. Input para motivo
        if (input) {
            const counterHtml = input.maxlength
                ? `<div class="sca-input-counter"><span id="scaInputCount">0</span>/${input.maxlength}</div>`
                : '';
            html += `<div class="sca-input">
                <label>${input.label}${input.required ? ' <span class="text-danger">*</span>' : ''}</label>
                ${input.type === 'textarea'
                    ? `<textarea id="scaInput" rows="${input.rows || 3}" placeholder="${input.placeholder || ''}" ${input.required ? 'required' : ''} ${input.maxlength ? 'maxlength="' + input.maxlength + '"' : ''}></textarea>`
                    : `<input type="${input.type || 'text'}" id="scaInput" placeholder="${input.placeholder || ''}" ${input.required ? 'required' : ''} ${input.maxlength ? 'maxlength="' + input.maxlength + '"' : ''}>`}
                <div class="invalid-feedback" id="scaInputError" style="display:none;font-size:0.8rem;color:var(--sca-error);margin-top:0.3rem;"></div>
                ${counterHtml}
            </div>`;

            if (input.maxlength) {
                setTimeout(() => {
                    const inp = document.getElementById('scaInput');
                    const counter = document.getElementById('scaInputCount');
                    if (inp && counter) {
                        inp.addEventListener('input', () => { counter.textContent = inp.value.length; });
                    }
                }, 50);
            }
        }

        bodyEl.innerHTML = html;

        // 6. Barra de progreso solo en loading
        const existingBar = contentEl.querySelector('.progress-bar-wrap');
        if (existingBar) existingBar.remove();
        if (tipo === 'loading') {
            contentEl.insertAdjacentHTML('beforeend', '<div class="progress-bar-wrap"></div>');
        }

        // 7. Footer (botones)
        if (tipo === 'loading') {
            footerEl.innerHTML = '';
        } else if (botones && botones.length) {
            footerEl.innerHTML = botones.map(b =>
                `<button type="button" class="btn ${b.class || 'btn-secondary'}" data-sca-action="${b.action}">${b.icon || ''} ${b.text}</button>`
            ).join('');
        } else {
            footerEl.innerHTML = `<button type="button" class="btn ${BTN_CLASS[tipo]}" data-sca-action="ok">${BTN_TEXT[tipo] || 'Aceptar'}</button>`;
        }

        // 8. Wire los botones del footer
        footerEl.querySelectorAll('button[data-sca-action]').forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.dataset.scaAction;

                if (action === 'ok' || action === 'close') {
                    wasConfirmed = false;
                    modal.hide();
                } else if (action === 'confirm') {
                    let val = null;
                    const inp = document.getElementById('scaInput');
                    if (inp) {
                        if (inp.required && !inp.value.trim()) {
                            inp.classList.add('is-invalid');
                            const err = document.getElementById('scaInputError');
                            if (err) {
                                err.textContent = 'Este campo es obligatorio';
                                err.style.display = 'block';
                            }
                            return;
                        }
                        val = inp.value.trim();
                    }
                    wasConfirmed = true;
                    confirmValue = val;
                    modal.hide();
                }
            });
        });

        // 9. Re-trigger de animaciones
        contentEl.style.animation = 'none';
        contentEl.offsetHeight;
        contentEl.style.animation = '';

        // 10. Mostrar el modal
        const isVisible = modal._isShown || modalEl.classList.contains('show');
        if (!isVisible) {
            if (isHandlingHidden) {
                setTimeout(() => modal.show(), 0);
            } else {
                modal.show();
            }
        }

        return modal;
    }

    // ============================================================
    // API PÚBLICA — expuesta como window.ScaModal
    // ============================================================
    window.ScaModal = {

        /**
         * Modal de éxito (verde).
         * @param {string} titulo
         * @param {string} mensaje  (HTML permitido)
         * @param {Object} opts
         *   - detalles: Array de bloques de detalle
         *   - onOk: function() — callback al clic en "Aceptar" o al auto-cerrar
         *   - autoClose: number (ms) — auto-cerrar después de X milisegundos
         */
        success(titulo, mensaje, opts = {}) {
            isConfirmMode = false;
            // FIX v4: NO asignamos onOk ni autoClose aquí. Se asignan en render()
            // cuando el modal realmente se muestra. Esto evita que onOk se dispare
            // cuando el modal anterior (loading) termina de cerrarse.
            return render({
                tipo: 'success',
                titulo,
                mensaje,
                detalles: opts.detalles,
                onOk: opts.onOk,
                autoClose: opts.autoClose,
            });
        },

        error(titulo, mensaje, opts = {}) {
            isConfirmMode = false;
            return render({
                tipo: 'error',
                titulo,
                mensaje,
                onOk: opts.onOk,
                autoClose: opts.autoClose,
            });
        },

        warning(titulo, mensaje, opts = {}) {
            isConfirmMode = false;
            return render({
                tipo: 'warning',
                titulo,
                mensaje,
                onOk: opts.onOk,
                autoClose: opts.autoClose,
            });
        },

        info(titulo, mensaje, opts = {}) {
            isConfirmMode = false;
            return render({
                tipo: 'info',
                titulo,
                mensaje,
                onOk: opts.onOk,
                autoClose: opts.autoClose,
            });
        },

        loading(titulo, mensaje = 'Procesando...') {
            isConfirmMode = false;
            // FIX v4: loading no tiene onOk ni autoClose. render() los seteará a null.
            return render({ tipo: 'loading', titulo, mensaje });
        },

        confirm(cfg = {}) {
            const {
                titulo, mensaje, tipo = 'warning',
                requiereInput = null,
                textoConfirmar = 'Confirmar',
                textoCancelar = 'Cancelar',
                onConfirm = null,
                onCancel = null
            } = cfg;

            isConfirmMode = true;

            const esDanger = (tipo === 'danger');
            const botones = [
                { text: textoCancelar,  class: 'btn-secondary',                       action: 'ok' },
                { text: textoConfirmar, class: esDanger ? 'btn-danger' : 'btn-warning', action: 'confirm', icon: '<i class="bi bi-check-lg"></i>' }
            ];

            onConfirmCallback = onConfirm;
            onCancelCallback  = onCancel;

            const realTipo = esDanger ? 'danger' : tipo;
            const promiseMode = (typeof onConfirm !== 'function');
            let promise = null;
            if (promiseMode) {
                promise = new Promise(resolve => { confirmPromiseResolver = resolve; });
            }

            render({
                tipo: realTipo,
                titulo,
                mensaje,
                input: requiereInput ? {
                    type: requiereInput.type || 'text',
                    label: requiereInput.label,
                    placeholder: requiereInput.placeholder,
                    required: !!requiereInput.required,
                    rows: requiereInput.rows,
                    maxlength: requiereInput.maxlength || 500
                } : null,
                botones
            });

            return promise;
        },

        close() { modal.hide(); }
    };

    // ============================================================
    // FIX: Congelar el loading durante el redirect
    //
    // Cuando el browser está por navegar a otra página (beforeunload),
    // si hay un modal de loading visible, le agregamos una clase CSS
    // que quita la animación de fade-out. Así el loading se queda
    // visible hasta que la nueva página cargue (DOM se reemplaza),
    // eliminando el "hueco" entre el fade-out y el redirect.
    // ============================================================
    window.addEventListener('beforeunload', () => {
        if (modalEl.classList.contains('show') && contentEl.classList.contains('sca-modal-loading')) {
            modalEl.classList.add('sca-loading-frozen');
        }
    });
})();