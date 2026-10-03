(() => {
    const debounce = (callback, delay = 220) => {
        let timer = null;
        return (...args) => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => callback(...args), delay);
        };
    };

    const setupPicker = ({
        rootSelector,
        inputSelector,
        idSelector,
        resultsSelector,
        selectionSelector,
        renderItem,
        renderSelection
    }) => {
        const root = document.querySelector(rootSelector);
        if (!root) return;

        const input = root.querySelector(inputSelector);
        const hiddenId = root.querySelector(idSelector);
        const results = root.querySelector(resultsSelector);
        const selection = root.querySelector(selectionSelector);
        const endpoint = root.dataset.searchUrl || '';
        let requestSequence = 0;

        const clearSelection = () => {
            if (hiddenId) hiddenId.value = '';
            if (selection) {
                selection.hidden = true;
                selection.textContent = '';
            }
        };

        const status = (message) => {
            if (!results) return;
            results.innerHTML = '';
            const item = document.createElement('div');
            item.className = 'warehouse-picker-status';
            item.textContent = message;
            results.appendChild(item);
            results.hidden = false;
        };

        const runSearch = debounce(async () => {
            const query = input?.value.trim() || '';
            if (query.length < 2) {
                if (results) results.hidden = true;
                return;
            }

            const sequence = ++requestSequence;
            status('Buscando en RRHH2029...');

            try {
                const response = await fetch(endpoint + '?q=' + encodeURIComponent(query), {
                    headers: {'Accept': 'application/json'}
                });
                const payload = await response.json();

                if (sequence !== requestSequence) return;
                if (!response.ok || !payload.ok) {
                    status(payload.message || 'No fue posible consultar RRHH2029.');
                    return;
                }

                results.innerHTML = '';
                if (!payload.items?.length) {
                    status('No se encontraron coincidencias.');
                    return;
                }

                payload.items.forEach((item) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'warehouse-picker-result';
                    renderItem(button, item);
                    button.addEventListener('click', () => {
                        hiddenId.value = item.id ?? item.employee_id;
                        input.value = item.official_name ?? item.display_name;
                        selection.textContent = renderSelection(item);
                        selection.hidden = false;
                        results.hidden = true;
                    });
                    results.appendChild(button);
                });
                results.hidden = false;
            } catch (error) {
                if (sequence === requestSequence) {
                    status('No fue posible consultar RRHH2029.');
                }
            }
        });

        input?.addEventListener('input', () => {
            clearSelection();
            runSearch();
        });

        input?.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && results) {
                results.hidden = true;
            }
        });

        document.addEventListener('click', (event) => {
            if (results && !root.contains(event.target)) {
                results.hidden = true;
            }
        });
    };

    setupPicker({
        rootSelector: '[data-unit-picker]',
        inputSelector: '[data-unit-search]',
        idSelector: '[data-unit-id]',
        resultsSelector: '[data-unit-results]',
        selectionSelector: '[data-unit-selection]',
        renderItem: (button, unit) => {
            const title = document.createElement('strong');
            title.textContent = unit.official_name;

            const meta = document.createElement('span');
            meta.textContent = [
                unit.system_code || unit.institutional_code || null,
                unit.unit_type || null,
                unit.official_abbreviation || null
            ].filter(Boolean).join(' · ');

            button.append(title, meta);
        },
        renderSelection: (unit) => [
            unit.official_name,
            unit.system_code || unit.institutional_code || null,
            unit.unit_type || null
        ].filter(Boolean).join(' · ')
    });

    setupPicker({
        rootSelector: '[data-p4-employee-picker]',
        inputSelector: '[data-p4-employee-search]',
        idSelector: '[data-p4-employee-id]',
        resultsSelector: '[data-p4-employee-results]',
        selectionSelector: '[data-p4-employee-selection]',
        renderItem: (button, employee) => {
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
        },
        renderSelection: (employee) => [
            employee.display_name,
            employee.document_number ? 'Cédula ' + employee.document_number : null,
            employee.position_number ? 'Pos. ' + employee.position_number : null
        ].filter(Boolean).join(' · ')
    });
})();
