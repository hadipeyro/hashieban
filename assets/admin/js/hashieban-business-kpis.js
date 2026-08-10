(function () {
    'use strict';

    if (
        typeof Chart === 'undefined'
        || typeof hashiebanBusinessKpis === 'undefined'
    ) {
        return;
    }

    var data = hashiebanBusinessKpis;

    function number(value) {
        var parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function nullable(value) {
        if (value === null || typeof value === 'undefined' || value === '') {
            return 0;
        }

        return number(value);
    }

    function commonTooltip() {
        return {
            rtl: true,
            textDirection: 'rtl'
        };
    }

    var pulse = document.getElementById('hashieban-business-pulse-chart');

    if (pulse) {
        new Chart(pulse, {
            type: 'radar',
            data: {
                labels: data.pulse.labels || [],
                datasets: [{
                    label: 'امتیاز',
                    data: data.pulse.values || [],
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    backgroundColor: 'rgba(47, 102, 239, .14)',
                    borderColor: '#2f66ef',
                    pointBackgroundColor: '#2f66ef'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        min: 0,
                        max: 100,
                        ticks: {
                            stepSize: 20,
                            display: false
                        },
                        grid: {
                            color: 'rgba(89, 107, 137, .12)'
                        },
                        angleLines: {
                            color: 'rgba(89, 107, 137, .12)'
                        },
                        pointLabels: {
                            color: '#5f6d82',
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: commonTooltip()
                }
            }
        });
    }

    var growth = document.getElementById('hashieban-business-growth-chart');

    if (growth) {
        var growthValues = (data.growth.values || []).map(nullable);

        new Chart(growth, {
            type: 'bar',
            data: {
                labels: data.growth.labels || [],
                datasets: [{
                    label: 'تغییر نسبت به دوره قبل',
                    data: growthValues,
                    borderRadius: 8,
                    backgroundColor: growthValues.map(function (value) {
                        return value < 0
                            ? '#ef4444'
                            : '#2f66ef';
                    })
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        grid: {
                            color: 'rgba(89, 107, 137, .08)'
                        },
                        ticks: {
                            callback: function (value) {
                                return value + '٪';
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
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
                                return number(context.raw).toLocaleString('fa-IR', {
                                    maximumFractionDigits: 1
                                }) + '٪';
                            }
                        }
                    }
                }
            }
        });
    }

    var costs = document.getElementById('hashieban-business-cost-chart');

    if (costs) {
        new Chart(costs, {
            type: 'doughnut',
            data: {
                labels: data.costs.labels || [],
                datasets: [{
                    data: (data.costs.values || []).map(number),
                    backgroundColor: [
                        '#2f66ef',
                        '#8b5cf6',
                        '#f59e0b',
                        '#14b8a6'
                    ],
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '66%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        rtl: true,
                        labels: {
                            boxWidth: 10,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 18
                        }
                    },
                    tooltip: {
                        rtl: true,
                        textDirection: 'rtl',
                        callbacks: {
                            label: function (context) {
                                return context.label + ': '
                                    + number(context.raw).toLocaleString('fa-IR')
                                    + ' '
                                    + (data.currencyLabel || '');
                            }
                        }
                    }
                }
            }
        });
    }
})();
