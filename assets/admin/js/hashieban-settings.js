(function () {
    'use strict';

    function nextRuleIndex(container) {
        var maxIndex = -1;
        var fields = container.querySelectorAll('[name^="rules["]');

        fields.forEach(function (field) {
            var match = field.name.match(/^rules\[(\d+)\]/);

            if (match) {
                maxIndex = Math.max(
                    maxIndex,
                    parseInt(match[1], 10)
                );
            }
        });

        return maxIndex + 1;
    }

    function addRuleRow(container, template) {
        var index = nextRuleIndex(container);
        var html = template.innerHTML.replace(
            /__INDEX__/g,
            String(index)
        );

        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();

        var row = wrapper.firstElementChild;

        if (! row) {
            return;
        }

        container.appendChild(row);

        var titleInput = row.querySelector(
            'input[name$="[title]"]'
        );

        if (titleInput) {
            titleInput.focus();
        }
    }

    document.addEventListener(
        'DOMContentLoaded',
        function () {
            var container =
                document.getElementById(
                    'hb-global-cost-rows'
                );

            var template =
                document.getElementById(
                    'hb-global-cost-template'
                );

            var addButton =
                document.getElementById(
                    'hb-add-global-cost'
                );

            if (
                ! container
                || ! template
                || ! addButton
            ) {
                return;
            }

            addButton.addEventListener(
                'click',
                function () {
                    addRuleRow(
                        container,
                        template
                    );
                }
            );

            container.addEventListener(
                'click',
                function (event) {
                    var button =
                        event.target.closest(
                            '.hb-remove-global-cost'
                        );

                    if (! button) {
                        return;
                    }

                    var row =
                        button.closest(
                            '.hb-global-cost-row'
                        );

                    if (row) {
                        row.remove();
                    }
                }
            );
        }
    );
}());
