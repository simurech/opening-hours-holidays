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

        // Dim time inputs for days marked as closed (read-only, so the times are kept).
        $('.ohhol-open-toggle').on('change', function () {
            var cells = $('.ohhol-times-cell[data-day="' + $(this).data('day') + '"]');
            cells.find('input').prop('readonly', !this.checked);
            cells.toggleClass('ohhol-inactive', !this.checked);
        }).trigger('change');

        // Disable time inputs for holidays with status "closed".
        $(document).on('change', '.ohhol-status-select', function () {
            var $cell      = $(this).closest('tr').find('.ohhol-holiday-times');
            var isModified = $(this).val() === 'modified';
            $cell.find('input').prop('disabled', !isModified);
            $cell.toggleClass('ohhol-inactive', !isModified);
        });
        $('.ohhol-status-select').trigger('change');

        // Validate a time window: both times needed, not identical; mark overnight windows.
        function checkWindow($close) {
            var name   = $close.attr('name') || '';
            var $open  = $close.closest('tr').find('input[name="' + name.replace(/\[close(\d)\]$/, '[open$1]') + '"]');
            var open   = $open.val();
            var close  = $close.val();
            var invalid = (open === '') !== (close === '') || (open !== '' && open === close);
            var overnight = open !== '' && close !== '' && close < open;

            $open.add($close).toggleClass('ohhol-invalid', invalid)
                .attr('aria-invalid', invalid ? 'true' : null);

            var $hint = $close.next('.ohhol-next-day');
            if (overnight && !$hint.length) {
                $hint = $('<span>', {
                    'class': 'ohhol-next-day',
                    title: i18n.nextDay || '',
                    text: '+1'
                });
                $close.after($hint);
            } else if (!overnight) {
                $hint.remove();
            }
        }

        $(document).on('change input', 'input.ohhol-time-input', function () {
            var name = $(this).attr('name') || '';
            var $close = /\[open\d\]$/.test(name)
                ? $(this).closest('tr').find('input[name="' + name.replace(/\[open(\d)\]$/, '[close$1]') + '"]')
                : $(this);
            checkWindow($close);
        });
        $('input.ohhol-time-input[name$="[close1]"], input.ohhol-time-input[name$="[close2]"]').each(function () {
            checkWindow($(this));
        });
    });
})(jQuery);
