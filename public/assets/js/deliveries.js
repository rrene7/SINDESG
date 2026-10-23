(() => {
    const debounce = (callback, delay = 220) => {
        let timer = null;
        return (...args) => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => callback(...args), delay);
        };
    };

    const renderHistory = async (root, employeeId) => {
        const historyUrl = root.dataset.historyUrl || '';
        const panel = document.querySelector('[data-history-preview]');
        const body = document.querySelector('[data-history-preview-body]');

        if (!historyUrl || !panel || !body || !employeeId) return;

        panel.hidden = false;
        body.textContent = 'Consultando entregas anteriores...';

        try {
            const response = await fetch(
                historyUrl + '?employee_id=' + encodeURIComponent(employeeId),
                {headers: {'Accept': 'application/json'}}
            );
            const payload = await response.json();

            body.innerHTML = '';

            if (!response.ok || !payload.ok) {
                body.textContent = payload.message || 'No fue posible consultar el historial.';
                return;
            }

            if (!payload.items?.length) {
                body.innerHTML = '<div class="history-preview-empty">Sin entregas confirmadas anteriores.</div>';
                return;
            }

            payload.items.forEach((row) => {
                const item = document.createElement('div');
                item.className = 'history-preview-row';

                const title = document.createElement('strong');
                title.textContent = row.product_name;

                const variant = document.createElement('span');
                variant.textContent = row.variant_code
                    + (row.size_name ? ' · Talla ' + row.size_name : '');

                const meta = document.createElement('span');
                meta.textContent = Number(row.quantity).toLocaleString()
                    + ' · ' + new Date(row.delivered_at.replace(' ', 'T')).toLocaleDateString();

                item.append(title, variant, meta);
                body.appendChild(item);
            });
        } catch (error) {
            body.textContent = 'No fue posible consultar el historial.';
        }
    };

    const scopeRoot = document.querySelector('[data-scope-override]');
    if (scopeRoot) {
        const checkbox = scopeRoot.querySelector('[data-scope-checkbox]');
        const reasonWrap = scopeRoot.querySelector('[data-scope-reason-wrap]');
        const reason = scopeRoot.querySelector('[data-scope-reason]');

        const syncScopeOverride = () => {
            const enabled = Boolean(checkbox?.checked);
            if (reasonWrap) reasonWrap.hidden = !enabled;
            if (reason) {
                reason.required = enabled;
                reason.disabled = !enabled;
                if (!enabled) reason.value = '';
            }
        };

        checkbox?.addEventListener('change', syncScopeOverride);
        syncScopeOverride();
    }

    document.querySelectorAll('[data-delivery-picker]').forEach((root) => {
        const input = root.querySelector('[data-delivery-search]');
        const hiddenId = root.querySelector('[data-delivery-id]');
        const results = root.querySelector('[data-delivery-results]');
        const selection = root.querySelector('[data-delivery-selection]');
        const endpoint = root.dataset.searchUrl || '';
        let sequence = 0;

        const clearSelection = () => {
            hiddenId.value = '';
            selection.hidden = true;
            selection.textContent = '';
        };

        const showStatus = (message) => {
            results.innerHTML = '';
            const el = document.createElement('div');
            el.className = 'delivery-picker-status';
            el.textContent = message;
            results.appendChild(el);
            results.hidden = false;
        };

        const search = debounce(async () => {
            const query = input.value.trim();

            if (query.length < 2) {
                results.hidden = true;
                return;
            }

            const current = ++sequence;
            showStatus('Buscando en RRHH2029...');

            try {
                const response = await fetch(
                    endpoint + '?q=' + encodeURIComponent(query),
                    {headers: {'Accept': 'application/json'}}
                );
                const payload = await response.json();

                if (current !== sequence) return;

                if (!response.ok || !payload.ok) {
                    showStatus(payload.message || 'No fue posible consultar RRHH2029.');
                    return;
                }

                results.innerHTML = '';

                if (!payload.items?.length) {
                    showStatus('No se encontraron funcionarios.');
                    return;
                }

                payload.items.forEach((employee) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'delivery-picker-result';

                    const title = document.createElement('strong');
                    title.textContent = employee.display_name;

                    const meta = document.createElement('span');
                    meta.textContent = [
                        employee.document_number ? 'Cédula ' + employee.document_number : null,
                        employee.position_number ? 'Pos. ' + employee.position_number : null,
                        employee.direction_name ? 'Dirección: ' + employee.direction_name : null,
                        employee.office_name ? 'Oficina: ' + employee.office_name : null
                    ].filter(Boolean).join(' · ');

                    button.append(title, meta);

                    button.addEventListener('click', () => {
                        hiddenId.value = employee.employee_id;
                        input.value = employee.display_name;
                        selection.textContent = meta.textContent;
                        selection.hidden = false;
                        results.hidden = true;

                        if (root.hasAttribute('data-recipient-picker')) {
                            renderHistory(root, employee.employee_id);
                        }
                    });

                    results.appendChild(button);
                });

                results.hidden = false;
            } catch (error) {
                if (current === sequence) {
                    showStatus('No fue posible consultar RRHH2029.');
                }
            }
        });

        input?.addEventListener('input', () => {
            clearSelection();
            search();
        });

        input?.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') results.hidden = true;
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) results.hidden = true;
        });
    });
})();


// Entrega P4 simplificada: una sola pantalla, firma manuscrita y POST único.
// Nunca confirmar con un lienzo vacío; no guardar la firma en almacenamiento local.
(() => {
    const form = document.querySelector('[data-p4-direct-signature]');
    if (!form) return;
    const canvas = form.querySelector('[data-p4-direct-canvas]');
    const field = form.querySelector('[data-p4-direct-signature-value]');
    const button = form.querySelector('[data-p4-direct-submit]');
    const clear = form.querySelector('[data-p4-direct-clear]');
    const ctx = canvas?.getContext('2d');
    if (!canvas || !ctx || !field || !button || !clear) return;

    let activePointer = null;
    let drawn = false;
    ctx.strokeStyle = '#142b47';
    ctx.lineWidth = 2.7;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const point = (event) => {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (event.clientX - rect.left) * canvas.width / rect.width,
            y: (event.clientY - rect.top) * canvas.height / rect.height,
        };
    };
    canvas.addEventListener('pointerdown', event => {
        if (activePointer !== null) return;
        activePointer = event.pointerId;
        const p = point(event);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        ctx.lineTo(p.x + 0.1, p.y + 0.1);
        ctx.stroke();
        drawn = true;
        canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', event => {
        if (event.pointerId !== activePointer) return;
        const p = point(event);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
    });
    const end = event => {
        if (event.pointerId === activePointer) activePointer = null;
    };
    canvas.addEventListener('pointerup', end);
    canvas.addEventListener('pointercancel', end);
    clear.addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        drawn = false;
        field.value = '';
    });
    form.addEventListener('submit', event => {
        if (!drawn) {
            event.preventDefault();
            window.alert('Capture la firma del funcionario receptor.');
            return;
        }
        field.value = canvas.toDataURL('image/png');
        if (field.value.length > 220000) {
            event.preventDefault();
            window.alert('La firma es demasiado grande. Límpiela e intente nuevamente.');
            field.value = '';
            return;
        }
        button.disabled = true; // Evita doble clic; servidor garantiza atomicidad.
        button.textContent = 'Registrando entrega…';
    });
})();
