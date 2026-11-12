(() => {
    const setupPicker = ({
        rootSelector, inputSelector, idSelector, resultsSelector, selectionSelector,
        renderItem, renderSelection, allowEmpty = false, getExtraParams = () => ({}),
        onSelect = () => {}, onClear = () => {}
    }) => {
        const root = document.querySelector(rootSelector);
        if (!root) return null;

        const input = root.querySelector(inputSelector);
        const hiddenId = root.querySelector(idSelector);
        const results = root.querySelector(resultsSelector);
        const selection = root.querySelector(selectionSelector);
        const endpoint = root.dataset.searchUrl || '';
        if (!input || !hiddenId || !results || !selection || !endpoint) return null;

        let requestSequence = 0;
        let timer = null;
        let activeController = null;

        const invalidate = () => {
            requestSequence += 1;
            window.clearTimeout(timer);
            timer = null;
            if (activeController) {
                activeController.abort();
                activeController = null;
            }
        };

        const clearSelection = () => {
            hiddenId.value = '';
            selection.hidden = true;
            selection.textContent = '';
            onClear();
        };

        const status = (message) => {
            results.innerHTML = '';
            const item = document.createElement('div');
            item.className = 'warehouse-picker-status';
            item.textContent = message;
            results.appendChild(item);
            results.hidden = false;
        };

        const search = async (sequence) => {
            const query = input.value.trim();
            if (query.length > 100 || (allowEmpty ? query.length === 1 : query.length < 2)) {
                results.hidden = true;
                return;
            }
            const extraParams = getExtraParams();
            if (extraParams === null) {
                results.hidden = true;
                return;
            }

            const controller = new AbortController();
            activeController = controller;
            let timedOut = false;
            const timeout = window.setTimeout(() => {
                timedOut = true;
                controller.abort();
            }, 8500);
            status('Buscando en RRHH2029...');

            try {
                const searchUrl = new URL(endpoint, window.location.href);
                searchUrl.searchParams.set('q', query);
                for (const [key, value] of Object.entries(extraParams)) {
                    searchUrl.searchParams.set(key, String(value));
                }
                const response = await fetch(searchUrl.toString(), {
                    credentials: 'same-origin',
                    headers: {'Accept': 'application/json'},
                    signal: controller.signal
                });
                const payload = await response.json();
                if (sequence !== requestSequence) return;

                if (!response.ok || !payload.ok) {
                    status(payload.message || 'No fue posible consultar RRHH2029.');
                    return;
                }
                results.innerHTML = '';
                if (!Array.isArray(payload.items) || payload.items.length === 0) {
                    status('No se encontraron unidades o funcionarios vigentes.');
                    return;
                }
                payload.items.forEach((item) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'warehouse-picker-result';
                    renderItem(button, item);
                    button.addEventListener('click', () => {
                        invalidate();
                        hiddenId.value = item.id ?? item.employee_id;
                        input.value = item.official_name ?? item.display_name;
                        selection.textContent = renderSelection(item);
                        selection.hidden = false;
                        results.hidden = true;
                        onSelect(item);
                    });
                    results.appendChild(button);
                });
                results.hidden = false;
            } catch (error) {
                if (sequence !== requestSequence) return;
                status(timedOut
                    ? 'RRHH2029 tardó más de 8,5 segundos. Intente nuevamente.'
                    : 'No fue posible consultar RRHH2029.');
            } finally {
                window.clearTimeout(timeout);
                if (activeController === controller) activeController = null;
            }
        };

        const schedule = (immediate = false) => {
            invalidate();
            clearSelection();
            const current = requestSequence;
            if (immediate) {
                void search(current);
            } else {
                timer = window.setTimeout(() => {
                    timer = null;
                    void search(current);
                }, 260);
            }
        };

        input.addEventListener('input', () => schedule());
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                invalidate();
                results.hidden = true;
            }
        });
        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) results.hidden = true;
        });

        return {
            reset: () => {
                invalidate();
                input.value = '';
                clearSelection();
                results.hidden = true;
            },
            searchNow: () => schedule(true)
        };
    };

    const renderUnit = (button, unit) => {
        const title = document.createElement('strong');
        title.textContent = unit.official_name;
        const meta = document.createElement('span');
        meta.textContent = [
            unit.system_code || unit.institutional_code || null,
            unit.unit_type || null,
            unit.depth ? 'Nivel ' + unit.depth : null,
            unit.official_abbreviation || null
        ].filter(Boolean).join(' · ');
        button.append(title, meta);
    };
    const unitSelection = (unit) => [
        unit.official_name,
        unit.system_code || unit.institutional_code || null,
        unit.unit_type || null
    ].filter(Boolean).join(' · ');

    const subunitSection = document.querySelector('[data-p4-subunit-section]');
    const subunitArea = document.querySelector('[data-p4-subunit-area]');
    const useSubunit = document.querySelector('[data-p4-use-subunit]');
    const headId = document.querySelector('[data-unit-id]');

    const subordinatePicker = setupPicker({
        rootSelector: '[data-p4-subunit-picker]',
        inputSelector: '[data-p4-subunit-search]',
        idSelector: '[data-p4-subunit-id]',
        resultsSelector: '[data-p4-subunit-results]',
        selectionSelector: '[data-p4-subunit-selection]',
        renderItem: renderUnit,
        renderSelection: unitSelection,
        allowEmpty: true,
        getExtraParams: () => headId?.value ? { parent_id: headId.value } : null
    });

    const resetSubordinate = () => {
        subordinatePicker?.reset();
        if (useSubunit) useSubunit.checked = false;
        if (subunitArea) subunitArea.hidden = true;
    };

    setupPicker({
        rootSelector: '[data-unit-picker]',
        inputSelector: '[data-unit-search]',
        idSelector: '[data-unit-id]',
        resultsSelector: '[data-unit-results]',
        selectionSelector: '[data-unit-selection]',
        renderItem: renderUnit,
        renderSelection: unitSelection,
        onClear: () => {
            resetSubordinate();
            if (subunitSection) subunitSection.hidden = true;
        },
        onSelect: () => {
            if (subunitSection) subunitSection.hidden = false;
        }
    });

    useSubunit?.addEventListener('change', () => {
        subordinatePicker?.reset();
        if (subunitArea) subunitArea.hidden = !useSubunit.checked;
        if (useSubunit.checked && headId?.value) {
            subordinatePicker?.searchNow();
        }
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
