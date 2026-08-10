(function () {
    'use strict';

    var dataNode = document.getElementById('hashieban-time-intelligence-data');

    if (!dataNode || typeof Chart === 'undefined') {
        return;
    }

    var data;

    try {
        data = JSON.parse(dataNode.textContent || '{}');
    } catch (error) {
        return;
    }

    var formatNumber = new Intl.NumberFormat('fa-IR', {
        maximumFractionDigits: 1
    });

    function money(value) {
        return formatNumber.format(Number(value || 0)) + ' ' + (data.currencyLabel || '');
    }

    function commonOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 18
                    }
                },
                tooltip: {
                    rtl: true,
                    textDirection: 'rtl',
                    callbacks: {
                        label: function (context) {
                            return context.dataset.label + ': ' + money(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 14
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(148, 163, 184, .16)'
                    },
                    ticks: {
                        callback: function (value) {
                            return formatNumber.format(value);
                        }
                    }
                }
            }
        };
    }

    var trendCanvas = document.getElementById('hashieban-time-trend-chart');
    if (trendCanvas && data.timeline) {
        new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: data.timeline.labels || [],
                datasets: [
                    {
                        label: 'فروش',
                        data: data.timeline.revenue || [],
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, .10)',
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        borderWidth: 2.5,
                        fill: true,
                        tension: .32
                    },
                    {
                        label: 'سود خالص',
                        data: data.timeline.profit || [],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, .05)',
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        borderWidth: 2.5,
                        fill: false,
                        tension: .32
                    }
                ]
            },
            options: commonOptions()
        });
    }

    var comparisonCanvas = document.getElementById('hashieban-time-comparison-chart');
    if (comparisonCanvas && data.comparison) {
        new Chart(comparisonCanvas, {
            type: 'bar',
            data: {
                labels: data.comparison.labels || [],
                datasets: [
                    {
                        label: 'دوره فعلی',
                        data: data.comparison.current || [],
                        backgroundColor: '#2563eb',
                        borderRadius: 8
                    },
                    {
                        label: 'دوره قبل',
                        data: data.comparison.previous || [],
                        backgroundColor: '#cbd5e1',
                        borderRadius: 8
                    }
                ]
            },
            options: commonOptions()
        });
    }

    var weekdayCanvas = document.getElementById('hashieban-time-weekday-chart');
    if (weekdayCanvas && data.weekday) {
        new Chart(weekdayCanvas, {
            type: 'bar',
            data: {
                labels: data.weekday.labels || [],
                datasets: [
                    {
                        label: 'میانگین فروش روزانه',
                        data: data.weekday.revenue || [],
                        backgroundColor: '#8b5cf6',
                        borderRadius: 8
                    },
                    {
                        label: 'میانگین سود روزانه',
                        data: data.weekday.profit || [],
                        backgroundColor: '#10b981',
                        borderRadius: 8
                    }
                ]
            },
            options: commonOptions()
        });
    }

    var seasonalityCanvas = document.getElementById('hashieban-time-seasonality-chart');
    if (seasonalityCanvas && data.seasonality) {
        new Chart(seasonalityCanvas, {
            type: 'line',
            data: {
                labels: data.seasonality.labels || [],
                datasets: [
                    {
                        label: 'میانگین فروش ماه',
                        data: data.seasonality.revenue || [],
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14, 165, 233, .08)',
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true,
                        tension: .3
                    },
                    {
                        label: 'میانگین سود ماه',
                        data: data.seasonality.profit || [],
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, .05)',
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false,
                        tension: .3
                    }
                ]
            },
            options: commonOptions()
        });
    }
}());
