(() => {
    const types = ['output', 'input'];

    const refresh = (type) => {
        const container = document.querySelector('[data-production-lines="' + type + '"]');
        if (!container) return;

        const rows = [...container.querySelectorAll('[data-production-line]')];
        rows.forEach((row) => {
            const button = row.querySelector('[data-production-remove]');
            if (!button) return;

            const mustKeepOne = type === 'output';
            button.disabled = mustKeepOne && rows.length === 1;
            button.title = button.disabled ? 'Debe existir al menos un producto terminado.' : 'Eliminar renglón';
        });
    };

    const bindRemove = (type, row) => {
        row.querySelector('[data-production-remove]')?.addEventListener('click', () => {
            const container = document.querySelector('[data-production-lines="' + type + '"]');
            if (!container) return;
            const rows = container.querySelectorAll('[data-production-line]');
            if (type === 'output' && rows.length <= 1) return;
            row.remove();
            refresh(type);
        });
    };

    types.forEach((type) => {
        const container = document.querySelector('[data-production-lines="' + type + '"]');
        const template = document.querySelector('[data-production-template="' + type + '"]');
        const add = document.querySelector('[data-production-add="' + type + '"]');

        if (!container || !template || !add) return;

        [...container.querySelectorAll('[data-production-line]')].forEach((row) => bindRemove(type, row));

        add.addEventListener('click', () => {
            const fragment = template.content.cloneNode(true);
            const row = fragment.querySelector('[data-production-line]');
            container.appendChild(fragment);
            if (row) {
                bindRemove(type, row);
                row.querySelector('select')?.focus();
            }
            refresh(type);
        });

        refresh(type);
    });
})();
