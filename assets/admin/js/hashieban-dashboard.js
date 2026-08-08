document.addEventListener(
    'DOMContentLoaded',
    function () {
        const chart = document.getElementById(
            'hashieban-live-chart'
        );

        if (!chart) {
            return;
        }

        const buttons =
            document.querySelectorAll(
                '.hb-chart-switch'
            );

        const columns =
            chart.querySelectorAll(
                '.hb-chart-column'
            );

        const detail =
            document.getElementById(
                'hashieban-chart-detail'
            );

        const detailTitle =
            document.getElementById(
                'hashieban-chart-detail-title'
            );

        const detailRevenue =
            document.getElementById(
                'hashieban-chart-detail-revenue'
            );

        const detailProfit =
            document.getElementById(
                'hashieban-chart-detail-profit'
            );

        const currency =
            chart.dataset.currency || '';

        let activeSeries = 'revenue';

        function formatNumber(value) {
            const numericValue =
                Number(value);

            if (
                Number.isNaN(
                    numericValue
                )
            ) {
                return '0';
            }

            return (
                new Intl.NumberFormat(
                    'fa-IR'
                ).format(numericValue)
                + ' '
                + currency
            );
        }

        function compactNumber(value) {
            const numericValue =
                Number(value);

            if (
                Number.isNaN(
                    numericValue
                )
            ) {
                return '0';
            }

            return new Intl.NumberFormat(
                'fa-IR',
                {
                    notation: 'compact',
                    maximumFractionDigits: 1
                }
            ).format(numericValue);
        }

        function renderChart() {
            let maxValue = 0;

            columns.forEach(
                function (column) {
                    const value =
                        Math.abs(
                            Number(
                                column.dataset[
                                    activeSeries
                                ] || 0
                            )
                        );

                    if (
                        value > maxValue
                    ) {
                        maxValue = value;
                    }
                }
            );

            columns.forEach(
                function (column) {
                    const rawValue =
                        Number(
                            column.dataset[
                                activeSeries
                            ] || 0
                        );

                    const absoluteValue =
                        Math.abs(rawValue);

                    const percentage =
                        maxValue > 0
                            ? (
                                absoluteValue
                                / maxValue
                            ) * 100
                            : 0;

                    const bar =
                        column.querySelector(
                            '.hb-chart-bar'
                        );

                    const valueLabel =
                        column.querySelector(
                            '.hb-chart-value'
                        );

                    if (bar) {
                        bar.style.height =
                            Math.max(
                                2,
                                percentage
                            )
                            + '%';
                    }

                    if (valueLabel) {
                        valueLabel.textContent =
                            compactNumber(
                                rawValue
                            );
                    }

                    column.classList.toggle(
                        'is-negative',
                        rawValue < 0
                    );
                }
            );
        }

        buttons.forEach(
            function (button) {
                button.addEventListener(
                    'click',
                    function () {
                        activeSeries =
                            button.dataset.series;

                        buttons.forEach(
                            function (item) {
                                item.classList.remove(
                                    'is-active'
                                );
                            }
                        );

                        button.classList.add(
                            'is-active'
                        );

                        renderChart();
                    }
                );
            }
        );

        columns.forEach(
            function (column) {
                column.addEventListener(
                    'click',
                    function () {
                        columns.forEach(
                            function (item) {
                                item.classList.remove(
                                    'is-selected'
                                );
                            }
                        );

                        column.classList.add(
                            'is-selected'
                        );

                        if (!detail) {
                            return;
                        }

                        detail.hidden = false;

                        if (detailTitle) {
                            detailTitle.textContent =
                                column.dataset.label;
                        }

                        if (detailRevenue) {
                            detailRevenue.textContent =
                                formatNumber(
                                    column.dataset.revenue
                                );
                        }

                        if (detailProfit) {
                            detailProfit.textContent =
                                formatNumber(
                                    column.dataset.profit
                                );
                        }
                    }
                );
            }
        );

        renderChart();
    }
);
