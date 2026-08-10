(function () {
    'use strict';

    function parsePayload() {
        var node = document.getElementById('hashieban-inventory-chart-data');

        if (!node) {
            return null;
        }

        try {
            return JSON.parse(node.textContent || '{}');
        } catch (error) {
            return null;
        }
    }

    function openProduct(urls, elements) {
        if (!elements || !elements.length) {
            return;
        }

        var index = elements[0].index;
        var url = urls[index] || '';

        if (url) {
            window.location.href = url;
        }
    }

    function baseOptions(horizontal) {
        var options = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        };

        if (horizontal) {
            options.indexAxis = 'y';
        }

        return options;
    }

    function barChart(id, labels, values, urls, tooltipSuffix, horizontal) {
        var canvas = document.getElementById(id);

        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        var options = baseOptions(horizontal);

        options.onClick = function (event, elements) {
            openProduct(urls || [], elements);
        };

        options.plugins.tooltip = {
            callbacks: {
                label: function (context) {
                    return context.formattedValue + (tooltipSuffix || '');
                }
            }
        };

        new window.Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels || [],
                datasets: [{
                    data: values || [],
                    borderWidth: 0,
                    borderRadius: 7
                }]
            },
            options: options
        });
    }

    function init() {
        var payload = parsePayload();

        if (!payload) {
            return;
        }

        barChart(
            'hashieban-inventory-reorder-chart',
            payload.reorder.labels,
            payload.reorder.values,
            payload.reorder.urls,
            ' واحد',
            true
        );

        barChart(
            'hashieban-inventory-velocity-chart',
            payload.velocity.labels,
            payload.velocity.values,
            payload.velocity.urls,
            ' واحد/روز',
            true
        );

        barChart(
            'hashieban-inventory-capital-chart',
            payload.capital.labels,
            payload.capital.values,
            payload.capital.urls,
            ' ' + payload.currencyLabel,
            true
        );

        var statusCanvas = document.getElementById('hashieban-inventory-status-chart');

        if (statusCanvas && typeof window.Chart !== 'undefined') {
            new window.Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: payload.status.labels || [],
                    datasets: [{
                        data: payload.status.values || [],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '66%',
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return context.label + ': '
                                        + context.formattedValue
                                        + ' '
                                        + payload.currencyLabel;
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
        return;
    }

    init();
}());
