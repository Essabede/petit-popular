<?php
// Run in a disposable WordPress, never on a production database.
require_once '/wordpress/wp-load.php';
require_once '/wordpress/wp-admin/includes/plugin.php';
require_once '/wordpress/wp-admin/includes/template.php';
activate_plugin('petit-popular/petit-popular.php', '', is_multisite());
Petit_Popular::ensure_schema();
Petit_Popular_Saved::register();
$checks = 0;
function pp_check($condition, $label) {
    global $checks;
    $checks++;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
}
function pp_seed($id, $day, $views) {
    global $wpdb;
    $wpdb->replace(Petit_Popular::table(), ['post_id' => $id, 'view_date' => $day, 'views' => $views]);
}
$today = current_datetime()->format('Y-m-d');
$old = current_datetime()->modify('-40 days')->format('Y-m-d');
$news = wp_insert_term('Notícies', 'category', ['slug' => 'noticies']);
$a = wp_insert_post(['post_title' => 'Notícia A', 'post_content' => 'Una notícia de prova.', 'post_status' => 'publish']);
$b = wp_insert_post(['post_title' => 'Notícia B', 'post_status' => 'publish']);
$draft = wp_insert_post(['post_title' => 'Privat', 'post_status' => 'draft']);
$protected = wp_insert_post(['post_title' => 'Contrasenya', 'post_status' => 'publish', 'post_password' => 'test']);
wp_set_post_terms($a, [$news['term_id']], 'category');
wp_set_post_terms($b, [$news['term_id']], 'category');
pp_seed($a, $today, 11); pp_seed($a, $old, 1000); pp_seed($b, $today, 8);
pp_seed($draft, $today, 9999); pp_seed($protected, $today, 9999);
$args = Petit_Popular::defaults();
$rows = Petit_Popular::ranked($args);
pp_check(count($rows) === 2, 'Only public unprotected posts');
pp_check((int) $rows[0]->ID === $a && (int) $rows[0]->real_views === 11, 'Recent period excludes historical views');
$all = Petit_Popular::ranked(array_merge($args, ['period' => 'all']));
pp_check((int) $all[0]->real_views === 1011, 'All-time includes historical views');
$custom = Petit_Popular::ranked(array_merge($args, ['period' => 'custom', 'from' => $old, 'to' => $old]));
pp_check(count($custom) === 1 && (int) $custom[0]->real_views === 1000, 'Exact date interval');
pp_check(count(Petit_Popular::ranked(array_merge($args, ['limit' => 1]))) === 1, 'Limit');
$cat = wp_insert_term('Ciutat', 'category', ['slug' => 'ciutat', 'parent' => $news['term_id']]);
wp_set_post_terms($b, [$cat['term_id']], 'category');
$filtered = Petit_Popular::ranked(array_merge($args, ['category' => 'ciutat']));
pp_check(count($filtered) === 1 && (int) $filtered[0]->ID === $b, 'Category filter');
// Publication age is independent of the date on which visits occurred.
$outside = wp_insert_post(['post_title' => 'Altra categoria', 'post_status' => 'publish']);
pp_seed($outside, $today, 50000);
$stale_date = current_datetime()->modify('-7 months')->format('Y-m-d H:i:s');
$stale = wp_insert_post(['post_title' => 'Notícia antiga', 'post_status' => 'publish', 'post_date' => $stale_date]);
wp_set_post_terms($stale, [$news['term_id']], 'category'); pp_seed($stale, $today, 40000);
$recent = Petit_Popular::ranked($args);
pp_check(count($recent) === 2 && (int) $recent[0]->ID === $a, 'Non-news and old publications excluded despite recent traffic');
$extended = Petit_Popular::ranked(array_merge($args, ['post_months' => 12]));
pp_check((int) $extended[0]->ID === $stale, 'Publication age configurable independently');
$override = Petit_Popular::ranked(array_merge($args, ['category' => 'uncategorized']));
pp_check(!$override, 'Optional category cannot bypass mandatory news category');

