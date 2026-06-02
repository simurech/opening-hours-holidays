<?php
/**
 * Einstellungen: Öffnungszeiten
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Menü hinzufügen
 */
function oz_oeffnungszeiten_menu() {
    add_menu_page(
        'Öffnungszeiten',
        'Öffnungszeiten',
        'manage_options',
        'oz-oeffnungszeiten',
        'oz_oeffnungszeiten_page',
        'dashicons-clock',
        26
    );
}
add_action('admin_menu', 'oz_oeffnungszeiten_menu');

/**
 * Einstellungen registrieren
 */
function oz_oeffnungszeiten_register_settings() {
    register_setting('oz_oeffnungszeiten', 'oz_oeffnungszeiten', [
        'sanitize_callback' => 'oz_sanitize_oeffnungszeiten'
    ]);

    register_setting('oz_oeffnungszeiten', 'oz_oeffnungszeiten_spezial', [
        'sanitize_callback' => 'oz_sanitize_oeffnungszeiten_spezial'
    ]);

    register_setting('oz_oeffnungszeiten', 'oz_oeffnungszeiten_optionen', [
        'sanitize_callback' => 'oz_sanitize_oeffnungszeiten_optionen'
    ]);

    register_setting('oz_oeffnungszeiten', 'oz_oeffnungszeiten_design', [
        'sanitize_callback' => 'oz_sanitize_oeffnungszeiten_design'
    ]);
}
add_action('admin_init', 'oz_oeffnungszeiten_register_settings');

/**
 * Wochentage
 */
function oz_get_wochentage() {
    return [
        'montag'     => 'Montag',
        'dienstag'   => 'Dienstag',
        'mittwoch'   => 'Mittwoch',
        'donnerstag' => 'Donnerstag',
        'freitag'    => 'Freitag',
        'samstag'    => 'Samstag',
        'sonntag'    => 'Sonntag'
    ];
}

/**
 * Standard-Öffnungszeiten
 */
function oz_get_oeffnungszeiten_defaults() {
    $defaults = [];
    foreach (oz_get_wochentage() as $key => $name) {
        $defaults[$key] = [
            'offen' => ($key !== 'sonntag') ? 1 : 0,
            'von1'  => '09:00',
            'bis1'  => '12:00',
            'von2'  => '13:30',
            'bis2'  => '18:00'
        ];
    }
    return $defaults;
}

/**
 * Öffnungszeiten abrufen (mit Caching)
 */
function oz_get_oeffnungszeiten() {
    static $cached = null;
    if ($cached !== null) return $cached;
    $saved = get_option('oz_oeffnungszeiten', []);
    $cached = wp_parse_args($saved, oz_get_oeffnungszeiten_defaults());
    return $cached;
}

/**
 * Spezielle Öffnungszeiten abrufen (mit Caching)
 */
function oz_get_oeffnungszeiten_spezial() {
    static $cached = null;
    if ($cached !== null) return $cached;
    $cached = get_option('oz_oeffnungszeiten_spezial', []);
    return $cached;
}

/**
 * Optionen abrufen (mit Caching)
 */
function oz_get_oeffnungszeiten_optionen() {
    static $cached = null;
    if ($cached !== null) return $cached;
    $defaults = ['jetzt_offen' => 0];
    $cached = wp_parse_args(get_option('oz_oeffnungszeiten_optionen', []), $defaults);
    return $cached;
}

/**
 * Standard-Design-Werte
 */
function oz_get_oeffnungszeiten_design_defaults() {
    return [
        // Heute-Badge
        'heute_badge_bg'   => '#000000',
        'heute_badge_text' => '#ffffff',
        // Zebra-Streifen
        'zebra_color'      => '#f9f9f9',
        'zebra_enabled'    => 1,
        // Textfarben
        'text_muted'       => '#666666',
        'text_faint'       => '#999999',
        // Semantische Farben
        'geschlossen_farbe' => '#c62828',
        // Badge "Jetzt geöffnet"
        'offen_bg'         => '#e8f5e9',
        'offen_text'       => '#2e7d32',
        'offen_dot'        => '#4caf50',
        // Badge "Aktuell geschlossen"
        'zu_bg'            => '#ffebee',
        'zu_text'          => '#c62828',
        'zu_dot'           => '#f44336',
    ];
}

