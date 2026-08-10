ا(function () {
    'use strict';

    var dataNode = document.getElementById('hashieban-product-profitability-data');

    if (!dataNode || typeof Chart === 'undefined') {
        return;
    }

    var payload;

    try {
        payload = JSON.parse(dataNode.textContent || '{}');
    } catch (error) {
        return;
    }

    var currencyLabel = payload.currencyLabel || '';

    function formatNumber(value) {
        var number = Number(value || 0);

        return new Intl.NumberFormat('fa-IR', {
            maximumFractionDigits: 2
        }).format(number) + (currencyLabel ? ' ' + currencyLabel : '');
    }

    function commonOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            interaction: {
                mode: 'nearest',
                intersect: false
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    rtl: true,
                    textDirection: 'rtl',
                    callbacks: {
                        label: function (context) {
                            return formatNumber(context.raw);
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return new Intl.NumberFormat('fa-IR', {
                                notation: 'compact',
                                maximumFractionDigits: 1
                            }).format(value);
                        }
                    },
                    grid: {
                        color: 'rgba(148, 163, 184, 0.18)'
                    }
                },
                y: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        autoSkip: false
                    }
                }
            }
        };
    }

    var revenueCanvas = document.getElementById('hashieban-product-revenue-chart');

    if (revenueCanvas && payload.revenue) {
        new Chart(revenueCanvas, {
            type: 'bar',
            data: {
                labels: payload.revenue.labels || [],
                datasets: [{
                    label: 'فروش',
                    data: payload.revenue.values || [],
                    backgroundColor: '#2563eb',
                    borderRadius: 7,
                    borderSkipped: false
                }]
            },
            options: commonOptions()
        });
    }

    var profitCanvas = document.getElementById('hashieban-product-profit-chart');

    if (profitCanvas && payload.profit) {
        var values = payload.profit.values || [];
        var colors = values.map(function (value) {
            return Number(value) < 0 ? '#dc2626' : '#16a34a';
        });

        new Chart(profitCanvas, {
            type: 'bar',
            data: {
                labels: payload.profit.labels || [],
                datasets: [{
                    label: 'سود',
                    data: values,
                    backgroundColor: colors,
                    borderRadius: 7,
                    borderSkipped: false
                }]
            },
            options: commonOptions()
        });
    }
}());
