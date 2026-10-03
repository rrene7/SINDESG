(() => {
    const debounce = (callback, delay = 220) => {
        let timer = null;
        return (...args) => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => callback(...args), delay);
        };
    };

    const setupEmployeePicker = ({
        rootSelector,
        inputSelector,
        idSelector,
        resultsSelector,
        selectionSelector
    }) => {
        const root = document.querySelector(rootSelector);
        if (!root) return;

        const input = root.querySelector(inputSelector);
        const hiddenId = root.querySelector(idSelector);
        const results = root.querySelector(resultsSelector);
        const selection = root.querySelector(selectionSelector);
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
            el.className = 'request-picker-status';
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
                const response = await fetch(endpoint + '?q=' + encodeURIComponent(query), {
                    headers: {'Accept': 'application/json'}
                });
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
                    button.className = 'request-picker-result';

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
                    showStatus('No fue posible consultar RRHH2029.');
                }
            }
        });

        input.addEventListener('input', () => {
            clearSelection();
            search();
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') results.hidden = true;
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) results.hidden = true;
        });
    };

    setupEmployeePicker({
        rootSelector: '[data-request-employee-picker]',
        inputSelector: '[data-request-employee-search]',
        idSelector: '[data-request-employee-id]',
        resultsSelector: '[data-request-employee-results]',
        selectionSelector: '[data-request-employee-selection]'
    });

    setupEmployeePicker({
        rootSelector: '[data-beneficiary-picker]',
        inputSelector: '[data-beneficiary-search]',
        idSelector: '[data-beneficiary-id]',
        resultsSelector: '[data-beneficiary-results]',
        selectionSelector: '[data-beneficiary-selection]'
    });

    setupEmployeePicker({
        rootSelector: '[data-reviewer-picker]',
        inputSelector: '[data-reviewer-search]',
        idSelector: '[data-reviewer-id]',
        resultsSelector: '[data-reviewer-results]',
        selectionSelector: '[data-reviewer-selection]'
    });
})();