$cutoff = Petit_Popular_Dates::publication_start(6);
$before_cutoff = (new DateTimeImmutable($cutoff, wp_timezone()))->modify('-1 day')->format('Y-m-d H:i:s');
$boundary = wp_insert_post(['post_title' => 'Data límit', 'post_status' => 'publish', 'post_date' => $cutoff]);
$before = wp_insert_post(['post_title' => 'Abans del límit', 'post_status' => 'publish', 'post_date' => $before_cutoff]);
foreach ([$boundary, $before] as $id) { wp_set_post_terms($id, [$news['term_id']], 'category'); pp_seed($id, $today, 1); }
$boundary_ids = array_map(static fn($row) => (int) $row->ID, Petit_Popular::ranked($args));
pp_check(in_array($boundary, $boundary_ids, true) && !in_array($before, $boundary_ids, true), 'Six-month publication cutoff inclusive and exact');
wp_delete_post($boundary, true); wp_delete_post($before, true);
$missing_settings = Petit_Popular::settings(); $missing_settings['news_category'] = 'missing-news-category';
update_option(Petit_Popular::OPTION, $missing_settings);
pp_check(Petit_Popular::ranked($args) === [], 'Missing news category never falls back to all posts');
update_option(Petit_Popular::OPTION, ['post_factor' => 4, 'event_factor' => 2]);
wp_set_current_user(0);
$html = Petit_Popular::shortcode(['show_count' => 'yes', 'title' => '<script>bad()</script>']);
pp_check(strpos($html, '44 visualitzacions') !== false, 'Adjusted score rendered');
pp_check(strpos($html, '11 visualitzacions') === false && strpos($html, 'real_views') === false, 'Real count absent from markup');
pp_check(strpos($html, '<script>') === false, 'Title escaped');
pp_check(strpos(Petit_Popular::shortcode([]), 'visualitzacions') === false, 'Count hidden by default');
foreach (['cards', 'list', 'hero', 'vertical'] as $layout) {
    pp_check(strpos(Petit_Popular::shortcode(['layout' => $layout]), 'pp-' . $layout) !== false, 'Layout ' . $layout);
}
pp_check(strpos(Petit_Popular::shortcode(['layout' => 'vertical', 'image_position' => 'top']), 'pp-image-top') !== false, 'Vertical stacked images');
pp_check(strpos(Petit_Popular::shortcode(['layout' => 'vertical', 'image_position' => 'invalid']), 'pp-image-side') !== false, 'Vertical safe default');
pp_check(Petit_Popular::shortcode(['period' => 'custom', 'from' => 'bad', 'to' => 'bad']) === '', 'Public invalid filter does not expose diagnostics');
$settings = Petit_Popular::sanitize_settings(['post_factor' => -2, 'event_factor' => 3, 'accent' => 'url(evil)', 'radius' => 999, 'gap' => -1]);
pp_check($settings['post_factor'] === 4.0 && $settings['event_factor'] === 3.0, 'Invalid factor rejected, valid saved');
pp_check($settings['radius'] === 48 && $settings['gap'] === 0 && $settings['accent'] === '#2e9c87', 'Style constraints');

pp_check(Petit_Popular::brand_color('sabadell') === '#2e9c87', 'Manual Sabadell color');
pp_check(Petit_Popular::brand_color('terrassa') === '#fc634f', 'Manual Terrassa color');
pp_check(strpos(Petit_Popular::shortcode(['brand' => 'terrassa']), '--pp-accent:#fc634f') !== false, 'Terrassa preset output');
pp_check(strpos(Petit_Popular::shortcode(['brand' => 'sabadell']), '--pp-accent:#2e9c87') !== false, 'Sabadell preset output');
pp_check(strpos(Petit_Popular::shortcode(['brand' => 'terrassa', 'accent' => '#123456']), '--pp-accent:#123456') !== false, 'Explicit accent overrides preset');
$brand_settings = Petit_Popular::sanitize_settings(['brand' => 'terrassa', 'accent' => '#123456']);
pp_check($brand_settings['accent'] === '#fc634f', 'Site preset applies corporate accent');

