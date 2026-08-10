(function () {
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

    function formatPercent(value) {
        return new Intl.NumberFormat('fa-IR', {
            maximumFractionDigits: 1
        }).format(Number(value || 0)) + '٪';
    }

    function openUrl(url) {
        if (typeof url !== 'string' || url === '') {
            return;
        }

        window.location.href = url;
    }

    function makeClickable(chart, urls) {
        chart.options.onHover = function (event, elements) {
            if (!event || !event.native || !event.native.target) {
                return;
            }

            event.native.target.style.cursor = elements && elements.length
                ? 'pointer'
                : 'default';
        };

        chart.options.onClick = function (event, elements) {
            if (!elements || !elements.length) {
                return;
            }

            var index = elements[0].index;
            openUrl((urls || [])[index] || '');
        };
    }

    function commonBarOptions() {
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
        var revenueOptions = commonBarOptions();
        var revenueChart = new Chart(revenueCanvas, {
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
            options: revenueOptions
        });

        makeClickable(revenueChart, payload.revenue.urls || []);
        revenueChart.update();
    }

    var profitCanvas = document.getElementById('hashieban-product-profit-chart');

    if (profitCanvas && payload.profit) {
        var values = payload.profit.values || [];
        var colors = values.map(function (value) {
            return Number(value) < 0 ? '#dc2626' : '#16a34a';
        });
        var profitOptions = commonBarOptions();
        var profitChart = new Chart(profitCanvas, {
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
            options: profitOptions
        });

        makeClickable(profitChart, payload.profit.urls || []);
        profitChart.update();
    }

    var scatterCanvas = document.getElementById('hashieban-product-margin-scatter');

    if (scatterCanvas && Array.isArray(payload.scatter)) {
        var scatterRows = payload.scatter;
        var scatterChart = new Chart(scatterCanvas, {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'محصولات',
                    data: scatterRows.map(function (row) {
                        return {
                            x: Number(row.x || 0),
                            y: Number(row.y || 0)
                        };
                    }),
                    backgroundColor: scatterRows.map(function (row) {
                        return Number(row.y || 0) < 0 ? '#dc2626' : '#7c3aed';
                    }),
                    borderColor: 'rgba(255,255,255,.85)',
                    borderWidth: 1.5,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'nearest',
                    intersect: true
                },
                onHover: function (event, elements) {
                    if (event && event.native && event.native.target) {
                        event.native.target.style.cursor = elements && elements.length
                            ? 'pointer'
                            : 'default';
                    }
                },
                onClick: function (event, elements) {
                    if (!elements || !elements.length) {
                        return;
                    }

                    var row = scatterRows[elements[0].index] || {};
                    openUrl(row.url || '');
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        rtl: true,
                        textDirection: 'rtl',
                        callbacks: {
                            title: function (items) {
                                var item = items && items.length ? items[0] : null;
                                var row = item ? scatterRows[item.dataIndex] : null;
                                return row && row.name ? row.name : '';
                            },
                            label: function (context) {
                                var row = scatterRows[context.dataIndex] || {};
                                return [
                                    'فروش: ' + formatNumber(row.x),
                                    'Margin: ' + formatPercent(row.y),
                                    'سود: ' + formatNumber(row.profit)
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'فروش'
                        },
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
                        title: {
                            display: true,
                            text: 'Margin %'
                        },
                        ticks: {
                            callback: function (value) {
                                return formatPercent(value);
                            }
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.18)'
                        }
                    }
                }
            }
        });

        scatterChart.update();
    }

    var contributionCanvas = document.getElementById('hashieban-product-contribution-chart');

    if (contributionCanvas && payload.contribution) {
        var contributionValues = payload.contribution.values || [];
        var contributionUrls = payload.contribution.urls || [];
        var contributionChart = new Chart(contributionCanvas, {
            type: 'doughnut',
            data: {
                labels: payload.contribution.labels || [],
                datasets: [{
                    data: contributionValues,
                    backgroundColor: [
                        '#2563eb',
                        '#16a34a',
                        '#7c3aed',
                        '#f59e0b',
                        '#ec4899',
                        '#cbd5e1'
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                onHover: function (event, elements) {
                    if (event && event.native && event.native.target) {
                        event.native.target.style.cursor = elements && elements.length
                            ? 'pointer'
                            : 'default';
                    }
                },
                onClick: function (event, elements) {
                    if (!elements || !elements.length) {
                        return;
                    }

                    openUrl(contributionUrls[elements[0].index] || '');
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        rtl: true,
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 16
                        }
                    },
                    tooltip: {
                        rtl: true,
                        textDirection: 'rtl',
                        callbacks: {
                            label: function (context) {
                                var total = contributionValues.reduce(function (sum, value) {
                                    return sum + Number(value || 0);
                                }, 0);
                                var value = Number(context.raw || 0);
                                var share = total > 0 ? (value / total) * 100 : 0;

                                return context.label + ': ' + formatNumber(value) + ' (' + formatPercent(share) + ')';
                            }
                        }
                    }
                }
            }
        });

        contributionChart.update();
    }
}());
