(function () {
    'use strict';

    const globalCfg = window.RaaviSellerAjaxSearch || {};

    function parsePostId(item) {
        if (!item) return 0;

        const direct = item.getAttribute('data-post-id') || item.getAttribute('data-id-post') || '';
        if (/^\d+$/.test(direct)) return Number(direct);

        const classes = Array.from(item.classList || []);
        for (const cls of classes) {
            const match = /^post-(\d+)$/.exec(cls);
            if (match) return Number(match[1]);
        }

        const nested = item.querySelector('[class*="post-"]');
        if (nested) {
            for (const cls of Array.from(nested.classList || [])) {
                const match = /^post-(\d+)$/.exec(cls);
                if (match) return Number(match[1]);
            }
        }

        return 0;
    }

    function findItems(target) {
        const loop = target.querySelector('.elementor-loop-container') || target.querySelector('.elementor-grid') || target;
        let items = Array.from(loop.children || []).filter(function (el) {
            return el.nodeType === 1 && (
                el.classList.contains('e-loop-item') ||
                el.classList.contains('elementor-grid-item') ||
                Array.from(el.classList).some(function (c) { return /^post-\d+$/.test(c); }) ||
                el.tagName === 'ARTICLE'
            );
        });

        if (!items.length) {
            items = Array.from(target.querySelectorAll('.e-loop-item, .elementor-grid-item, article[class*="post-"], [class*="post-"]')).filter(function (el) {
                return parsePostId(el) > 0;
            });
        }

        return Array.from(new Set(items));
    }

    class SellerSearch {
        constructor(root) {
            this.root = root;
            this.input = root.querySelector('.raavi-seller-search__input');
            this.button = root.querySelector('.raavi-seller-search__button');
            this.status = root.querySelector('.raavi-seller-search__status');
            this.targetSelector = root.dataset.target || '.raavi-seller-results';
            this.live = root.dataset.live !== '0';
            this.minChars = Math.max(1, Number(root.dataset.minChars || 1));
            this.debounce = Math.max(150, Number(root.dataset.debounce || 350));
            this.noResultsText = root.dataset.noResults || 'فروشگاهی با این نام پیدا نشد.';
            this.timer = null;
            this.controller = null;
            this.lastIds = null;
            this.currentTerm = '';
            this.observer = null;

            if (!this.input || !this.button) return;
            this.bind();
        }

        getTarget() {
            try {
                return document.querySelector(this.targetSelector);
            } catch (e) {
                return null;
            }
        }

        bind() {
            this.button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.search(true);
            });

            this.input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    clearTimeout(this.timer);
                    this.search(true);
                }
                if (e.key === 'Escape') {
                    this.input.value = '';
                    this.clear();
                }
            });

            this.input.addEventListener('input', () => {
                if (!this.live) return;
                const term = this.input.value.trim();
                clearTimeout(this.timer);
                if (!term) {
                    this.clear();
                    return;
                }
                if (term.length < this.minChars) return;
                this.timer = setTimeout(() => this.search(false), this.debounce);
            });

            this.installObserver();
        }

        installObserver() {
            const target = this.getTarget();
            if (!target || !window.MutationObserver) return;
            this.observer = new MutationObserver(() => {
                if (Array.isArray(this.lastIds)) {
                    window.requestAnimationFrame(() => this.apply(this.lastIds));
                }
            });
            this.observer.observe(target, { childList: true, subtree: true });
        }

        setLoading(on) {
            this.root.classList.toggle('is-loading', !!on);
            this.input.setAttribute('aria-busy', on ? 'true' : 'false');
        }

        setError(message) {
            this.root.classList.add('has-error');
            this.status.textContent = message || globalCfg.genericError || 'خطا در جستجو.';
        }

        clearError() {
            this.root.classList.remove('has-error');
            this.status.textContent = '';
        }

        clearNoResults(target) {
            const old = target && target.querySelector('.raavi-seller-no-results');
            if (old) old.remove();
        }

        clear() {
            clearTimeout(this.timer);
            if (this.controller) this.controller.abort();
            this.currentTerm = '';
            this.lastIds = null;
            this.setLoading(false);
            this.clearError();

            const target = this.getTarget();
            if (!target) return;
            this.clearNoResults(target);
            findItems(target).forEach(function (item) {
                item.classList.remove('raavi-seller-search-hidden');
            });
        }

        apply(ids) {
            const target = this.getTarget();
            if (!target) {
                this.setError(globalCfg.targetError || 'لیست فروشگاه‌ها برای جستجو پیدا نشد.');
                return;
            }

            this.clearNoResults(target);
            const idSet = new Set((ids || []).map(Number));
            const items = findItems(target);
            let shown = 0;

            items.forEach(function (item) {
                const id = parsePostId(item);
                const shouldShow = id > 0 && idSet.has(id);
                item.classList.toggle('raavi-seller-search-hidden', !shouldShow);
                if (shouldShow) shown += 1;
            });

            if (shown === 0) {
                const message = document.createElement('div');
                message.className = 'raavi-seller-no-results';
                message.setAttribute('role', 'status');
                message.textContent = this.noResultsText;
                const loop = target.querySelector('.elementor-loop-container') || target.querySelector('.elementor-grid');
                if (loop && loop.parentNode) {
                    loop.parentNode.insertBefore(message, loop.nextSibling);
                } else {
                    target.appendChild(message);
                }
            }
        }

        async search(force) {
            const term = this.input.value.trim();
            clearTimeout(this.timer);
            this.clearError();

            if (!term) {
                this.clear();
                return;
            }
            if (!force && term.length < this.minChars) return;
            if (term.length < this.minChars) return;

            const target = this.getTarget();
            if (!target) {
                this.setError(globalCfg.targetError || 'لیست فروشگاه‌ها برای جستجو پیدا نشد.');
                return;
            }

            if (this.controller) this.controller.abort();
            this.controller = new AbortController();
            const requestTerm = term;
            this.currentTerm = term;
            this.setLoading(true);

            const body = new URLSearchParams();
            body.set('action', globalCfg.action || 'raavi_seller_ajax_search');
            body.set('nonce', globalCfg.nonce || '');
            body.set('term', term);

            try {
                const response = await fetch(globalCfg.ajaxUrl || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString(),
                    signal: this.controller.signal,
                    cache: 'no-store'
                });

                if (!response.ok) throw new Error('HTTP ' + response.status);
                const json = await response.json();
                if (!json || json.success !== true || !json.data) {
                    throw new Error((json && json.data && json.data.message) || 'Invalid AJAX response');
                }
                if (this.currentTerm !== requestTerm) return;

                const ids = Array.isArray(json.data.ids) ? json.data.ids : [];
                this.lastIds = ids;
                this.apply(ids);
            } catch (err) {
                if (err && err.name === 'AbortError') return;
                console.error('Raavi Seller AJAX Search:', err);
                this.setError(globalCfg.genericError || 'خطا در جستجو. لطفاً دوباره تلاش کنید.');
            } finally {
                if (this.currentTerm === requestTerm) this.setLoading(false);
            }
        }
    }

    function init(scope) {
        const base = scope && scope.querySelectorAll ? scope : document;
        const roots = [];
        if (base.matches && base.matches('.raavi-seller-search')) roots.push(base);
        base.querySelectorAll('.raavi-seller-search').forEach(function (el) { roots.push(el); });

        roots.forEach(function (root) {
            if (root.dataset.raaviSearchReady === '1') return;
            root.dataset.raaviSearchReady = '1';
            new SellerSearch(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(document); });
    } else {
        init(document);
    }

    window.addEventListener('elementor/frontend/init', function () {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/raavi_seller_ajax_search.default', function ($scope) {
                init($scope && $scope[0] ? $scope[0] : document);
            });
        }
    });
})();