/**
 * Design-Einstellungen abrufen (mit Caching)
 */
function oz_get_oeffnungszeiten_design() {
    static $cached = null;
    if ($cached !== null) return $cached;
    $settings = get_option('oz_oeffnungszeiten_design', []);
    $cached = wp_parse_args($settings, oz_get_oeffnungszeiten_design_defaults());
    return $cached;
}

/**
 * Sanitize: Reguläre Öffnungszeiten
 */
function oz_sanitize_oeffnungszeiten($input) {
    $sanitized = [];
    foreach (oz_get_wochentage() as $key => $name) {
        $sanitized[$key] = [
            'offen' => isset($input[$key]['offen']) ? 1 : 0,
            'von1'  => oz_sanitize_time($input[$key]['von1'] ?? ''),
            'bis1'  => oz_sanitize_time($input[$key]['bis1'] ?? ''),
            'von2'  => oz_sanitize_time($input[$key]['von2'] ?? ''),
            'bis2'  => oz_sanitize_time($input[$key]['bis2'] ?? '')
        ];
    }
    return $sanitized;
}

/**
 * Sanitize: Spezielle Öffnungszeiten
 */
function oz_sanitize_oeffnungszeiten_spezial($input) {
    if (!is_array($input)) return [];

    $sanitized = [];
    foreach ($input as $item) {
        if (empty($item['datum'])) continue;
        $sanitized[] = [
            'datum'     => sanitize_text_field($item['datum']),
            'status'    => in_array($item['status'], ['geschlossen', 'geaendert'])
                          ? $item['status']
                          : 'geschlossen',
            'von1'      => oz_sanitize_time($item['von1'] ?? ''),
            'bis1'      => oz_sanitize_time($item['bis1'] ?? ''),
            'von2'      => oz_sanitize_time($item['von2'] ?? ''),
            'bis2'      => oz_sanitize_time($item['bis2'] ?? ''),
            'bemerkung' => sanitize_text_field($item['bemerkung'] ?? '')
        ];
    }

    usort($sanitized, function($a, $b) {
        return strtotime($a['datum']) - strtotime($b['datum']);
    });

    return $sanitized;
}

/**
 * Sanitize: Optionen
 */
function oz_sanitize_oeffnungszeiten_optionen($input) {
    return ['jetzt_offen' => isset($input['jetzt_offen']) ? 1 : 0];
}

/**
 * Sanitize: Design-Einstellungen
 */
function oz_sanitize_oeffnungszeiten_design($input) {
    $sanitized = [];
    $defaults  = oz_get_oeffnungszeiten_design_defaults();

    $color_fields = [
        'heute_badge_bg', 'heute_badge_text',
        'zebra_color',
        'text_muted', 'text_faint',
        'geschlossen_farbe',
        'offen_bg', 'offen_text', 'offen_dot',
        'zu_bg', 'zu_text', 'zu_dot',
    ];

    foreach ($color_fields as $field) {
        if (isset($input[$field])) {
            $color = sanitize_hex_color($input[$field]);
            $sanitized[$field] = $color ?: $defaults[$field];
        }
    }

    $sanitized['zebra_enabled'] = isset($input['zebra_enabled']) ? 1 : 0;
    return $sanitized;
}

/**
 * Zeit sanitieren (Format HH:MM)
 */
function oz_sanitize_time($time) {
    $time = sanitize_text_field($time);
    if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
        return $time;
    }
    return '';
}

/**
 * Einstellungsseite
 */
