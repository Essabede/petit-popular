<?php
// Runs after WordPress and the TEC contract fixture in the disposable integration suite.
function pp_email($data, $method = 'POST') {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = wp_slash($data); $_REQUEST = $_POST;
    ob_start();
    try { Petit_Popular_Email::ajax(); } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'ajax complete') { throw $e; }
    }
    return json_decode(ob_get_clean(), true);
}
wp_set_current_user(0);
pp_check(!pp_email([])['success'], 'Email export is not public');
wp_set_current_user(1); $email_nonce = wp_create_nonce('petit_popular_email');
pp_check(!pp_email(['nonce' => 'invalid'])['success'], 'Email export requires dedicated nonce');
pp_check(!pp_email(['nonce' => $email_nonce], 'GET')['success'], 'Email export requires POST');
$email_args = array_merge(Petit_Popular::defaults(), ['layout' => 'plain', 'show_image' => 'no', 'show_count' => 'yes', 'title' => 'El més llegit', 'title_color' => '#20252d', 'date_color' => '#ffffff', 'hover_color' => '#ff00ff', 'density' => 'compact']);
$email_request = ['nonce' => $email_nonce, 'args' => $email_args, 'layout' => 'cards', 'columns' => '2', 'background' => '#2e9c87', 'mode' => 'demo'];
$email_response = pp_email($email_request);
pp_check($email_response['success'] && $email_response['data']['count'] === 2, 'Export uses actual ranked posts even if caller passes demo mode');
$email_html = $email_response['data']['html'];
file_put_contents(__DIR__ . '/email-horizontal-fixture.html', $email_html);
pp_check(strpos($email_html, 'Notícia A') !== false && strpos($email_html, '48 visualitzacions') !== false, 'Snapshot contains current titles and multiplied counts');
pp_check(strpos($email_html, 'bgcolor="#2e9c87"') !== false && strpos($email_html, 'color:#ffffff') !== false, 'Email background can preserve readability of custom date color');
pp_check(strpos($email_html, '<table role="presentation"') !== false && strpos($email_html, 'width="292"') !== false, 'Horizontal design uses two email table columns');
pp_check(strpos($email_html, '<img') === false, 'No-photo preference preserved in email');
foreach (['<script', '<style', '<iframe', 'display:grid', 'display:flex', 'position:absolute', 'var(', 'class=', 'javascript:', '#ff00ff', 'petit_popular_track'] as $forbidden) {
    pp_check(strpos($email_html, $forbidden) === false, 'Email fragment is standalone and does not include ' . $forbidden);
}
pp_check(strpos($email_html, 'href="' . esc_url(get_permalink($a)) . '"') !== false, 'Email headline links point to real posts');
pp_check($email_response['data']['generated_at'] !== '' && substr($email_response['data']['filename'], -5) === '.html', 'Snapshot has generation date and HTML download name');
foreach (array_keys(Petit_Popular_Email::LABELS) as $email_layout) {
    $response = pp_email(array_merge($email_request, ['layout' => $email_layout, 'columns' => '3']));
    pp_check($response['success'] && strpos($response['data']['html'], 'Notícia A') !== false, 'Email renderer supports ' . $email_layout);
    file_put_contents(__DIR__ . '/email-' . $email_layout . '-fixture.html', $response['data']['html']);
}
$same_layout = pp_email(array_merge($email_request, ['layout' => 'same']));
pp_check($same_layout['success'] && $same_layout['data']['layout'] === Petit_Popular_Email::LABELS['plain'], 'Same-as-web mode resolves chosen web layout');
pp_check(!pp_email(array_merge($email_request, ['columns' => '4']))['success'], 'Unsupported email column count rejected');
pp_check(!pp_email(array_merge($email_request, ['layout' => 'unsafe']))['success'], 'Unknown export layout rejected');
pp_check(!pp_email(array_merge($email_request, ['background' => 'red;position:fixed']))['success'], 'Unsafe email background rejected');
$empty_args = array_merge($email_args, ['period' => 'custom', 'from' => '2099-01-01', 'to' => '2099-01-02']);
pp_check(!pp_email(array_merge($email_request, ['args' => $empty_args]))['success'], 'Empty ranking produces an error instead of invented email content');
$before_export = Petit_Popular::ranked(Petit_Popular::defaults());
pp_check((int) $before_export[0]->real_views === 12, 'Exporting does not increase page views');
pp_seed($a, current_datetime()->format('Y-m-d'), 20);
$fresh_export = pp_email($email_request);
pp_check(strpos($email_html, '48 visualitzacions') !== false && strpos($fresh_export['data']['html'], '80 visualitzacions') !== false, 'Old HTML stays frozen while regeneration uses new totals');
pp_seed($a, current_datetime()->format('Y-m-d'), 12);
$event_export_args = array_merge($event_args, ['show_image' => 'no', 'show_count' => 'yes']);
$event_export = pp_email(array_merge($email_request, ['args' => $event_export_args]));
pp_check($event_export['success'] && strpos($event_export['data']['html'], '12/11/2026') !== false && strpos($event_export['data']['html'], '52 visualitzacions') !== false, 'Event email uses real celebration date and event factor');
pp_check(strpos($event_export['data']['html'], '17:00') === false, 'Event email does not include unrequested event time');
$attachment = wp_insert_attachment(['post_title' => 'Fotografia', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'guid' => home_url('/wp-content/uploads/petit-test.jpg')]);
update_post_meta($attachment, '_wp_attached_file', 'petit-test.jpg');
update_post_meta($attachment, '_wp_attachment_metadata', ['width' => 800, 'height' => 500, 'file' => 'petit-test.jpg', 'sizes' => []]);
set_post_thumbnail($a, $attachment);
$photo_export = pp_email(array_merge($email_request, ['args' => array_merge($email_args, ['show_image' => 'yes'])]));
pp_check($photo_export['success'] && preg_match('/<img src="https?:\/\//', $photo_export['data']['html']), 'Email photos use absolute web URLs');
delete_post_thumbnail($a); wp_delete_attachment($attachment, true);
