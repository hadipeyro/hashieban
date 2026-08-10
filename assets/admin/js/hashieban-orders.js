(function () {
    'use strict';

    var source = document.getElementById('hashieban-order-center-data');

    if (!source || typeof Chart === 'undefined') {
        return;
    }

    var payload;

    try {
        payload = JSON.parse(source.textContent || '{}');
    } catch (error) {
        return;
    }

    var numberFormatter = new Intl.NumberFormat('fa-IR', {
        maximumFractionDigits: 1
    });

    function money(value) {
        return numberFormatter.format(Number(value || 0)) + ' ' + (payload.currencyLabel || '');
    }

    function baseOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'nearest',
                intersect: false
            },
            plugins: {
                legend: {
                    labels: {
                        usePointStyle: true,
                        font: {
                            family: 'Tahoma, Arial, sans-serif'
                        }
                    }
                },
                tooltip: {
                    rtl: true,
                    titleAlign: 'right',
                    bodyAlign: 'right'
                }
            }
        };
    }

    function renderList() {
        var profitabilityCanvas = document.getElementById('hashieban-orders-profitability-chart');
        var marginCanvas = document.getElementById('hashieban-orders-margin-chart');
        var scatterCanvas = document.getElementById('hashieban-orders-scatter-chart');

        if (profitabilityCanvas && payload.profitability) {
            new Chart(profitabilityCanvas, {
                type: 'doughnut',
                data: {
                    labels: payload.profitability.labels || [],
                    datasets: [{
                        data: payload.profitability.values || [],
                        backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
                        borderWidth: 3,
                        borderColor: '#ffffff',
                        hoverOffset: 8
                    }]
                },
                options: Object.assign(baseOptions(), {
                    cutout: '68%',
                    plugins: Object.assign({}, baseOptions().plugins, {
                        tooltip: Object.assign({}, baseOptions().plugins.tooltip, {
                            callbacks: {
                                label: function (context) {
                                    return context.label + ': ' + numberFormatter.format(context.raw || 0) + ' سفارش';
                                }
                            }
                        })
                    })
                })
            });
        }

        if (marginCanvas && payload.margin) {
            var marginOptions = baseOptions();
            marginOptions.scales = {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    },
                    grid: {
                        color: 'rgba(148,163,184,.16)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            };
            marginOptions.plugins.legend.display = false;
            marginOptions.plugins.tooltip.callbacks = {
                label: function (context) {
                    return numberFormatter.format(context.raw || 0) + ' سفارش';
                }
            };

            new Chart(marginCanvas, {
                type: 'bar',
                data: {
                    labels: payload.margin.labels || [],
                    datasets: [{
                        data: payload.margin.values || [],
                        backgroundColor: '#6366f1',
                        borderRadius: 8,
                        maxBarThickness: 52
                    }]
                },
                options: marginOptions
            });
        }

        if (scatterCanvas && Array.isArray(payload.scatter)) {
            var scatterOptions = baseOptions();
            scatterOptions.scales = {
                x: {
                    title: {
                        display: true,
                        text: 'مبلغ سفارش (' + (payload.currencyLabel || '') + ')'
                    },
                    grid: {
                        color: 'rgba(148,163,184,.13)'
                    },
                    ticks: {
                        callback: function (value) {
                            return numberFormatter.format(value);
                        }
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Margin %'
                    },
                    grid: {
                        color: 'rgba(148,163,184,.13)'
                    },
                    ticks: {
                        callback: function (value) {
                            return numberFormatter.format(value) + '٪';
                        }
                    }
                }
            };
            scatterOptions.plugins.legend.display = false;
            scatterOptions.plugins.tooltip.callbacks = {
                title: function (items) {
                    if (!items.length) {
                        return '';
                    }
                    var raw = items[0].raw || {};
                    return (raw.order || '') + ' · ' + (raw.customer || '');
                },
                label: function (context) {
                    var raw = context.raw || {};
                    return [
                        'فروش: ' + money(raw.x),
                        'Margin: ' + numberFormatter.format(raw.y || 0) + '٪',
                        'سود: ' + money(raw.profit)
                    ];
                }
            };
            scatterOptions.onClick = function (event, elements, chart) {
                if (!elements || !elements.length) {
                    return;
                }

                var point = chart.data.datasets[elements[0].datasetIndex].data[elements[0].index];

                if (point && point.url) {
                    window.location.href = point.url;
                }
            };
            scatterOptions.onHover = function (event, elements) {
                if (event && event.native && event.native.target) {
                    event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                }
            };

            new Chart(scatterCanvas, {
                type: 'scatter',
                data: {
                    datasets: [{
                        data: payload.scatter,
                        parsing: false,
                        pointRadius: 6,
                        pointHoverRadius: 9,
                        backgroundColor: function (context) {
                            var raw = context.raw || {};
                            return Number(raw.profit || 0) < 0 ? '#ef4444' : '#2563eb';
                        }
                    }]
                },
                options: scatterOptions
            });
        }
    }

    function renderDetail() {
        var canvas = document.getElementById('hashieban-order-detail-breakdown-chart');
        var semanticsCanvas = document.getElementById('hashieban-order-semantics-chart');

        if (canvas && payload.breakdown) {
            var options = baseOptions();
            options.plugins.legend.display = false;
            options.plugins.tooltip.callbacks = {
                label: function (context) {
                    return money(context.raw || 0);
                }
            };
            options.scales = {
                y: {
                    beginAtZero: false,
                    grid: {
                        color: 'rgba(148,163,184,.13)'
                    },
                    ticks: {
                        callback: function (value) {
                            return numberFormatter.format(value);
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            };

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: payload.breakdown.labels || [],
                    datasets: [{
                        data: payload.breakdown.values || [],
                        backgroundColor: ['#2563eb', '#f59e0b', '#8b5cf6', '#06b6d4', '#10b981'],
                        borderRadius: 9,
                        maxBarThickness: 58
                    }]
                },
                options: options
            });
        }

        if (semanticsCanvas && payload.semantics) {
            var semanticsOptions = baseOptions();
            semanticsOptions.indexAxis = 'y';
            semanticsOptions.plugins.legend.display = false;
            semanticsOptions.plugins.tooltip.callbacks = {
                label: function (context) {
                    return money(context.raw || 0);
                }
            };
            semanticsOptions.scales = {
                x: {
                    grid: {
                        color: 'rgba(148,163,184,.13)'
                    },
                    ticks: {
                        callback: function (value) {
                            return numberFormatter.format(value);
                        }
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            };

            new Chart(semanticsCanvas, {
                type: 'bar',
                data: {
                    labels: payload.semantics.labels || [],
                    datasets: [{
                        data: payload.semantics.values || [],
                        backgroundColor: function (context) {
                            if (context.dataIndex === 5) {
                                return '#94a3b8';
                            }

                            return Number(context.raw || 0) < 0 ? '#ef4444' : '#2563eb';
                        },
                        borderRadius: 9,
                        maxBarThickness: 38
                    }]
                },
                options: semanticsOptions
            });
        }
    }

    if (payload.mode === 'detail') {
        renderDetail();
    } else {
        renderList();
    }
}());
