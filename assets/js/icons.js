(function () {
    'use strict';

    // Change here if sprite location changes
    const SPRITE_PATH = '/assets/icons/sprite.svg';

    function initIcons(root = document) {
        const icons = root.querySelectorAll('svg.icon:not([data-icon-ready])');

        icons.forEach(svg => {
            const iconClass = [...svg.classList].find(c => c.startsWith('icon-'));
            if (!iconClass) return;

            const symbolId = iconClass;
            const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
            use.setAttributeNS(null, 'href', SPRITE_PATH + '#' + symbolId);

            svg.appendChild(use);
            svg.setAttribute('aria-hidden', 'true');
            svg.setAttribute('focusable', 'false');
            svg.dataset.iconReady = '1';
        });
    }

    function start() {
        initIcons();

        // Observe dynamic DOM changes (AJAX updates).
        const observer = new MutationObserver(mutations => {
            for (const mutation of mutations) {
                mutation.addedNodes.forEach(node => {
                    if (!(node instanceof Element)) return;

                    if (node.matches?.('svg.icon')) {
                        initIcons(node.parentNode || document);
                    } else {
                        initIcons(node);
                    }
                });
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }

    // Expose manually if needed
    window.KamiIcons = { init: initIcons };

})();