function oz_oeffnungszeiten_page() {
    if (!current_user_can('manage_options')) return;

    $wochentage = oz_get_wochentage();
    $zeiten     = oz_get_oeffnungszeiten();
    $spezial    = oz_get_oeffnungszeiten_spezial();
    $optionen   = oz_get_oeffnungszeiten_optionen();
    $design     = oz_get_oeffnungszeiten_design();
    $heute      = current_time('Y-m-d');
    ?>
    <div class="wrap">
        <h1>Öffnungszeiten</h1>

        <form method="post" action="options.php">
            <?php settings_fields('oz_oeffnungszeiten'); ?>

            <h2>Reguläre Öffnungszeiten</h2>
            <table class="widefat oz-oeffnungszeiten-table">
                <thead>
                    <tr>
                        <th style="width: 120px;">Tag</th>
                        <th style="width: 60px;">Offen</th>
                        <th>Vormittag / Zeitfenster 1</th>
                        <th>Nachmittag / Zeitfenster 2</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wochentage as $key => $name):
                        $tag = $zeiten[$key];
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($name); ?></strong></td>
                        <td>
                            <input type="checkbox"
                                   name="oz_oeffnungszeiten[<?php echo esc_attr($key); ?>][offen]"
                                   value="1"
                                   class="oz-offen-toggle"
                                   data-tag="<?php echo esc_attr($key); ?>"
                                   <?php checked($tag['offen'], 1); ?>>
                        </td>
                        <td class="oz-zeiten-cell" data-tag="<?php echo esc_attr($key); ?>">
                            <input type="time"
                                   name="oz_oeffnungszeiten[<?php echo esc_attr($key); ?>][von1]"
                                   value="<?php echo esc_attr($tag['von1']); ?>"
                                   class="oz-time-input">
                            <span>–</span>
                            <input type="time"
                                   name="oz_oeffnungszeiten[<?php echo esc_attr($key); ?>][bis1]"
                                   value="<?php echo esc_attr($tag['bis1']); ?>"
                                   class="oz-time-input">
                        </td>
                        <td class="oz-zeiten-cell" data-tag="<?php echo esc_attr($key); ?>">
                            <input type="time"
                                   name="oz_oeffnungszeiten[<?php echo esc_attr($key); ?>][von2]"
                                   value="<?php echo esc_attr($tag['von2']); ?>"
                                   class="oz-time-input">
                            <span>–</span>
                            <input type="time"
                                   name="oz_oeffnungszeiten[<?php echo esc_attr($key); ?>][bis2]"
                                   value="<?php echo esc_attr($tag['bis2']); ?>"
                                   class="oz-time-input">
                            <span class="description" style="margin-left: 10px;">Leer lassen falls keine Pause</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2 style="margin-top: 40px;">Spezielle Öffnungszeiten</h2>
            <p class="description">Feiertage, Betriebsferien oder abweichende Zeiten für bestimmte Daten.</p>

            <table class="widefat oz-spezial-table" id="oz-spezial-table">
                <thead>
                    <tr>
                        <th style="width: 140px;">Datum</th>
                        <th style="width: 130px;">Status</th>
                        <th>Zeiten (falls geändert)</th>
                        <th style="width: 200px;">Bemerkung</th>
                        <th style="width: 50px;"></th>
                    </tr>
                </thead>
                <tbody id="oz-spezial-body">
                    <?php if (!empty($spezial)): ?>
                        <?php foreach ($spezial as $index => $eintrag):
                            $vergangen = $eintrag['datum'] < $heute;
                        ?>
                        <tr class="<?php echo $vergangen ? 'oz-vergangen' : ''; ?>">
                            <td>
                                <input type="date"
                                       name="oz_oeffnungszeiten_spezial[<?php echo $index; ?>][datum]"
                                       value="<?php echo esc_attr($eintrag['datum']); ?>">
                            </td>
                            <td>
                                <select name="oz_oeffnungszeiten_spezial[<?php echo $index; ?>][status]"
                                        class="oz-status-select">
                                    <option value="geschlossen" <?php selected($eintrag['status'], 'geschlossen'); ?>>Geschlossen</option>
                                    <option value="geaendert" <?php selected($eintrag['status'], 'geaendert'); ?>>Geänderte Zeiten</option>
                                </select>
                            </td>
                            <td class="oz-spezial-zeiten">
                                <input type="time" name="oz_oeffnungszeiten_spezial[<?php echo $index; ?>][von1]" value="<?php echo esc_attr($eintrag['von1']); ?>" class="oz-time-input">
                                <span>–</span>
                                <input type="time" name="oz_oeffnungszeiten_spezial[<?php echo $index; ?>][bis1]" value="<?php echo esc_attr($eintrag['bis1']); ?>" class="oz-time-input">
                                <span style="margin: 0 5px;">/</span>
                                <input type="time" name="oz_oeffnungszeiten_spezial[<?php echo $index; ?>][von2]" value="<?php echo esc_attr($eintrag['von2']); ?>" class="oz-time-input">
                                <span>–</span>
                                <input type="time" name="oz_oeffnungszeiten_spezial[<?php echo $index; ?>][bis2]" value="<?php echo esc_attr($eintrag['bis2']); ?>" class="oz-time-input">
                            </td>
                            <td>
                                <input type="text"
                                       name="oz_oeffnungszeiten_spezial[<?php echo $index; ?>][bemerkung]"
                                       value="<?php echo esc_attr($eintrag['bemerkung']); ?>"
                                       placeholder="z.B. Feiertag"
                                       class="regular-text">
                            </td>
                            <td>
                                <button type="button" class="button oz-remove-row">×</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <p>
                <button type="button" class="button" id="oz-add-spezial">+ Eintrag hinzufügen</button>
            </p>

            <h2 style="margin-top: 40px;">Optionen</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">"Jetzt geöffnet" anzeigen</th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="oz_oeffnungszeiten_optionen[jetzt_offen]"
                                   value="1"
                                   <?php checked($optionen['jetzt_offen'], 1); ?>>
                            Beim Shortcode <code>[oeffnungszeiten_heute]</code> anzeigen, ob gerade geöffnet ist
                        </label>
                    </td>
                </tr>
            </table>

            <h2 style="margin-top: 40px;">Design-Einstellungen</h2>
            <p class="description" style="margin-bottom: 15px;">
                Schriftart und primäre Textfarbe werden automatisch vom Theme übernommen.<br>
                Die folgenden Farben können hier angepasst werden.
            </p>

            <h3>Texte &amp; Tabelle</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Gedämpfte Textfarbe</th>
                    <td>
                        <input type="color"
                               name="oz_oeffnungszeiten_design[text_muted]"
                               value="<?php echo esc_attr($design['text_muted']); ?>">
                        <code><?php echo esc_html($design['text_muted']); ?></code>
                        <p class="description">Für Uhrzeiten und Nebeninformationen (Standard: #666666)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Sehr gedämpfte Textfarbe</th>
                    <td>
                        <input type="color"
                               name="oz_oeffnungszeiten_design[text_faint]"
                               value="<?php echo esc_attr($design['text_faint']); ?>">
                        <code><?php echo esc_html($design['text_faint']); ?></code>
                        <p class="description">Für Bemerkungen und Klammerzusätze (Standard: #999999)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Zebra-Streifen aktivieren</th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="oz_oeffnungszeiten_design[zebra_enabled]"
                                   value="1"
                                   <?php checked($design['zebra_enabled'], 1); ?>>
                            Ungerade Zeilen farblich hinterlegen
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Zebra-Streifen Farbe</th>
                    <td>
                        <input type="color"
                               name="oz_oeffnungszeiten_design[zebra_color]"
                               value="<?php echo esc_attr($design['zebra_color']); ?>">
                        <code><?php echo esc_html($design['zebra_color']); ?></code>
                        <p class="description">Hintergrundfarbe für ungerade Zeilen (nur wenn aktiviert)</p>
                    </td>
                </tr>
            </table>

            <h3 style="margin-top: 30px;">"Heute"-Marker</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Hintergrundfarbe</th>
                    <td>
                        <input type="color"
                               name="oz_oeffnungszeiten_design[heute_badge_bg]"
                               value="<?php echo esc_attr($design['heute_badge_bg']); ?>">
                        <code><?php echo esc_html($design['heute_badge_bg']); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Textfarbe</th>
                    <td>
                        <input type="color"
                               name="oz_oeffnungszeiten_design[heute_badge_text]"
                               value="<?php echo esc_attr($design['heute_badge_text']); ?>">
                        <code><?php echo esc_html($design['heute_badge_text']); ?></code>
                    </td>
                </tr>
            </table>

            <h3 style="margin-top: 30px;">Status-Farben</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Geschlossen-Farbe</th>
                    <td>
                        <input type="color"
                               name="oz_oeffnungszeiten_design[geschlossen_farbe]"
                               value="<?php echo esc_attr($design['geschlossen_farbe']); ?>">
                        <code><?php echo esc_html($design['geschlossen_farbe']); ?></code>
                        <p class="description">Textfarbe für "Geschlossen"</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Badge "Jetzt geöffnet"</th>
                    <td>
                        <label>Hintergrund</label>
                        <input type="color"
                               name="oz_oeffnungszeiten_design[offen_bg]"
                               value="<?php echo esc_attr($design['offen_bg']); ?>">
                        <code><?php echo esc_html($design['offen_bg']); ?></code>
                        &nbsp;&nbsp;
                        <label>Text</label>
                        <input type="color"
                               name="oz_oeffnungszeiten_design[offen_text]"
                               value="<?php echo esc_attr($design['offen_text']); ?>">
                        <code><?php echo esc_html($design['offen_text']); ?></code>
                        &nbsp;&nbsp;
                        <label>Punkt</label>
                        <input type="color"
                               name="oz_oeffnungszeiten_design[offen_dot]"
                               value="<?php echo esc_attr($design['offen_dot']); ?>">
                        <code><?php echo esc_html($design['offen_dot']); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Badge "Aktuell geschlossen"</th>
                    <td>
                        <label>Hintergrund</label>
                        <input type="color"
                               name="oz_oeffnungszeiten_design[zu_bg]"
                               value="<?php echo esc_attr($design['zu_bg']); ?>">
                        <code><?php echo esc_html($design['zu_bg']); ?></code>
                        &nbsp;&nbsp;
                        <label>Text</label>
                        <input type="color"
                               name="oz_oeffnungszeiten_design[zu_text]"
                               value="<?php echo esc_attr($design['zu_text']); ?>">
                        <code><?php echo esc_html($design['zu_text']); ?></code>
                        &nbsp;&nbsp;
                        <label>Punkt</label>
                        <input type="color"
                               name="oz_oeffnungszeiten_design[zu_dot]"
                               value="<?php echo esc_attr($design['zu_dot']); ?>">
                        <code><?php echo esc_html($design['zu_dot']); ?></code>
                    </td>
                </tr>
            </table>

            <?php submit_button('Einstellungen speichern'); ?>
        </form>

        <hr style="margin-top: 40px;">

        <h2>Shortcode-Anleitung</h2>
        <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px;">
            <h3>Wochenübersicht</h3>
            <code style="display: block; background: #f0f0f1; padding: 10px; margin: 10px 0;">[oeffnungszeiten]</code>
            <p>Zeigt eine Tabelle mit allen Wochentagen und den regulären Öffnungszeiten.</p>
            <h4 style="margin-top: 15px; font-size: 13px;">Parameter:</h4>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><code>title="Unsere Öffnungszeiten"</code> – Optional: Titel über der Tabelle</li>
                <li><code>title_tag="h2"</code> – Optional: HTML-Tag für den Titel (Standard: h2)</li>
            </ul>

            <h3 style="margin-top: 25px;">Heutiger Tag</h3>
            <code style="display: block; background: #f0f0f1; padding: 10px; margin: 10px 0;">[oeffnungszeiten_heute]</code>
            <p>Zeigt nur den heutigen Tag mit Öffnungszeiten. Berücksichtigt spezielle Öffnungszeiten automatisch.</p>

            <h3 style="margin-top: 25px;">Spezielle Öffnungszeiten</h3>
            <code style="display: block; background: #f0f0f1; padding: 10px; margin: 10px 0;">[oeffnungszeiten_spezial]</code>
            <p>Zeigt kommende spezielle Öffnungszeiten (Feiertage, Betriebsferien, etc.).</p>
            <h4 style="margin-top: 15px; font-size: 13px;">Parameter:</h4>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><code>tage="30"</code> – Nur Einträge bis X Tage in der Zukunft (Standard: 30)</li>
                <li><code>limit="5"</code> – Maximale Anzahl Einträge (optional)</li>
                <li><code>title="Besondere Tage"</code> – Optional: Titel (nur wenn Daten vorhanden)</li>
                <li><code>title_tag="h2"</code> – Optional: HTML-Tag für den Titel (Standard: h2)</li>
            </ul>

            <h3 style="margin-top: 25px;">Kompakte Übersicht (Footer/Sidebar)</h3>
            <code style="display: block; background: #f0f0f1; padding: 10px; margin: 10px 0;">[oeffnungszeiten_kompakt]</code>
            <p>Platzsparende Darstellung. Tage mit gleichen Zeiten werden automatisch zusammengefasst (z.B. "Montag – Freitag").</p>
            <h4 style="margin-top: 15px; font-size: 13px;">Parameter:</h4>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><code>status="1"</code> – "Jetzt geöffnet / Aktuell geschlossen" Badge anzeigen</li>
                <li><code>title="Öffnungszeiten"</code> – Optionaler Titel</li>
                <li><code>title_tag="h3"</code> – HTML-Tag für den Titel (Standard: h3)</li>
            </ul>
            <h4 style="margin-top: 15px; font-size: 13px;">Beispiel:</h4>
            <code style="display: block; background: #f0f0f1; padding: 10px; margin: 10px 0;">[oeffnungszeiten_kompakt title="Öffnungszeiten" status="1"]</code>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var spezialIndex = <?php echo !empty($spezial) ? max(array_keys($spezial)) + 1 : 0; ?>;

        $('#oz-add-spezial').on('click', function() {
            var row = `
                <tr>
                    <td><input type="date" name="oz_oeffnungszeiten_spezial[${spezialIndex}][datum]"></td>
                    <td>
                        <select name="oz_oeffnungszeiten_spezial[${spezialIndex}][status]" class="oz-status-select">
                            <option value="geschlossen">Geschlossen</option>
                            <option value="geaendert">Geänderte Zeiten</option>
                        </select>
                    </td>
                    <td class="oz-spezial-zeiten">
                        <input type="time" name="oz_oeffnungszeiten_spezial[${spezialIndex}][von1]" class="oz-time-input">
                        <span>–</span>
                        <input type="time" name="oz_oeffnungszeiten_spezial[${spezialIndex}][bis1]" class="oz-time-input">
                        <span style="margin: 0 5px;">/</span>
                        <input type="time" name="oz_oeffnungszeiten_spezial[${spezialIndex}][von2]" class="oz-time-input">
                        <span>–</span>
                        <input type="time" name="oz_oeffnungszeiten_spezial[${spezialIndex}][bis2]" class="oz-time-input">
                    </td>
                    <td><input type="text" name="oz_oeffnungszeiten_spezial[${spezialIndex}][bemerkung]" placeholder="z.B. Feiertag" class="regular-text"></td>
                    <td><button type="button" class="button oz-remove-row">×</button></td>
                </tr>
            `;
            $('#oz-spezial-body').append(row);
            spezialIndex++;
        });

        $(document).on('click', '.oz-remove-row', function() {
            $(this).closest('tr').remove();
        });

        $('.oz-offen-toggle').on('change', function() {
            var tag = $(this).data('tag');
            var cells = $('.oz-zeiten-cell[data-tag="' + tag + '"]');
            cells.find('input').prop('disabled', !this.checked);
            cells.css('opacity', this.checked ? 1 : 0.4);
        }).trigger('change');

        $(document).on('change', '.oz-status-select', function() {
            var zeitenCell = $(this).closest('tr').find('.oz-spezial-zeiten');
            var isGeaendert = $(this).val() === 'geaendert';
            zeitenCell.find('input').prop('disabled', !isGeaendert);
            zeitenCell.css('opacity', isGeaendert ? 1 : 0.4);
        });
        $('.oz-status-select').trigger('change');
    });
    </script>
    <?php
}
