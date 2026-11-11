<?php
if (!defined('ABSPATH')) { exit; }

/** Frozen, email-compatible rendering. It neither sends email nor calls Mailchimp. */
final class Petit_Popular_Email {
    const LABELS = ['cards' => 'Targetes horitzontals', 'list' => 'Llistat amb imatge lateral',
        'vertical' => 'Rànquing vertical', 'hero' => 'Primer destacat', 'plain' => 'Llistat sense targetes'];

    const FONT_LABELS = ['same' => 'La del rànquing web (amb alternativa per a correu)',
        'arial' => 'Arial', 'helvetica' => 'Helvetica', 'verdana' => 'Verdana',
        'tahoma' => 'Tahoma', 'trebuchet' => 'Trebuchet MS', 'georgia' => 'Georgia',
        'times' => 'Times New Roman', 'nunito' => 'Nunito (alternativa: Arial)',
        'opensans' => 'Open Sans (alternativa: Arial)', 'lato' => 'Lato (alternativa: Arial)'];
    const FONTS = ['arial' => 'Arial,Helvetica,sans-serif', 'helvetica' => 'Helvetica,Arial,sans-serif',
        'verdana' => 'Verdana,Geneva,sans-serif', 'tahoma' => 'Tahoma,Verdana,sans-serif',
        'trebuchet' => 'Trebuchet MS,Arial,sans-serif', 'georgia' => 'Georgia,Times New Roman,serif',
        'times' => 'Times New Roman,Times,serif', 'nunito' => 'Nunito,Arial,Helvetica,sans-serif',
        'opensans' => 'Open Sans,Arial,Helvetica,sans-serif', 'lato' => 'Lato,Arial,Helvetica,sans-serif'];

    public static function ajax() {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message' => 'Accés restringit.'], 403); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_send_json_error(['message' => 'Cal una petició POST.'], 405); }
        if (!check_ajax_referer('petit_popular_email', 'nonce', false)) { wp_send_json_error(['message' => 'La sessió ha caducat. Recarrega la pàgina.'], 403); }
        try {
            $input = isset($_POST['args']) && is_array($_POST['args']) ? wp_unslash($_POST['args']) : [];
            $args = Petit_Popular_Saved::normalize($input);
            $layout = isset($_POST['layout']) && is_string($_POST['layout']) ? $_POST['layout'] : 'cards';
            if ($layout === 'same') { $layout = $args['layout']; }
            if (!isset(self::LABELS[$layout])) { throw new InvalidArgumentException('Tria un disseny de correu vàlid.'); }
            $columns = $_POST['columns'] ?? '2';
            if (!is_scalar($columns) || !in_array((string) $columns, ['1','2','3'], true)) { throw new InvalidArgumentException('Tria entre 1 i 3 columnes.'); }
            $background = isset($_POST['background']) && is_string($_POST['background']) ? sanitize_hex_color($_POST['background']) : '#ffffff';
            if (!$background) { throw new InvalidArgumentException('Tria un color de fons vàlid.'); }
            $email_font = isset($_POST['email_font']) && is_string($_POST['email_font']) ? $_POST['email_font'] : 'same';
            if (!isset(self::FONT_LABELS[$email_font])) { throw new InvalidArgumentException('Tria una tipografia de correu vàlida.'); }
            $rows = Petit_Popular::ranked($args);
            if (!$rows) { throw new InvalidArgumentException('No hi ha continguts amb visites que compleixin els filtres. L’exportació només utilitza el rànquing real.'); }
            $html = self::render($args, $rows, $layout, (int) $columns, $background, $email_font);
            wp_send_json_success(['html' => $html, 'count' => count($rows), 'layout' => self::LABELS[$layout],
                'font_label' => self::FONT_LABELS[$email_font], 'generated_at' => current_datetime()->format('d/m/Y H:i'),
                'filename' => 'petit-popular-' . current_datetime()->format('Ymd-His') . '.html']);
        } catch (InvalidArgumentException $e) { wp_send_json_error(['message' => $e->getMessage()], 400); }
    }

    private static function url($url) {
        $url = (string) $url;
        if ($url === '') { return ''; }
        if (strpos($url, '//') === 0) { $url = 'https:' . $url; }
        elseif (!wp_parse_url($url, PHP_URL_SCHEME)) { $url = home_url('/' . ltrim($url, '/')); }
        return esc_url_raw($url, ['http', 'https']);
    }

