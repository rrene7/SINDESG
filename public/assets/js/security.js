(() => {
    document.querySelectorAll('[data-security-picker]').forEach((root) => {
        const input = root.querySelector('[data-security-search]');
        const hidden = root.querySelector('[data-security-id]');
        const results = root.querySelector('[data-security-results]');
        const selection = root.querySelector('[data-security-selection]');
        const endpoint = root.dataset.searchUrl || '';
        if (!input || !hidden || !results || !selection || !endpoint) return;

        let timer = null;
        let sequence = 0;
        let activeController = null;

        const close = () => { results.hidden = true; };
        const show = (message) => {
            results.innerHTML = '';
            const row = document.createElement('div');
            row.className = 'security-picker-status';
            row.textContent = message;
            results.appendChild(row);
            results.hidden = false;
        };

        const cancelPending = () => {
            sequence++;
            window.clearTimeout(timer);
            timer = null;
            if (activeController) {
                activeController.abort();
                activeController = null;
            }
        };

        const search = async (query, current) => {
            const controller = new AbortController();
            activeController = controller;
            let timedOut = false;
            const timeout = window.setTimeout(() => {
                timedOut = true;
                controller.abort();
            }, 8500);
            show('Buscando en RRHH2029...');

            try {
                const separator = endpoint.includes('?') ? '&' : '?';
                const response = await fetch(endpoint + separator + 'q=' + encodeURIComponent(query), {
                    credentials: 'same-origin',
                    headers: {'Accept': 'application/json'},
                    signal: controller.signal
                });
                const payload = await response.json();
                if (current !== sequence) return;

                if (!response.ok || !payload.ok) {
                    show(payload.message || 'No fue posible consultar RRHH2029.');
                    return;
                }

                results.innerHTML = '';
                if (!Array.isArray(payload.items) || payload.items.length === 0) {
                    show('No se encontraron funcionarios. Verifique la posición o cédula.');
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
                        cancelPending();
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
                if (current !== sequence) return;
                show(timedOut
                    ? 'La búsqueda superó 8,5 segundos. Compruebe la conexión con RRHH2029.'
                    : 'No fue posible consultar RRHH2029. Intente nuevamente.');
            } finally {
                window.clearTimeout(timeout);
                if (activeController === controller) activeController = null;
            }
        };

        input.addEventListener('input', () => {
            // Invalidar inmediatamente respuestas antiguas, incluso antes
            // de que transcurra la espera para enviar la nueva solicitud.
            cancelPending();
            hidden.value = '';
            selection.hidden = true;
            selection.textContent = '';
            const query = input.value.trim();

            if (query.length < 2) {
                close();
                return;
            }
            const current = sequence;
            timer = window.setTimeout(() => {
                timer = null;
                void search(query, current);
            }, 260);
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                cancelPending();
                close();
            }
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) close();
        });
    });
})();
