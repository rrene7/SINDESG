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
        const resultCount = bulkForm.querySelector('[data-bulk-results-count]');
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
            if (resultCount) {
                resultCount.textContent = list.length
                    + (list.length === 1 ? ' funcionario encontrado en RRHH2029.' :
                        ' funcionarios encontrados en RRHH2029.')
                    + ' Marque los que necesite.';
            }
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
                    + ' · Estado RRHH: ' + (person.status_name || 'No especificado')
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
                ++lastDeliverySequence;
                shown = [];
                results.replaceChildren();
                results.hidden = true;
                selectPage.hidden = true;
                if (resultCount) resultCount.textContent =
                    'Escriba al menos 2 caracteres para buscar en RRHH2029.';
                return;
            }
            results.hidden = false;
            results.textContent = 'Consultando RRHH2029...';
            if (resultCount) resultCount.textContent = 'Buscando funcionarios...';
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
                results.textContent = 'No fue posible consultar RRHH2029. Verifique la conexión o sesión.';
                if (resultCount) resultCount.textContent = 'Error al buscar funcionarios.';
                selectPage.hidden = true;
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


/* Prevalidación P4: seleccionar varias incidencias para una sola validación. */
(() => {
    const init = () => {
        const form = document.getElementById('p4-bulk-review-form');
        if (!form) return;
        const boxes = Array.from(document.querySelectorAll('[data-p4-review-row]'));
        const selectAll = form.querySelector('[data-p4-review-select-all]');
        const counter = form.querySelector('[data-p4-review-counter]');
        const submit = form.querySelector('[data-p4-review-submit]');
        const sync = () => {
            const selected = boxes.filter(input => input.checked).length;
            if (counter) counter.textContent = selected + ' seleccionada(s) de ' + boxes.length;
            if (submit) submit.disabled = selected === 0;
            if (selectAll) {
                selectAll.textContent = selected === boxes.length
                    ? 'Quitar selección' : 'Seleccionar todas las incidencias';
            }
        };
        if (selectAll) selectAll.addEventListener('click', () => {
            const allSelected = boxes.every(input => input.checked);
            boxes.forEach(input => { input.checked = !allSelected; });
            sync();
        });
        boxes.forEach(input => input.addEventListener('change', sync));
        sync();
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();


/* Central: decidir por talla sin abrir cientos de expedientes. Las
 * validaciones de stock y beneficiarios siguen en el servidor. */
(() => {
    const init = () => {
        const form = document.querySelector('[data-central-review]');
        if (!form) return;
        const summary = form.querySelector('[data-central-message]');
        const groups = Array.from(form.querySelectorAll('[data-central-variant]'));
        const parse = (value) => {
            const n = Number(value);
            return Number.isFinite(n) && n >= 0 ? n : 0;
        };
        const display = (value) => Number(value.toFixed(3)).toLocaleString('es-PA', {
            maximumFractionDigits: 3
        });
        const refresh = () => {
            let assigned = 0;
            groups.forEach(group => {
                const variantId = group.dataset.variantId;
                const inputs = Array.from(form.querySelectorAll('[data-central-item]'))
                    .filter(x => x.dataset.variantId === variantId);
                const value = inputs.reduce((sum, input) => sum + parse(input.value), 0);
                const selectedPeople = inputs.filter(input => parse(input.value) > 0).length;
                assigned += value;
                const groupCounter = group.querySelector('[data-central-selected]');
                if (groupCounter) groupCounter.textContent = display(value) + ' de '
                    + display(parse(group.dataset.centralRequested)) + ' unidades · '
                    + selectedPeople + ' de ' + inputs.length + ' destinatarios';
            });
            if (summary) summary.textContent = display(assigned)
                + ' unidades seleccionadas para aprobación. No se descontará inventario hasta un despacho.';
        };
        groups.forEach(group => {
            const variantId = group.dataset.variantId;
            const quantity = group.querySelector('[data-central-quantity]');
            const button = group.querySelector('[data-central-apply]');
            const inputs = Array.from(form.querySelectorAll('[data-central-item]'))
                .filter(input => input.dataset.variantId === variantId);
            const available = parse(group.dataset.variantAvailable);
            if (available <= 0 && button) {
                button.disabled = true;
                button.title = 'Sin stock comprometible en Almacén Central';
            }
            if (button && quantity) button.addEventListener('click', () => {
                const desired = parse(quantity.value);
                let remaining = Math.min(desired, available);
                inputs.forEach(input => {
                    const allowed = parse(input.dataset.requested);
                    const selected = Math.min(allowed, Math.max(0, remaining));
                    input.value = selected.toFixed(3);
                    remaining = Math.max(0, remaining - selected);
                });
                refresh();
                if (desired > available && summary) {
                    summary.textContent = 'La disponibilidad no alcanza la cantidad indicada para esta talla. '
                        + 'Se asignaron como máximo ' + display(available)
                        + ' unidades; compruebe los funcionarios antes de continuar.';
                }
            });
            inputs.forEach(input => input.addEventListener('input', refresh));
        });
        form.addEventListener('submit', event => {
            let total = 0;
            let requestedTotal = 0;
            let invalid = false;
            groups.forEach(group => {
                const available = parse(group.dataset.variantAvailable);
                requestedTotal += parse(group.dataset.centralRequested);
                const inputs = Array.from(form.querySelectorAll('[data-central-item]'))
                    .filter(x => x.dataset.variantId === group.dataset.variantId);
                const totalForGroup = inputs.reduce((sum, el) => sum + parse(el.value), 0);
                if (totalForGroup > available + 0.000001) invalid = true;
                total += totalForGroup;
            });
            if (invalid) {
                event.preventDefault();
                if (summary) summary.textContent =
                    'La selección supera el stock comprometible de al menos una talla. Corrija antes de continuar.';
                return;
            }
            if (total === 0 && !window.confirm(
                'Está a punto de rechazar la solicitud porque ninguna unidad será aprobada. ¿Continuar?'
            )) {
                event.preventDefault();
            } else if (total > 0 && total < requestedTotal - 0.000001 && !window.confirm(
                'Aprobación parcial: ' + display(total) + ' de ' + display(requestedTotal)
                + ' unidades. Verifique los funcionarios incluidos en cada talla antes de confirmar. ¿Registrar esta asignación nominal?'
            )) {
                event.preventDefault();
            }
        });
        refresh();
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else init();
})();

/* Vista nominal: búsqueda, filtro y paginación solo en el navegador.
 * Todas las líneas siguen protegidas por la consulta/autorización de servidor.
 * Sin solicitudes de red y sin modificar cantidades, formularios ni kardex. */
(() => {
    const init = () => {
        const root = document.querySelector('[data-request-nominal-list]');
        if (!root) return;
        const controls = root.querySelector('[data-request-nominal-controls]');
        const rows = Array.from(root.querySelectorAll('[data-request-nominal-row]'));
        const search = root.querySelector('[data-request-nominal-search]');
        const filter = root.querySelector('[data-request-nominal-filter]');
        const size = root.querySelector('[data-request-nominal-page-size]');
        const count = root.querySelector('[data-request-nominal-count]');
        const nav = root.querySelector('[data-request-nominal-pagination]');
        const empty = root.querySelector('[data-request-nominal-empty]');
        const pageLabel = root.querySelector('[data-request-nominal-page]');
        const previous = root.querySelector('[data-request-nominal-prev]');
        const next = root.querySelector('[data-request-nominal-next]');
        if (!rows.length || !controls || !search || !filter || !size
            || !count || !nav || !empty || !pageLabel || !previous || !next) return;

        const normalize = (value) => String(value || '').normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
        const indexed = rows.map(row => ({
            row,
            state: row.dataset.requestLineState || '',
            text: normalize(row.textContent)
        }));
        let page = 0;
        const update = () => {
            const term = normalize(search.value.trim());
            const state = filter.value;
            const perPage = Number(size.value) || 25;
            const matches = indexed.filter(entry =>
                (state === 'all' || entry.state === state) && entry.text.includes(term));
            const totalPages = Math.max(1, Math.ceil(matches.length / perPage));
            page = Math.min(page, totalPages - 1);
            const from = page * perPage;
            const visible = new Set(matches.slice(from, from + perPage).map(entry => entry.row));
            rows.forEach(row => { row.hidden = !visible.has(row); });
            const first = matches.length ? from + 1 : 0;
            const last = Math.min(from + perPage, matches.length);
            count.textContent = 'Mostrando ' + first + '–' + last + ' de '
                + matches.length + ' renglones coincidentes (' + rows.length + ' en la solicitud)';
            pageLabel.textContent = 'Página ' + (page + 1) + ' de ' + totalPages;
            previous.disabled = page === 0;
            next.disabled = page >= totalPages - 1;
            empty.hidden = matches.length !== 0;
        };
        controls.hidden = false;
        nav.hidden = false;
        search.addEventListener('input', () => { page = 0; update(); });
        filter.addEventListener('change', () => { page = 0; update(); });
        size.addEventListener('change', () => { page = 0; update(); });
        previous.addEventListener('click', () => { if (page > 0) { page -= 1; update(); } });
        next.addEventListener('click', () => { page += 1; update(); });
        update();
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else init();
})();