$compact = Petit_Popular::shortcode(['show_excerpt' => 'yes']);
pp_check(strpos($compact, 'pp-excerpt') === false && strpos($compact, 'Una notícia de prova.') === false, 'No excerpt even with legacy shortcode');
pp_check(strpos($compact, 'pp-category') === false, 'No category or publication branding');
pp_check(strpos($compact, '<time datetime="' . get_the_date('Y-m-d', $a) . '">' . get_the_date('d/m/Y', $a) . '</time>') !== false, 'News publication date rendered');
pp_check(strpos($compact, esc_url(get_permalink($a))) !== false, 'Link to the original news item');

foreach (['cards', 'list', 'hero', 'vertical'] as $no_image_layout) {
    $without = Petit_Popular::shortcode(['layout' => $no_image_layout, 'show_image' => 'no', 'rank_size' => 40]);
    pp_check(strpos($without, 'pp-no-image') !== false && strpos($without, 'class="pp-image"') === false && strpos($without, '<img') === false, 'No image rendered for ' . $no_image_layout);
    pp_check(strpos($without, 'class="pp-rank"') !== false && strpos($without, '--pp-rank-size:40px') !== false, 'Prominent ranking preserved without photo for ' . $no_image_layout);
}

// Exercise the actual public counter, including its authorization and JSON output.
if (!defined('DOING_AJAX')) { define('DOING_AJAX', true); }
add_filter('wp_die_ajax_handler', function () { return function () { throw new RuntimeException('ajax complete'); }; });
function pp_track($id, $nonce, $agent = 'Mozilla/5.0') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_USER_AGENT'] = $agent;
    $_POST = ['post_id' => $id, 'nonce' => $nonce];
    $_REQUEST = $_POST;
    ob_start();
    try { Petit_Popular::track(); } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'ajax complete') { throw $e; }
    }
    return json_decode(ob_get_clean(), true);
}
$nonce = wp_create_nonce('petit_popular_' . $a);
$response = pp_track($a, $nonce);
pp_check($response['success'] === true && $response['data'] === ['counted' => true], 'Counter accepts public view without returning totals');
$tracked = Petit_Popular::ranked($args);
pp_check((int) $tracked[0]->real_views === 12, 'Atomic increment stored');
pp_check(pp_track($a, $nonce, 'Googlebot')['data']['counted'] === false, 'Known crawler ignored');
pp_check(pp_track($a, 'invalid')['success'] === false, 'Invalid nonce rejected');
pp_check(pp_track($draft, wp_create_nonce('petit_popular_' . $draft))['success'] === false, 'Draft cannot be counted');
wp_set_current_user(1);
pp_check(pp_track($a, wp_create_nonce('petit_popular_' . $a))['success'] === false, 'Editor visits excluded');
wp_set_current_user(0);

