/**
 * Vanilla per-slot sortable controller for MageAustralia_CheckoutSuccess.
 * One controller instance per slot config field. Keeps a hidden input in
 * sync with the user's checkbox + drag-handle state.
 *
 * No external deps — uses HTML5 drag-and-drop API and event delegation.
 *
 * Loaded by the admin layout for the config-edit screen. init() is idempotent
 * (guards against double-initialisation if the field re-renders).
 */
(function (global) {
    'use strict';

    const initialized = new WeakSet();

    function syncHidden(list, hidden) {
        const codes = Array.from(list.querySelectorAll('li'))
            .filter(function (li) {
                const cb = li.querySelector('input[type="checkbox"]');
                return cb && cb.checked;
            })
            .map(function (li) { return li.dataset.code; });
        hidden.value = codes.join(',');
    }

    function init(listId, hiddenId) {
        const list = document.getElementById(listId);
        const hidden = document.getElementById(hiddenId);
        if (!list || !hidden || initialized.has(list)) {
            return;
        }
        initialized.add(list);

        // Checkbox toggles drive the hidden value.
        list.addEventListener('change', function (e) {
            if (e.target && e.target.matches && e.target.matches('input[type="checkbox"]')) {
                syncHidden(list, hidden);
            }
        });

        // HTML5 drag-and-drop reordering. Drag any part of the <li> — the
        // <li> itself carries draggable=true. Drop position is determined
        // by whether the cursor is in the top or bottom half of the target.
        let dragging = null;

        list.addEventListener('dragstart', function (e) {
            const li = e.target.closest('li');
            if (!li) { return; }
            dragging = li;
            li.classList.add('csl-dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                // Required for Firefox to actually start the drag.
                try { e.dataTransfer.setData('text/plain', li.dataset.code || ''); } catch (_) {}
            }
        });

        list.addEventListener('dragend', function () {
            if (dragging) {
                dragging.classList.remove('csl-dragging');
                dragging = null;
                syncHidden(list, hidden);
            }
        });

        list.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!dragging) { return; }
            const target = e.target.closest('li');
            if (!target || target === dragging) { return; }
            const rect = target.getBoundingClientRect();
            const before = (e.clientY - rect.top) < rect.height / 2;
            target.parentNode.insertBefore(dragging, before ? target : target.nextSibling);
        });
    }

    global.MageAustraliaCheckoutSuccessSortable = { init: init };
})(window);
