/**
 * Admin common JS
 *
 * One place for all admin-side interactions.
 * - Global click routing
 * - Global submit routing
 * - Ajax GET links
 * - Ajax forms (GET/POST)
 * - Confirm handling
 *
 * Extend by adding routes and handlers below.
 */
(function () {

    /**
     * =========================
     * CLICK ROUTES CONFIG
     * =========================
     *
     * Order matters.
     */
    const clickRoutes = [
        {
            selector: 'a[data-ajax][data-target]',
            handler: onAjaxLinkClick,
            stop: true
        },
        {
            selector: '[data-admin-sidebar-toggle]',
            handler: onSidebarToggle,
            stop: true
        },
        // Add more click routes here
    ];

    /**
     * =========================
     * SUBMIT ROUTES CONFIG
     * =========================
     *
     * Order matters.
     */
    const submitRoutes = [
        {
            selector: 'form[data-ajax-form][data-target]',
            handler: onAjaxFormSubmit,
            stop: true
        },
        // Add more submit routes here
    ];

    /**
     * =========================
     * GLOBAL CLICK HANDLER
     * =========================
     */
    document.addEventListener('click', function (e) {
        // Only plain left click
        if (e.defaultPrevented) return;
        if (e.button !== 0) return;
        if (e.ctrlKey || e.shiftKey || e.metaKey || e.altKey) return;
        if (!document.body?.classList.contains('kami-admin')) return;

        for (const route of clickRoutes) {
            const el = e.target.closest(route.selector);
            if (!el) continue;

            const result = route.handler(e, el);
            if (result === true || route.stop === true) break;
        }
    });

    /**
     * =========================
     * GLOBAL SUBMIT HANDLER
     * =========================
     */
    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        if (!document.body?.classList.contains('kami-admin')) return;

        const form = e.target.closest('form');
        if (!form) return;

        for (const route of submitRoutes) {
            const el = form.matches(route.selector) ? form : null;
            if (!el) continue;

            const result = route.handler(e, el);
            if (result === true || route.stop === true) break;
        }
    });

    /**
     * =========================
     * ROUTE HANDLERS
     * =========================
     */

    /**
     * Toggle the admin sidebar and persist its state.
     */
    function onSidebarToggle(e, button) {
        e.preventDefault();

        const shell = button.closest('[data-admin-shell]');
        const sidebar = shell?.querySelector('[data-admin-sidebar]');
        if (!sidebar) return true;

        const collapsed = !sidebar.classList.contains('collapsed');
        setSidebarState(sidebar, button, collapsed);
        localStorage.setItem('kami_admin_sidebar_collapsed', collapsed ? '1' : '0');

        return true;
    }

    function setSidebarState(sidebar, button, collapsed) {
        sidebar.classList.toggle('collapsed', collapsed);
        button.setAttribute('aria-expanded', String(!collapsed));

        const icon = button.querySelector('[data-admin-sidebar-toggle-icon]');
        if (icon) icon.textContent = collapsed ? '›' : '‹';
    }

    /**
     * Handle Ajax GET links with a target container.
     */
    async function onAjaxLinkClick(e, link) {
        e.preventDefault();

        const url = link.getAttribute('href');
        const targetSelector = link.dataset.target;
        const confirmText = link.dataset.confirm;

        if (!url || !targetSelector) {
            console.warn('Ajax link skipped: missing href or data-target', link);
            return true;
        }

        if (confirmText && !window.confirm(confirmText)) {
            return true;
        }

        const target = document.querySelector(targetSelector);
        if (!target) {
            console.warn('Ajax target not found:', targetSelector);
            return true;
        }

        try {
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const html = await response.text();

            target.innerHTML = html;
            executeScripts(target);

        } catch (err) {
            console.error('Ajax request failed:', err);
            target.innerHTML = '<div class="uk-alert uk-alert-danger">Ajax request failed</div>';
        }

        return true;
    }

    /**
     * Ajax form submit handler (GET/POST)
     *
     * <form
     *   action="/ajax/plugin/method"
     *   method="post"
     *   data-ajax-form
     *   data-target="#result"
     *   data-confirm="Are you sure?"
     * >
     *   ...
     * </form>
     */
    async function onAjaxFormSubmit(e, form) {
        e.preventDefault();

        const action = form.getAttribute('action') || window.location.href;
        const method = (form.getAttribute('method') || 'POST').toUpperCase();
        const targetSelector = form.dataset.target;
        const confirmText = form.dataset.confirm;

        if (!targetSelector) {
            console.warn('Ajax form skipped: missing data-target', form);
            return true;
        }

        if (confirmText && !window.confirm(confirmText)) {
            return true;
        }

        const target = document.querySelector(targetSelector);
        if (!target) {
            console.warn('Ajax target not found:', targetSelector);
            return true;
        }

        try {
            const formData = new FormData(form);

            let url = action;
            const fetchOptions = {
                method,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            if (method === 'GET') {
                const qs = new URLSearchParams(formData).toString();
                url = appendQuery(url, qs);
            } else {
                // Send as x-www-form-urlencoded for predictable PHP input handling
                const body = new URLSearchParams(formData);
                fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
                fetchOptions.body = body;
            }

            const response = await fetch(url, fetchOptions);

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const html = await response.text();

            target.innerHTML = html;
            executeScripts(target);

        } catch (err) {
            console.error('Ajax form request failed:', err);
            target.innerHTML = '<div class="uk-alert uk-alert-danger">Ajax request failed</div>';
        }

        return true;
    }

    /**
     * =========================
     * HELPERS
     * =========================
     */

    function appendQuery(url, queryString) {
        if (!queryString) return url;
        const sep = url.includes('?') ? '&' : '?';
        return url + sep + queryString;
    }

    /**
     * Execute scripts inside injected HTML
     *
     * Browsers do not execute <script> added via innerHTML,
     * so we recreate them manually.
     */
    function executeScripts(container) {
        const scripts = container.querySelectorAll('script');

        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');

            if (oldScript.src) {
                newScript.src = oldScript.src;
                newScript.async = false;
            } else {
                newScript.textContent = oldScript.textContent;
            }

            document.head.appendChild(newScript);
            oldScript.remove();
        });
    }

    /**
     * =========================
     * OPTIONAL EXTENSION API
     * =========================
     *
     * Allows adding routes from other admin JS files
     * without touching this one.
     */
    document.addEventListener('DOMContentLoaded', function () {
        const shell = document.querySelector('[data-admin-shell]');
        const sidebar = shell?.querySelector('[data-admin-sidebar]');
        const button = shell?.querySelector('[data-admin-sidebar-toggle]');
        if (!sidebar || !button) return;

        const collapsed = localStorage.getItem('kami_admin_sidebar_collapsed') === '1';
        setSidebarState(sidebar, button, collapsed);
    });

    window.Admin = window.Admin || {};
    window.Admin.addClickRoute = function (selector, handler, stop = false) {
        clickRoutes.push({ selector, handler, stop });
    };
    window.Admin.addSubmitRoute = function (selector, handler, stop = false) {
        submitRoutes.push({ selector, handler, stop });
    };
    window.Admin.executeScripts = executeScripts;
    window.Admin.initDynamic = function (root = document) {
        window.Admin.initRemoteSelects?.(root);
        window.Admin.initFormsTomSelects?.(root);
    };


})();