function pp_saved($data) {
    $_POST = wp_slash($data); $_REQUEST = $_POST;
    ob_start();
    try { Petit_Popular_Saved::ajax(); } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'ajax complete') { throw $e; }
    }
    return json_decode(ob_get_clean(), true);
}
wp_set_current_user(1);
$manage_nonce = wp_create_nonce('petit_popular_preview');
$save = pp_saved(['operation' => 'save', 'nonce' => $manage_nonce, 'name' => 'Rànquing de la portada', 'args' => ['layout' => 'vertical', 'title' => 'L’actualitat "del dia"', 'show_image' => 'no', 'rank_size' => 40, 'radius' => 9, 'gap' => 13]]);
pp_check($save['success'], 'Named ranking saved');
$ranking_id = $save['data']['ranking']['id'];
pp_check($ranking_id > 0 && get_post_type($ranking_id) === Petit_Popular_Saved::TYPE, 'Ranking stored as private post type');
pp_check(count($save['data']['saved']) === 1, 'Named ranking listed');
pp_check($save['data']['ranking']['args']['show_image'] === 'no' && (int) $save['data']['ranking']['args']['rank_size'] === 40, 'Photo visibility and rank size persist in named ranking');
$saved_html = Petit_Popular::shortcode(['id' => $ranking_id]);
pp_check(strpos($saved_html, 'pp-vertical') !== false && strpos($saved_html, 'L’actualitat &quot;del dia&quot;') !== false, 'Stable shortcode renders saved options and preserves quotes');
$edited = pp_saved(['operation' => 'save', 'nonce' => $manage_nonce, 'id' => $ranking_id, 'name' => 'Rànquing intern', 'args' => ['layout' => 'list', 'title' => 'Canvi desat']]);
pp_check($edited['success'] && $edited['data']['ranking']['id'] === $ranking_id && count($edited['data']['saved']) === 1, 'Editing keeps stable ID without duplicates');
pp_check(strpos(Petit_Popular::shortcode(['id' => $ranking_id]), 'Canvi desat') !== false, 'Existing ID reflects updates');
$loaded = pp_saved(['operation' => 'load', 'nonce' => $manage_nonce, 'id' => $ranking_id]);
pp_check($loaded['success'] && $loaded['data']['name'] === 'Rànquing intern', 'Saved ranking can be recovered');
$import = pp_saved(['operation' => 'import', 'nonce' => $manage_nonce, 'code' => '[petit_popular type="posts" period="30d" layout="vertical" title="Títol antic"]']);
pp_check($import['success'] && $import['data']['id'] === 0 && $import['data']['args']['period'] === '30d', 'Legacy inline shortcode imports without executing');
$import_id = pp_saved(['operation' => 'import', 'nonce' => $manage_nonce, 'code' => '[petit_popular id="' . $ranking_id . '"]']);
pp_check($import_id['success'] && $import_id['data']['id'] === $ranking_id, 'Stable shortcode imports current stored options');
pp_check(!pp_saved(['operation' => 'import', 'nonce' => $manage_nonce, 'code' => '[other_plugin evil="1"]'])['success'], 'Foreign shortcode rejected');
pp_check(!pp_saved(['operation' => 'save', 'nonce' => $manage_nonce, 'name' => '', 'args' => []])['success'], 'Empty ranking names rejected');
pp_check(!pp_saved(['operation' => 'save', 'nonce' => $manage_nonce, 'name' => 'Incorrecte', 'args' => ['post_months' => '0']])['success'], 'Invalid publication window rejected when saving');
pp_check(!pp_saved(['operation' => 'save', 'nonce' => $manage_nonce, 'id' => $a, 'name' => 'No', 'args' => []])['success'], 'Cannot overwrite application posts via ranking endpoint');
pp_check(!pp_saved(['operation' => 'save', 'nonce' => 'invalid', 'name' => 'No', 'args' => []])['success'], 'Ranking save requires a nonce');
wp_set_current_user(0);
pp_check(!pp_saved(['operation' => 'load', 'nonce' => $manage_nonce, 'id' => $ranking_id])['success'], 'Anonymous access to ranking management denied');
pp_check(Petit_Popular::shortcode(['id' => 999999]) === '', 'Missing ID does not render an unrelated ranking');
wp_set_current_user(1);

