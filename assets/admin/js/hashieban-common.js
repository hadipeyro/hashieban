document.addEventListener(
    'DOMContentLoaded',
    function () {
        if (
            typeof window.jalaliDatepicker
            === 'undefined'
        ) {
            return;
        }

        window.jalaliDatepicker.startWatch({
            selector: 'input[data-jdp]',
            persianDigits: true,
            autoHide: true,
            showTodayBtn: true,
            showEmptyBtn: false,
            useDropdownYears: true,
            position: 'right',
            targetValueInput: 'attr',
            targetValueType: 'attr'
        });
    }
);
