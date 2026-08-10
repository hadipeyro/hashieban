(function () {
    'use strict';

    var dataNode = document.getElementById('hashieban-reports-data');
    if (!dataNode || typeof Chart === 'undefined') {
        return;
    }

    var data;
    try {
        data = JSON.parse(dataNode.textContent || '{}');
    } catch (error) {
        return;
    }

    var currency = data.currencyLabel || '';
    var format = function (value) {
        try {
            return new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 }).format(Number(value || 0)) + ' ' + currency;
        } catch (error) {
            return String(value || 0) + ' ' + currency;
        }
    };

    var common = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { usePointStyle: true, boxWidth: 8 } },
            tooltip: {
                callbacks: {
                    label: function (context) {
                        var label = context.dataset.label ? context.dataset.label + ': ' : '';
                        var value = context.parsed && typeof context.parsed.y !== 'undefined'
                            ? context.parsed.y
                            : context.parsed;
                        return label + format(value);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function (value) {
                        return new Intl.NumberFormat('fa-IR', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
                    }
                }
            }
        }
    };

    var bridge = document.getElementById('hashieban-reports-profit-bridge');
    if (bridge) {
        new Chart(bridge, {
            type: 'bar',
            data: {
                labels: (data.bridge && data.bridge.labels) || [],
                datasets: [{
                    label: 'اثر مالی',
                    data: (data.bridge && data.bridge.values) || [],
                    borderWidth: 1,
                    borderRadius: 9
                }]
            },
            options: common
        });
    }

    var productShare = document.getElementById('hashieban-reports-product-share');
    if (productShare) {
        var productData = data.products || {};
        new Chart(productShare, {
            type: 'doughnut',
            data: {
                labels: productData.labels || [],
                datasets: [{ data: productData.values || [], borderWidth: 2 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '64%',
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var values = productData.values || [];
                                var total = values.reduce(function (sum, value) { return sum + Number(value || 0); }, 0);
                                var value = Number(context.raw || 0);
                                var percent = total > 0 ? (value / total * 100) : 0;
                                return context.label + ': ' + format(value) + ' (' + percent.toLocaleString('fa-IR', { maximumFractionDigits: 1 }) + '٪)';
                            }
                        }
                    }
                },
                onClick: function (event, elements) {
                    if (!elements.length) {
                        return;
                    }
                    var index = elements[0].index;
                    var url = (productData.urls || [])[index];
                    if (url) {
                        window.location.href = url;
                    }
                }
            }
        });
    }

    var timeline = document.getElementById('hashieban-reports-timeline');
    if (timeline) {
        new Chart(timeline, {
            type: 'line',
            data: {
                labels: (data.timeline && data.timeline.labels) || [],
                datasets: [
                    {
                        label: 'فروش',
                        data: (data.timeline && data.timeline.revenue) || [],
                        tension: 0.3,
                        fill: false,
                        pointRadius: 2
                    },
                    {
                        label: 'سود',
                        data: (data.timeline && data.timeline.profit) || [],
                        tension: 0.3,
                        fill: false,
                        pointRadius: 2
                    }
                ]
            },
            options: common
        });
    }
}());
