(function () {
    'use strict';

    var dataNode = document.getElementById('hashieban-geo-data');
    var map = document.getElementById('hashieban-iran-map');

    if (!dataNode || !map) {
        return;
    }

    var payload;
    try {
        payload = JSON.parse(dataNode.textContent || '{}');
    } catch (error) {
        return;
    }

    var provinces = Array.isArray(payload.provinces) ? payload.provinces : [];
    var cities = Array.isArray(payload.cities) ? payload.cities : [];
    var currencyLabel = payload.currencyLabel || '';
    var currentMetric = 'revenue';
    var selectedProvince = null;
    var cityChart = null;
    var compareChart = null;
    var tooltip = document.getElementById('hb-geo-tooltip');

    var provinceLabels = {
        'West Azarbaijan': 'آذربایجان غربی',
        'East Azarbaijan': 'آذربایجان شرقی',
        'Ardebil': 'اردبیل',
        'Gilan': 'گیلان',
        'Kordestan': 'کردستان',
        'Kermanshah': 'کرمانشاه',
        'Ilam': 'ایلام',
        'Khuzestan': 'خوزستان',
        'North Khorasan': 'خراسان شمالی',
        'Golestan': 'گلستان',
        'Razavi Khorasan': 'خراسان رضوی',
        'South Khorasan': 'خراسان جنوبی',
        'Sistan and Baluchestan': 'سیستان و بلوچستان',
        'Bushehr': 'بوشهر',
        'Hormozgan': 'هرمزگان',
        'Mazandaran': 'مازندران',
        'Semnan': 'سمنان',
        'Zanjan': 'زنجان',
        'Qazvin': 'قزوین',
        'Markazi': 'مرکزی',
        'Esfahan': 'اصفهان',
        'Chahar Mahall and Bakhtiari': 'چهارمحال و بختیاری',
        'Kohgiluyeh and Buyer Ahmad': 'کهگیلویه و بویراحمد',
        'Fars': 'فارس',
        'Kerman': 'کرمان',
        'Hamadan': 'همدان',
        'Lorestan': 'لرستان',
        'Yazd': 'یزد',
        'Qom': 'قم',
        'Tehran': 'تهران',
        'Alborz': 'البرز'
    };

    var metricDefinitions = {
        revenue: {
            label: 'فروش',
            shareLabel: 'سهم از فروش',
            shareKey: 'salesShare',
            value: function (row) { return number(row.revenue); },
            format: money
        },
        profit: {
            label: 'سود',
            shareLabel: 'سهم از سود',
            shareKey: 'profitShare',
            value: function (row) { return number(row.profit); },
            format: money
        },
        orders: {
            label: 'تعداد سفارش',
            shareLabel: 'سهم از سفارش‌ها',
            shareKey: 'orderShare',
            value: function (row) { return number(row.orders); },
            format: integer
        },
        customers: {
            label: 'تعداد مشتری',
            shareLabel: 'سهم از مشتریان',
            shareKey: 'customerShare',
            value: function (row) { return number(row.customers); },
            format: integer
        },
        margin: {
            label: 'حاشیه سود',
            shareLabel: 'درصد سود',
            shareKey: null,
            value: function (row) { return row.margin === null ? 0 : number(row.margin); },
            format: percent
        }
    };

    var provinceByMapName = {};
    var provinceByName = {};
    var provinceByCanonicalLabel = {};

    function normalizeProvinceLabel(value) {
        var label = String(value || '').trim();
        var match = label.match(/\(([^()]*)\)/);
        if (match && /[\u0600-\u06ff]/.test(match[1])) {
            label = match[1];
        }

        return label
            .replace(/^استان\s+/, '')
            .replace(/ي/g, 'ی')
            .replace(/ى/g, 'ی')
            .replace(/ك/g, 'ک')
            .replace(/\s+/g, ' ')
            .trim();
    }

    provinces.forEach(function (row) {
        if (row.mapName) {
            provinceByMapName[row.mapName] = row;
        }
        provinceByName[row.name] = row;
        provinceByCanonicalLabel[normalizeProvinceLabel(row.name)] = row;
    });

    function rowForMapName(mapName) {
        if (provinceByMapName[mapName]) {
            return provinceByMapName[mapName];
        }

        var persianLabel = provinceLabels[mapName] || '';
        if (persianLabel && provinceByCanonicalLabel[persianLabel]) {
            return provinceByCanonicalLabel[persianLabel];
        }

        if (provinceByCanonicalLabel[normalizeProvinceLabel(mapName)]) {
            return provinceByCanonicalLabel[normalizeProvinceLabel(mapName)];
        }

        return null;
    }

    function number(value) {
        var parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function faNumber(value, maxDecimals) {
        return new Intl.NumberFormat('fa-IR', {
            maximumFractionDigits: typeof maxDecimals === 'number' ? maxDecimals : 0
        }).format(number(value));
    }

    function money(value) {
        var decimals = Math.abs(number(value) - Math.round(number(value))) > 0.000001 ? 1 : 0;
        return faNumber(value, decimals) + (currencyLabel ? ' ' + currencyLabel : '');
    }

    function integer(value) {
        return faNumber(Math.round(number(value)), 0);
    }

    function percent(value) {
        return faNumber(value, 1) + '٪';
    }

    function escapeHtml(value) {
        return String(value === null || typeof value === 'undefined' ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeHex(hex) {
        var value = String(hex || '').replace('#', '');
        if (value.length === 3) {
            value = value.split('').map(function (char) { return char + char; }).join('');
        }
        return value.length === 6 ? value : '2563eb';
    }

    function mixColor(from, to, ratio) {
        var a = normalizeHex(from);
        var b = normalizeHex(to);
        var r = Math.max(0, Math.min(1, ratio));
        var out = [0, 2, 4].map(function (index) {
            var start = parseInt(a.substr(index, 2), 16);
            var end = parseInt(b.substr(index, 2), 16);
            return Math.round(start + (end - start) * r).toString(16).padStart(2, '0');
        }).join('');
        return '#' + out;
    }

    function metricRows(rows) {
        var definition = metricDefinitions[currentMetric];
        return rows.slice().sort(function (a, b) {
            return definition.value(b) - definition.value(a);
        });
    }

    function paintMap() {
        var definition = metricDefinitions[currentMetric];
        var values = provinces.map(definition.value).filter(function (value) { return Number.isFinite(value); });
        var positiveValues = values.filter(function (value) { return value > 0; });
        var max = positiveValues.length ? Math.max.apply(null, positiveValues) : 0;
        var min = positiveValues.length ? Math.min.apply(null, positiveValues) : 0;
        var paths = map.querySelectorAll('.hb-geo-province');

        paths.forEach(function (path) {
            var mapName = path.getAttribute('name') || '';
            var row = rowForMapName(mapName);
            path.classList.remove('is-selected', 'is-no-data');

            if (!row) {
                path.style.fill = '#f1f5f9';
                path.classList.add('is-no-data');
            } else {
                var value = definition.value(row);
                if (currentMetric === 'profit' || currentMetric === 'margin') {
                    if (value < 0) {
                        var negativeRatio = Math.min(1, Math.abs(value) / Math.max(Math.abs(value), max || 1));
                        path.style.fill = mixColor('#fee2e2', '#dc2626', .35 + negativeRatio * .65);
                    } else {
                        var profitRatio = max > min ? (value - min) / (max - min) : (value > 0 ? 1 : 0);
                        path.style.fill = value > 0 ? mixColor('#dbeafe', '#1e3a8a', .2 + profitRatio * .8) : '#f1f5f9';
                    }
                } else {
                    var ratio = max > min ? (value - min) / (max - min) : (value > 0 ? 1 : 0);
                    path.style.fill = value > 0 ? mixColor('#dbeafe', '#1e3a8a', .2 + ratio * .8) : '#f1f5f9';
                }
            }

            if (selectedProvince && row && row.name === selectedProvince) {
                path.classList.add('is-selected');
            }
        });

        var maxLabel = document.getElementById('hb-geo-legend-max');
        var minLabel = document.getElementById('hb-geo-legend-min');
        if (maxLabel) {
            maxLabel.textContent = max > 0 ? 'بیشترین: ' + definition.format(max) : 'بیشترین';
        }
        if (minLabel) {
            minLabel.textContent = min > 0 ? 'کمترین: ' + definition.format(min) : 'کمترین';
        }
    }

    function tooltipHtml(row, mapName) {
        var definition = metricDefinitions[currentMetric];
        var label = row ? row.name : (provinceLabels[mapName] || mapName || 'استان');

        if (!row) {
            return '<strong>' + escapeHtml(label) + '</strong><span>داده کافی در این بازه وجود ندارد.</span>';
        }

        var share = definition.shareKey ? row[definition.shareKey] : row.margin;
        return '' +
            '<strong>' + escapeHtml(label) + '</strong>' +
            '<span><em>' + escapeHtml(definition.label) + '</em><b>' + escapeHtml(definition.format(definition.value(row))) + '</b></span>' +
            '<span><em>' + escapeHtml(definition.shareLabel) + '</em><b>' + escapeHtml(share === null || typeof share === 'undefined' ? '—' : percent(share)) + '</b></span>' +
            '<span><em>سفارش</em><b>' + escapeHtml(integer(row.orders)) + '</b></span>' +
            '<span><em>مشتری</em><b>' + escapeHtml(integer(row.customers)) + '</b></span>';
    }

    function showTooltip(event, path) {
        if (!tooltip) {
            return;
        }
        var mapName = path.getAttribute('name') || '';
        tooltip.innerHTML = tooltipHtml(rowForMapName(mapName), mapName);
        tooltip.hidden = false;
        moveTooltip(event);
    }

    function moveTooltip(event) {
        if (!tooltip || tooltip.hidden) {
            return;
        }
        var x = event.clientX + 14;
        var y = event.clientY + 14;
        var width = tooltip.offsetWidth || 220;
        var height = tooltip.offsetHeight || 120;
        if (x + width > window.innerWidth - 10) {
            x = event.clientX - width - 14;
        }
        if (y + height > window.innerHeight - 10) {
            y = event.clientY - height - 14;
        }
        tooltip.style.left = Math.max(10, x) + 'px';
        tooltip.style.top = Math.max(10, y) + 'px';
    }

    function hideTooltip() {
        if (tooltip) {
            tooltip.hidden = true;
        }
    }

    function updateProvinceRanking() {
        var root = document.getElementById('hb-geo-province-ranking');
        var caption = document.getElementById('hb-geo-ranking-caption');
        if (!root) {
            return;
        }

        var definition = metricDefinitions[currentMetric];
        var rows = metricRows(provinces).slice(0, 10);
        var max = rows.length ? Math.max.apply(null, rows.map(definition.value).map(Math.abs)) : 0;

        if (caption) {
            caption.textContent = currentMetric === 'margin'
                ? 'بر اساس حاشیه سود سفارش‌های منطقه‌ای'
                : 'بر اساس ' + definition.label + ' و سهم هر استان';
        }

        if (!rows.length) {
            root.innerHTML = '<div class="hb-geo-empty">هنوز داده استان برای رتبه‌بندی وجود ندارد.</div>';
            return;
        }

        root.innerHTML = rows.map(function (row, index) {
            var value = definition.value(row);
            var bar = max > 0 ? Math.max(4, Math.abs(value) / max * 100) : 0;
            var share = definition.shareKey ? row[definition.shareKey] : row.margin;
            var valueText = currentMetric === 'margin'
                ? definition.format(value)
                : (share === null || typeof share === 'undefined' ? definition.format(value) : percent(share));

            return '<div class="hb-geo-rank-row" data-province="' + escapeHtml(row.name) + '">' +
                '<span class="hb-geo-rank-row__number">' + faNumber(index + 1, 0) + '</span>' +
                '<div class="hb-geo-rank-row__name"><strong>' + escapeHtml(row.name) + '</strong>' +
                '<div class="hb-geo-rank-row__bar"><span style="width:' + bar.toFixed(1) + '%"></span></div></div>' +
                '<span class="hb-geo-rank-row__value">' + escapeHtml(valueText) + '</span>' +
                '</div>';
        }).join('');

        root.querySelectorAll('[data-province]').forEach(function (element) {
            element.addEventListener('click', function () {
                selectProvince(element.getAttribute('data-province'));
            });
        });
    }

    function miniInsight(label, row, fallback, kind) {
        if (!row || !row.name) {
            return '<div class="hb-geo-insight"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(fallback) + '</strong><small>داده کافی وجود ندارد</small></div>';
        }
        var detail = '';
        if (kind === 'product' && row.quantity) {
            detail = integer(row.quantity) + ' عدد • ' + money(row.revenue);
        } else if (row.orders) {
            detail = integer(row.orders) + ' سفارش • ' + money(row.revenue);
        } else {
            detail = money(row.revenue);
        }
        var title = row.url
            ? '<a href="' + escapeHtml(row.url) + '">' + escapeHtml(row.name) + '</a>'
            : escapeHtml(row.name);
        return '<div class="hb-geo-insight"><span>' + escapeHtml(label) + '</span><strong>' + title + '</strong><small>' + escapeHtml(detail) + '</small></div>';
    }

    function updateSelectedPanel(row) {
        var title = document.getElementById('hb-geo-selected-title');
        var subtitle = document.getElementById('hb-geo-selected-subtitle');
        var metrics = document.getElementById('hb-geo-selected-metrics');
        var insights = document.getElementById('hb-geo-selected-insights');

        if (!title || !metrics || !insights) {
            return;
        }

        if (!row) {
            title.textContent = 'کل ایران';
            if (subtitle) {
                subtitle.textContent = 'برای دیدن جزئیات، روی یک استان در نقشه یا رتبه‌بندی کلیک کن.';
            }
            metrics.innerHTML = '';
            insights.innerHTML = '';
            return;
        }

        title.textContent = row.name;
        if (subtitle) {
            subtitle.textContent = 'نمای منطقه‌ای ' + row.name + ' با سهم فروش، سود، مشتری و شهرهای برتر.';
        }

        var metricRows = [
            ['فروش', money(row.revenue)],
            ['سود', money(row.profit)],
            ['سفارش', integer(row.orders)],
            ['مشتری', integer(row.customers)],
            ['درصد سود', row.margin === null ? '—' : percent(row.margin)],
            ['میانگین سفارش', money(row.averageOrder)]
        ];

        metrics.innerHTML = metricRows.map(function (item) {
            return '<div class="hb-geo-selected-metric"><span>' + escapeHtml(item[0]) + '</span><strong>' + escapeHtml(item[1]) + '</strong></div>';
        }).join('');

        insights.innerHTML = '' +
            miniInsight('شهر اول استان', row.topCity, '—', 'city') +
            miniInsight('محصول اول استان', row.topProduct, '—', 'product') +
            miniInsight('مشتری اول استان', row.topCustomer, '—', 'customer');
    }

    function selectedCities() {
        var rows = selectedProvince
            ? cities.filter(function (row) { return row.province === selectedProvince; })
            : cities.slice();
        return metricRows(rows);
    }

    function updateCityRanking(rows) {
        var root = document.getElementById('hb-geo-city-ranking');
        if (!root) {
            return;
        }
        var definition = metricDefinitions[currentMetric];
        var top = rows.slice(0, 8);
        if (!top.length) {
            root.innerHTML = '<div class="hb-geo-empty">برای این محدوده هنوز داده شهری کافی نیست.</div>';
            return;
        }
        root.innerHTML = top.map(function (row) {
            var share = definition.shareKey ? row[definition.shareKey] : row.margin;
            var value = currentMetric === 'margin'
                ? definition.format(definition.value(row))
                : (share === null || typeof share === 'undefined' ? definition.format(definition.value(row)) : percent(share));
            return '<div class="hb-geo-city-pill"><div><strong>' + escapeHtml(row.name) + '</strong><small>' + escapeHtml(row.province) + '</small></div><b>' + escapeHtml(value) + '</b></div>';
        }).join('');
    }

    function updateCityChart() {
        var canvas = document.getElementById('hashieban-geo-city-chart');
        var caption = document.getElementById('hb-geo-city-caption');
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        var rows = selectedCities().slice(0, 10);
        var definition = metricDefinitions[currentMetric];
        if (caption) {
            caption.textContent = selectedProvince
                ? 'شهرهای برتر استان ' + selectedProvince + ' بر اساس ' + definition.label
                : 'شهرهای برتر ایران بر اساس ' + definition.label;
        }

        if (cityChart) {
            cityChart.destroy();
        }

        cityChart = new window.Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map(function (row) { return row.name; }),
                datasets: [{
                    label: definition.label,
                    data: rows.map(definition.value),
                    backgroundColor: '#2563eb',
                    borderRadius: 7,
                    borderSkipped: false,
                    maxBarThickness: 22
                }]
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        rtl: true,
                        textDirection: 'rtl',
                        callbacks: {
                            label: function (context) {
                                var row = rows[context.dataIndex];
                                var share = definition.shareKey ? row[definition.shareKey] : row.margin;
                                var extra = share === null || typeof share === 'undefined' ? '' : ' • ' + percent(share);
                                return definition.label + ': ' + definition.format(context.raw) + extra;
                            },
                            afterLabel: function (context) {
                                var row = rows[context.dataIndex];
                                return 'استان: ' + row.province + ' | سفارش: ' + integer(row.orders);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: currentMetric !== 'profit' && currentMetric !== 'margin',
                        grid: { color: '#eef2f7' },
                        ticks: {
                            callback: function (value) {
                                return currentMetric === 'revenue' || currentMetric === 'profit'
                                    ? compactNumber(value)
                                    : (currentMetric === 'margin' ? value + '٪' : faNumber(value, 0));
                            }
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: '#475569' }
                    }
                }
            }
        });

        updateCityRanking(rows);
    }

    function compactNumber(value) {
        var n = number(value);
        var abs = Math.abs(n);
        if (abs >= 1000000000) {
            return faNumber(n / 1000000000, 1) + 'B';
        }
        if (abs >= 1000000) {
            return faNumber(n / 1000000, 1) + 'M';
        }
        if (abs >= 1000) {
            return faNumber(n / 1000, 1) + 'K';
        }
        return faNumber(n, 0);
    }

    function createCompareChart() {
        var canvas = document.getElementById('hashieban-geo-compare-chart');
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        var rows = provinces.slice().sort(function (a, b) { return number(b.revenue) - number(a.revenue); }).slice(0, 8);

        compareChart = new window.Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map(function (row) { return row.name; }),
                datasets: [
                    {
                        label: 'فروش',
                        data: rows.map(function (row) { return number(row.revenue); }),
                        backgroundColor: '#2563eb',
                        borderRadius: 6,
                        borderSkipped: false
                    },
                    {
                        label: 'سود',
                        data: rows.map(function (row) { return number(row.profit); }),
                        backgroundColor: '#10b981',
                        borderRadius: 6,
                        borderSkipped: false
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        rtl: true,
                        labels: { usePointStyle: true, boxWidth: 8 }
                    },
                    tooltip: {
                        rtl: true,
                        textDirection: 'rtl',
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ' + money(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#64748b' } },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#eef2f7' },
                        ticks: { callback: compactNumber }
                    }
                }
            }
        });
    }

    function selectProvince(name) {
        var row = provinceByName[name] || null;
        selectedProvince = row ? row.name : null;
        updateSelectedPanel(row);
        paintMap();
        updateCityChart();
    }

    map.querySelectorAll('.hb-geo-province').forEach(function (path) {
        path.addEventListener('mouseenter', function (event) { showTooltip(event, path); });
        path.addEventListener('mousemove', moveTooltip);
        path.addEventListener('mouseleave', hideTooltip);
        path.addEventListener('click', function () {
            var mapName = path.getAttribute('name') || '';
            var row = rowForMapName(mapName);
            if (row) {
                selectProvince(row.name);
            }
        });
    });

    document.querySelectorAll('.hb-geo-metric-switch [data-metric]').forEach(function (button) {
        button.addEventListener('click', function () {
            var metric = button.getAttribute('data-metric');
            if (!metricDefinitions[metric]) {
                return;
            }
            currentMetric = metric;
            document.querySelectorAll('.hb-geo-metric-switch [data-metric]').forEach(function (item) {
                item.classList.toggle('is-active', item === button);
            });
            paintMap();
            updateProvinceRanking();
            updateCityChart();
        });
    });

    var reset = document.getElementById('hb-geo-reset');
    if (reset) {
        reset.addEventListener('click', function () {
            selectedProvince = null;
            updateSelectedPanel(null);
            paintMap();
            updateCityChart();
        });
    }

    paintMap();
    updateProvinceRanking();
    createCompareChart();

    var firstProvince = provinces.slice().sort(function (a, b) {
        return number(b.revenue) - number(a.revenue);
    })[0];

    if (firstProvince) {
        selectProvince(firstProvince.name);
    } else {
        updateSelectedPanel(null);
        updateCityChart();
    }
})();
