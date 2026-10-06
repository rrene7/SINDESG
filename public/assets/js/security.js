(() => {
    document.querySelectorAll('[data-security-picker]').forEach((root) => {
        const input = root.querySelector('[data-security-search]');
        const hidden = root.querySelector('[data-security-id]');
        const results = root.querySelector('[data-security-results]');
        const selection = root.querySelector('[data-security-selection]');
        const endpoint = root.dataset.searchUrl || '';
        let timer = null;
        let sequence = 0;

        const close = () => { results.hidden = true; };
        const show = (message) => {
            results.innerHTML = '';
            const row = document.createElement('div');
            row.className = 'security-picker-status';
            row.textContent = message;
            results.appendChild(row);
            results.hidden = false;
        };

        input?.addEventListener('input', () => {
            hidden.value = '';
            selection.hidden = true;
            window.clearTimeout(timer);
            const query = input.value.trim();

            if (query.length < 2) {
                close();
                return;
            }

            timer = window.setTimeout(async () => {
                const current = ++sequence;
                show('Buscando en RRHH2029...');

                try {
                    const response = await fetch(endpoint + '?q=' + encodeURIComponent(query), {
                        headers: {'Accept': 'application/json'}
                    });
                    const payload = await response.json();
                    if (current !== sequence) return;

                    if (!response.ok || !payload.ok) {
                        show(payload.message || 'No fue posible consultar RRHH2029.');
                        return;
                    }

                    results.innerHTML = '';
                    if (!payload.items?.length) {
                        show('No se encontraron funcionarios.');
                        return;
                    }

                    payload.items.forEach((employee) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'security-picker-result';

                        const strong = document.createElement('strong');
                        strong.textContent = employee.display_name;

                        const span = document.createElement('span');
                        span.textContent = [
                            employee.document_number ? 'Cédula ' + employee.document_number : null,
                            employee.position_number ? 'Pos. ' + employee.position_number : null,
                            employee.direction_name || null,
                            employee.office_name || null
                        ].filter(Boolean).join(' · ');

                        button.append(strong, span);
                        button.addEventListener('click', () => {
                            hidden.value = employee.employee_id;
                            input.value = employee.display_name;
                            selection.textContent = span.textContent;
                            selection.hidden = false;
                            close();
                        });
                        results.appendChild(button);
                    });

                    results.hidden = false;
                } catch (error) {
                    show('No fue posible consultar RRHH2029.');
                }
            }, 220);
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) close();
        });
    });
})();
