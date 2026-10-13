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
    // Búsqueda por coincidencia en catálogo ACTIVO (también con stock 0).
    // Se reusan las opciones entregadas por PHP; no inventamos artículos.
    const normalizeCatalogText = (text) => String(text || '')
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('es')
        .replace(/\s+/g, ' ')
        .trim();

    document.querySelectorAll('[data-catalog-picker]').forEach((root) => {
        const search = root.querySelector('[data-catalog-search]');
        const select = root.querySelector('[data-catalog-select]');
        const counter = root.querySelector('[data-catalog-count]');
        const noMatches = root.querySelector('[data-catalog-empty]');
        if (!search || !select || !counter || !noMatches) return;

        const placeholder = select.querySelector('option[value=""]');
        if (!placeholder) return;

        const all = Array.from(select.options)
            .filter((option) => option.value !== '')
            .map((option) => ({
                option,
                searchable: normalizeCatalogText(
                    option.dataset.searchTerms + ' ' + option.textContent
                )
            }));

        const update = () => {
            const terms = normalizeCatalogText(search.value)
                .split(' ').filter(Boolean);
            const selected = select.value;
            const matches = all.filter(({searchable}) =>
                terms.every((term) => searchable.includes(term))
            );

            // Actualizar de verdad el dropdown, no solo ocultar <option>,
            // porque algunos navegadores siguen mostrando opciones ocultas.
            select.replaceChildren(placeholder, ...matches.map(({option}) => option));
            if (selected && matches.some(({option}) => option.value === selected)) {
                select.value = selected;
            } else {
                select.value = '';
            }
            const noun = matches.length === 1 ? 'coincidencia' : 'coincidencias';
            counter.textContent = matches.length + ' ' + noun
                + ' de ' + all.length + ' opciones activas'
                + (terms.length ? ' para "' + search.value.trim() + '"' : '')
                + '. Se pueden solicitar variantes con stock 0.';
            noMatches.hidden = matches.length !== 0;
        };

        search.addEventListener('input', update);
        search.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (select.options.length === 2 && !select.value) {
                    select.value = select.options[1].value;
                    select.dispatchEvent(new Event('change', {bubbles: true}));
                }
                select.focus();
            }
        });

        update();
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
        const variantSelect = bulkForm.querySelector('[data-catalog-select]');
        let shown = [];
        let sequence = 0;
        let lastDeliverySequence = 0;

        const dateLabel = (person) => {
            if (person.last_delivery_status === 'unavailable') {
                return 'Última entrega: historial no disponible';
            }
            if (person.last_delivery_status !== 'loaded') {
                return 'Última entrega: consultando...';
            }
            const date = person.last_delivered_at;
            return date
                ? 'Última entrega de este artículo: '
                    + date.slice(8, 10) + '/' + date.slice(5, 7) + '/' + date.slice(0, 4)
                : 'Sin entrega registrada de este artículo en SINDESG';
        };

        const loadLastDeliveries = async () => {
            const current = ++lastDeliverySequence;
            if (!variantSelect || !variantSelect.value) {
                [...chosen.values(), ...shown].forEach((p) => {
                    p.last_delivery_status = 'pending';
                    p.last_delivered_at = null;
                });
                renderSelected();
                renderResults(shown);
                return;
            }
            const people = new Map();
            [...chosen.values(), ...shown].forEach((person) => {
                people.set(String(person.employee_id), person);
                person.last_delivery_status = 'pending';
            });
            renderSelected();
            renderResults(shown);
            const persons = Array.from(people.values());
            try {
                for (let i = 0; i < persons.length; i += 100) {
                    const portion = persons.slice(i, i + 100);
                    const url = new URL(bulkForm.dataset.lastDeliveryUrl, window.location.href);
                    url.searchParams.set('variant_id', variantSelect.value);
                    url.searchParams.set('employee_ids', portion.map(p => p.employee_id).join(','));
                    const response = await fetch(url.toString(), {
                        headers: {Accept: 'application/json'}
                    });
                    const payload = await response.json();
                    if (!response.ok || !payload.ok) {
                        throw new Error(payload.message || 'Historial no disponible');
                    }
                    if (current !== lastDeliverySequence) return;
                    portion.forEach((person) => {
                        person.last_delivered_at = payload.dates[String(person.employee_id)] || null;
                        person.last_delivery_status = 'loaded';
                    });
                }
            } catch (_) {
                if (current !== lastDeliverySequence) return;
                persons.forEach(p => {
                    if (p.last_delivery_status !== 'loaded') {
                        p.last_delivery_status = 'unavailable';
                    }
                });
            }
            if (current === lastDeliverySequence) {
                renderSelected();
                renderResults(shown);
            }
        };

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
                    + ' · Pos. ' + (person.position_number || '—')
                    + ' · ' + dateLabel(person);

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
                    + ' · ' + (person.organization_unit_name || 'Sin unidad')
                    + ' · ' + dateLabel(person);
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
                const items = Array.isArray(payload.items) ? payload.items : [];
                renderResults(items);  // RRHH global: incluye personal prestado de otras unidades
                await loadLastDeliveries();
            } catch (error) {
                if (current !== sequence) return;
                results.textContent = 'No fue posible consultar RRHH2029.';
            }
        }, 250));

        if (variantSelect) {
            variantSelect.addEventListener('change', loadLastDeliveries);
        }

        bulkForm.addEventListener('submit', (event) => {
            if (chosen.size === 0) {
                event.preventDefault();
                countView.textContent = 'Seleccione al menos un funcionario.';
            }
        });
    }

})();
