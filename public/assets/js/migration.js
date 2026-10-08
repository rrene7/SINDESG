(() => {
    const input = document.querySelector('[data-migration-file]');
    const label = document.querySelector('[data-migration-filename]');

    input?.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!label) return;

        if (!file) {
            label.textContent = 'Seleccione un XLSX o CSV';
            return;
        }

        const mb = file.size / 1024 / 1024;
        label.textContent = file.name + ' · ' + mb.toFixed(2) + ' MB';
    });

    document.querySelectorAll('form[data-confirm-message]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.dataset.confirmMessage || '¿Continuar?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
})();
