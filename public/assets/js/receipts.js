(() => {
    const lines = document.querySelector('[data-receipt-lines]');
    const template = document.querySelector('[data-receipt-template]');
    const addButton = document.querySelector('[data-receipt-add]');

    if (!lines || !template || !addButton) {
        return;
    }

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
})();
