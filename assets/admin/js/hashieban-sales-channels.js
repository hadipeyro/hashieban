(function () {
    'use strict';

    if (
        typeof Chart === 'undefined'
        || typeof hashiebanSalesChannels === 'undefined'
    ) {
        return;
    }

    var data = hashiebanSalesChannels;
    var labels = Array.isArray(data.labels) ? data.labels : [];

    if (!labels.length) {
        return;
    }

    var money = function (value) {
        var number = Number(value || 0);

        try {
            return new Intl.NumberFormat('fa-IR', {
                maximumFractionDigits: 1
            }).format(number) + ' ' + (data.currencyLabel || '');
        } catch (error) {
            return number + ' ' + (data.currencyLabel || '');
        }
    };

    var valueCanvas = document.getElementById('hashieban-channel-value-chart');

    if (valueCanvas) {
        new Chart(valueCanvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'فروش',
                        data: data.sales || [],
                        borderWidth: 1,
                        borderRadius: 7
                    },
                    {
                        label: 'سود سفارش',
                        data: data.profits || [],
                        borderWidth: 1,
                        borderRadius: 7
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
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
                        ticks: {
                            callback: function (value) {
                                return money(value);
                            }
                        }
                    }
                }
            }
        });
    }

    var orderCanvas = document.getElementById('hashieban-channel-order-chart');

    if (orderCanvas) {
        new Chart(orderCanvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'سفارش',
                        data: data.orders || [],
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        rtl: true,
                        textDirection: 'rtl',
                        callbacks: {
                            label: function (context) {
                                return context.label + ': '
                                    + new Intl.NumberFormat('fa-IR').format(Number(context.raw || 0))
                                    + ' سفارش';
                            }
                        }
                    }
                }
            }
        });
    }
}());
