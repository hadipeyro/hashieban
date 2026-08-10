document.addEventListener(
    'DOMContentLoaded',
    function () {
        const rows =
            document.getElementById(
                'hashieban-direct-cost-rows'
            );

        const addButton =
            document.getElementById(
                'hashieban-add-direct-cost'
            );

        const template =
            document.getElementById(
                'hashieban-direct-cost-template'
            );

        const totalElement =
            document.getElementById(
                'hashieban-direct-cost-total'
            );

        if (
            !rows
            || !addButton
            || !template
        ) {
            return;
        }

        let index = Date.now();

        function calculateTotal() {
            if (!totalElement) {
                return;
            }

            let total = 0;

            rows
                .querySelectorAll(
                    '.hashieban-direct-cost-amount'
                )
                .forEach(
                    function (input) {
                        const value =
                            Number(input.value);

                        if (
                            Number.isFinite(value)
                            && value > 0
                        ) {
                            total += value;
                        }
                    }
                );

            totalElement.textContent =
                new Intl.NumberFormat(
                    'fa-IR'
                ).format(total);
        }

        function updateCategoryDot(
            select
        ) {
            if (!select) {
                return;
            }

            const wrapper =
                select.closest(
                    '.hb-order-category-select'
                );

            if (!wrapper) {
                return;
            }

            const dot =
                wrapper.querySelector(
                    '.hb-order-category-dot'
                );

            const option =
                select.options[
                    select.selectedIndex
                ];

            if (
                dot
                && option
                && option.dataset.color
            ) {
                dot.style.background =
                    option.dataset.color;
            }
        }

        function initializeCategoryDots(
            root
        ) {
            root
                .querySelectorAll(
                    '.hb-order-category'
                )
                .forEach(
                    updateCategoryDot
                );
        }

        addButton.addEventListener(
            'click',
            function () {
                const html =
                    template.innerHTML.replace(
                        /__INDEX__/g,
                        String(index)
                    );

                rows.insertAdjacentHTML(
                    'beforeend',
                    html
                );

                index++;

                const newRows =
                    rows.querySelectorAll(
                        '.hashieban-direct-cost-row'
                    );

                const newRow =
                    newRows[
                        newRows.length - 1
                    ];

                if (newRow) {
                    initializeCategoryDots(
                        newRow
                    );

                    const firstText =
                        newRow.querySelector(
                            'input[type="text"]'
                        );

                    if (firstText) {
                        firstText.focus();
                    }
                }

                calculateTotal();
            }
        );

        rows.addEventListener(
            'click',
            function (event) {
                const button =
                    event.target.closest(
                        '.hashieban-remove-direct-cost'
                    );

                if (!button) {
                    return;
                }

                const row =
                    button.closest(
                        '.hashieban-direct-cost-row'
                    );

                if (row) {
                    row.remove();
                    calculateTotal();
                }
            }
        );

        rows.addEventListener(
            'input',
            function (event) {
                if (
                    event.target.classList
                        .contains(
                            'hashieban-direct-cost-amount'
                        )
                ) {
                    calculateTotal();
                }
            }
        );

        rows.addEventListener(
            'change',
            function (event) {
                if (
                    event.target.classList
                        .contains(
                            'hb-order-category'
                        )
                ) {
                    updateCategoryDot(
                        event.target
                    );
                }
            }
        );

        initializeCategoryDots(rows);
        calculateTotal();
    }
);
