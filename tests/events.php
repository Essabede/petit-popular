<?php
// Contract fixture based on TEC's documented repository behavior; not Events Calendar Pro itself.
if (!function_exists('tribe_get_events')) {
    function tribe_get_events($query) {
        $GLOBALS['pp_last_event_query'] = $query;
        $out = [];
        foreach ($GLOBALS['pp_event_fixtures'] as $id) {
            if (in_array($id, $GLOBALS['pp_provider_excluded'] ?? [], true)) { continue; }
            $start = get_post_meta($id, '_pp_start', true); $end = get_post_meta($id, '_pp_end', true);
            if (isset($query['date_overlaps'])) {
                [$lower, $upper] = $query['date_overlaps'];
                if ($start > $upper || $end < $lower) { continue; }
            } else {
                if (isset($query['start_date']) && $start < $query['start_date']) { continue; }
                if (isset($query['end_date']) && $end > $query['end_date']) { continue; }
            }
            $out[] = ($query['fields'] ?? 'all') === 'ids' ? $id : get_post($id);
        }
        return $out;
    }
    function tribe_get_start_date($id, $with_time = false, $format = 'Y-m-d') { return (new DateTimeImmutable(get_post_meta($id, '_pp_start', true), wp_timezone()))->format($format); }
    function tribe_get_end_date($id, $with_time = false, $format = 'Y-m-d') { return (new DateTimeImmutable(get_post_meta($id, '_pp_end', true), wp_timezone()))->format($format); }
}
register_post_type('tribe_events', ['public' => true]);
$GLOBALS['pp_event_fixtures'] = [];
function pp_event_fixture($title, $start, $end, $views) {
    $id = wp_insert_post(['post_type' => 'tribe_events', 'post_status' => 'publish', 'post_title' => $title]);
    update_post_meta($id, '_pp_start', $start); update_post_meta($id, '_pp_end', $end);
    $GLOBALS['pp_event_fixtures'][] = $id;
    if ($views) pp_seed($id, current_datetime()->format('Y-m-d'), $views);
    return $id;
}
$slime = pp_event_fixture('Taller La química de l’slime', '2026-11-12 17:00:00', '2026-11-12 18:00:00', 26);
$ongoing = pp_event_fixture('Exposició en curs', '2026-11-01 10:00:00', '2027-01-01 20:00:00', 10);
$cross_end = pp_event_fixture('Comença dins i acaba després', '2026-11-30 10:00:00', '2027-01-02 20:00:00', 9);
$outside = pp_event_fixture('Fora del període', '2026-11-08 10:00:00', '2026-11-08 20:00:00', 100);
$no_views = pp_event_fixture('Activitat sense visites', '2026-11-20 10:00:00', '2026-11-20 11:00:00', 0);
$event_args = array_merge(Petit_Popular::defaults(), ['type' => 'events', 'period' => '30d', 'event_period' => 'custom', 'event_from' => '2026-11-09', 'event_to' => '2026-12-08']);
$event_rows = Petit_Popular::ranked($event_args);
$event_ids = array_map(static fn($row) => (int) $row->ID, $event_rows);
pp_check(in_array($slime, $event_ids, true) && (int) $event_rows[0]->real_views === 26, 'Nov 12 unique event with 26 recent views appears in Nov 9-Dec 8 ranking');
pp_check(in_array($ongoing, $event_ids, true) && in_array($cross_end, $event_ids, true), 'Overlap admits ongoing events and events ending after the window');
pp_check(!in_array($outside, $event_ids, true) && !in_array($no_views, $event_ids, true), 'Ranking excludes outside dates and events without views');
pp_check(isset($GLOBALS['pp_last_event_query']['date_overlaps']) && !isset($GLOBALS['pp_last_event_query']['start_date']), 'Uses official TEC inclusive-overlap query rather than full containment');
$without_ongoing = Petit_Popular::ranked(array_merge($event_args, ['ongoing' => 'no']));
$without_ids = array_map(static fn($row) => (int) $row->ID, $without_ongoing);
pp_check(!in_array($ongoing, $without_ids, true) && in_array($cross_end, $without_ids, true), 'Excluding ongoing only excludes events started before first day');
wp_set_current_user(1); $nonce = wp_create_nonce('petit_popular_preview');
$real_preview = pp_preview($event_args, $nonce, 'content');
pp_check($real_preview['success'] && strpos($real_preview['data']['html'], 'Activitat sense visites') !== false, 'Content preview includes real eligible events without visits');
pp_check(strpos($real_preview['data']['html'], '12/11/2026') !== false, 'Content preview uses real event dates');
file_put_contents(__DIR__ . '/content-fixture.html', $real_preview['data']['html']);
$no_fake_counts = pp_preview(array_merge($event_args, ['show_count' => 'yes']), $nonce, 'content');
pp_check(strpos($no_fake_counts['data']['html'], 'pp-score') === false, 'Content preview never fabricates visit counts');
$GLOBALS['pp_provider_excluded'] = [$slime];
$diag_preview = pp_preview($event_args, $nonce, 'ranking');
$slime_diag = array_values(array_filter($diag_preview['data']['diagnostics'], static fn($row) => strpos($row['title'], 'slime') !== false));
pp_check(count($slime_diag) === 1 && strpos($slime_diag[0]['reason'], 'no el retorna') !== false, 'Diagnostics identify eligible event excluded by calendar provider');
pp_check($slime_diag[0]['views'] === 26 && $slime_diag[0]['start'] === '2026-11-12', 'Diagnostics show real visits and event date for administrator');
$GLOBALS['pp_provider_excluded'] = [];
$hidden_rank = Petit_Popular::shortcode(['rank_style' => 'none', 'show_image' => 'no']);
pp_check(strpos($hidden_rank, 'class="pp-rank"') === false, 'No rank badge rendered when numbers hidden');
$light_rank = Petit_Popular::shortcode(['rank_style' => 'number', 'rank_size' => 18, 'rank_weight' => 400, 'rank_color' => '#888888']);
pp_check(strpos($light_rank, 'pp-rank-number') !== false && strpos($light_rank, '--pp-rank-weight:400') !== false && strpos($light_rank, '--pp-rank-size:18px') !== false, 'Discreet numbers use independent size and weight');
$clear_rank = Petit_Popular::shortcode(['rank_background' => 'transparent', 'rank_border' => 'none']);
pp_check(strpos($clear_rank, '--pp-rank-bg:transparent') !== false && strpos($clear_rank, '--pp-rank-border:transparent') !== false, 'Rank background and border can be removed');
pp_check(Petit_Popular::defaults()['rank_style'] === 'circle' && Petit_Popular::builder_defaults()['rank_style'] === 'number', 'Old shortcodes retain circle while new builder uses discreet style');
$compact_posts = [];
foreach (['El Parc Taulí transforma en vídeos el programa de suport a famílies amb fills amb TDAH', 'El Festival elPetit creix de Sabadell fins a 14 ciutats amb gairebé un centenar de funcions'] as $i => $headline) {
    $post_id = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => $headline]);
    wp_set_post_terms($post_id, [get_term_by('slug', 'noticies', 'category')->term_id], 'category');
    pp_seed($post_id, current_datetime()->format('Y-m-d'), 50 - $i * 5);
    $compact_posts[] = $post_id;
}
$density_args = ['layout' => 'plain', 'show_image' => 'no', 'rank_size' => 16, 'rank_style' => 'circle', 'title_size' => 16, 'show_count' => 'yes', 'date_color' => '#ffffff', 'hover_color' => '#e3708f'];
foreach (['comfortable','compact','tight'] as $density) {
    $density_html = Petit_Popular::shortcode(array_merge($density_args, ['density' => $density]));
    pp_check(strpos($density_html, 'pp-density-' . $density) !== false && strpos($density_html, '--pp-hover-color:#e3708f') !== false, 'Density and hover settings render: ' . $density);
    file_put_contents(__DIR__ . '/density-' . $density . '-fixture.html', $density_html);
}
$not_compact_cards = Petit_Popular::shortcode(['layout' => 'cards', 'show_image' => 'no', 'density' => 'tight']);
pp_check(strpos($not_compact_cards, 'pp-compact-list') === false, 'Density does not change card layouts');
pp_check(Petit_Popular::defaults()['density'] === 'comfortable' && Petit_Popular::builder_defaults()['density'] === 'compact', 'Legacy density retained while new builder suggests compact');
foreach ($compact_posts as $post_id) { wp_delete_post($post_id, true); }
