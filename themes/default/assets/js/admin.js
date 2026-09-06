(function () {

    const storageKey = 'kami.admin.sidebar.collapsed';
    const shell = document.querySelector('[data-admin-shell]');
    const toggle = document.querySelector('[data-admin-sidebar-toggle]');

	console.log(toggle);

    if (!shell || !toggle) {
        return;
    }

    const setCollapsed = collapsed => {
        shell.toggleAttribute('data-sidebar-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', String(!collapsed));
		document.documentElement.classList.toggle('admin-sidebar-collapsed');

        localStorage.setItem(storageKey, collapsed ? '1' : '0');
    };

    setCollapsed(localStorage.getItem(storageKey) === '1');

    toggle.addEventListener('click', () => {
        setCollapsed(!shell.hasAttribute('data-sidebar-collapsed'));
    });

})();

(() => {
    'use strict';

    let tooltip = null;
    let target = null;

    function show(element) {
        const text = element.dataset.tooltip;

        if (!text) {
            return;
        }

        if (
			element.closest('.admin-sidebar')
			&& !document.documentElement.classList.contains('admin-sidebar-collapsed')
		) {
			return;
		}

        target = element;

        tooltip ??= createTooltip();
        tooltip.textContent = text;
        tooltip.hidden = false;

        position();
    }

    function hide() {
        if (tooltip) {
            tooltip.hidden = true;
        }

        target = null;
    }

    function createTooltip() {
        const element = document.createElement('div');

        element.className = 'admin-tooltip';
        element.setAttribute('role', 'tooltip');
        element.hidden = true;

        document.body.appendChild(element);

        return element;
    }

    function position() {
        if (!tooltip || !target) {
            return;
        }

        const rect = target.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();

        // tooltip.style.left =
        //     `${rect.left + rect.width / 2 - tooltipRect.width / 2}px`;
        //
        // tooltip.style.top =
        //     `${rect.top - tooltipRect.height - 8}px`;

		tooltip.style.left = `${rect.right - 8}px`;
		tooltip.style.top = `${rect.top + 6}px`;
    }

    document.addEventListener('mouseover', event => {
        const element = event.target.closest('[data-tooltip]');

        if (element) {
            show(element);
        }
    });

    document.addEventListener('mouseout', event => {
        if (
            target
            && !target.contains(event.relatedTarget)
        ) {
            hide();
        }
    });

    document.addEventListener('focusin', event => {
        const element = event.target.closest('[data-tooltip]');

        if (element) {
            show(element);
        }
    });

    document.addEventListener('focusout', hide);

    window.addEventListener('scroll', hide, true);
    window.addEventListener('resize', hide);
})();
