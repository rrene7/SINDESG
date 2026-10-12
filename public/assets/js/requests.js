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
    // Selección múltiple: solo crea renglones nominales en un borrador P4.
    // La pertenencia a RRHH/P4 se verifica nuevamente en el servidor.
    const bulkForm = document.querySelector('[data-bulk-nominal-picker]');
    if (bulkForm) {
        const input = bulkForm.querySelector('[data-bulk-search]');
        const results = bulkForm.querySelector('[data-bulk-results]');
        const selectedView = bulkForm.querySelector('[data-bulk-selection]');
        const countView = bulkForm.querySelector('[data-bulk-counter]');
        const selectPage = bulkForm.querySelector('[data-bulk-select-page]');
        const submit = bulkForm.querySelector('[data-bulk-submit]');
        const chosen = new Map();
        let shown = [];
        let sequence = 0;

        const renderSelected = () => {
            selectedView.replaceChildren();
            for (const person of chosen.values()) {
                const label = document.createElement('span');
                label.className = 'request-selected-person';

                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'employee_ids[]';
                hidden.value = String(person.employee_id);

                const name = document.createElement('span');
                name.textContent = person.display_name
                    + ' · Pos. ' + (person.position_number || '—');

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'request-remove-button';
                remove.textContent = '×';
                remove.setAttribute('aria-label', 'Quitar a ' + person.display_name);
                remove.addEventListener('click', () => {
                    chosen.delete(String(person.employee_id));
                    renderSelected();
                    renderResults(shown);
                });
                label.append(hidden, name, remove);
                selectedView.appendChild(label);
            }
            countView.textContent = chosen.size + ' funcionarios seleccionados';
            submit.disabled = chosen.size === 0;
        };

        const addPerson = (person) => {
            if (chosen.size >= 500 && !chosen.has(String(person.employee_id))) {
                countView.textContent = 'Límite de 500 por operación alcanzado.';
                return;
            }
            chosen.set(String(person.employee_id), person);
            renderSelected();
        };

        const renderResults = (list) => {
            shown = list;
            results.replaceChildren();
            if (!list.length) {
                const message = document.createElement('p');
                message.textContent = 'No se encontraron funcionarios.';
                results.appendChild(message);
            }
            list.forEach((person) => {
                const label = document.createElement('label');
                label.className = 'request-picker-result';
                const check = document.createElement('input');
                check.type = 'checkbox';
                check.checked = chosen.has(String(person.employee_id));
                check.addEventListener('change', () => {
                    if (check.checked) {
                        addPerson(person);
                    } else {
                        chosen.delete(String(person.employee_id));
                        renderSelected();
                    }
                    check.checked = chosen.has(String(person.employee_id));
                });
                const description = document.createElement('span');
                description.textContent = person.display_name
                    + ' · Pos. ' + (person.position_number || '—')
                    + ' · ' + (person.organization_unit_name || 'Sin unidad');
                label.append(check, description);
                results.appendChild(label);
            });
            results.hidden = false;
            selectPage.hidden = list.length === 0;
        };

        selectPage.addEventListener('click', () => {
            shown.forEach(addPerson);
            renderResults(shown);
        });

        input.addEventListener('input', debounce(async () => {
            const term = input.value.trim();
            const current = ++sequence;
            if (term.length < 2) {
                shown = [];
                results.hidden = true;
                selectPage.hidden = true;
                return;
            }
            results.hidden = false;
            results.textContent = 'Consultando RRHH2029...';
            selectPage.hidden = true;
            try {
                const url = new URL(bulkForm.dataset.searchUrl, window.location.href);
                url.searchParams.set('q', term);
                const response = await fetch(url.toString(), {
                    headers: {Accept: 'application/json'}
                });
                const payload = await response.json();
                if (current !== sequence) return;
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'Error al consultar RRHH');
                }
                const p4Unit = Number(bulkForm.dataset.p4UnitId || 0);
                const items = Array.isArray(payload.items) ? payload.items : [];
                const eligible = items.filter((person) => {
                    const path = Array.isArray(person.unit_path_ids) ? person.unit_path_ids : [];
                    return p4Unit > 0 && path.some((id) => Number(id) === p4Unit);
                });
                renderResults(eligible);
            } catch (error) {
                if (current !== sequence) return;
                results.textContent = 'No fue posible consultar RRHH2029.';
            }
        }, 250));

        bulkForm.addEventListener('submit', (event) => {
            if (chosen.size === 0) {
                event.preventDefault();
                countView.textContent = 'Seleccione al menos un funcionario.';
            }
        });
    }

})();