    public static function render($a, $rows, $layout = 'cards', $columns = 2, $background = '#ffffff', $email_font = 'same') {
        if (!isset(self::LABELS[$layout]) || $columns < 1 || $columns > 3) { throw new InvalidArgumentException('Disseny de correu no vàlid.'); }
        if (!sanitize_hex_color($background)) { throw new InvalidArgumentException('Color de fons no vàlid.'); }
        if (!isset(self::FONT_LABELS[$email_font])) { throw new InvalidArgumentException('Tipografia de correu no vàlida.'); }
        if ($email_font === 'same') { $email_font = ['serif' => 'georgia', 'nunito' => 'nunito'][$a['font']] ?? 'arial'; }
        $a['layout'] = $layout;
        $settings = Petit_Popular::settings();
        $theme = ['background' => $background,
            'accent' => sanitize_hex_color($a['accent']) ?: (Petit_Popular::brand_color($a['brand']) ?: $settings['accent']),
            'title' => sanitize_hex_color($a['title_color']) ?: '#20252d',
            'date' => sanitize_hex_color($a['date_color']) ?: '#526070',
            'font' => self::FONTS[$email_font], 'content_width' => 564,
            'size' => max(14, min(48, (int) $a['title_size'])),
            'radius' => $a['radius'] === '' ? (int) $settings['radius'] : max(0, min(48, (int) $a['radius'])),
            'gap' => $a['gap'] === '' ? (int) $settings['gap'] : max(0, min(32, (int) $a['gap'])),
        ];
        $factor = $settings[$a['type'] === 'events' ? 'event_factor' : 'post_factor'];
        $items = [];
        foreach ($rows as $index => $row) {
            $id = (int) $row->ID;
            $post = get_post($id);
            if (!$post || $post->post_status !== 'publish' || $post->post_password !== '' || $post->post_type !== ($a['type'] === 'events' ? 'tribe_events' : 'post')) { continue; }
            if ($a['type'] === 'events' && !function_exists('tribe_get_start_date')) { throw new InvalidArgumentException('No es pot recuperar la data de celebració d’Events Calendar.'); }
            $items[] = ['title' => wp_strip_all_tags(get_the_title($id)), 'url' => self::url(get_permalink($id)),
                'image' => $a['show_image'] === 'yes' ? self::url(get_the_post_thumbnail_url($id, 'medium_large')) : '',
                'date' => $a['type'] === 'events' ? tribe_get_start_date($id, false, 'd/m/Y') : get_the_date('d/m/Y', $id),
                'rank' => $index + 1, 'score' => Petit_Popular_Dates::score($row->real_views, $factor)];
        }
        if (!$items) { throw new InvalidArgumentException('Ja no hi ha continguts públics elegibles per exportar.'); }
        $font = esc_attr($theme['font']);
        $html = '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;"><tr><td align="center" bgcolor="' . esc_attr($background) . '" style="padding:0;background-color:' . esc_attr($background) . ';">';
        $html .= '<table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;border-collapse:collapse;font-family:' . $font . ';">';
        $html .= '<tr><td style="padding:0 18px;"><table role="presentation" width="564" border="0" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-family:' . $font . ';">';
        if ($a['title'] !== '') {
            $html .= '<tr><td style="padding:0 0 16px;color:' . esc_attr($theme['title']) . ';font-family:' . $font . ';font-size:24px;line-height:1.25;font-weight:bold;">' . esc_html($a['title']) . '</td></tr>';
        }
        if ($layout === 'hero') {
            $lead = array_shift($items);
            $html .= '<tr><td style="padding:0 0 16px;">' . self::card($lead, $a, $theme, $theme['content_width'], true) . '</td></tr>';
        }
        if (in_array($layout, ['cards','hero'], true)) {
            $html .= '<tr><td style="padding:0;">' . self::grid($items, $a, $theme, $columns) . '</td></tr>';
        } else {
            foreach ($items as $i => $item) {
                $plain = $layout === 'plain';
                $compact = $a['density'] !== 'comfortable' && $a['show_image'] === 'no';
                $gap = $compact ? ($a['density'] === 'tight' ? 3 : 6) : $theme['gap'];
                $border = $plain && $i < count($items) - 1 ? 'border-bottom:1px solid #dcdcde;' : '';
                $html .= '<tr><td style="padding:' . $gap . 'px 0;' . $border . '">' . self::row($item, $a, $theme, $plain) . '</td></tr>';
            }
        }
        return $html . '</table></td></tr></table></td></tr></table>';
    }

    private static function grid($items, $a, $t, $columns) {
        $out = '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="width:100%;table-layout:fixed;border-collapse:collapse;">';
        $width = (int) floor(($t['content_width'] - ($columns - 1) * 16) / $columns);
        foreach (array_chunk($items, $columns) as $chunk) {
            $out .= '<tr>';
            for ($i = 0; $i < $columns; $i++) {
                if ($i) { $out .= '<td width="16" style="width:16px;font-size:0;">&nbsp;</td>'; }
                $out .= '<td width="' . $width . '" valign="top" style="width:' . $width . 'px;vertical-align:top;padding:0 0 16px;">';
                if (isset($chunk[$i])) { $out .= self::card($chunk[$i], $a, $t, $width); }
                $out .= '</td>';
            }
            $out .= '</tr>';
        }
        return $out . '</table>';
    }

