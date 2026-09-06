(function () {
    'use strict';

    if (window.KamiNotificationsInitialized) {
        return;
    }
    window.KamiNotificationsInitialized = true;

    const DISPLAY_TIME = 5000;

    function createNotification(message) {
        const element = document.createElement('div');
        const style = ['default', 'success', 'alert', 'danger'].includes(message.style)
            ? message.style
            : 'default';

        element.className = 'kc-notification kc-notification-' + style;
        element.dataset.notificationId = String(message.id || '');
        element.setAttribute('role', style === 'danger' || style === 'alert' ? 'alert' : 'status');

        const text = document.createElement('div');
        text.className = 'kc-notification-text';
        text.textContent = String(message.text || '');

        const close = document.createElement('button');
        close.className = 'kc-notification-close';
        close.type = 'button';
        close.setAttribute('aria-label', 'Close');
        close.textContent = '×';
        close.addEventListener('click', function () {
            removeNotification(element);
        });

        element.append(text, close);
        return element;
    }

    function removeNotification(element) {
        if (!element || element.classList.contains('is-leaving')) {
            return;
        }

        element.classList.add('is-leaving');
        window.setTimeout(function () {
            element.remove();
        }, 220);
    }

    function showNotification(container, message, index) {
        const element = createNotification(message);
        container.append(element);

        window.requestAnimationFrame(function () {
            element.classList.add('is-visible');
        });

        window.setTimeout(function () {
            removeNotification(element);
        }, DISPLAY_TIME + (index * 120));
    }

    async function loadNotifications(container) {
        const endpoint = container.dataset.endpoint || '/ajax/Notifications/get';

        try {
            const response = await fetch(endpoint, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            if (!payload || payload.status !== 'ok' || !Array.isArray(payload.messages)) {
                return;
            }

            payload.messages.forEach(function (message, index) {
                showNotification(container, message, index);
            });
        } catch (error) {
            // Notifications are non-critical and must never affect page behavior.
        }
    }

    function init() {
        const container = document.querySelector('[data-notifications]');
        if (container) {
            loadNotifications(container);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
}());
