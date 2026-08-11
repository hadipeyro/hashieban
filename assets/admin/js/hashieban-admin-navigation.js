(function () {
    'use strict';

    var hiddenPages = [
        'hashieban-kpis',
        'hashieban-products',
        'hashieban-channels',
        'hashieban-coupons',
        'hashieban-inventory',
        'hashieban-customers',
        'hashieban-time',
        'hashieban-alerts',
        'hashieban-reports',
        'hashieban-expense-intelligence',
        'hashieban-data-health',
        'hashieban-geo',
        'hashieban-bulk-tools',
        'hashieban-expense-categories',
        'hashieban-status',
        'hashieban-onboarding'
    ];

    var pageMeta = {
        'hashieban': ['پیشخوان', ''],
        'hashieban-analytics': ['گزارش‌ها و تحلیل‌ها', ''],
        'hashieban-kpis': ['نبض کسب‌وکار', 'analytics'],
        'hashieban-channels': ['کانال‌های فروش', 'analytics'],
        'hashieban-coupons': ['تخفیف‌ها و کوپن‌ها', 'analytics'],
        'hashieban-products': ['سودآوری محصولات', 'analytics'],
        'hashieban-inventory': ['موجودی و پیشنهاد خرید', 'analytics'],
        'hashieban-customers': ['سودآوری مشتریان', 'analytics'],
        'hashieban-time': ['روند فروش و سود', 'analytics'],
        'hashieban-orders': ['سودآوری سفارش‌ها', ''],
        'hashieban-alerts': ['هشدارهای سود و فروش', 'analytics'],
        'hashieban-reports': ['گزارش‌های مدیریتی', 'analytics'],
        'hashieban-expense-intelligence': ['تحلیل هزینه‌ها', 'analytics'],
        'hashieban-data-health': ['سلامت داده', 'analytics'],
        'hashieban-geo': ['نقشه فروش ایران', 'analytics'],
        'hashieban-bulk-tools': ['ابزارهای مدیریت داده', 'analytics'],
        'hashieban-expenses': ['هزینه‌های فروشگاه', ''],
        'hashieban-expense-categories': ['دسته‌های هزینه', 'analytics'],
        'hashieban-settings': ['تنظیمات', ''],
        'hashieban-status': ['وضعیت سیستم', 'analytics'],
        'hashieban-onboarding': ['شروع سریع', 'analytics']
    };

    function currentPage() {
        try {
            return new URL(window.location.href).searchParams.get('page') || '';
        } catch (error) {
            return '';
        }
    }

    function hideSpecialistMenuItems() {
        var root = document.getElementById('toplevel_page_hashieban');

        if (!root) {
            return;
        }

        var current = currentPage();
        var analyticsLink = null;
        var links = root.querySelectorAll('.wp-submenu a[href]');

        links.forEach(function (link) {
            var page;

            try {
                page = new URL(link.href, window.location.href).searchParams.get('page');
            } catch (error) {
                return;
            }

            if (page === 'hashieban-analytics') {
                analyticsLink = link;
            }

            if (hiddenPages.indexOf(page) === -1) {
                return;
            }

            var item = link.closest('li');

            if (item) {
                item.hidden = true;
                item.setAttribute('aria-hidden', 'true');
            }
        });

        if (
            analyticsLink
            && pageMeta[current]
            && pageMeta[current][1] === 'analytics'
        ) {
            var analyticsItem = analyticsLink.closest('li');

            if (analyticsItem) {
                analyticsItem.classList.add('current');
            }
        }
    }

    function makeLink(label, url, className) {
        var link = document.createElement('a');
        link.href = url;
        link.textContent = label;
        link.className = className || '';
        return link;
    }

    function addContextNavigation() {
        var page = currentPage();
        var meta = pageMeta[page];

        if (!meta) {
            return;
        }

        var wrap = document.querySelector('#wpbody-content > .wrap');

        if (!wrap || wrap.querySelector('.hb-context-nav')) {
            return;
        }

        var nav = document.createElement('nav');
        nav.className = 'hb-context-nav';
        nav.setAttribute('aria-label', 'مسیر صفحه حاشیه‌بان');

        var trail = document.createElement('div');
        trail.className = 'hb-context-nav__trail';

        trail.appendChild(
            makeLink('حاشیه‌بان', 'admin.php?page=hashieban', 'hb-context-nav__home')
        );

        if (meta[1] === 'analytics') {
            var sepOne = document.createElement('span');
            sepOne.className = 'hb-context-nav__sep';
            sepOne.textContent = '‹';
            trail.appendChild(sepOne);
            trail.appendChild(
                makeLink('گزارش‌ها و تحلیل‌ها', 'admin.php?page=hashieban-analytics')
            );
        }

        if (page !== 'hashieban') {
            var sepTwo = document.createElement('span');
            sepTwo.className = 'hb-context-nav__sep';
            sepTwo.textContent = '‹';
            trail.appendChild(sepTwo);

            var current = document.createElement('strong');
            current.textContent = meta[0];
            trail.appendChild(current);
        }

        nav.appendChild(trail);

        var actions = document.createElement('div');
        actions.className = 'hb-context-nav__actions';

        if (meta[1] === 'analytics') {
            actions.appendChild(
                makeLink('بازگشت به گزارش‌ها', 'admin.php?page=hashieban-analytics', 'button')
            );
        } else if (page !== 'hashieban') {
            actions.appendChild(
                makeLink('پیشخوان حاشیه‌بان', 'admin.php?page=hashieban', 'button')
            );
        }

        if (actions.childNodes.length > 0) {
            nav.appendChild(actions);
        }

        wrap.insertBefore(nav, wrap.firstChild);
    }

    function boot() {
        hideSpecialistMenuItems();
        addContextNavigation();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
        return;
    }

    boot();
}());