    private static function photo($item, $width) {
        if (!$item['image']) { return ''; }
        return '<a href="' . esc_url($item['url']) . '" style="text-decoration:none;"><img src="' . esc_url($item['image']) . '" width="' . (int) $width . '" alt="' . esc_attr($item['title']) . '" border="0" style="display:block;width:100%;max-width:' . (int) $width . 'px;height:auto;border:0;"></a>';
    }

    private static function card($item, $a, $t, $width, $lead = false) {
        $style = 'width:100%;border:1px solid #e3e6e9;border-radius:' . $t['radius'] . 'px;background-color:' . $t['background'] . ';border-collapse:separate;';
        $out = '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="' . esc_attr($style) . '">';
        if ($item['image']) { $out .= '<tr><td style="padding:0;">' . self::photo($item, $width) . '</td></tr>'; }
        if ($lead) { $t['size'] = max($t['size'], 24); }
        return $out . '<tr><td style="padding:12px;">' . self::body($item, $a, $t, false) . '</td></tr></table>';
    }

    private static function row($item, $a, $t, $plain) {
        if ($a['layout'] === 'vertical' && $a['image_position'] === 'top' && !$plain) { return self::card($item, $a, $t, $t['content_width']); }
        $style = 'width:100%;border-collapse:separate;' . ($plain ? '' : 'background-color:' . $t['background'] . ';border:1px solid #e3e6e9;border-radius:' . $t['radius'] . 'px;');
        $pad = $plain ? 0 : 12;
        $out = '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="' . esc_attr($style) . '"><tr>';
        if ($item['image']) { $out .= '<td width="112" valign="top" style="width:112px;padding:0 12px 0 0;vertical-align:top;">' . self::photo($item, 112) . '</td>'; }
        return $out . '<td valign="top" style="padding:' . $pad . 'px;vertical-align:top;">' . self::body($item, $a, $t, $a['density'] !== 'comfortable' && $a['show_image'] === 'no') . '</td></tr></table>';
    }

    private static function body($item, $a, $t, $compact) {
        $font = esc_attr($t['font']);
        $out = '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;"><tr>';
        if ($a['rank_style'] !== 'none') {
            $size = max(12, min(48, (int) $a['rank_size'])); $number = $a['rank_style'] === 'number';
            $bg = $a['rank_background'] === 'transparent' ? 'transparent' : (sanitize_hex_color($a['rank_background']) ?: '#ffffff');
            $border = $a['rank_border'] === 'none' ? 'none' : '2px solid ' . (sanitize_hex_color($a['rank_border']) ?: $t['accent']);
            $rank_style = 'display:inline-block;text-align:center;white-space:nowrap;color:' . (sanitize_hex_color($a['rank_color']) ?: '#20252d') . ';font-size:' . $size . 'px;font-weight:' . (int) $a['rank_weight'] . ';line-height:1.2;';
            if (!$number) {
                $rank_style .= 'background-color:' . $bg . ';border:' . $border . ';border-radius:' . ($a['rank_style'] === 'circle' ? '50%' : '6px') . ';';
                if ($a['rank_style'] === 'circle') {
                    $diameter = (int) ceil($size * 1.8);
                    $rank_style .= 'width:' . $diameter . 'px;height:' . $diameter . 'px;line-height:' . ($diameter - ($border === 'none' ? 0 : 4)) . 'px;box-sizing:border-box;padding:0;';
                } else { $rank_style .= 'padding:4px 7px;'; }
            }
            $rank_width = $number ? (int) ceil($size * 1.4) : (int) ceil($size * 2);
            $out .= '<td width="' . $rank_width . '" valign="top" style="width:' . $rank_width . 'px;vertical-align:top;padding:0 8px 0 0;font-family:' . $font . ';"><span style="' . esc_attr($rank_style) . '">' . (int) $item['rank'] . '</span></td>';
        }
        $out .= '<td valign="top" style="vertical-align:top;font-family:' . $font . ';">';
        $title_style = 'margin:0 0 6px;font-family:' . $t['font'] . ';font-size:' . $t['size'] . 'px;line-height:1.25;font-weight:bold;overflow-wrap:break-word;';
        $out .= '<p style="' . esc_attr($title_style) . '"><a href="' . esc_url($item['url']) . '" style="color:' . esc_attr($t['title']) . ';text-decoration:none;">' . esc_html($item['title']) . '</a></p>';
        $out .= '<p style="margin:0;font-family:' . $font . ';font-size:13px;line-height:1.5;color:' . esc_attr($t['date']) . ';">' . esc_html($item['date']);
        if ($a['show_count'] === 'yes') {
            $out .= $compact ? ' &middot; ' : '<br>';
            $out .= esc_html(number_format_i18n($item['score'])) . ' visualitzacions';
        }
        return $out . '</p></td></tr></table>';
    }
}
