document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const dataNode = document.getElementById(
        'hashieban-dashboard-data'
    );

    const trendCanvas = document.getElementById(
        'hashieban-trend-chart'
    );

    const compositionCanvas = document.getElementById(
        'hashieban-composition-chart'
    );

    const summaryCanvas = document.getElementById(
        'hashieban-summary-chart'
    );

    if (!dataNode) {
        console.error(
            'Hashieban: dashboard data was not found.'
        );

        return;
    }

    if (typeof window.Chart === 'undefined') {
        console.error(
            'Hashieban: Chart.js was not loaded.'
        );

        return;
    }

    if (
        !trendCanvas
        || !compositionCanvas
        || !summaryCanvas
    ) {
        console.error(
            'Hashieban: chart canvas elements were not found.'
        );

        return;
    }

    let payload;

    try {
        payload = JSON.parse(
            dataNode.textContent || '{}'
        );
    } catch (error) {
        console.error(
            'Hashieban: invalid dashboard JSON.',
            error
        );

        return;
    }

    const currencyLabel =
        payload.currencyLabel || '';

    const trend =
        payload.trend || {};

    const composition =
        payload.composition || {};

    const summary =
        payload.summary || {};

    const trendLabels =
        Array.isArray(trend.labels)
            ? trend.labels
            : [];

    const trendRevenue =
        Array.isArray(trend.revenue)
            ? trend.revenue
            : [];

    const trendProfit =
        Array.isArray(trend.profit)
            ? trend.profit
            : [];

    const trendExpenses =
        Array.isArray(trend.expenses)
            ? trend.expenses
            : [];

    const compositionLabels =
        Array.isArray(composition.labels)
            ? composition.labels
            : [];

    const compositionValues =
        Array.isArray(composition.values)
            ? composition.values
            : [];

    const summaryLabels =
        Array.isArray(summary.labels)
            ? summary.labels
            : [];

    const summaryValues =
        Array.isArray(summary.values)
            ? summary.values
            : [];

    const formatter =
        new Intl.NumberFormat(
            'fa-IR',
            {
                maximumFractionDigits: 2
            }
        );

    function formatMoney(value) {
        const number = Number(value);

        if (!Number.isFinite(number)) {
            return '۰ ' + currencyLabel;
        }

        return (
            formatter.format(number)
            + ' '
            + currencyLabel
        );
    }

    const palette = {
        revenue: '#2563eb',
        revenueSoft: 'rgba(37, 99, 235, 0.18)',

        profit: '#16a34a',
        profitSoft: 'rgba(22, 163, 74, 0.18)',

        expenses: '#ef4444',
        expensesSoft: 'rgba(239, 68, 68, 0.18)',

        purple: '#8b5cf6',
        amber: '#f59e0b',
        cyan: '#06b6d4',
        pink: '#ec4899'
    };

    let currentSeries = 'revenue';
    let currentTrendType = 'bar';
    let currentCompositionType = 'doughnut';

    function getTrendSeries() {
        switch (currentSeries) {
            case 'profit':
                return {
                    label: 'سود خالص',
                    data: trendProfit,
                    color: palette.profit,
                    softColor: palette.profitSoft
                };

            case 'expenses':
                return {
                    label: 'هزینه‌ها',
                    data: trendExpenses,
                    color: palette.expenses,
                    softColor: palette.expensesSoft
                };

            case 'revenue':
            default:
                return {
                    label: 'فروش',
                    data: trendRevenue,
                    color: palette.revenue,
                    softColor: palette.revenueSoft
                };
        }
    }

    function makeTrendDataset() {
        const series = getTrendSeries();

        const area =
            currentTrendType === 'area';

        const bar =
            currentTrendType === 'bar';

        return {
            label: series.label,

            data: series.data,

            borderColor:
                series.color,

            backgroundColor:
                area
                    ? series.softColor
                    : series.color,

            fill:
                area,

            tension:
                0.35,

            borderWidth:
                3,

            pointRadius:
                bar ? 0 : 3,

            pointHoverRadius:
                bar ? 0 : 6,

            pointBackgroundColor:
                series.color,

            pointBorderColor:
                '#ffffff',

            pointBorderWidth:
                2,

            borderRadius:
                bar ? 8 : 0,

            maxBarThickness:
                44
        };
    }

    const commonTooltip = {
        rtl: true,

        titleAlign: 'right',

        bodyAlign: 'right',

        displayColors: true,

        callbacks: {
            label: function (context) {
                const label =
                    context.dataset.label
                    ? context.dataset.label + ': '
                    : '';

                const value =
                    context.parsed.y
                    !== undefined
                        ? context.parsed.y
                        : context.raw;

                return label
                    + formatMoney(value);
            }
        }
    };

    const trendChart =
        new window.Chart(
            trendCanvas,
            {
                type: 'bar',

                data: {
                    labels:
                        trendLabels,

                    datasets: [
                        makeTrendDataset()
                    ]
                },

                options: {
                    responsive: true,

                    maintainAspectRatio: false,

                    animation: {
                        duration: 650
                    },

                    interaction: {
                        mode: 'index',
                        intersect: false
                    },

                    plugins: {
                        legend: {
                            display: false
                        },

                        tooltip:
                            commonTooltip
                    },

                    scales: {
                        x: {
                            grid: {
                                display: false
                            },

                            ticks: {
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 12
                            }
                        },

                        y: {
                            beginAtZero: true,

                            grid: {
                                color:
                                    'rgba(148, 163, 184, 0.15)'
                            },

                            ticks: {
                                callback:
                                    function (value) {
                                        return formatter.format(
                                            value
                                        );
                                    }
                            }
                        }
                    }
                }
            }
        );

    const compositionChart =
        new window.Chart(
            compositionCanvas,
            {
                type: 'doughnut',

                data: {
                    labels:
                        compositionLabels,

                    datasets: [
                        {
                            data:
                                compositionValues,

                            backgroundColor: [
                                palette.revenue,
                                palette.amber,
                                palette.purple,
                                palette.pink
                            ],

                            borderColor:
                                '#ffffff',

                            borderWidth:
                                3,

                            hoverOffset:
                                12
                        }
                    ]
                },

                options: {
                    responsive: true,

                    maintainAspectRatio: false,

                    cutout: '62%',

                    animation: {
                        duration: 700
                    },

                    plugins: {
                        legend: {
                            position: 'bottom',

                            rtl: true,

                            labels: {
                                usePointStyle: true,
                                padding: 18
                            }
                        },

                        tooltip: {
                            rtl: true,

                            callbacks: {
                                label:
                                    function (context) {
                                        return (
                                            context.label
                                            + ': '
                                            + formatMoney(
                                                context.raw
                                            )
                                        );
                                    }
                            }
                        }
                    }
                }
            }
        );

    const summaryChart =
        new window.Chart(
            summaryCanvas,
            {
                type: 'bar',

                data: {
                    labels:
                        summaryLabels,

                    datasets: [
                        {
                            label:
                                'وضعیت مالی',

                            data:
                                summaryValues,

                            backgroundColor: [
                                palette.revenue,
                                palette.expenses,
                                palette.profit
                            ],

                            borderRadius:
                                10,

                            maxBarThickness:
                                70
                        }
                    ]
                },

                options: {
                    responsive: true,

                    maintainAspectRatio: false,

                    animation: {
                        duration: 700
                    },

                    plugins: {
                        legend: {
                            display: false
                        },

                        tooltip: {
                            rtl: true,

                            callbacks: {
                                label:
                                    function (context) {
                                        return formatMoney(
                                            context.raw
                                        );
                                    }
                            }
                        }
                    },

                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },

                        y: {
                            beginAtZero: true,

                            grid: {
                                color:
                                    'rgba(148, 163, 184, 0.15)'
                            },

                            ticks: {
                                callback:
                                    function (value) {
                                        return formatter.format(
                                            value
                                        );
                                    }
                            }
                        }
                    }
                }
            }
        );

    function updateTrendChart() {
        const chartType =
            currentTrendType === 'area'
                ? 'line'
                : currentTrendType;

        trendChart.config.type =
            chartType;

        trendChart.data.datasets = [
            makeTrendDataset()
        ];

        trendChart.update();
    }

    function rebuildCompositionChart() {
        compositionChart.config.type =
            currentCompositionType;

        if (
            currentCompositionType
            === 'doughnut'
        ) {
            compositionChart.options.cutout =
                '62%';
        } else {
            compositionChart.options.cutout =
                0;
        }

        compositionChart.update();
    }

    function activateButton(
        container,
        button
    ) {
        container
            .querySelectorAll('button')
            .forEach(
                function (item) {
                    item.classList.remove(
                        'is-active'
                    );
                }
            );

        button.classList.add(
            'is-active'
        );
    }

    const seriesSwitcher =
        document.getElementById(
            'hb-series-switcher'
        );

    const trendSwitcher =
        document.getElementById(
            'hb-trend-type-switcher'
        );

    const compositionSwitcher =
        document.getElementById(
            'hb-composition-type-switcher'
        );

    if (seriesSwitcher) {
        seriesSwitcher.addEventListener(
            'click',
            function (event) {
                const button =
                    event.target.closest(
                        'button[data-series]'
                    );

                if (!button) {
                    return;
                }

                currentSeries =
                    button.dataset.series;

                activateButton(
                    seriesSwitcher,
                    button
                );

                updateTrendChart();
            }
        );
    }

    if (trendSwitcher) {
        trendSwitcher.addEventListener(
            'click',
            function (event) {
                const button =
                    event.target.closest(
                        'button[data-type]'
                    );

                if (!button) {
                    return;
                }

                currentTrendType =
                    button.dataset.type;

                activateButton(
                    trendSwitcher,
                    button
                );

                updateTrendChart();
            }
        );
    }

    if (compositionSwitcher) {
        compositionSwitcher.addEventListener(
            'click',
            function (event) {
                const button =
                    event.target.closest(
                        'button[data-type]'
                    );

                if (!button) {
                    return;
                }

                currentCompositionType =
                    button.dataset.type;

                activateButton(
                    compositionSwitcher,
                    button
                );

                rebuildCompositionChart();
            }
        );
    }

    console.info(
        'Hashieban charts loaded successfully.'
    );
});
