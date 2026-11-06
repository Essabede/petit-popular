<?php
/**
 * Plugin Name: Petit Popular
 * Description: Rànquings de notícies i d'Events Calendar, amb períodes, factors i dissenys per web.
 * Version: 0.3.2
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Petit
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: petit-popular
 */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/includes.php';
require_once __DIR__ . '/saved.php';

final class Petit_Popular {
    const VERSION = '0.3.2';
    const OPTION = 'petit_popular_settings';

    public static function boot() {
        add_action('init', [__CLASS__, 'ensure_schema']);
        add_action('init', ['Petit_Popular_Saved', 'register']);
        add_action('wp_ajax_petit_popular_saved', ['Petit_Popular_Saved', 'ajax']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('wp_ajax_petit_popular_preview', [__CLASS__, 'preview']);
        add_action('wp_ajax_petit_popular_view', [__CLASS__, 'track']);
        add_action('wp_ajax_nopriv_petit_popular_view', [__CLASS__, 'track']);
        add_shortcode('petit_popular', [__CLASS__, 'shortcode']);
    }

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'petit_popular_daily';
    }

    // Lazy per-site provisioning supports network activation and newly created sites.
    public static function ensure_schema() {
        if (get_option('petit_popular_schema') === self::VERSION) { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $collate = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            post_id bigint(20) unsigned NOT NULL,
            view_date date NOT NULL,
            views bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (post_id,view_date),
            KEY view_date (view_date)
        ) $collate;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) {
            update_option('petit_popular_schema', self::VERSION, false);
        }
    }

    public static function settings() {
        return wp_parse_args(get_option(self::OPTION, []), [
            'post_factor' => 1, 'event_factor' => 1, 'news_category' => 'noticies',
            'brand' => 'custom', 'accent' => '#2e9c87', 'radius' => 16, 'gap' => 20,
        ]);
    }

    public static function brand_color($brand) {
        $colors = ['sabadell' => '#2e9c87', 'terrassa' => '#fc634f'];
        return $colors[$brand] ?? null;
    }

    public static function register_settings() {
        register_setting('petit_popular', self::OPTION, [
            'type' => 'array', 'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
        ]);
    }

    public static function sanitize_settings($input) {
        $input = is_array($input) ? $input : [];
        $old = self::settings();
        foreach (['post_factor', 'event_factor'] as $field) {
            $value = $input[$field] ?? $old[$field];
            if (!is_numeric($value) || !is_finite((float) $value) || $value < 0.01 || $value > 1000) {
                add_settings_error(self::OPTION, $field, 'Els factors han de ser entre 0,01 i 1.000.');
                $value = $old[$field];
            }
            $old[$field] = (float) $value;
        }
        $old['accent'] = sanitize_hex_color($input['accent'] ?? '') ?: $old['accent'];
        $brand = $input['brand'] ?? $old['brand'];
        $old['brand'] = in_array($brand, ['custom', 'sabadell', 'terrassa'], true) ? $brand : $old['brand'];
        $old['accent'] = self::brand_color($old['brand']) ?: $old['accent'];
        $old['radius'] = max(0, min(48, (int) ($input['radius'] ?? $old['radius'])));
        $old['gap'] = max(0, min(64, (int) ($input['gap'] ?? $old['gap'])));
        if (isset($input['news_category']) && is_scalar($input['news_category'])) {
            $slug = sanitize_title($input['news_category']);
            if ($slug && get_term_by('slug', $slug, 'category')) { $old['news_category'] = $slug; }
            else { add_settings_error(self::OPTION, 'news_category', 'Selecciona una categoria de notícies existent.'); }
        }
        return $old;
    }

    public static function menu() {
        add_options_page('Petit Popular', 'Petit Popular', 'manage_options', 'petit-popular', [__CLASS__, 'admin']);
    }

    public static function assets() {
        wp_register_style('petit-popular', plugins_url('assets/popular.css', __FILE__), [], self::VERSION);
        // Enqueued before wp_head, including shortcodes rendered later in the page.
        wp_enqueue_style('petit-popular');
        if (!is_singular(['post', 'tribe_events']) || is_preview() || current_user_can('edit_posts')) { return; }
        $id = get_queried_object_id();
        if (!self::public_post($id)) { return; }
        wp_enqueue_script('petit-popular-track', plugins_url('assets/track.js', __FILE__), [], self::VERSION, true);
        wp_localize_script('petit-popular-track', 'PetitPopular', [
            'url' => admin_url('admin-ajax.php'), 'id' => $id,
            'nonce' => wp_create_nonce('petit_popular_' . $id),
        ]);
    }

    private static function public_post($id) {
        $post = get_post($id);
        return $post && in_array($post->post_type, ['post', 'tribe_events'], true)
            && $post->post_status === 'publish' && $post->post_password === '';
    }

    public static function track() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_send_json_error(null, 405); }
        $id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!check_ajax_referer('petit_popular_' . $id, 'nonce', false)) { wp_send_json_error(null, 403); }
        if (!self::public_post($id) || current_user_can('edit_posts')) { wp_send_json_error(null, 403); }
        $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
        if ($agent === '' || preg_match('/bot|crawl|spider|slurp|headless|preview/i', $agent)) {
            wp_send_json_success(['counted' => false]);
        }
        global $wpdb;
        $result = $wpdb->query($wpdb->prepare(
            'INSERT INTO ' . self::table() . ' (post_id, view_date, views) VALUES (%d, %s, 1)
             ON DUPLICATE KEY UPDATE views = views + 1', $id, current_datetime()->format('Y-m-d')
        ));
        if ($result === false) { wp_send_json_error(null, 503); }
        // Never return the stored count or factor to a public request.
        wp_send_json_success(['counted' => true]);
    }

    public static function ranked($args) {
        global $wpdb;
        $type = $args['type'] === 'events' ? 'tribe_events' : 'post';
        $visits = Petit_Popular_Dates::range($args['period'], $args['days'], $args['from'], $args['to']);
        $conditions = ['p.post_type = %s', "p.post_status = 'publish'", "p.post_password = ''"];
        $values = [$type];
        if ($visits) {
            $conditions[] = 'v.view_date BETWEEN %s AND %s';
            array_push($values, $visits[0], $visits[1]);
        }
        if ($type === 'post') {
            $term = get_term_by('slug', self::settings()['news_category'], 'category');
            if (!$term) { return []; }
            $children = get_term_children((int) $term->term_id, 'category');
            $ids = array_merge([(int) $term->term_id], is_wp_error($children) ? [] : array_map('intval', $children));
            $conditions[] = "EXISTS (SELECT 1 FROM {$wpdb->term_relationships} nr
                INNER JOIN {$wpdb->term_taxonomy} nt ON nt.term_taxonomy_id = nr.term_taxonomy_id
                WHERE nr.object_id = p.ID AND nt.taxonomy = 'category' AND nt.term_id IN (" . implode(',', array_fill(0, count($ids), '%d')) . '))';
            array_push($values, ...$ids);
            $conditions[] = 'p.post_date >= %s AND p.post_date <= %s';
            $values[] = Petit_Popular_Dates::publication_start($args['post_months'] ?? 6);
            $values[] = current_datetime()->format('Y-m-d H:i:s');
        }
        if ($type === 'tribe_events') {
            if (!function_exists('tribe_get_events')) { throw new InvalidArgumentException('Cal activar The Events Calendar per mostrar esdeveniments.'); }
            $window = Petit_Popular_Dates::events($args['event_period'], $args['event_days'], $args['event_from'], $args['event_to']);
            if ($window) {
                // Use TEC's public query API, compatible with its own storage layer.
                $events = tribe_get_events([
                    'start_date' => $window[0] . ' 00:00:00', 'end_date' => $window[1] . ' 23:59:59',
                    'eventDisplay' => 'custom', 'posts_per_page' => -1, 'fields' => 'ids',
                    'post_status' => 'publish', 'suppress_filters' => false,
                ]);
                $ids = [];
                foreach ($events as $event) {
                    $event_id = is_object($event) ? $event->ID : $event;
                    if ($args['ongoing'] === 'no' && function_exists('tribe_get_start_date')) {
                        $start = tribe_get_start_date($event_id, false, 'Y-m-d');
                        if ($start < $window[0]) { continue; }
                    }
                    $ids[] = absint($event_id);
                }
                $ids = array_values(array_unique(array_filter($ids)));
                if (!$ids) { return []; }
                $conditions[] = 'p.ID IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')';
                array_push($values, ...$ids);
            }
        }
        if ($args['category'] !== '') {
            $conditions[] = "EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tr.object_id = p.ID AND tt.taxonomy = %s AND t.slug = %s)";
            $values[] = $type === 'post' ? 'category' : 'tribe_events_cat';
            $values[] = sanitize_title($args['category']);
        }
        $where = implode(' AND ', $conditions);
        $values[] = max(1, min(24, (int) $args['limit']));
        $sql = "SELECT p.ID, SUM(v.views) AS real_views FROM " . self::table() . " v
            INNER JOIN {$wpdb->posts} p ON p.ID = v.post_id WHERE $where
            GROUP BY p.ID ORDER BY real_views DESC, p.ID DESC LIMIT %d";
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    public static function defaults() {
        return ['type' => 'posts', 'period' => '7d', 'days' => 7, 'from' => '', 'to' => '',
            'event_period' => 'next', 'event_days' => 14, 'event_from' => '', 'event_to' => '', 'ongoing' => 'yes',
            'post_months' => 6, 'category' => '', 'limit' => 5, 'layout' => 'cards', 'columns' => 3, 'image_position' => 'side',
            'show_image' => 'yes', 'rank_size' => 32, 'show_count' => 'no', 'show_excerpt' => 'no', 'title' => '', 'accent' => '', 'radius' => '', 'gap' => '', 'font' => 'theme', 'title_size' => 20, 'title_color' => '', 'date_color' => '', 'brand' => 'site'];
    }

    public static function shortcode($atts = []) {
        if (is_array($atts) && array_key_exists('id', $atts)) {
            $saved = Petit_Popular_Saved::get(absint($atts['id']));
            if (is_wp_error($saved)) { return current_user_can('manage_options') ? '<p class="pp-notice">' . esc_html($saved->get_error_message()) . '</p>' : ''; }
            $atts = $saved['args'];
        }
        $a = shortcode_atts(self::defaults(), $atts, 'petit_popular');
        try {
            if (!in_array($a['type'], ['posts', 'events'], true)) { throw new InvalidArgumentException('Tipus de contingut no vàlid.'); }
            $rows = self::ranked($a);
        } catch (InvalidArgumentException $e) {
            return current_user_can('manage_options') ? '<p class="pp-notice">' . esc_html($e->getMessage()) . '</p>' : '';
        }
        if (!$rows) { return '<p class="pp-empty">Encara no hi ha continguts amb visites per a aquest període.</p>'; }
        return self::render($a, $rows);
    }

    private static function render($a, $rows) {
        $settings = self::settings();
        $factor = $settings[$a['type'] === 'events' ? 'event_factor' : 'post_factor'];
        $layout = in_array($a['layout'], ['cards', 'list', 'hero', 'vertical', 'plain'], true) ? $a['layout'] : 'cards';
        $image_position = $a['image_position'] === 'top' ? 'top' : 'side';
        $accent = sanitize_hex_color($a['accent']) ?: (self::brand_color($a['brand']) ?: $settings['accent']);
        $radius = $a['radius'] === '' ? $settings['radius'] : max(0, min(48, (int) $a['radius']));
        $gap = $a['gap'] === '' ? $settings['gap'] : max(0, min(64, (int) $a['gap']));
        $columns = max(1, min(4, (int) $a['columns']));
        $fonts = ['theme' => 'inherit', 'serif' => 'Georgia,serif', 'sans' => 'Arial,sans-serif', 'nunito' => 'Nunito,Arial,sans-serif'];
        $font = $fonts[$a['font']] ?? 'inherit';
        $title_size = max(14, min(48, (int) $a['title_size']));
        $rank_size = max(20, min(48, (int) $a['rank_size']));
        $image_class = $a['show_image'] === 'no' ? 'pp-no-image' : 'pp-with-image';
        $style = "--pp-rank-size:{$rank_size}px;--pp-font:$font;--pp-title-size:{$title_size}px;--pp-accent:$accent;--pp-radius:{$radius}px;--pp-gap:{$gap}px;--pp-columns:$columns";
        foreach (['title_color' => '--pp-title-color', 'date_color' => '--pp-date-color'] as $field => $property) {
            $color = sanitize_hex_color($a[$field]);
            if ($color) { $style .= ";$property:$color"; }
        }
        ob_start();
        ?>
        <section class="petit-popular pp-<?php echo esc_attr($layout); ?> pp-image-<?php echo esc_attr($image_position); ?> <?php echo esc_attr($image_class); ?>" style="<?php echo esc_attr($style); ?>" aria-label="<?php echo esc_attr($a['title'] ?: 'Continguts populars'); ?>">
            <?php if ($a['title'] !== '') : ?><h2 class="pp-heading"><?php echo esc_html($a['title']); ?></h2><?php endif; ?>
            <ol class="pp-items">
            <?php foreach ($rows as $index => $row) :
                $id = (int) $row->ID;
                $is_event = $a['type'] === 'events';
                $demo = isset($row->demo_title);
                $url = $demo ? '#' : get_permalink($id);
                $title = $demo ? $row->demo_title : get_the_title($id);
                ?>
                <li class="pp-item"><article class="pp-card">
                    <?php if ($a['show_image'] !== 'no') : ?>
                    <a class="pp-image" href="<?php echo esc_url($url); ?>" tabindex="-1" aria-hidden="true">
                        <?php if ($demo) { echo '<span class="pp-demo-art" aria-hidden="true"></span>'; } else { echo get_the_post_thumbnail($id, 'medium_large', ['loading' => 'lazy', 'alt' => '']); } ?>
                        <span class="pp-rank"><?php echo (int) $index + 1; ?></span>
                    </a>
                    <?php endif; ?>
                    <div class="pp-body">
                        <?php if ($a['show_image'] === 'no') : ?><span class="pp-rank" aria-hidden="true"><?php echo (int) $index + 1; ?></span><?php endif; ?>
                        <h3 class="pp-title"><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a></h3>
                        <?php
                        $date = $demo ? $row->demo_date->format('d/m/Y') : ($is_event && function_exists('tribe_get_start_date')
                            ? tribe_get_start_date($id, false, 'd/m/Y') : get_the_date('d/m/Y', $id));
                        $machine_date = $demo ? $row->demo_date->format('Y-m-d') : ($is_event && function_exists('tribe_get_start_date')
                            ? tribe_get_start_date($id, false, 'Y-m-d') : get_the_date('Y-m-d', $id));
                        ?>
                        <p class="pp-meta"><time datetime="<?php echo esc_attr($machine_date); ?>"><?php echo esc_html($date); ?></time></p>
                        <?php if ($a['show_count'] === 'yes') : ?><span class="pp-score"><?php echo esc_html(number_format_i18n(Petit_Popular_Dates::score($row->real_views, $factor))); ?> visualitzacions</span><?php endif; ?>
                    </div>
                </article></li>
            <?php endforeach; ?>
            </ol>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function preview() {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message' => 'Accés restringit.'], 403); }
        if (!check_ajax_referer('petit_popular_preview', 'nonce', false)) { wp_send_json_error(['message' => 'La sessió ha caducat. Recarrega la pàgina.'], 403); }
        $input = isset($_POST['args']) && is_array($_POST['args']) ? wp_unslash($_POST['args']) : [];
        $args = [];
        foreach (self::defaults() as $key => $default) {
            $args[$key] = isset($input[$key]) && is_scalar($input[$key]) ? sanitize_text_field((string) $input[$key]) : $default;
        }
        if (!in_array($args['type'], ['posts', 'events'], true)) { wp_send_json_error(['message' => 'Tipus de contingut no vàlid.'], 400); }
        $demo = ($_POST['mode'] ?? '') !== 'ranking';
        if ($demo) {
            $titles = $args['type'] === 'events'
                ? ['Un matí de contes i aventures', 'Música per compartir en família', 'Un taller per descobrir i crear']
                : ['Una ciutat per descobrir en família', 'Petites històries que fan gran el barri', 'Noves propostes per gaudir del cap de setmana'];
            $rows = [];
            $limit = max(1, min(24, (int) $args['limit']));
            for ($i = 0; $i < $limit; $i++) {
                $rows[] = (object) ['ID' => 0, 'real_views' => 120 - $i * 3,
                    'demo_title' => $titles[$i % count($titles)],
                    'demo_date' => current_datetime()->modify(($args['type'] === 'events' ? '+' : '-') . $i . ' days')];
            }
            $html = self::render($args, $rows);
        } else {
            $html = self::shortcode($args);
        }
        wp_send_json_success(['html' => $html, 'demo' => $demo]);
    }

    public static function admin() {
        if (!current_user_can('manage_options')) { return; }
        $s = self::settings();
        ?>
        <div class="wrap"><h1>Petit Popular</h1>
        <p>Configuració exclusiva de <strong><?php echo esc_html(get_bloginfo('name')); ?></strong>. Les visites i els factors no es comparteixen amb les altres webs del multisite.</p>
        <?php settings_errors(self::OPTION); ?>
        <details class="pp-site-settings"><summary>Configuració de la web i multiplicadors</summary>
        <form action="options.php" method="post">
        <?php settings_fields('petit_popular'); ?>
        <table class="form-table" role="presentation">
        <tr><th><label for="pp-news-category">Categoria de notícies</label></th><td><select id="pp-news-category" name="<?php echo esc_attr(self::OPTION); ?>[news_category]">
        <?php $terms = get_terms(['taxonomy' => 'category', 'hide_empty' => false]); ?>
        <option value="">Selecciona la categoria de notícies</option>
        <?php foreach (is_wp_error($terms) ? [] : $terms as $term) : ?><option value="<?php echo esc_attr($term->slug); ?>" <?php selected($s['news_category'], $term->slug); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?>
        </select><p class="description">Els rànquings de notícies només inclouen aquesta categoria i les seves subcategories.</p></td></tr>
        <tr><th><label for="pp-site-brand">Identitat del mitjà</label></th><td><select id="pp-site-brand" name="<?php echo esc_attr(self::OPTION); ?>[brand]">
        <?php foreach (['custom' => 'Personalitzat', 'sabadell' => 'Petit Sabadell · verd #2E9C87', 'terrassa' => 'Petit Terrassa · corall #FC634F'] as $value => $label) : ?>
        <option value="<?php echo esc_attr($value); ?>" <?php selected($s['brand'], $value); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?></select><p class="description">Colors del manual corporatiu. Per definir un altre color, selecciona Personalitzat.</p></td></tr>
        <?php foreach (['post_factor' => 'Factor de notícies', 'event_factor' => 'Factor d’esdeveniments', 'radius' => 'Arrodoniment (px)', 'gap' => 'Espai entre elements (px)'] as $key => $label) : ?>
            <tr><th scope="row"><label for="pp-site-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td><input id="pp-site-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(self::OPTION . '[' . $key . ']'); ?>" type="number" min="<?php echo strpos($key, 'factor') !== false ? '0.01' : '0'; ?>" max="<?php echo strpos($key, 'factor') !== false ? '1000' : ($key === 'gap' ? '64' : '48'); ?>" step="<?php echo strpos($key, 'factor') !== false ? '0.01' : '1'; ?>" value="<?php echo esc_attr($s[$key]); ?>"></td></tr>
        <?php endforeach; ?>
        <tr><th><label for="pp-site-accent">Color d’accent</label></th><td><input id="pp-site-accent" type="color" name="<?php echo esc_attr(self::OPTION); ?>[accent]" value="<?php echo esc_attr($s['accent']); ?>"></td></tr>
        </table>
        <p>El factor s’aplica només a la sortida pública del plugin. L’ordre depèn de les visites reals. La xifra pública de visualitzacions incorpora el factor configurat; les visites reals es conserven internament.</p>
        <?php submit_button(); ?></form></details>
        <h2>Els teus rànquings</h2>
        <?php if (!get_term_by('slug', $s['news_category'], 'category')) : ?><div class="notice notice-warning inline"><p>Selecciona i desa la categoria de notícies a «Configuració de la web i multiplicadors». Fins llavors, els rànquings reals de notícies no mostraran resultats.</p></div><?php endif; ?>
        <div class="pp-library"><label for="pp-saved-select">Rànquings desats</label><select id="pp-saved-select"><option value="">Tria un rànquing</option></select><button type="button" id="pp-load" class="button">Obre</button><button type="button" id="pp-new" class="button">Nou rànquing</button><button type="button" id="pp-delete" class="button">Elimina</button>
        <details><summary>Recupera un shortcode anterior</summary><label for="pp-import-code">Enganxa el codi complet</label><textarea id="pp-import-code" rows="3"></textarea><button type="button" id="pp-import" class="button">Recupera les opcions</button><p>Un codi antic es pot editar i desar amb nom. Per rebre els canvis futurs cal substituir-lo pel nou codi amb ID.</p></details><p id="pp-library-status" role="status" aria-live="polite"></p></div>
        <h2>Crea o edita el rànquing</h2>
        <p>Tria el contingut, el període i el disseny. Veuràs el resultat a la dreta abans d’inserir-lo a la web.</p>
        <div id="pp-builder" class="pp-workspace"><div class="pp-controls"><fieldset class="pp-control-group"><legend>1. Contingut i dates</legend>
        <?php self::select('type', 'Contingut', ['posts' => 'Notícies', 'events' => 'Esdeveniments']); ?>
        <?php self::input('post_months', 'Notícies publicades en els últims X mesos', 'number', '6'); ?>
        <?php self::select('period', 'Visites rebudes durant', ['7d' => 'Últims 7 dies', '30d' => 'Últims 30 dies', 'today' => 'Avui', 'week' => 'Aquesta setmana', 'month' => 'Aquest mes', 'days' => 'Últims X dies', 'custom' => 'Entre dues dates', 'all' => 'Tot l’històric']); ?>
        <?php self::input('days', 'X dies de visites', 'number', '7'); self::input('from', 'Visites des de', 'date'); self::input('to', 'Visites fins a', 'date'); ?>
        <?php self::select('event_period', 'Celebració dels esdeveniments', ['next' => 'Pròxims X dies', 'today' => 'Avui', 'weekend' => 'Aquest cap de setmana', 'month' => 'Aquest mes', 'custom' => 'Entre dues dates', 'all' => 'Sense filtre']); ?>
        <?php self::input('event_days', 'X dies d’agenda', 'number', '14'); self::input('event_from', 'Agenda des de', 'date'); self::input('event_to', 'Agenda fins a', 'date'); ?>
        <?php self::select('ongoing', 'Incloure esdeveniments en curs', ['yes' => 'Sí', 'no' => 'No']); ?>
        </fieldset><fieldset class="pp-control-group"><legend>2. Disseny</legend>
        <?php self::select('layout', 'Disseny', ['cards' => 'Targetes', 'list' => 'Llista amb imatges', 'hero' => 'Primer destacat', 'vertical' => 'Rànquing vertical', 'plain' => 'Llistat sense targetes']); ?>
        <?php self::select('show_image', 'Fotografia', ['yes' => 'Amb foto', 'no' => 'Sense foto']); ?>
        <?php self::select('rank_size', 'Mida dels números', ['32' => 'Gran', '24' => 'Visible', '40' => 'Molt gran']); ?>
        <?php self::select('image_position', 'Imatge al rànquing vertical', ['side' => 'Al costat del text (compacte)', 'top' => 'Al damunt del text (targetes apilades)']); ?>
        <?php self::select('show_count', 'Mostrar visualitzacions', ['no' => 'No', 'yes' => 'Sí']); ?>
        <?php self::select('brand', 'Identitat d’aquest rànquing', ['site' => 'La configurada a la web', 'sabadell' => 'Petit Sabadell', 'terrassa' => 'Petit Terrassa']); ?>
        <?php self::select('font', 'Tipografia', ['theme' => 'La del tema', 'serif' => 'Editorial (Georgia)', 'sans' => 'Sense serifa (Arial)', 'nunito' => 'Nunito (si està instal·lada al tema)']); ?>
        <?php self::input('title_size', 'Mida dels titulars (px)', 'number', '20'); ?>
        <p><label for="pp-color-mode">Color del rànquing</label><select id="pp-color-mode"><option value="brand">Utilitza el color del mitjà</option><option value="custom">Tria un altre color</option></select></p>
        <p><label for="pp-accent-picker">Tria el color</label><input type="color" id="pp-accent-picker" value="<?php echo esc_attr($s['accent']); ?>"><input type="hidden" id="pp-accent" data-pp="accent" value=""></p>
        <?php foreach (['title_color' => 'Color dels titulars', 'date_color' => 'Color de les dates'] as $field => $label) : ?>
        <p><label for="pp-<?php echo esc_attr($field); ?>-mode"><?php echo esc_html($label); ?></label><select id="pp-<?php echo esc_attr($field); ?>-mode"><option value="default">Predeterminat</option><option value="custom">Tria un color</option></select></p>
        <p><label for="pp-<?php echo esc_attr($field); ?>-picker"><?php echo esc_html($label); ?></label><input type="color" id="pp-<?php echo esc_attr($field); ?>-picker" value="<?php echo $field === 'title_color' ? '#20252d' : '#526070'; ?>"><input type="hidden" data-pp="<?php echo esc_attr($field); ?>" id="pp-<?php echo esc_attr($field); ?>" value=""></p>
        <?php endforeach; ?>
        <?php self::select('radius', 'Forma de les cantonades', ['' => 'La configurada a la web', '0' => 'Rectes', '8' => 'Lleugerament arrodonides', '16' => 'Arrodonides', '24' => 'Molt arrodonides']); ?>
        <?php self::select('gap', 'Separació entre elements', ['' => 'La configurada a la web', '8' => 'Compacta', '16' => 'Normal', '24' => 'Àmplia', '32' => 'Molt àmplia']); ?>
        <?php self::input('title', 'Títol del rànquing'); self::input('category', 'Slug de categoria (opcional)'); self::input('limit', 'Nombre d’elements (1–24)', 'number', '5'); self::input('columns', 'Columnes (1–4)', 'number', '3'); ?>
        </fieldset><div class="pp-insert"><h3>3. Desa’l i insereix-lo a WordPress</h3><label for="pp-ranking-name">Nom intern del rànquing</label><input id="pp-ranking-name" type="text" maxlength="160" placeholder="Rànquing de la portada"><button type="button" id="pp-save" class="button button-primary">Desa el rànquing</button><p id="pp-save-status" role="status"></p><p>Copia el codi i enganxa’l en un bloc «Shortcode» de la pàgina o del widget.</p>
        <label for="pp-code" class="screen-reader-text">Codi del rànquing</label><textarea readonly id="pp-code" rows="4"></textarea>
        <button type="button" id="pp-copy" class="button button-primary">Copia el codi</button><span id="pp-copy-status" role="status"></span></div></div>
        <div class="pp-preview-panel"><div class="pp-preview-toolbar"><h3>Previsualització</h3>
        <label for="pp-preview-mode">Continguts</label><select id="pp-preview-mode"><option value="demo">Mostra d’estil</option><option value="ranking">Rànquing real</option></select>
        <label for="pp-preview-width">Amplada</label><select id="pp-preview-width"><option value="100%">Completa</option><option value="340px">Barra lateral</option></select></div>
        <p id="pp-preview-note">Mostra amb textos, imatges i xifres fictícies. Els filtres temporals s’apliquen al rànquing real.</p>
        <p id="pp-preview-status" role="status" aria-live="polite"></p><iframe id="pp-preview-frame" title="Previsualització del rànquing" sandbox="allow-same-origin"></iframe>
        <p class="description">El tema de la web pot modificar la tipografia i els espais. Desa els canvis dels multiplicadors abans de previsualitzar-los.</p></div></div>
        <h2>Visites reals · últims 7 dies</h2>
        <p>Aquesta informació només es mostra a administradors. El recompte comença en activar el plugin; no importa l’històric d’altres plugins.</p>
        <?php foreach (['posts' => 'Notícies', 'events' => 'Esdeveniments'] as $type => $label) : ?>
            <h3><?php echo esc_html($label); ?></h3><ul>
            <?php
            try {
                $rows = self::ranked(array_merge(self::defaults(), ['type' => $type, 'event_period' => 'all']));
                if (!$rows) { echo '<li>Encara no hi ha visites.</li>'; }
                foreach ($rows as $row) { echo '<li>' . esc_html(get_the_title($row->ID)) . ': ' . esc_html(number_format_i18n($row->real_views)) . ' visites reals</li>'; }
            } catch (InvalidArgumentException $e) { echo '<li>' . esc_html($e->getMessage()) . '</li>'; }
            ?></ul>
        <?php endforeach; ?>
        <p>Comptem càrregues de pàgina amb JavaScript, excloent editors i bots identificables. No són persones úniques ni una mètrica auditada. No desem IP ni identificadors de visitant. Una pàgina mantinguda en memòria cau més de 12 hores pot deixar de comptar fins que es renovi el nonce.</p>
        </div>
        <?php
    }

    public static function admin_assets($hook) {
        if ($hook !== 'settings_page_petit-popular') { return; }
        wp_enqueue_style('petit-popular-admin', plugins_url('assets/admin.css', __FILE__), [], self::VERSION);
        wp_enqueue_script('petit-popular-builder', plugins_url('assets/builder.js', __FILE__), [], self::VERSION, true);
        wp_localize_script('petit-popular-builder', 'PetitPopularBuilder', [
            'url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('petit_popular_preview'),
            'styles' => plugins_url('assets/popular.css', __FILE__), 'defaults' => self::defaults(), 'saved' => Petit_Popular_Saved::listing(),
        ]);
    }

    private static function select($key, $label, $options) {
        echo '<p><label for="pp-' . esc_attr($key) . '">' . esc_html($label) . '</label><br><select id="pp-' . esc_attr($key) . '" data-pp="' . esc_attr($key) . '">';
        foreach ($options as $value => $text) { echo '<option value="' . esc_attr($value) . '">' . esc_html($text) . '</option>'; }
        echo '</select></p>';
    }
    private static function input($key, $label, $type = 'text', $value = '') {
        echo '<p><label for="pp-' . esc_attr($key) . '">' . esc_html($label) . '</label><br><input id="pp-' . esc_attr($key) . '" data-pp="' . esc_attr($key) . '" type="' . esc_attr($type) . '" value="' . esc_attr($value) . '"></p>';
    }
}
Petit_Popular::boot();
