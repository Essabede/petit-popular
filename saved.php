<?php
if (!defined('ABSPATH')) { exit; }

final class Petit_Popular_Saved {
    const TYPE = 'pp_saved_ranking';
    public static function register() {
        register_post_type(self::TYPE, ['label' => 'Rànquings', 'public' => false, 'publicly_queryable' => false,
            'show_ui' => false, 'show_in_rest' => false, 'rewrite' => false, 'query_var' => false, 'supports' => ['title']]);
    }
    public static function listing() {
        $posts = get_posts(['post_type' => self::TYPE, 'post_status' => 'publish', 'posts_per_page' => -1,
            'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true]);
        return array_map(static function ($post) { return ['id' => $post->ID, 'name' => $post->post_title]; }, $posts);
    }
    public static function get($id) {
        $post = $id ? get_post($id) : null;
        if (!$post || $post->post_type !== self::TYPE || $post->post_status !== 'publish') {
            return new WP_Error('not_found', 'No s’ha trobat aquest rànquing en aquesta web.');
        }
        $args = get_post_meta($id, '_pp_args', true);
        if (!is_array($args)) { return new WP_Error('invalid', 'La configuració del rànquing no és vàlida.'); }
        return ['id' => $id, 'name' => $post->post_title, 'args' => wp_parse_args($args, Petit_Popular::defaults())];
    }
    public static function normalize($input) {
        $args = Petit_Popular::defaults();
        foreach ($args as $key => $default) {
            if (isset($input[$key])) {
                if (!is_scalar($input[$key])) { throw new InvalidArgumentException('Hi ha una opció amb un format no vàlid.'); }
                $args[$key] = sanitize_text_field((string) $input[$key]);
            }
        }
        $enums = ['type' => ['posts','events'], 'layout' => ['cards','list','vertical','hero','plain'],
            'image_position' => ['side','top'], 'show_image' => ['yes','no'], 'show_count' => ['yes','no'], 'ongoing' => ['yes','no'],
            'brand' => ['site','sabadell','terrassa'], 'font' => ['theme','serif','sans','nunito']];
        foreach ($enums as $key => $allowed) {
            if (!in_array($args[$key], $allowed, true)) { throw new InvalidArgumentException('Valor no vàlid: ' . $key); }
        }
        Petit_Popular_Dates::range($args['period'], $args['days'], $args['from'], $args['to']);
        if ($args['type'] === 'posts') { Petit_Popular_Dates::publication_start($args['post_months']); }
        else { Petit_Popular_Dates::events($args['event_period'], $args['event_days'], $args['event_from'], $args['event_to']); }
        foreach (['limit' => [1,24], 'columns' => [1,4], 'title_size' => [14,48], 'rank_size' => [20,48], 'radius' => [0,48], 'gap' => [0,64]] as $key => $bounds) {
            if ($args[$key] === '') { continue; }
            if (!preg_match('/^\d+$/', (string) $args[$key]) || $args[$key] < $bounds[0] || $args[$key] > $bounds[1]) {
                throw new InvalidArgumentException('Valor fora de rang: ' . $key);
            }
        }
        foreach (['accent', 'title_color', 'date_color'] as $field) {
            if ($args[$field] !== '' && !sanitize_hex_color($args[$field])) { throw new InvalidArgumentException('El color ha de ser hexadecimal.'); }
        }
        return $args;
    }
    public static function import($code) {
        $code = trim($code);
        if (!preg_match('/^' . get_shortcode_regex(['petit_popular']) . '$/s', $code, $matches) || !empty($matches[1]) || !empty($matches[6]) || !empty($matches[5])) {
            throw new InvalidArgumentException('Enganxa un únic shortcode [petit_popular ...] vàlid.');
        }
        $atts = shortcode_parse_atts($matches[3]);
        if (!is_array($atts)) { $atts = []; }
        foreach ($atts as $key => $value) {
            if (!is_string($key) || (!array_key_exists($key, Petit_Popular::defaults()) && $key !== 'id')) {
                throw new InvalidArgumentException('El shortcode conté una opció desconeguda.');
            }
        }
        if (isset($atts['id'])) {
            $saved = self::get(absint($atts['id']));
            if (is_wp_error($saved)) { throw new InvalidArgumentException($saved->get_error_message()); }
            return $saved;
        }
        return ['id' => 0, 'name' => '', 'args' => self::normalize($atts)];
    }
    public static function ajax() {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message' => 'Accés restringit.'], 403); }
        if (!check_ajax_referer('petit_popular_preview', 'nonce', false)) { wp_send_json_error(['message' => 'La sessió ha caducat. Recarrega la pàgina.'], 403); }
        $operation = isset($_POST['operation']) && is_string($_POST['operation']) ? $_POST['operation'] : '';
        $id = isset($_POST['id']) && is_scalar($_POST['id']) ? absint($_POST['id']) : 0;
        try {
            if ($operation === 'list') { wp_send_json_success(['saved' => self::listing()]); }
            if ($operation === 'import') {
                $code = isset($_POST['code']) && is_string($_POST['code']) ? wp_unslash($_POST['code']) : '';
                wp_send_json_success(self::import($code));
            }
            if ($id) {
                $existing = self::get($id);
                if (is_wp_error($existing)) { throw new InvalidArgumentException($existing->get_error_message()); }
            }
            if ($operation === 'load' && $id) { wp_send_json_success($existing); }
            if ($operation === 'delete' && $id) {
                if (!wp_delete_post($id, true)) { throw new InvalidArgumentException('No s’ha pogut eliminar el rànquing.'); }
                wp_send_json_success(['saved' => self::listing()]);
            }
            if ($operation !== 'save') { throw new InvalidArgumentException('Operació no vàlida.'); }
            $name = isset($_POST['name']) && is_string($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
            if ($name === '' || mb_strlen($name) > 160) { throw new InvalidArgumentException('Escriu un nom d’entre 1 i 160 caràcters.'); }
            $input = isset($_POST['args']) && is_array($_POST['args']) ? wp_unslash($_POST['args']) : [];
            $args = self::normalize($input);
            $post_id = wp_insert_post(wp_slash(['ID' => $id, 'post_type' => self::TYPE, 'post_status' => 'publish', 'post_title' => $name]), true);
            if (is_wp_error($post_id)) { throw new InvalidArgumentException('No s’ha pogut desar el rànquing.'); }
            update_post_meta($post_id, '_pp_args', wp_slash($args));
            wp_send_json_success(['ranking' => self::get($post_id), 'saved' => self::listing()]);
        } catch (InvalidArgumentException $e) { wp_send_json_error(['message' => $e->getMessage()], 400); }
    }
}
