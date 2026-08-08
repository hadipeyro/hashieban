document.addEventListener('DOMContentLoaded', function () {
    const rows = document.getElementById(
        'hashieban-direct-cost-rows'
    );

    const addButton = document.getElementById(
        'hashieban-add-direct-cost'
    );

    const template = document.getElementById(
        'hashieban-direct-cost-template'
    );

    const totalElement = document.getElementById(
        'hashieban-direct-cost-total'
    );

    if (!rows || !addButton || !template) {
        return;
    }

    let index = Date.now();

    function calculateTotal() {
        if (!totalElement) {
            return;
        }

        let total = 0;

        const amountInputs = rows.querySelectorAll(
            '.hashieban-direct-cost-amount'
        );

        amountInputs.forEach(function (input) {
            const value = parseFloat(input.value);

            if (!Number.isNaN(value) && value > 0) {
                total += value;
            }
        });

        totalElement.textContent =
            new Intl.NumberFormat('fa-IR').format(total);
    }

    addButton.addEventListener(
        'click',
        function () {
            const html = template.innerHTML.replace(
                /__INDEX__/g,
                String(index)
            );

            rows.insertAdjacentHTML(
                'beforeend',
                html
            );

            index++;

            const newRows = rows.querySelectorAll(
                '.hashieban-direct-cost-row'
            );

            const newRow = newRows[
                newRows.length - 1
            ];

            if (newRow) {
                const firstInput =
                    newRow.querySelector(
                        'input[type="text"]'
                    );

                if (firstInput) {
                    firstInput.focus();
                }
            }
        }
    );

    rows.addEventListener(
        'click',
        function (event) {
            const button = event.target.closest(
                '.hashieban-remove-direct-cost'
            );

            if (!button) {
                return;
            }

            const row = button.closest(
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
                event.target.classList.contains(
                    'hashieban-direct-cost-amount'
                )
            ) {
                calculateTotal();
            }
        }
    );

    calculateTotal();
});
