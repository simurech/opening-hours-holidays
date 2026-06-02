<?php
/**
 * Shortcodes: Öffnungszeiten
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcodes registrieren
 */
function oz_register_shortcodes() {
    add_shortcode('oeffnungszeiten', 'oz_oeffnungszeiten_shortcode');
    add_shortcode('oeffnungszeiten_heute', 'oz_oeffnungszeiten_heute_shortcode');
    add_shortcode('oeffnungszeiten_spezial', 'oz_oeffnungszeiten_spezial_shortcode');
    add_shortcode('oeffnungszeiten_kompakt', 'oz_oeffnungszeiten_kompakt_shortcode');
}
add_action('init', 'oz_register_shortcodes');

/**
 * Hilfsfunktion: Zeitfenster formatieren
 */
function oz_format_zeiten($von1, $bis1, $von2 = '', $bis2 = '') {
    if (empty($von1) || empty($bis1)) {
        return 'Geschlossen';
    }

    $output = esc_html($von1) . ' – ' . esc_html($bis1);

    if (!empty($von2) && !empty($bis2)) {
        $output .= ' / ' . esc_html($von2) . ' – ' . esc_html($bis2);
    }

    return $output;
}

/**
 * Hilfsfunktion: Wochentag-Mapping (PHP → Deutsch)
 */
function oz_get_wochentag_key($php_day) {
    $mapping = [
        'Monday'    => 'montag',
        'Tuesday'   => 'dienstag',
        'Wednesday' => 'mittwoch',
        'Thursday'  => 'donnerstag',
        'Friday'    => 'freitag',
        'Saturday'  => 'samstag',
        'Sunday'    => 'sonntag'
    ];
    return $mapping[$php_day] ?? 'montag';
}

/**
 * Hilfsfunktion: Spezielle Öffnungszeit für Datum prüfen
 */
function oz_get_spezial_fuer_datum($datum) {
    foreach (oz_get_oeffnungszeiten_spezial() as $eintrag) {
        if ($eintrag['datum'] === $datum) {
            return $eintrag;
        }
    }
    return null;
}

/**
 * CSS laden (mit Inline-Styles für Design-Einstellungen)
 */
function oz_enqueue_styles() {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    wp_enqueue_style('oz-oeffnungszeiten', OZ_PLUGIN_URL . 'assets/css/oeffnungszeiten.css', [], OZ_PLUGIN_VERSION);

    $design     = oz_get_oeffnungszeiten_design();
    $inline_css = oz_generate_inline_styles($design);
    wp_add_inline_style('oz-oeffnungszeiten', $inline_css);

    wp_enqueue_script('oz-oeffnungszeiten', OZ_PLUGIN_URL . 'assets/js/oeffnungszeiten.js', [], OZ_PLUGIN_VERSION, true);

    $tz_obj          = wp_timezone();
    $tz_now          = new DateTime('now', $tz_obj);
    $heute_str       = current_time('Y-m-d');
    $spezial_aktuell = array_values(array_filter(
        oz_get_oeffnungszeiten_spezial(),
        fn($e) => $e['datum'] >= $heute_str
    ));
    wp_localize_script('oz-oeffnungszeiten', 'ozData', [
        'regulaer'   => oz_get_oeffnungszeiten(),
        'spezial'    => $spezial_aktuell,
        'wochentage' => oz_get_wochentage(),
        'timezone'   => wp_timezone_string(),
        'utc_offset' => intval($tz_now->getOffset() / 60),
    ]);
}

/**
 * Inline-Styles aus Design-Einstellungen generieren (überschreibt CSS Custom Properties)
 */
