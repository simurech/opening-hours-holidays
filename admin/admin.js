/**
 * Opening Hours & Holidays – settings page.
 */
(function ($) {
    'use strict';

    var i18n = window.ohholAdmin || {};

    $(function () {
        var $body     = $('#ohhol-holidays-body');
        var nextIndex = parseInt($body.data('next-index'), 10) || 0;

        // Must be kept in sync with ohhol_render_holiday_row() in includes/settings.php.
        function holidayRow(index) {
            var n = 'ohhol_holidays[' + index + ']';
            return $('<tr>')
                .append($('<td>').append($('<input>', { type: 'date', name: n + '[date]' })))
                .append($('<td>').append(
                    $('<select>', { name: n + '[status]', 'class': 'ohhol-status-select' })
                        .append($('<option>', { value: 'closed', text: i18n.statusClosed || 'Closed' }))
                        .append($('<option>', { value: 'modified', text: i18n.statusModified || 'Modified hours' }))
                ))
                .append($('<td>', { 'class': 'ohhol-holiday-times' })
                    .append($('<input>', { type: 'time', name: n + '[open1]', 'class': 'ohhol-time-input' }))
                    .append($('<span>').text('–'))
                    .append($('<input>', { type: 'time', name: n + '[close1]', 'class': 'ohhol-time-input' }))
                    .append($('<span>', { 'class': 'ohhol-window-sep' }).text('/'))
                    .append($('<input>', { type: 'time', name: n + '[open2]', 'class': 'ohhol-time-input' }))
                    .append($('<span>').text('–'))
                    .append($('<input>', { type: 'time', name: n + '[close2]', 'class': 'ohhol-time-input' }))
                )
                .append($('<td>').append($('<input>', {
                    type: 'text',
                    name: n + '[note]',
                    placeholder: i18n.notePlaceholder || '',
                    'class': 'regular-text'
                })))
                .append($('<td>').append($('<button>', {
                    type: 'button',
                    'class': 'button ohhol-remove-row',
                    'aria-label': i18n.removeLabel || '',
                    text: '×'
                })));
        }

        $('#ohhol-add-holiday').on('click', function () {
            var $row = holidayRow(nextIndex++);
            $body.append($row);
            $row.find('.ohhol-status-select').trigger('change');
        });

        $(document).on('click', '.ohhol-remove-row', function () {
            $(this).closest('tr').remove();
        });

        // Disable time inputs for days marked as closed.
        $('.ohhol-open-toggle').on('change', function () {
            var cells = $('.ohhol-times-cell[data-day="' + $(this).data('day') + '"]');
            cells.find('input').prop('disabled', !this.checked);
            cells.css('opacity', this.checked ? 1 : 0.4);
        }).trigger('change');

        // Disable time inputs for holidays with status "closed".
        $(document).on('change', '.ohhol-status-select', function () {
            var $cell      = $(this).closest('tr').find('.ohhol-holiday-times');
            var isModified = $(this).val() === 'modified';
            $cell.find('input').prop('disabled', !isModified);
            $cell.css('opacity', isModified ? 1 : 0.4);
        });
        $('.ohhol-status-select').trigger('change');
    });
})(jQuery);