if (is_multisite()) {
    $site = wp_insert_site(['domain' => 'localhost', 'path' => '/terrassa/', 'title' => 'Petit Terrassa', 'user_id' => 1]);
    pp_check(!is_wp_error($site), 'Second site created');
    $table = Petit_Popular::table();
    switch_to_blog($site);
    Petit_Popular::ensure_schema();
    pp_check(Petit_Popular::table() !== $table, 'Independent table prefix');
    pp_check(Petit_Popular::settings()['post_factor'] === 1, 'Independent defaults');
    pp_check(Petit_Popular::ranked($args) === [], 'No cross-site views');
    pp_check(Petit_Popular_Saved::listing() === [], 'No cross-site saved rankings');
    pp_check(is_wp_error(Petit_Popular_Saved::get($ranking_id)), 'Ranking IDs cannot retrieve another site’s record');
    update_option(Petit_Popular::OPTION, ['post_factor' => 9]);
    restore_current_blog();
    pp_check(Petit_Popular::settings()['post_factor'] === 4, 'Original site settings preserved');
} else { echo "NOTE: multisite tests not executed\n"; }
wp_set_current_user(1);
$deleted = pp_saved(['operation' => 'delete', 'nonce' => wp_create_nonce('petit_popular_preview'), 'id' => $ranking_id]);
pp_check($deleted['success'] && $deleted['data']['saved'] === [], 'Named ranking deleted');
pp_check(is_wp_error(Petit_Popular_Saved::get($ranking_id)), 'Deleted ranking cannot be loaded');
pp_check(get_post($a) !== null, 'Deleting ranking does not delete news');

function pp_preview($args, $nonce, $mode = 'demo') {
    $_POST = ['args' => $args, 'nonce' => $nonce, 'mode' => $mode];
    $_REQUEST = $_POST;
    ob_start();
    try { Petit_Popular::preview(); } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'ajax complete') { throw $e; }
    }
    return json_decode(ob_get_clean(), true);
}
wp_set_current_user(0);
pp_check(pp_preview([], 'invalid')['success'] === false, 'Anonymous preview denied');
wp_set_current_user(1);
$preview_nonce = wp_create_nonce('petit_popular_preview');
pp_check(pp_preview([], 'invalid')['success'] === false, 'Preview nonce required');
$sample = pp_preview(['layout' => 'vertical', 'brand' => 'terrassa', 'limit' => '3'], $preview_nonce);
pp_check($sample['success'] && $sample['data']['demo'], 'Style sample explicitly marked as demo');
pp_check(substr_count($sample['data']['html'], '<li class="pp-item">') === 3, 'Preview item count');
pp_check(strpos($sample['data']['html'], 'pp-vertical') !== false && strpos($sample['data']['html'], '#fc634f') !== false, 'Preview applies layout and brand');
pp_check(pp_preview(['type' => 'events'], $preview_nonce)['success'], 'Event style demo works without TEC or traffic');
$real = pp_preview(['show_count' => 'yes'], $preview_nonce, 'ranking');
pp_check($real['success'] && !$real['data']['demo'] && strpos($real['data']['html'], 'Notícia A') !== false, 'Real preview uses site ranking');
$unchanged = Petit_Popular::ranked(Petit_Popular::defaults());
pp_check((int) $unchanged[0]->real_views === 12, 'Preview never adds page views');
Petit_Popular::admin_assets('settings_page_petit-popular');
pp_check(wp_style_is('petit-popular-admin', 'enqueued'), 'Admin stylesheet registered before rendering');
ob_start();
Petit_Popular::admin();
$admin = ob_get_clean();
file_put_contents(__DIR__ . '/admin-fixture.html', $admin);
pp_check(strpos($admin, 'Factor de notícies') !== false && strpos($admin, 'pp-builder') !== false, 'Admin settings and builder render');
pp_check(strpos($admin, 'pp-preview-frame') !== false && strpos($admin, 'pp-copy') !== false, 'Visual preview panel and copy action render');
file_put_contents(__DIR__ . '/last-run.json', json_encode([
    'checks' => $checks, 'wordpress' => get_bloginfo('version'), 'php' => PHP_VERSION,
    'multisite' => is_multisite(), 'result' => 'PASS', 'at' => gmdate('c'),
], JSON_PRETTY_PRINT));
echo "PASS: $checks WordPress integration checks\n";
