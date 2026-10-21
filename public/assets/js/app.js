(() => {
    const qs = (selector, scope = document) => scope.querySelector(selector);

    const carousel = qs('[data-carousel]');
    const previous = qs('[data-carousel-prev]');
    const next = qs('[data-carousel-next]');

    if (carousel && previous && next) {
        const move = (direction) => {
            const card = qs('.product-carousel-card', carousel);
            const amount = card ? card.getBoundingClientRect().width + 16 : 280;
            carousel.scrollBy({ left: amount * direction, behavior: 'smooth' });
        };

        previous.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
    }

    const focusPayload = qs('[data-dashboard-product-focus]');
    const focusPanel = qs('[data-dashboard-focus-panel]');
    const focusCards = Array.from(document.querySelectorAll('[data-dashboard-product-id]'));

    if (focusPayload && focusPanel && focusCards.length) {
        let productFocus = {};

        try {
            productFocus = JSON.parse(focusPayload.textContent || '{}');
        } catch (error) {
            productFocus = {};
        }

        const fields = {
            category: qs('[data-feature-category]', focusPanel),
            name: qs('[data-feature-name]', focusPanel),
            color: qs('[data-feature-color]', focusPanel),
            description: qs('[data-feature-description]', focusPanel),
            link: qs('[data-feature-link]', focusPanel),
            icon: qs('[data-feature-icon]', focusPanel),
            tags: qs('[data-feature-tags]', focusPanel),
            image: qs('[data-feature-image]', focusPanel),
            available: qs('[data-feature-available]', focusPanel),
            breakdown: qs('[data-feature-breakdown]', focusPanel),
            sizes: qs('[data-feature-sizes]', focusPanel),
            turntable: qs('[data-digital-turntable]', focusPanel),
            replay: qs('[data-turntable-replay]', focusPanel),
        };

        const number = new Intl.NumberFormat('es-PA', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3,
        });

        const value = (input) => number.format(Number(input || 0));

        const replaceChildrenWithText = (element, values) => {
            if (!element) return;
            element.replaceChildren();

            values.forEach((text) => {
                const span = document.createElement('span');
                span.textContent = text;
                element.appendChild(span);
            });
        };

        const renderImage = (entry) => {
            if (!fields.image) return;

            fields.image.replaceChildren();
            const product = entry.product || null;

            if (product?.image_url) {
                const image = document.createElement('img');
                image.src = product.image_url;
                image.alt = product.name || entry.category?.name || 'Artículo';
                fields.image.appendChild(image);
                return;
            }

            const ring = document.createElement('div');
            ring.className = 'scan-ring';

            const monogram = document.createElement('div');
            monogram.className = 'product-monogram';
            monogram.textContent = entry.category?.initials || 'SG';

            const label = document.createElement('span');
            label.textContent = product ? 'Imagen pendiente' : 'Sin artículo destacado';

            fields.image.append(ring, monogram, label);
        };

        // Presentation effect only: a static image tilts gently above a CSS
        // platform. It is not a 3D reconstruction or a real 360-degree turn.
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const replayPresentation = () => {
            if (!fields.turntable || reducedMotion.matches) return;
            fields.turntable.classList.remove('is-presenting');
            // Force animation restart even when the same item is selected twice.
            void fields.turntable.offsetWidth;
            fields.turntable.classList.add('is-presenting');
        };

        fields.replay?.addEventListener('click', replayPresentation);
        fields.image?.addEventListener('click', replayPresentation);
        fields.turntable?.addEventListener('animationend', (event) => {
            if (event.target === fields.turntable.querySelector('.digital-turntable__product img')
                || event.target === fields.turntable.querySelector('.digital-turntable__product')) {
                fields.turntable.classList.remove('is-presenting');
            }
        });

        const renderSizes = (sizes) => {
            if (!fields.sizes) return;

            fields.sizes.replaceChildren();

            if (!Array.isArray(sizes) || sizes.length === 0) {
                const item = document.createElement('span');
                item.textContent = '—';

                const small = document.createElement('small');
                small.textContent = 'Sin tallas con stock';
                item.appendChild(small);

                fields.sizes.appendChild(item);
                return;
            }

            sizes.forEach((size) => {
                const item = document.createElement('span');
                item.textContent = size.name || 'S/T';

                const small = document.createElement('small');
                small.textContent = value(size.quantity);
                item.appendChild(small);

                fields.sizes.appendChild(item);
            });
        };

        const activateProduct = (card) => {
            const productId = String(card.dataset.dashboardProductId || '');
            const entry = productFocus[productId];
            if (!entry) return;

            focusCards.forEach((candidate) => {
                const active = candidate === card;
                candidate.classList.toggle('featured', active);
                candidate.classList.toggle('is-selected', active);
                candidate.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            const product = entry.product || null;
            const stock = entry.stock || {};
            const category = entry.category || {};

            if (fields.category) fields.category.textContent = category.name || 'Inventario';
            if (fields.name) fields.name.textContent = product?.name || 'Sin artículo seleccionado';
            if (fields.color) fields.color.textContent = product?.color || 'Sin color registrado';
            if (fields.description) {
                fields.description.textContent = product?.description
                    || 'Artículo registrado sin descripción adicional.';
            }
            if (fields.icon) fields.icon.textContent = category.initials || 'SG';

            if (fields.link) {
                fields.link.href = product?.url || '#';
                fields.link.textContent = 'Abrir artículo →';
            }

            const institutional = value(stock.institutional);
            const available = value(stock.available);

            if (product) {
                replaceChildrenWithText(fields.tags, [
                    product.internal_code || 'Sin código',
                    available + ' disponibles',
                    institutional + ' unidades institucionales',
                    product.sex || 'no aplica',
                ]);
            } else {
                replaceChildrenWithText(fields.tags, [
                    'Sin artículos activos',
                    institutional + ' unidades institucionales',
                ]);
            }

            renderImage(entry);
            replayPresentation();

            if (fields.available) fields.available.textContent = available;
            if (fields.breakdown) {
                fields.breakdown.textContent =
                    'Central físico ' + value(stock.central)
                    + ' · Comprometido ' + value(stock.committed)
                    + ' · P4 ' + value(stock.p4)
                    + ' · Tránsito ' + value(stock.transit)
                    + ' · Institucional ' + institutional;
            }

            renderSizes(entry.sizes);
        };

        // On initial dashboard load, the first selected uniform is presented.
        if (focusCards.some((card) => card.classList.contains('is-selected'))) {
            replayPresentation();
        }

        focusCards.forEach((card) => {
            card.addEventListener('click', (event) => {
                if (event.target.closest('a')) return;
                activateProduct(card);
            });

            card.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                if (event.target.closest('a')) return;

                event.preventDefault();
                activateProduct(card);
            });
        });
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
