(() => {
    const qs = (selector, scope = document) => scope.querySelector(selector);

    const carousel = qs('[data-carousel]');
    const previous = qs('[data-carousel-prev]');
    const next = qs('[data-carousel-next]');

    if (carousel && previous && next) {
        const move = (direction) => {
            const card = qs('.category-card', carousel);
            const amount = card ? card.getBoundingClientRect().width + 16 : 280;
            carousel.scrollBy({ left: amount * direction, behavior: 'smooth' });
        };

        previous.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
    }

    const moreToggle = qs('[data-more-toggle]');
    const moreMenu = qs('[data-more-menu]');
    if (moreToggle && moreMenu) {
        moreToggle.addEventListener('click', () => {
            moreMenu.hidden = !moreMenu.hidden;
        });

        document.addEventListener('click', (event) => {
            if (!moreMenu.hidden && !moreMenu.contains(event.target) && event.target !== moreToggle) {
                moreMenu.hidden = true;
            }
        });
    }

    const overlay = qs('[data-search-overlay]');
    const openSearch = qs('[data-search-open]');
    const closeSearch = qs('[data-search-close]');
    const input = qs('[data-search-input]');
    const results = qs('[data-search-results]');
    const items = Array.isArray(window.SINDESG_SEARCH_ITEMS) ? window.SINDESG_SEARCH_ITEMS : [];

    const renderResults = (query = '') => {
        if (!results) return;

        const normalized = query.trim().toLocaleLowerCase('es');
        const filtered = items
            .filter((item) => !normalized || item.label.toLocaleLowerCase('es').includes(normalized))
            .slice(0, 12);

        results.innerHTML = '';

        if (!filtered.length) {
            const empty = document.createElement('div');
            empty.className = 'search-empty';
            empty.textContent = 'No se encontraron coincidencias.';
            results.appendChild(empty);
            return;
        }

        filtered.forEach((item) => {
            const link = document.createElement('a');
            link.href = item.url;

            const label = document.createElement('strong');
            label.textContent = item.label;

            const meta = document.createElement('span');
            meta.textContent = item.meta || '';

            link.append(label, meta);
            results.appendChild(link);
        });
    };

    const showSearch = () => {
        if (!overlay) return;
        overlay.hidden = false;
        document.body.classList.add('modal-open');
        renderResults('');
        requestAnimationFrame(() => input?.focus());
    };

    const hideSearch = () => {
        if (!overlay) return;
        overlay.hidden = true;
        document.body.classList.remove('modal-open');
    };

    openSearch?.addEventListener('click', showSearch);
    closeSearch?.addEventListener('click', hideSearch);
    overlay?.addEventListener('click', (event) => {
        if (event.target === overlay) hideSearch();
    });
    input?.addEventListener('input', (event) => renderResults(event.target.value));

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            showSearch();
        }
        if (event.key === 'Escape') hideSearch();
    });
})();
