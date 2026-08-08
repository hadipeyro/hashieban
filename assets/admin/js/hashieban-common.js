document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.jalaliDatepicker === 'undefined') {
        return;
    }

    window.jalaliDatepicker.startWatch({
        selector: '[data-jdp]',
        persianDigits: true,
        autoHide: true,
        showTodayBtn: true,
        showEmptyBtn: false,
        useDropDownYears: true,
        useDropDownMonths: true
    });
});
