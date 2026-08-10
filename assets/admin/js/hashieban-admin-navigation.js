(function () {
    'use strict';

    var hiddenPages = [
        'hashieban-products',
        'hashieban-inventory',
        'hashieban-customers',
        'hashieban-time',
        'hashieban-reports',
        'hashieban-expense-intelligence',
        'hashieban-data-health',
        'hashieban-expense-categories',
        'hashieban-status'
    ];

    function hideSpecialistMenuItems() {
        var root = document.getElementById('toplevel_page_hashieban');

        if (!root) {
            return;
        }

        var links = root.querySelectorAll('.wp-submenu a[href]');

        links.forEach(function (link) {
            var url;
            var page;

            try {
                url = new URL(link.href, window.location.href);
                page = url.searchParams.get('page');
            } catch (error) {
                return;
            }

            if (hiddenPages.indexOf(page) === -1) {
                return;
            }

            var item = link.closest('li');

            if (item) {
                item.hidden = true;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            hideSpecialistMenuItems
        );
        return;
    }

    hideSpecialistMenuItems();
}());
