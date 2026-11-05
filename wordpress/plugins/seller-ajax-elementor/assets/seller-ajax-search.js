(function () {
    'use strict';

    const cfg = window.SASSellerSearch || {};
    const searchSelector = cfg.searchSelector || '.seller-ajax-search';
    const resultsSelector = cfg.resultsSelector || '.seller-ajax-results';
    const requestParam = cfg.requestParam || 'seller_search';
    const debounceMs = Number(cfg.debounce || 400);
    const minChars = Number(cfg.minChars || 1);

    let activeController = null;
    let debounceTimer = null;
    let taxonomyTimer = null;
    let requestSequence = 0;

    function getNodes() {
        const searchRoot = document.querySelector(searchSelector);
        const resultsRoot = document.querySelector(resultsSelector);

        if (!searchRoot || !resultsRoot) {
            return null;
        }

        const input = searchRoot.querySelector('input[type="search"], input[type="text"]');
        const form = input ? input.closest('form') : null;

        if (!input) {
            return null;
        }

        return { searchRoot, resultsRoot, input, form };
    }

    function buildUrl(term) {
        const url = new URL(window.location.href);

        // Reset common pagination params so a search always starts from the first result page.
        ['paged', 'page'].forEach((key) => url.searchParams.delete(key));
        Array.from(url.searchParams.keys()).forEach((key) => {
            if (/^e-page-/i.test(key)) {
                url.searchParams.delete(key);
            }
        });

        if (term) {
            url.searchParams.set(requestParam, term);
        } else {
            url.searchParams.delete(requestParam);
        }

        return url;
    }

    function setLoading(nodes, loading) {
        nodes.resultsRoot.classList.toggle('seller-ajax-is-loading', loading);
        nodes.resultsRoot.setAttribute('aria-busy', loading ? 'true' : 'false');
        nodes.searchRoot.classList.toggle('seller-ajax-searching', loading);
    }

    function clearError(nodes) {
        const old = nodes.searchRoot.querySelector('.seller-ajax-error');
        if (old) old.remove();
    }

    function showError(nodes, message) {
        clearError(nodes);
        const box = document.createElement('div');
        box.className = 'seller-ajax-error';
        box.setAttribute('role', 'status');
        box.textContent = message || 'خطا در دریافت نتایج.';
        nodes.searchRoot.appendChild(box);
    }

    function reinitializeElementor(scope) {
        try {
            if (
                window.elementorFrontend &&
                window.elementorFrontend.elementsHandler &&
                typeof window.elementorFrontend.elementsHandler.runReadyTrigger === 'function' &&
                window.jQuery
            ) {
                window.elementorFrontend.elementsHandler.runReadyTrigger(window.jQuery(scope));
            }
        } catch (e) {
            // The grid itself is already rendered server-side; re-init is only for optional widget interactions.
            console.debug('Seller AJAX Search: Elementor re-init skipped.', e);
        }
    }

    async function requestResults(term, options = {}) {
        const nodes = getNodes();
        if (!nodes) return;

        const cleanTerm = (term || '').trim();
        if (cleanTerm && cleanTerm.length < minChars) return;

        if (activeController) {
            activeController.abort();
        }

        const controller = new AbortController();
        activeController = controller;
        const sequence = ++requestSequence;
        const url = buildUrl(cleanTerm);

        clearError(nodes);
        setLoading(nodes, true);

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'text/html,application/xhtml+xml',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Seller-Ajax-Search': '1'
                },
                signal: controller.signal
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const html = await response.text();
            if (sequence !== requestSequence) return;

            const doc = new DOMParser().parseFromString(html, 'text/html');
            const incoming = doc.querySelector(resultsSelector);

            if (!incoming) {
                throw new Error('Loop Grid selector not found in AJAX response.');
            }

            // Preserve the live wrapper (and Elementor's references to it), replace only its rendered contents.
            nodes.resultsRoot.innerHTML = incoming.innerHTML;
            reinitializeElementor(nodes.resultsRoot);

            // Keep Elementor Taxonomy Filter deep-link parameters and our search term together in the URL.
            if (options.updateHistory !== false) {
                const visibleUrl = url.pathname + url.search + url.hash;
                window.history.replaceState(window.history.state, '', visibleUrl);
            }
        } catch (error) {
            if (error && error.name === 'AbortError') return;
            console.error('Seller AJAX Search:', error);
            showError(nodes, cfg.errorMessage);
        } finally {
            if (sequence === requestSequence) {
                setLoading(nodes, false);
            }
        }
    }

    function scheduleSearch(term, delay = debounceMs, options = {}) {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => requestResults(term, options), delay);
    }

    function bind() {
        const nodes = getNodes();
        if (!nodes || nodes.searchRoot.dataset.sellerAjaxBound === '1') return;

        nodes.searchRoot.dataset.sellerAjaxBound = '1';

        const initial = new URL(window.location.href).searchParams.get(requestParam);
        if (initial !== null) {
            nodes.input.value = initial;
        }

        nodes.input.setAttribute('autocomplete', 'off');
        nodes.input.setAttribute('aria-controls', nodes.resultsRoot.id || 'seller-ajax-results');

        nodes.input.addEventListener('input', function () {
            const term = nodes.input.value.trim();
            scheduleSearch(term);
        });

        if (nodes.form) {
            nodes.form.addEventListener('submit', function (event) {
                event.preventDefault();
                window.clearTimeout(debounceTimer);
                requestResults(nodes.input.value.trim());
            });
        }

        // Elementor's taxonomy filters use URL deep links (e-filter-...).
        // After a native taxonomy click finishes, re-run the active text search against that filtered URL.
        document.addEventListener('click', function (event) {
            const filterItem = event.target.closest('.e-filter-item');
            if (!filterItem || !nodes.input.value.trim()) return;

            window.clearTimeout(taxonomyTimer);
            taxonomyTimer = window.setTimeout(function () {
                requestResults(nodes.input.value.trim(), { updateHistory: false });
            }, 650);
        }, true);

        window.addEventListener('popstate', function () {
            const currentTerm = new URL(window.location.href).searchParams.get(requestParam) || '';
            nodes.input.value = currentTerm;
            requestResults(currentTerm, { updateHistory: false });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }

    // Elementor may render the page/widgets after DOMContentLoaded in some contexts.
    window.addEventListener('elementor/frontend/init', function () {
        window.setTimeout(bind, 0);
    });
})();
