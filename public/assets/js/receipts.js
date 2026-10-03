(() => {
    const lines = document.querySelector('[data-receipt-lines]');
    const template = document.querySelector('[data-receipt-template]');
    const addButton = document.querySelector('[data-receipt-add]');

    if (lines && template && addButton) {
        const refreshRemoveButtons = () => {
            const rows = [...lines.querySelectorAll('[data-receipt-line]')];
            rows.forEach((row) => {
                const button = row.querySelector('[data-receipt-remove]');
                if (!button) return;
                button.disabled = rows.length === 1;
                button.title = rows.length === 1
                    ? 'El lote debe tener al menos un renglón.'
                    : 'Eliminar renglón';
            });
        };

        const bindRemove = (row) => {
            const button = row.querySelector('[data-receipt-remove]');
            button?.addEventListener('click', () => {
                const rows = lines.querySelectorAll('[data-receipt-line]');
                if (rows.length <= 1) return;
                row.remove();
                refreshRemoveButtons();
            });
        };

        [...lines.querySelectorAll('[data-receipt-line]')].forEach(bindRemove);

        addButton.addEventListener('click', () => {
            const fragment = template.content.cloneNode(true);
            const row = fragment.querySelector('[data-receipt-line]');
            lines.appendChild(fragment);

            if (row) {
                bindRemove(row);
                row.querySelector('select')?.focus();
            }

            refreshRemoveButtons();
        });

        refreshRemoveButtons();
    }

    const picker = document.querySelector('[data-employee-picker]');
    if (!picker) return;

    const input = picker.querySelector('[data-employee-search]');
    const hiddenId = picker.querySelector('[data-employee-id]');
    const results = picker.querySelector('[data-employee-results]');
    const selected = picker.querySelector('[data-employee-selection]');
    const endpoint = picker.dataset.searchUrl || '';
    let timer = null;
    let requestSequence = 0;

    const clearSelection = () => {
        if (hiddenId) hiddenId.value = '';
        if (selected) {
            selected.hidden = true;
            selected.textContent = '';
        }
    };

    const renderStatus = (message) => {
        if (!results) return;
        results.innerHTML = '';
        const status = document.createElement('div');
        status.className = 'employee-search-status';
        status.textContent = message;
        results.appendChild(status);
        results.hidden = false;
    };

    const renderResults = (items) => {
        if (!results) return;
        results.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            renderStatus('No se encontraron funcionarios con esos datos.');
            return;
        }

        items.forEach((employee) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'employee-result';

            const title = document.createElement('strong');
            title.textContent = employee.display_name;

            const meta = document.createElement('span');
            const parts = [
                employee.document_number ? 'Cédula ' + employee.document_number : null,
                employee.position_number ? 'Pos. ' + employee.position_number : null,
                employee.organization_unit_name || null
            ].filter(Boolean);
            meta.textContent = parts.join(' · ');

            button.append(title, meta);
            button.addEventListener('click', () => {
                hiddenId.value = employee.employee_id;
                input.value = employee.display_name;
                selected.textContent = parts.join(' · ');
                selected.hidden = false;
                results.hidden = true;
            });

            results.appendChild(button);
        });

        results.hidden = false;
    };

    input?.addEventListener('input', () => {
        clearSelection();
        const query = input.value.trim();

        window.clearTimeout(timer);

        if (query.length < 2) {
            if (results) results.hidden = true;
            return;
        }

        timer = window.setTimeout(async () => {
            const sequence = ++requestSequence;
            renderStatus('Buscando en RRHH2029...');

            try {
                const response = await fetch(endpoint + '?q=' + encodeURIComponent(query), {
                    headers: {'Accept': 'application/json'}
                });
                const payload = await response.json();

                if (sequence !== requestSequence) return;

                if (!response.ok || !payload.ok) {
                    renderStatus(payload.message || 'No fue posible consultar RRHH2029.');
                    return;
                }

                renderResults(payload.items || []);
            } catch (error) {
                if (sequence === requestSequence) {
                    renderStatus('No fue posible consultar RRHH2029.');
                }
            }
        }, 220);
    });

    input?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && results) {
            results.hidden = true;
        }
    });

    document.addEventListener('click', (event) => {
        if (results && !picker.contains(event.target)) {
            results.hidden = true;
        }
    });
})();
