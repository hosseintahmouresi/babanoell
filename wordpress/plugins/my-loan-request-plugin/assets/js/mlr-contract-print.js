(function(){
    'use strict';

    var activeFrame = null;
    var cleanupTimer = null;

    function asElement(source) {
        if (!source) return null;
        if (source.nodeType === 1) return source;
        if (source.jquery && source.length) return source[0];
        if (typeof source === 'string') {
            try { return document.querySelector(source); } catch (e) { return null; }
        }
        return null;
    }

    function htmlEscapeAttr(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }


    // Preserve the visual contract styling from whichever portal opened the preview,
    // without importing the whole theme/admin stylesheet into the isolated print frame.
    // Dedicated print CSS still wins via !important for A4/header/footer mechanics.
    var SNAPSHOT_STYLE_PROPS = [
        'font-family','font-size','font-weight','font-style','line-height','letter-spacing',
        'color','text-align','text-align-last','text-decoration','text-transform','direction',
        'white-space','word-break','overflow-wrap','list-style-type','list-style-position',
        'background-color','background-image','background-size','background-position','background-repeat',
        'border-top-width','border-right-width','border-bottom-width','border-left-width',
        'border-top-style','border-right-style','border-bottom-style','border-left-style',
        'border-top-color','border-right-color','border-bottom-color','border-left-color',
        'border-radius','box-shadow','vertical-align','opacity',
        'display','flex-direction','flex-wrap','justify-content','align-items','align-content','gap',
        'grid-template-columns','grid-template-rows','grid-column','grid-row','place-items',
        'border-collapse','border-spacing','table-layout','object-fit','object-position'
    ];

    function copyComputedVisualStyles(source, clone) {
        if (!source || !clone || source.nodeType !== 1 || clone.nodeType !== 1) return;
        try {
            var cs = window.getComputedStyle(source);
            SNAPSHOT_STYLE_PROPS.forEach(function(prop){
                var value = cs.getPropertyValue(prop);
                if (!value) return;
                try { clone.style.setProperty(prop, value); } catch (e) {}
            });
        } catch (e) {}

        var srcChildren = source.children || [];
        var cloneChildren = clone.children || [];
        var count = Math.min(srcChildren.length, cloneChildren.length);
        for (var i = 0; i < count; i++) copyComputedVisualStyles(srcChildren[i], cloneChildren[i]);
    }

    function sanitizePrintClone(clone) {
        if (!clone || clone.nodeType !== 1) return clone;
        // Screen-only Hami footer is intentionally duplicated in the live preview;
        // the <tfoot> copy is the one that must repeat in print. Remove the screen
        // copy physically so it can never leak into print if another stylesheet wins.
        clone.querySelectorAll('.mlr-retirees-page-footer-screen,.mlr-no-print,.mlr-print-actions,.mlr-loan-contract-review-actions,.mlr-contract-actions,.mlr-contract-signature-section,.mlr-loan-contract-review-status').forEach(function(node){
            if (node && node.parentNode) node.parentNode.removeChild(node);
        });
        // 8.4.6.84 defensive print guard: when Hami has a plan-selected footer image,
        // never allow its legacy repeating signature/address footer to print as a second footer.
        if (clone.querySelector('.mlr-hami-retirees-contract.mlr-hami-custom-footer-active') &&
            clone.querySelector('.mlr-contract-footer .mlr-contract-media-image,.mlr-print-footer .mlr-contract-media-image')) {
            clone.querySelectorAll('.mlr-hami-retirees-repeat-footer').forEach(function(node){
                if (node && node.parentNode) node.parentNode.removeChild(node);
            });
        }
        clone.querySelectorAll('[contenteditable]').forEach(function(node){ node.removeAttribute('contenteditable'); });
        return clone;
    }

    function snapshotForPrint(el) {
        var clone = el.cloneNode(true);
        copyComputedVisualStyles(el, clone);
        sanitizePrintClone(clone);
        clone.classList.add('mlr-print-style-snapshot');
        return clone;
    }

    function printCssUrl() {
        var node = document.querySelector('link#mlr-contract-print-css-css, link[data-mlr-contract-print-css]');
        if (node && node.href) return node.href;
        if (window.mlrContractPrint && window.mlrContractPrint.css_url) return window.mlrContractPrint.css_url;
        if (window.mlr_loan_contract_public_ajax && window.mlr_loan_contract_public_ajax.print_css_url) return window.mlr_loan_contract_public_ajax.print_css_url;
        return '';
    }

    function fontAssets() {
        var out = '';
        var seen = {};
        document.querySelectorAll('link[rel="stylesheet"]').forEach(function(link){
            var href = String(link.href || '');
            if (!href || seen[href]) return;
            // Keep only dedicated font/typography sheets. Theme/admin layout sheets are intentionally excluded.
            if (!/(?:font|yekan|vazir|iran|typograph)/i.test(href)) return;
            seen[href] = true;
            out += '<link rel="stylesheet" href="' + htmlEscapeAttr(href) + '">';
        });
        document.querySelectorAll('style').forEach(function(style){
            var text = String(style.textContent || '');
            if (/@font-face/i.test(text)) out += '<style>' + text + '</style>';
        });
        return out;
    }

    function waitForResources(doc) {
        var waits = [];
        Array.prototype.forEach.call(doc.querySelectorAll('link[rel="stylesheet"]'), function(link){
            if (link.sheet) return;
            waits.push(new Promise(function(resolve){
                var done = false;
                var finish = function(){ if (!done) { done = true; resolve(); } };
                link.addEventListener('load', finish, {once:true});
                link.addEventListener('error', finish, {once:true});
                setTimeout(finish, 5000);
            }));
        });
        Array.prototype.forEach.call(doc.images || [], function(img){
            if (img.complete) {
                if (img.decode) waits.push(img.decode().catch(function(){}));
                return;
            }
            waits.push(new Promise(function(resolve){
                var finish = function(){
                    if (img.decode) img.decode().catch(function(){}).then(resolve); else resolve();
                };
                img.addEventListener('load', finish, {once:true});
                img.addEventListener('error', resolve, {once:true});
                setTimeout(resolve, 5000);
            }));
        });
        if (doc.fonts && doc.fonts.ready) waits.push(doc.fonts.ready.catch(function(){}));
        return Promise.all(waits);
    }

    function removeFrame(frame) {
        if (!frame) return;
        try { frame.parentNode && frame.parentNode.removeChild(frame); } catch (e) {}
        if (activeFrame === frame) activeFrame = null;
    }

    function isolatedPrint(source, options) {
        var el = asElement(source);
        options = options || {};
        if (!el) {
            window.alert('محتوای قرارداد برای چاپ پیدا نشد.');
            return false;
        }

        var printClone = snapshotForPrint(el);
        var content = printClone.outerHTML || '';
        if (!content || !String(el.textContent || '').trim()) {
            window.alert('متن قرارداد برای چاپ خالی است.');
            return false;
        }

        var cssUrl = printCssUrl();
        if (!cssUrl) {
            window.alert('فایل استایل چاپ قرارداد بارگذاری نشده است. صفحه را یک‌بار تازه‌سازی کنید.');
            return false;
        }

        if (activeFrame) removeFrame(activeFrame);
        if (cleanupTimer) { clearTimeout(cleanupTimer); cleanupTimer = null; }

        var frame = document.createElement('iframe');
        frame.setAttribute('aria-hidden', 'true');
        frame.setAttribute('tabindex', '-1');
        frame.style.position = 'fixed';
        frame.style.left = '-10000px';
        frame.style.top = '0';
        frame.style.width = '1px';
        frame.style.height = '1px';
        frame.style.border = '0';
        frame.style.opacity = '0';
        frame.style.pointerEvents = 'none';
        document.body.appendChild(frame);
        activeFrame = frame;

        var baseHref = htmlEscapeAttr(document.baseURI || window.location.href);
        var title = htmlEscapeAttr(options.title || 'چاپ قرارداد');
        var html = '<!doctype html><html lang="fa" dir="rtl"><head>' +
            '<meta charset="utf-8">' +
            '<meta name="viewport" content="width=device-width,initial-scale=1">' +
            '<base href="' + baseHref + '">' +
            '<title>' + title + '</title>' +
            fontAssets() +
            '<link rel="stylesheet" data-mlr-contract-print-css href="' + htmlEscapeAttr(cssUrl) + '">' +
            '</head><body class="mlr-contract-print-document"><div id="mlr-isolated-print-root">' + content + '</div></body></html>';

        var doc = frame.contentDocument || (frame.contentWindow && frame.contentWindow.document);
        if (!doc) {
            removeFrame(frame);
            window.alert('مرورگر امکان آماده‌سازی نسخه چاپی را نداد.');
            return false;
        }

        doc.open();
        doc.write(html);
        doc.close();

        var started = false;
        var start = function(){
            if (started || !frame.contentWindow) return;
            started = true;
            waitForResources(doc).then(function(){
                return new Promise(function(resolve){
                    var win = frame.contentWindow;
                    if (!win) { resolve(); return; }
                    win.requestAnimationFrame(function(){
                        win.requestAnimationFrame(function(){ setTimeout(resolve, 220); });
                    });
                });
            }).then(function(){
                var win = frame.contentWindow;
                if (!win) throw new Error('print frame unavailable');
                var root = doc.getElementById('mlr-isolated-print-root');
                if (!root || !String(root.innerText || root.textContent || '').trim()) throw new Error('print content missing');

                // Last-resort overflow guard for legacy/custom HTML: no node may force
                // the printable table wider than the physical A4 content cell.
                Array.prototype.forEach.call(root.querySelectorAll('table,img,svg,pre,code'), function(node){
                    try {
                        node.style.maxWidth = '100%';
                        if (node.tagName === 'TABLE' && !node.classList.contains('mlr-print-layout-table')) node.style.width = '100%';
                        if (node.tagName === 'PRE' || node.tagName === 'CODE') node.style.whiteSpace = 'pre-wrap';
                    } catch (e) {}
                });

                var after = function(){
                    try { win.removeEventListener('afterprint', after); } catch (e) {}
                    setTimeout(function(){ removeFrame(frame); }, 250);
                };
                try { win.addEventListener('afterprint', after, {once:true}); } catch (e) {}
                win.focus();
                win.print();
                cleanupTimer = setTimeout(function(){ removeFrame(frame); }, 60000);
            }).catch(function(){
                removeFrame(frame);
                window.alert('نسخه چاپی قرارداد آماده نشد. صفحه را تازه‌سازی کرده و دوباره تلاش کنید.');
            });
        };

        if (doc.readyState === 'complete') start();
        else frame.addEventListener('load', start, {once:true});
        // srcdoc/document.write load events can be inconsistent in some Chromium builds.
        setTimeout(start, 80);
        return true;
    }

    window.mlrPrintContractNode = isolatedPrint;
})();
