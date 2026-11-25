/**
 * "Opening Hours" block – editor script (plain JS, no build step).
 */
(function (wp) {
    'use strict';

    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, SelectControl, TextControl, ToggleControl, RangeControl, Disabled } = wp.components;
    const { __ } = wp.i18n;
    const ServerSideRender = wp.serverSideRender;

    registerBlockType('ohhol/opening-hours', {
        edit: function (props) {
            const { attributes: a, setAttributes } = props;
            const blockProps = useBlockProps();

            const controls = [
                el(SelectControl, {
                    key: 'view',
                    label: __('View', 'opening-hours-holidays'),
                    value: a.view,
                    options: [
                        { value: 'week', label: __('Weekly overview', 'opening-hours-holidays') },
                        { value: 'today', label: __('Today', 'opening-hours-holidays') },
                        { value: 'holidays', label: __('Holidays & exceptions', 'opening-hours-holidays') },
                        { value: 'compact', label: __('Compact overview (footer/sidebar)', 'opening-hours-holidays') }
                    ],
                    onChange: function (view) { setAttributes({ view: view }); },
                    __nextHasNoMarginBottom: true
                })
            ];

            if (a.view !== 'today') {
                controls.push(
                    el(TextControl, {
                        key: 'title',
                        label: __('Title', 'opening-hours-holidays'),
                        value: a.title,
                        onChange: function (title) { setAttributes({ title: title }); },
                        __nextHasNoMarginBottom: true
                    }),
                    el(SelectControl, {
                        key: 'titleTag',
                        label: __('Title level', 'opening-hours-holidays'),
                        value: a.titleTag,
                        options: ['h2', 'h3', 'h4', 'h5', 'h6'].map(function (t) { return { value: t, label: t.toUpperCase() }; }),
                        onChange: function (titleTag) { setAttributes({ titleTag: titleTag }); },
                        __nextHasNoMarginBottom: true
                    })
                );
            }

            if (a.view === 'compact' || a.view === 'today') {
                controls.push(el(ToggleControl, {
                    key: 'showStatus',
                    label: __('Show "Open now" status', 'opening-hours-holidays'),
                    checked: !!a.showStatus,
                    onChange: function (showStatus) { setAttributes({ showStatus: showStatus }); },
                    __nextHasNoMarginBottom: true
                }));
            }

            if (a.view === 'holidays') {
                controls.push(
                    el(RangeControl, {
                        key: 'days',
                        label: __('Days ahead', 'opening-hours-holidays'),
                        value: a.days,
                        min: 1,
                        max: 365,
                        onChange: function (days) { setAttributes({ days: days || 30 }); },
                        __nextHasNoMarginBottom: true
                    }),
                    el(RangeControl, {
                        key: 'limit',
                        label: __('Maximum number of entries (0 = all)', 'opening-hours-holidays'),
                        value: a.limit,
                        min: 0,
                        max: 20,
                        onChange: function (limit) { setAttributes({ limit: limit || 0 }); },
                        __nextHasNoMarginBottom: true
                    })
                );
            }

            return el(Fragment, null,
                el(InspectorControls, null,
                    el(PanelBody, { title: __('Settings', 'opening-hours-holidays') }, controls)
                ),
                el('div', blockProps,
                    el(Disabled, null,
                        el(ServerSideRender, {
                            block: 'ohhol/opening-hours',
                            attributes: a,
                            EmptyResponsePlaceholder: function () {
                                return el('p', { className: 'ohhol-block-empty' },
                                    __('No upcoming holidays or exceptions.', 'opening-hours-holidays'));
                            }
                        })
                    )
                )
            );
        },

        // Rendered on the server.
        save: function () {
            return null;
        }
    });
})(window.wp);
