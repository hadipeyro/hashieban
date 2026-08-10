(function () {
    'use strict';

    var dataNode = document.getElementById('hashieban-data-health-data');

    if (!dataNode || typeof Chart === 'undefined') {
        return;
    }

    var payload;

    try {
        payload = JSON.parse(dataNode.textContent || '{}');
    } catch (error) {
        return;
    }

    Chart.defaults.font.family = 'Tahoma, Arial, sans-serif';
    Chart.defaults.color = '#475569';

    var readinessCanvas = document.getElementById('hashieban-data-health-readiness-chart');

    if (readinessCanvas && payload.readiness) {
        new Chart(readinessCanvas, {
            type: 'bar',
            data: {
                labels: payload.readiness.labels || [],
                datasets: [{
                    label: 'آمادگی داده',
                    data: payload.readiness.values || [],
                    borderWidth: 0,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function (value) {
                                return value + '٪';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return ' ' + Number(context.raw || 0).toLocaleString('fa-IR', {
                                    maximumFractionDigits: 1
                                }) + '٪';
                            }
                        }
                    }
                }
            }
        });
    }

    var ordersCanvas = document.getElementById('hashieban-data-health-orders-chart');

    if (ordersCanvas && payload.orders) {
        new Chart(ordersCanvas, {
            type: 'doughnut',
            data: {
                labels: payload.orders.labels || [],
                datasets: [{
                    data: payload.orders.values || [],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 16
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var value = Number(context.raw || 0);
                                return ' ' + context.label + ': ' + value.toLocaleString('fa-IR') + ' سفارش';
                            }
                        }
                    }
                }
            }
        });
    }
}());
