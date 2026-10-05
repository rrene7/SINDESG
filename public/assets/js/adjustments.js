(() => {
    const form = document.querySelector('[data-adjustment-form]');
    const type = form?.querySelector('[data-adjustment-type]');

    const syncFields = () => {
        if (!form || !type) return;

        const value = type.value;
        const returnCondition = form.querySelector('[data-return-condition]');
        const replacement = form.querySelector('[data-replacement-variant]');
        const normalQuantity = form.querySelector('[data-normal-quantity]');
        const countedQuantity = form.querySelector('[data-counted-quantity]');
        const manualDirection = form.querySelector('[data-manual-direction]');

        if (returnCondition) {
            const visible = value === 'return' || value === 'exchange';
            returnCondition.hidden = !visible;
            const select = returnCondition.querySelector('select');
            if (select) select.required = visible;
        }

        if (replacement) {
            const visible = value === 'exchange' || value === 'replacement';
            replacement.hidden = !visible;
            const select = replacement.querySelector('select');
            if (select) select.required = visible;
        }

        if (normalQuantity && countedQuantity) {
            const isCount = value === 'cycle_count';
            normalQuantity.hidden = isCount;
            countedQuantity.hidden = !isCount;

            const normalInput = normalQuantity.querySelector('input');
            const countedInput = countedQuantity.querySelector('input');
            if (normalInput) normalInput.required = !isCount;
            if (countedInput) countedInput.required = isCount;
        }

        if (manualDirection) {
            const visible = value === 'authorized_adjustment';
            manualDirection.hidden = !visible;
            const select = manualDirection.querySelector('select');
            if (select) select.required = visible;
        }
    };

    type?.addEventListener('change', syncFields);
    syncFields();

    const debounce = (callback, delay = 220) => {
        let timer = null;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => callback(...args), delay);
        };
    };

    document.querySelectorAll('[data-adjustment-picker]').forEach((root) => {
        const input = root.querySelector('[data-adjustment-search]');
        const hiddenId = root.querySelector('[data-adjustment-id]');
        const results = root.querySelector('[data-adjustment-results]');
        const selection = root.querySelector('[data-adjustment-selection]');
        const endpoint = root.dataset.searchUrl || '';
        let sequence = 0;

        const showStatus = (message) => {
            results.innerHTML = '';
            const el = document.createElement('div');
            el.className = 'adjustment-picker-status';
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

                results.innerHTML = '';

                if (!response.ok || !payload.ok) {
                    showStatus(payload.message || 'No fue posible consultar RRHH2029.');
                    return;
                }

                if (!payload.items?.length) {
                    showStatus('No se encontraron funcionarios.');
                    return;
                }

                payload.items.forEach((employee) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'adjustment-picker-result';

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
                showStatus('No fue posible consultar RRHH2029.');
            }
        });

        input?.addEventListener('input', () => {
            hiddenId.value = '';
            selection.hidden = true;
            selection.textContent = '';
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