function oz_generate_inline_styles($design) {
    $vars = [
        '--oz-heute-badge-bg'   => $design['heute_badge_bg'],
        '--oz-heute-badge-text' => $design['heute_badge_text'],
        '--oz-text-muted'       => $design['text_muted'],
        '--oz-text-faint'       => $design['text_faint'],
        '--oz-geschlossen'      => $design['geschlossen_farbe'],
        '--oz-offen-bg'         => $design['offen_bg'],
        '--oz-offen-text'       => $design['offen_text'],
        '--oz-offen-dot'        => $design['offen_dot'],
        '--oz-zu-bg'            => $design['zu_bg'],
        '--oz-zu-text'          => $design['zu_text'],
        '--oz-zu-dot'           => $design['zu_dot'],
    ];

    $root_css = ':root {';
    foreach ($vars as $prop => $value) {
        $root_css .= ' ' . $prop . ': ' . esc_attr($value) . ';';
    }
    $root_css .= ' }';

    if ($design['zebra_enabled']) {
        $root_css .= sprintf(
            ' .oz-oeffnungszeiten-tabelle tr:nth-child(odd), .oz-spezial-tabelle tr:nth-child(odd) { background-color: %s; }',
            esc_attr($design['zebra_color'])
        );
    }

    $root_css .= ' .oz-status:empty { display: none; }';

    return preg_replace('/\s+/', ' ', trim($root_css));
}

/**
 * Shortcode: Wochenübersicht [oeffnungszeiten]
 */
