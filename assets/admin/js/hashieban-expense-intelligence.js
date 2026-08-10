(function () {
    'use strict';

    var node = document.getElementById('hashieban-expense-intelligence-data');

    if (!node || typeof Chart === 'undefined') {
        return;
    }

    var data;

    try {
        data = JSON.parse(node.textContent || '{}');
    } catch (error) {
        return;
    }

    var unit = data.currencyLabel || '';
    var grid = 'rgba(148, 163, 184, 0.18)';
    var text = '#334155';

    function money(value) {
        return new Intl.NumberFormat('fa-IR', {
            maximumFractionDigits: 2
        }).format(Number(value || 0)) + ' ' + unit;
    }

    function commonScales(beginAtZero) {
        return {
            x: {
                grid: { color: grid },
                ticks: { color: text }
            },
            y: {
                beginAtZero: beginAtZero !== false,
                grid: { color: grid },
                ticks: {
                    color: text,
                    callback: function (value) {
                        return new Intl.NumberFormat('fa-IR', {
                            notation: 'compact',
                            maximumFractionDigits: 1
                        }).format(value);
                    }
                }
            }
        };
    }

    var categoryCanvas = document.getElementById('hashieban-expense-category-chart');
    if (categoryCanvas && data.categories) {
        new Chart(categoryCanvas, {
            type: 'doughnut',
            data: {
                labels: data.categories.labels || [],
                datasets: [{
                    data: data.categories.values || [],
                    backgroundColor: data.categories.colors || [],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '66%',
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, color: text } },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var values = context.dataset.data || [];
                                var total = values.reduce(function (sum, value) { return sum + Number(value || 0); }, 0);
                                var value = Number(context.raw || 0);
                                var share = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                return context.label + ': ' + money(value) + ' (' + share + '٪)';
                            }
                        }
                    }
                }
            }
        });
    }

    var trendCanvas = document.getElementById('hashieban-expense-trend-chart');
    if (trendCanvas && data.trend) {
        new Chart(trendCanvas, {
            data: {
                labels: data.trend.labels || [],
                datasets: [
                    {
                        type: 'bar',
                        label: 'هزینه عملیاتی',
                        data: data.trend.expenses || [],
                        borderWidth: 1,
                        borderRadius: 8
                    },
                    {
                        type: 'line',
                        label: 'فروش',
                        data: data.trend.revenue || [],
                        tension: 0.35,
                        pointRadius: 3,
                        borderWidth: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: commonScales(true),
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, color: text } },
                    tooltip: { callbacks: { label: function (context) { return context.dataset.label + ': ' + money(context.raw); } } }
                }
            }
        });
    }

    var budgetCanvas = document.getElementById('hashieban-expense-budget-chart');
    if (budgetCanvas && data.budget) {
        new Chart(budgetCanvas, {
            type: 'bar',
            data: {
                labels: data.budget.labels || [],
                datasets: [
                    { label: 'هزینه واقعی', data: data.budget.actual || [], borderRadius: 7 },
                    { label: 'بودجه بازه', data: data.budget.target || [], borderRadius: 7 }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: commonScales(true).y,
                    y: { grid: { display: false }, ticks: { color: text } }
                },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, color: text } },
                    tooltip: { callbacks: { label: function (context) { return context.dataset.label + ': ' + money(context.raw); } } }
                }
            }
        });
    }

    var structureCanvas = document.getElementById('hashieban-expense-structure-chart');
    if (structureCanvas && data.structure) {
        new Chart(structureCanvas, {
            type: 'polarArea',
            data: {
                labels: data.structure.labels || [],
                datasets: [{ data: data.structure.values || [] }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { r: { beginAtZero: true, grid: { color: grid }, ticks: { display: false } } },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, color: text } },
                    tooltip: { callbacks: { label: function (context) { return context.label + ': ' + money(context.raw); } } }
                }
            }
        });
    }
}());
