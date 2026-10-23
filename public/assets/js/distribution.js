(() => {
    const debounce = (callback, delay = 220) => {
        let timer = null;
        return (...args) => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => callback(...args), delay);
        };
    };

    document.querySelectorAll('[data-distribution-picker]').forEach((root) => {
        const input = root.querySelector('[data-distribution-search]');
        const hiddenId = root.querySelector('[data-distribution-id]');
        const results = root.querySelector('[data-distribution-results]');
        const selection = root.querySelector('[data-distribution-selection]');
        const endpoint = root.dataset.searchUrl || '';
        let sequence = 0;

        const clear = () => {
            hiddenId.value = '';
            selection.hidden = true;
            selection.textContent = '';
        };

        const status = (message) => {
            results.innerHTML = '';
            const el = document.createElement('div');
            el.className = 'distribution-picker-status';
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
            status('Buscando en RRHH2029...');

            try {
                const response = await fetch(endpoint + '?q=' + encodeURIComponent(query), {
                    headers: {'Accept': 'application/json'}
                });
                const payload = await response.json();

                if (current !== sequence) return;

                if (!response.ok || !payload.ok) {
                    status(payload.message || 'No fue posible consultar RRHH2029.');
                    return;
                }

                results.innerHTML = '';
                if (!payload.items?.length) {
                    status('No se encontraron funcionarios.');
                    return;
                }

                payload.items.forEach((employee) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'distribution-picker-result';

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
                    });
                    results.appendChild(button);
                });

                results.hidden = false;
            } catch (error) {
                if (current === sequence) {
                    status('No fue posible consultar RRHH2029.');
                }
            }
        });

        input?.addEventListener('input', () => {
            clear();
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


/* Distribución: resumen por artículo y talla, con asignaciones nominales
 * existentes. Validar solo calcula: no realiza solicitudes HTTP ni POST. */
(() => {
    const init = () => {
        const form = document.querySelector('[data-distribution-grouped]');
        if (!form) return;
        const groups = Array.from(form.querySelectorAll('[data-transfer-group]'));
        const allRows = Array.from(form.querySelectorAll('[data-transfer-item]'));
        const summary = form.querySelector('[data-transfer-message]');
        const selectedGlobal = form.querySelector('[data-transfer-global-selected]');
        const validateButton = form.querySelector('[data-transfer-validate]');
        const selectAllButton = form.querySelector('[data-transfer-select-all]');
        const clearAllButton = form.querySelector('[data-transfer-clear-all]');
        let validated = false;
        const amount = (raw) => {
            if (String(raw).trim() === '') return NaN;
            const number = Number(raw);
            return Number.isFinite(number) && number >= 0 ? number : NaN;
        };
        const display = value => Number(value.toFixed(3)).toLocaleString('es-PA', {
            maximumFractionDigits: 3
        });
        const forGroup = group => allRows.filter(
            row => row.dataset.variantId === group.dataset.variantId
        );
        const sum = rows => rows.reduce((total, row) => total + (amount(row.value) || 0), 0);
        const invalidate = () => {
            validated = false;
            if (summary) summary.dataset.validated = 'no';
        };
        const update = () => {
            let total = 0;
            groups.forEach(group => {
                const rows = forGroup(group);
                const selected = sum(rows);
                const persons = rows.filter(row => amount(row.value) > 0).length;
                const message = group.querySelector('[data-transfer-selected]');
                if (message) message.textContent = display(selected) + ' de '
                    + display(amount(group.dataset.pending)) + ' unidades · '
                    + persons + ' de ' + rows.length + ' destinatarios';
                total += selected;
            });
            if (selectedGlobal) selectedGlobal.textContent = display(total);
            if (summary) summary.textContent = display(total)
                + ' unidades seleccionadas. Validar no guarda ninguna transferencia.';
            return total;
        };
        const apply = (group) => {
            const field = group.querySelector('[data-transfer-group-quantity]');
            const desired = amount(field.value);
            if (!Number.isFinite(desired)) {
                invalidate();
                if (summary) summary.textContent = 'Introduzca una cantidad numérica válida.';
                return;
            }
            const limit = Math.min(amount(group.dataset.pending), amount(group.dataset.stock));
            let remaining = Math.min(desired, limit);
            forGroup(group).forEach(row => {
                const chosen = Math.min(amount(row.dataset.max), Math.max(0, remaining));
                row.value = chosen.toFixed(3);
                remaining = Math.max(0, remaining - chosen);
            });
            invalidate();
            update();
            if (desired > limit && summary) {
                summary.textContent = 'La talla supera el límite visible de saldo/stock. '
                    + 'Se distribuyeron como máximo ' + display(limit)
                    + ' unidades; corrija la cantidad solicitada y valide nuevamente.';
            }
        };
        groups.forEach(group => {
            const field = group.querySelector('[data-transfer-group-quantity]');
            const applyButton = group.querySelector('[data-transfer-apply]');
            const maxButton = group.querySelector('[data-transfer-max-group]');
            if (field) {
                field.addEventListener('change', () => apply(group));
                field.addEventListener('input', invalidate);
            }
            if (applyButton) applyButton.addEventListener('click', () => apply(group));
            if (maxButton) maxButton.addEventListener('click', () => {
                const pending = amount(group.dataset.pending);
                const stock = amount(group.dataset.stock);
                const limit = Math.min(pending, stock);
                if (!Number.isFinite(limit)) return;
                field.value = limit.toFixed(3);
                apply(group);
                if (limit + 0.000001 < pending && summary) {
                    summary.textContent = 'Talla incompleta por stock: se seleccionaron '
                        + display(limit) + ' de ' + display(pending)
                        + ' unidades pendientes. Revise los destinatarios.';
                }
            });
            forGroup(group).forEach(row => row.addEventListener('input', () => {
                const groupTotal = sum(forGroup(group));
                if (field) field.value = groupTotal.toFixed(3);
                invalidate();
                update();
            }));
        });
        // Un solo clic distribuye todo lo comprometible de cada talla.
        // Ninguno de estos controles envía POST, crea transferencias o toca el kardex.
        if (selectAllButton) selectAllButton.addEventListener('click', () => {
            let stockLimitedGroups = 0;
            let pendingShortfall = 0;
            groups.forEach(group => {
                const pending = amount(group.dataset.pending);
                const stock = amount(group.dataset.stock);
                if (!Number.isFinite(pending) || !Number.isFinite(stock)) return;
                const limit = Math.min(pending, stock);
                const field = group.querySelector('[data-transfer-group-quantity]');
                if (field) {
                    field.value = limit.toFixed(3);
                    apply(group);
                }
                if (limit + 0.000001 < pending) {
                    stockLimitedGroups++;
                    pendingShortfall += pending - limit;
                }
            });
            const selected = update();
            if (summary) {
                summary.textContent = 'SELECCIÓN MASIVA SIN GUARDAR: ' + display(selected)
                    + ' unidades asignadas automáticamente a los funcionarios. '
                    + (stockLimitedGroups
                        ? stockLimitedGroups + ' tallas limitadas por stock; '
                            + display(pendingShortfall) + ' unidades permanecen sin seleccionar. '
                            + 'Revise esas tallas antes de continuar.'
                        : 'Todas las unidades pendientes disponibles están seleccionadas. ')
                    + 'Pulse «Validar sin guardar» antes de preparar.';
            }
        });
        if (clearAllButton) clearAllButton.addEventListener('click', () => {
            groups.forEach(group => {
                const field = group.querySelector('[data-transfer-group-quantity]');
                if (field) field.value = '0';
                forGroup(group).forEach(row => { row.value = '0.000'; });
            });
            invalidate();
            update();
            if (summary) summary.textContent =
                'SELECCIÓN LIMPIADA SIN GUARDAR: 0 unidades. No se preparó ninguna transferencia.';
        });
        const validate = () => {
            let approved = 0;
            let hasError = false;
            for (const group of groups) {
                const requested = amount(group.querySelector('[data-transfer-group-quantity]').value);
                const pending = amount(group.dataset.pending);
                const stock = amount(group.dataset.stock);
                const rows = forGroup(group);
                const total = sum(rows);
                if (!Number.isFinite(requested) || !Number.isFinite(stock)
                    || !Number.isFinite(pending) || requested > Math.min(stock, pending) + 0.000001
                    || Math.abs(requested - total) > 0.000001
                    || rows.some(row => {
                        const q = amount(row.value);
                        return !Number.isFinite(q) || q > amount(row.dataset.max) + 0.000001;
                    })) {
                    hasError = true;
                }
                approved += total;
            }
            if (hasError || approved <= 0) {
                validated = false;
                if (summary) summary.textContent = hasError
                    ? 'La cantidad de una talla no coincide con sus destinatarios, supera el saldo o el stock visible. Corrija y valide nuevamente.'
                    : 'No hay cantidades seleccionadas. Nada se preparó; elija al menos una unidad.';
                return false;
            }
            validated = true;
            if (summary) {
                summary.dataset.validated = 'yes';
                summary.textContent = 'VALIDACIÓN CORRECTA SIN GUARDAR: '
                    + display(approved) + ' unidades asignadas nominalmente. '
                    + 'Preparar la transferencia requiere una confirmación adicional.';
            }
            return true;
        };
        if (validateButton) validateButton.addEventListener('click', validate);
        form.addEventListener('submit', event => {
            if (!validated) {
                event.preventDefault();
                if (summary) summary.textContent = 'Primero pulse «Validar sin guardar». No se ha creado ninguna transferencia.';
                return;
            }
            if (!validate()) {
                event.preventDefault();
                return;
            }
            if (!window.confirm('Preparar transferencia con '
                + (selectedGlobal ? selectedGlobal.textContent : 'las cantidades indicadas')
                + ' unidades nominales. Esto crea un documento en estado preparado; NO despacha inventario. ¿Continuar?')) {
                event.preventDefault();
            }
        });
        update();
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else init();
})();