function oz_oeffnungszeiten_shortcode($atts) {
    $atts = shortcode_atts([
        'title'     => '',
        'title_tag' => 'h2'
    ], $atts, 'oeffnungszeiten');

    oz_enqueue_styles();

    $wochentage   = oz_get_wochentage();
    $zeiten       = oz_get_oeffnungszeiten();
    $spezial      = oz_get_oeffnungszeiten_spezial();
    $heute_datum  = current_time('Y-m-d');

    // Spezielle Öffnungszeiten der nächsten 7 Tage vorbereiten
    $spezial_map = [];
    foreach ($spezial as $eintrag) {
        $diff_tage = (strtotime($eintrag['datum']) - strtotime($heute_datum)) / 86400;
        if ($diff_tage >= 0 && $diff_tage < 7) {
            $wochentag_key = oz_get_wochentag_key(date('l', strtotime($eintrag['datum'])));
            $spezial_map[$wochentag_key] = $eintrag;
        }
    }

    $allowed_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    $title_tag    = in_array($atts['title_tag'], $allowed_tags) ? $atts['title_tag'] : 'h2';

    ob_start();
    ?>
    <div class="oz-oeffnungszeiten">
        <?php if (!empty($atts['title'])): ?>
            <<?php echo $title_tag; ?> class="oz-shortcode-title"><?php echo esc_html($atts['title']); ?></<?php echo $title_tag; ?>>
        <?php endif; ?>
        <table class="oz-oeffnungszeiten-tabelle">
            <tbody>
                <?php foreach ($wochentage as $key => $name):
                    $tag             = $zeiten[$key];
                    $hat_spezial     = isset($spezial_map[$key]);
                    $spezial_eintrag = $hat_spezial ? $spezial_map[$key] : null;
                    $regulaere_zeiten = '';

                    if ($hat_spezial) {
                        $regulaere_zeiten = 'Regulär: ' . ($tag['offen']
                            ? oz_format_zeiten($tag['von1'], $tag['bis1'], $tag['von2'], $tag['bis2'])
                            : 'Geschlossen');
                    }
                ?>
                <tr data-tag="<?php echo esc_attr($key); ?>" class="<?php echo $hat_spezial ? 'oz-hat-spezial' : ''; ?>">
                    <td class="oz-tag-name">
                        <?php echo esc_html($name); ?>
                    </td>
                    <td class="oz-tag-zeiten">
                        <?php if ($hat_spezial): ?>
                            <div class="oz-zeiten-wrapper">
                                <div class="oz-spezial-zeiten">
                                    <?php if ($spezial_eintrag['status'] === 'geschlossen'): ?>
                                        <span class="oz-geschlossen">Geschlossen</span>
                                    <?php else: ?>
                                        <?php echo oz_format_zeiten(
                                            $spezial_eintrag['von1'], $spezial_eintrag['bis1'],
                                            $spezial_eintrag['von2'], $spezial_eintrag['bis2']
                                        ); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($spezial_eintrag['bemerkung'])): ?>
                                        <span class="oz-bemerkung-inline">(<?php echo esc_html($spezial_eintrag['bemerkung']); ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="oz-regulaere-zeiten">
                                    <span class="oz-info-icon">ℹ️</span> <?php echo esc_html($regulaere_zeiten); ?>
                                </div>
                            </div>
                        <?php elseif ($tag['offen']): ?>
                            <?php echo oz_format_zeiten($tag['von1'], $tag['bis1'], $tag['von2'], $tag['bis2']); ?>
                        <?php else: ?>
                            <span class="oz-geschlossen">Geschlossen</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode: Heute [oeffnungszeiten_heute]
 */
function oz_oeffnungszeiten_heute_shortcode($atts) {
    oz_enqueue_styles();

    $wochentage      = oz_get_wochentage();
    $zeiten          = oz_get_oeffnungszeiten();
    $optionen        = oz_get_oeffnungszeiten_optionen();
    $heute           = current_time('Y-m-d');
    $wochentag_key   = oz_get_wochentag_key(current_time('l'));
    $wochentag_name  = $wochentage[$wochentag_key];
    $spezial         = oz_get_spezial_fuer_datum($heute);
    $zeiten_text     = '';
    $bemerkung       = '';
    $ist_geschlossen = false;

    if ($spezial) {
        if ($spezial['status'] === 'geschlossen') {
            $zeiten_text = 'Geschlossen'; $ist_geschlossen = true;
        } else {
            $zeiten_text = oz_format_zeiten($spezial['von1'], $spezial['bis1'], $spezial['von2'], $spezial['bis2']);
        }
        $bemerkung = $spezial['bemerkung'];
    } else {
        $tag = $zeiten[$wochentag_key];
        if ($tag['offen']) {
            $zeiten_text = oz_format_zeiten($tag['von1'], $tag['bis1'], $tag['von2'], $tag['bis2']);
        } else {
            $zeiten_text = 'Geschlossen'; $ist_geschlossen = true;
        }
    }

    $show_status = $optionen['jetzt_offen'];

    ob_start();
    ?>
    <div class="oz-oeffnungszeiten-heute" data-oz-heute>
        <?php if ($show_status): ?>
            <span class="oz-status" data-oz-status></span>
        <?php endif; ?>
    </div>
    <noscript>
        <div class="oz-oeffnungszeiten-heute">
            <span class="oz-heute-tag"><?php echo esc_html($wochentag_name); ?>:</span>
            <span class="oz-heute-zeiten <?php echo $ist_geschlossen ? 'oz-geschlossen' : ''; ?>">
                <?php echo $zeiten_text; ?>
            </span>
            <?php if ($bemerkung): ?>
                <span class="oz-heute-bemerkung">(<?php echo esc_html($bemerkung); ?>)</span>
            <?php endif; ?>
        </div>
    </noscript>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode: Spezielle Öffnungszeiten [oeffnungszeiten_spezial]
 */
function oz_oeffnungszeiten_spezial_shortcode($atts) {
    $atts = shortcode_atts([
        'limit'     => -1,
        'tage'      => 30,
        'title'     => '',
        'title_tag' => 'h2'
    ], $atts, 'oeffnungszeiten_spezial');

    $spezial = oz_get_oeffnungszeiten_spezial();
    $heute   = current_time('Y-m-d');

    // PHP filtert nur eindeutig vergangene Einträge; tage-Fenster und limit
    // übernimmt JS clientseitig anhand des echten Datums des Besuchers.
    $kommend = array_filter($spezial, fn($e) => $e['datum'] >= $heute);

    if (empty($kommend)) return '';

    oz_enqueue_styles();

    $allowed_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    $title_tag    = in_array($atts['title_tag'], $allowed_tags) ? $atts['title_tag'] : 'h2';

    ob_start();
    ?>
    <div class="oz-oeffnungszeiten-spezial"
         data-oz-spezial
         data-tage="<?php echo intval($atts['tage']); ?>"
         data-limit="<?php echo intval($atts['limit']); ?>">
        <?php if (!empty($atts['title'])): ?>
            <<?php echo $title_tag; ?> class="oz-shortcode-title"><?php echo esc_html($atts['title']); ?></<?php echo $title_tag; ?>>
        <?php endif; ?>
        <table class="oz-spezial-tabelle">
            <tbody>
                <?php foreach ($kommend as $eintrag):
                    $datum = date_i18n('l, j. F Y', strtotime($eintrag['datum']));
                ?>
                <tr data-datum="<?php echo esc_attr($eintrag['datum']); ?>">
                    <td class="oz-spezial-datum"><?php echo esc_html($datum); ?></td>
                    <td class="oz-spezial-info">
                        <?php if ($eintrag['status'] === 'geschlossen'): ?>
                            <span class="oz-geschlossen">Geschlossen</span>
                        <?php else: ?>
                            <?php echo oz_format_zeiten(
                                $eintrag['von1'], $eintrag['bis1'],
                                $eintrag['von2'], $eintrag['bis2']
                            ); ?>
                        <?php endif; ?>
                        <?php if ($eintrag['bemerkung']): ?>
                            <span class="oz-spezial-bemerkung">(<?php echo esc_html($eintrag['bemerkung']); ?>)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Hilfsfunktion: Aufeinanderfolgende Tage mit gleichen Zeiten gruppieren
 *
 * Gibt ein Array von Gruppen zurück, z.B.:
 * [['label' => 'Mo – Fr', 'tag' => [...]], ['label' => 'Sa', 'tag' => [...]], ...]
 */
function oz_group_tage_by_zeiten($zeiten, $wochentage) {
    $keys   = array_keys($wochentage);
    $groups = [];
    $i      = 0;

    while ($i < count($keys)) {
        $key     = $keys[$i];
        $current = $zeiten[$key];
        $start   = $i;

        while (
            $i + 1 < count($keys) &&
            $zeiten[$keys[$i + 1]] == $current
        ) {
            $i++;
        }

        $label = $start === $i
            ? $wochentage[$keys[$start]]
            : $wochentage[$keys[$start]] . ' – ' . $wochentage[$keys[$i]];

        $groups[] = ['label' => $label, 'tag' => $current];
        $i++;
    }

    return $groups;
}

/**
 * Shortcode: Kompakte Übersicht [oeffnungszeiten_kompakt]
 *
 * Geeignet für Footer/Sidebar. Fasst Tage mit gleichen Zeiten zusammen.
 *
 * Parameter:
 *   status="1"     – "Jetzt geöffnet/geschlossen" Badge anzeigen
 *   title=""       – Optionaler Titel
 *   title_tag="h3" – HTML-Tag für den Titel
 */
function oz_oeffnungszeiten_kompakt_shortcode($atts) {
    $atts = shortcode_atts([
        'status'    => 0,
        'title'     => '',
        'title_tag' => 'h3',
    ], $atts, 'oeffnungszeiten_kompakt');

    oz_enqueue_styles();

    $wochentage    = oz_get_wochentage();
    $zeiten        = oz_get_oeffnungszeiten();
    $optionen      = oz_get_oeffnungszeiten_optionen();
    $heute         = current_time('Y-m-d');
    $spezial_heute = oz_get_spezial_fuer_datum($heute);

    $gruppen     = oz_group_tage_by_zeiten($zeiten, $wochentage);
    $show_status = intval($atts['status']) || $optionen['jetzt_offen'];

    $allowed_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    $title_tag    = in_array($atts['title_tag'], $allowed_tags) ? $atts['title_tag'] : 'h3';

    ob_start();
    ?>
    <div class="oz-oeffnungszeiten-kompakt">
        <?php if (!empty($atts['title'])): ?>
            <<?php echo $title_tag; ?> class="oz-shortcode-title"><?php echo esc_html($atts['title']); ?></<?php echo $title_tag; ?>>
        <?php endif; ?>

        <?php if ($show_status): ?>
            <div class="oz-kompakt-status">
                <span class="oz-status" data-oz-status></span>
                <?php if ($spezial_heute && !empty($spezial_heute['bemerkung'])): ?>
                    <span class="oz-heute-bemerkung">(<?php echo esc_html($spezial_heute['bemerkung']); ?>)</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <ul class="oz-kompakt-liste">
            <?php foreach ($gruppen as $gruppe):
                $tag = $gruppe['tag'];
            ?>
            <li class="oz-kompakt-zeile">
                <span class="oz-kompakt-tag"><?php echo esc_html($gruppe['label']); ?></span>
                <span class="oz-kompakt-zeiten">
                    <?php if ($tag['offen']): ?>
                        <?php echo oz_format_zeiten($tag['von1'], $tag['bis1'], $tag['von2'], $tag['bis2']); ?>
                    <?php else: ?>
                        <span class="oz-geschlossen">Geschlossen</span>
                    <?php endif; ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}
