(function () {
    'use strict';

    if (typeof Chart === 'undefined' || typeof hashiebanCouponIntelligence === 'undefined') {
        return;
    }

    var data = hashiebanCouponIntelligence;
    var labels = Array.isArray(data.labels) ? data.labels : [];

    if (!labels.length) {
        return;
    }

    var money = function (value) {
        var number = Number(value || 0);
        try {
            return new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 1 }).format(number) + ' ' + (data.currencyLabel || '');
        } catch (error) {
            return number + ' ' + (data.currencyLabel || '');
        }
    };

    var common = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                rtl: true,
                textDirection: 'rtl',
                callbacks: {
                    label: function (context) {
                        return context.dataset.label + ': ' + money(context.raw);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: function (value) { return money(value); } }
            }
        }
    };

    var valueCanvas = document.getElementById('hashieban-coupon-value-chart');
    if (valueCanvas) {
        new Chart(valueCanvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'فروش سفارش‌ها', data: data.sales || [], borderWidth: 1, borderRadius: 7 },
                    { label: 'سود باقی‌مانده', data: data.profits || [], borderWidth: 1, borderRadius: 7 }
                ]
            },
            options: common
        });
    }

    var pressureCanvas = document.getElementById('hashieban-coupon-pressure-chart');
    if (pressureCanvas) {
        new Chart(pressureCanvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'تخفیف داده‌شده', data: data.discounts || [], borderWidth: 1, borderRadius: 7 },
                    { label: 'سود باقی‌مانده', data: data.profits || [], borderWidth: 1, borderRadius: 7 }
                ]
            },
            options: common
        });
    }
}());
