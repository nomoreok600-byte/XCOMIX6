<?php
/**
 * Plugin Name: X COMIX - MangaKatana Engine V9.11
 * Description: Cloudflare Cookie Support & Dedicated Buddy Override Tool.
 * Version: 9.11
 * Author: X COMIX
 */

if (!defined('ABSPATH')) exit;

// =========================================================================
// 1. SECURE EXTERNAL CRON ENDPOINT
// =========================================================================
add_action('parse_request', 'xcomix_external_cron_endpoint');
function xcomix_external_cron_endpoint($wp) {
    if (isset($_GET['xcomix_cron'])) {
        $saved_secret = get_option('xcomix_cron_secret', 'rifat_secure_cron');
        if (isset($_GET['secret']) && $_GET['secret'] === $saved_secret) {
            set_time_limit(0);
            while (ob_get_level()) ob_end_clean();
            status_header(200);
            header('Content-Type: application/json; charset=utf-8');

            if ($_GET['xcomix_cron'] === 'run') {
                $logs = xcomix_run_autopilot();
                echo wp_json_encode(['status' => 'success', 'mode' => 'latest', 'processed' => count($logs), 'logs' => $logs]);
            } elseif ($_GET['xcomix_cron'] === 'deep') {
                $logs = xcomix_run_deep_scraper();
                echo wp_json_encode(['status' => 'success', 'mode' => 'backlog', 'processed' => count($logs), 'logs' => $logs]);
            }
            exit;
        } else {
            while (ob_get_level()) ob_end_clean();
            status_header(403);
            header('Content-Type: application/json; charset=utf-8');
            echo wp_json_encode(['status' => 'error', 'message' => 'Access Denied: Invalid Secret Key']);
            exit;
        }
    }
}

// =========================================================================
// 2. INTERNAL WP CRON (Fallback)
// =========================================================================
add_filter('cron_schedules', function($schedules) {
    $schedules['fifteen_minutes'] = ['interval' => 900, 'display' => 'Every 15 Minutes'];
    return $schedules;
});
if (!wp_next_scheduled('xcomix_ultimate_autopilot')) {
    wp_schedule_event(time(), 'fifteen_minutes', 'xcomix_ultimate_autopilot');
}
add_action('xcomix_ultimate_autopilot', 'xcomix_run_autopilot');

// =========================================================================
// 3. ADMIN MENU
// =========================================================================
add_action('admin_menu', function() {
    add_menu_page('Manga Engine', 'Katana Engine', 'manage_options', 'xcomix-scraper', 'xcomix_scraper_page_html', 'dashicons-performance', 20);
});

// =========================================================================
// 4. STEALTH FETCH ENGINE (Cookie Supported)
// =========================================================================
function xcomix_fetch($url) {
    $cf_cookie = get_option('xcomix_cf_cookie', '');
    $custom_ua = get_option('xcomix_user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0.0.0 Safari/537.36');

    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'User-Agent: ' . $custom_ua,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Referer: https://mangakatana.com/',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        ]
    ];

    if (!empty($cf_cookie)) {
        $options[CURLOPT_COOKIE] = $cf_cookie;
    }

    curl_setopt_array($ch, $options);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

// =========================================================================
// 5. CORE PROCESSING (NUKE & OVERRIDE CAPABLE)
// =========================================================================
add_action('wp_ajax_xcomix_process_single', 'xcomix_ajax_process_single');
function xcomix_ajax_process_single() {
    if (!current_user_can('manage_options')) wp_die();
    set_time_limit(0);
    $url = esc_url_raw($_POST['manga_url']);
    $action_type = isset($_POST['action_type']) ? $_POST['action_type'] : 'standard';

    $log = xcomix_process_manga($url, $action_type);
    wp_send_json_success(['log' => $log[0]]);
}

function xcomix_process_manga($url, $action_type = 'standard') {
    global $wpdb;
    $url = strtok(esc_url_raw($url), '?');
    $url = rtrim($url, '/');

    if (preg_match('/mangakatana\.com\/manga\/([^\/\?#]+)/i', $url, $matches)) {
        $slug = strtolower(trim($matches[1]));
        $url = 'https://mangakatana.com/manga/' . $slug;
    } else {
        return ["<span style='color:#ef4444;'>❌ Invalid MangaKatana URL format.</span>"];
    }

    $import_status = get_option('xcomix_post_status', 'publish');
    $existing_manga_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_katana_url' AND meta_value = %s", $url));

    // ==============================================================
    // NUKE PROTOCOL (Action Type = 'nuke')
    // ==============================================================
    if ($action_type === 'nuke') {
        $like_url = $wpdb->esc_like($url) . '%';
        $posts_by_url = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_katana_url' AND meta_value LIKE %s", $like_url));
        $like_slug = $wpdb->esc_like($slug . '-') . '%';
        $posts_by_slug = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_type = 'chapter' AND post_name LIKE %s", $like_slug));

        $children = [];
        if ($existing_manga_id) {
            $children = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_parent = %d", $existing_manga_id));
        }

        $all_nuke_ids = array_unique(array_merge($posts_by_url, $posts_by_slug, $children, [$existing_manga_id]));

        foreach ($all_nuke_ids as $nid) {
            if (!empty($nid)) wp_delete_post($nid, true);
        }

        wp_cache_flush();
        $existing_manga_id = false;
    }

    // Autopilot Optimization
    if ($action_type === 'standard' && $existing_manga_id) {
        $last_checked = get_post_meta($existing_manga_id, '_xcomix_last_checked', true);
        if ($last_checked && (time() - $last_checked) < 7200) {
             return ["<span style='color:#6b7280;'>> ⚡ Skipped: '$slug' recently checked. Conserving resources.</span>"];
        }
    }

    $html = xcomix_fetch($url);
    if (!$html || strpos($html, 'Cloudflare') !== false || strpos($html, 'Just a moment') !== false) {
        return ["<span style='color:#ef4444;'>❌ Blocked by Cloudflare: $url. Please update your Clearance Cookie.</span>"];
    }

    $dom = new DOMDocument(); libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $title = trim($xpath->query('//h1')->item(0)->textContent ?? 'Unknown');
    $desc = trim($xpath->query('//div[contains(@class, "summary")]/p')->item(0)->textContent ?? '');
    $alt = trim($xpath->query('//div[contains(@class, "alt_name")]')->item(0)->textContent ?? '');
    $author = trim($xpath->query('//div[contains(@class, "author")]')->item(0)->textContent ?? 'Unknown');
    $status = trim($xpath->query('//div[contains(@class, "status")]')->item(0)->textContent ?? 'Ongoing');

    $type = "Manga";
    $is_18_plus = 0;
    $genres = [];

    $gNodes = $xpath->query('//div[contains(@class, "genres")]//a');
    foreach($gNodes as $gn) {
        $g = trim($gn->textContent);
        if(empty($g)) continue;
        $genres[] = $g;
        $g_lower = strtolower($g);
        if(in_array($g_lower, ['manhwa', 'webtoon'])) $type = "Manhwa";
        if($g_lower === 'manhua') $type = "Manhua";
        if(in_array($g_lower, ['smut', 'mature', 'adult', 'nsfw', 'erotica'])) $is_18_plus = 1;
    }

    $img_node = $xpath->query('//div[contains(@class, "cover")]//img | //div[contains(@class,"media-info")]//img')->item(0);
    $cover = $img_node ? ($img_node->getAttribute('data-src') ?: $img_node->getAttribute('src')) : '';

    $manga_id = $existing_manga_id;

    // ==============================================================
    // POST CREATION OR BUDDY OVERRIDE
    // ==============================================================
    if (!$manga_id) {
        $buddy_check = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_type = 'manga' AND (post_name = %s OR post_title = %s) LIMIT 1", $slug, $title));

        if ($buddy_check && $action_type !== 'override') {
            return ["<span style='color:#eab308; font-weight:bold;'>> ⏭️ Skipped: '{$title}' is already owned by Buddy. Use the Override tool to seize it.</span>"];
        } elseif ($buddy_check && $action_type === 'override') {
            $manga_id = $buddy_check;
            // We do NOT delete Buddy's chapters. We just seize the parent.
        } else {
            $manga_id = wp_insert_post([
                'post_title' => $title, 'post_content' => $desc, 'post_status' => $import_status, 'post_type' => 'manga', 'post_name' => $slug
            ]);
        }
    }

    update_post_meta($manga_id, '_katana_url', $url);
    update_post_meta($manga_id, '_manga_alt_title', $alt);
    update_post_meta($manga_id, '_manga_author', $author);
    update_post_meta($manga_id, '_manga_status', $status);
    update_post_meta($manga_id, '_manga_type', $type);
    update_post_meta($manga_id, '_is_18_plus', $is_18_plus);
    if (!empty($cover)) update_post_meta($manga_id, '_manga_cover_url', $cover);
    update_post_meta($manga_id, '_mv_alt_title', $alt);
    update_post_meta($manga_id, '_mv_author', $author);
    update_post_meta($manga_id, '_mv_is_18_plus', $is_18_plus);
    if (!empty($cover)) update_post_meta($manga_id, '_mv_cover_url', $cover);
    wp_set_object_terms($manga_id, $genres, 'genre');
    if (!empty($type)) wp_set_object_terms($manga_id, [$type], 'manga_type', false);
    if (!empty($status)) wp_set_object_terms($manga_id, [$status], 'manga_status', false);
    update_post_meta($manga_id, '_xcomix_last_checked', time());

    // CHAPTER EXTRACTION
    $chRows = $xpath->query('//*[contains(@class, "chapters")]//tr | //*[contains(@class, "chapters")]//li | //*[contains(@class, "chapters")]//div[contains(@class, "chapter")]');
    $added = 0;

    if ($chRows->length === 0) {
        $chRows = $xpath->query('//*[contains(@class, "chapters")]//a[contains(@href, "/c")]');
        $chArray = array_reverse(iterator_to_array($chRows));
        foreach ($chArray as $aNode) {
            $cUrl = strtok($aNode->getAttribute('href'), '?');
            $exists = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_katana_url' AND meta_value = %s", $cUrl));
            if (!$exists) {
                $cTitle = trim($aNode->textContent);
                preg_match('/([0-9]+(?:\.[0-9]+)?)/', $cTitle, $m);
                $num = (float)($m[1] ?? 0);

                $chap_id = wp_insert_post([
                    'post_title' => $cTitle, 'post_status' => $import_status, 'post_type' => 'chapter',
                    'post_parent' => $manga_id, 'post_name' => sanitize_title($slug . '-' . $num)
                ]);
                update_post_meta($chap_id, '_chapter_number', $num);
                update_post_meta($chap_id, '_mv_chapter_number', $num);
                update_post_meta($chap_id, '_mv_parent_manga', $manga_id);
                update_post_meta($chap_id, '_parent_manga_id', $manga_id);
                update_post_meta($chap_id, '_mv_source_url', $cUrl);
                update_post_meta($chap_id, '_katana_url', $cUrl);
                $added++;
            }
        }
    } else {
        $chArray = array_reverse(iterator_to_array($chRows));
        foreach ($chArray as $row) {
            $aNode = $xpath->query('.//a', $row)->item(0);
            if (!$aNode) continue;

            $cUrl = strtok($aNode->getAttribute('href'), '?');
            $exists = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_katana_url' AND meta_value = %s", $cUrl));

            if (!$exists) {
                $cTitle = trim($aNode->textContent);
                preg_match('/([0-9]+(?:\.[0-9]+)?)/', $cTitle, $m);
                $num = (float)($m[1] ?? 0);

                $timeNode = $xpath->query('./td[2] | .//*[contains(@class, "up_time")] | .//*[contains(@class, "time")]', $row)->item(0);
                $dateStr = $timeNode ? trim($timeNode->textContent) : 'now';
                $timestamp = strtotime($dateStr);
                if (!$timestamp || $timestamp > time()) $timestamp = time();
                $true_post_date = gmdate('Y-m-d H:i:s', $timestamp + ( get_option('gmt_offset') * HOUR_IN_SECONDS ));

                $chap_id = wp_insert_post([
                    'post_title' => $cTitle, 'post_status' => $import_status, 'post_type' => 'chapter',
                    'post_parent' => $manga_id, 'post_name' => sanitize_title($slug . '-' . $num),
                    'post_date' => $true_post_date, 'post_date_gmt' => get_gmt_from_date($true_post_date)
                ]);
                update_post_meta($chap_id, '_chapter_number', $num);
                update_post_meta($chap_id, '_mv_chapter_number', $num);
                update_post_meta($chap_id, '_mv_parent_manga', $manga_id);
                update_post_meta($chap_id, '_parent_manga_id', $manga_id);
                update_post_meta($chap_id, '_mv_source_url', $cUrl);
                update_post_meta($chap_id, '_katana_url', $cUrl);
                $added++;
            }
        }
    }

    if ($added > 0) wp_update_post(['ID' => $manga_id]);

    if ($action_type === 'nuke') {
        return ["> [KATANA] <a href='$url' target='_blank' style='color:#a855f7;'><b>[NUKED & REBUILT] $title</b></a> | Added: $added Chapters"];
    } elseif ($action_type === 'override') {
        return ["> [KATANA] <a href='$url' target='_blank' style='color:#3b82f6;'><b>[OVERRIDDEN] $title</b></a> | Added: $added Chapters"];
    } else {
        $chap_debug = ($added > 0) ? "<span style='color:#22c55e; font-weight:bold;'>+$added New</span>" : "<span style='color:#9ca3af;'>Up to date</span>";
        return ["> [KATANA] <a href='$url' target='_blank' style='color:#ea580c;'><b>$title</b></a> | Chapters: $chap_debug"];
    }
}

// =========================================================================
// 6. RAW REGEX CATCH-ALL EXTRACTOR
// =========================================================================
function xcomix_extract_strict_links($html) {
    $links = [];
    if (preg_match_all('/href=["\'](https?:\/\/(?:www\.)?mangakatana\.com\/manga\/[^\/"\']+)["\']/i', $html, $matches)) {
        foreach ($matches[1] as $href) {
            if (strpos($href, '/c') === false) {
                $links[] = strtok($href, '?');
            }
        }
    }
    return array_values(array_unique($links));
}

// =========================================================================
// 7. AUTOPILOT - LATEST UPDATES
// =========================================================================
function xcomix_run_autopilot() {
    $target_url = 'https://mangakatana.com/latest';
    $html = xcomix_fetch($target_url);
    $logs = [];

    if ($html && strpos($html, 'Cloudflare') === false && strpos($html, 'Just a moment') === false) {
        $linksFound = xcomix_extract_strict_links($html);
        if (empty($linksFound)) {
             $logs[] = "❌ Autopilot Found 0 links. Target blocked or layout wiped.";
        } else {
            $to_process = array_slice($linksFound, 0, 15);
            $delay = (int) get_option('xcomix_stealth_delay', 300000);

            foreach ($to_process as $href) {
                $result = xcomix_process_manga($href, 'standard');
                $logs[] = $result[0];
                usleep($delay);
            }
        }
    } else {
        $logs[] = "❌ Autopilot Failed: Blocked by Cloudflare JS Challenge. Please update Cookie in Settings.";
    }

    return $logs;
}

add_action('wp_ajax_xcomix_run_autopilot_manual', function() {
    if (!current_user_can('manage_options')) wp_die();
    set_time_limit(0);
    $logs = xcomix_run_autopilot();
    wp_send_json_success(['logs' => $logs]);
});

// =========================================================================
// 8. AUTOPILOT - DEEP BACKLOG CRAWLER
// =========================================================================
function xcomix_run_deep_scraper() {
    $page = (int) get_option('xcomix_deep_page', 2);
    $target_url = "https://mangakatana.com/latest/page/$page";
    $html = xcomix_fetch($target_url);
    $logs = [];

    if ($html && strpos($html, 'Cloudflare') === false && strpos($html, 'Just a moment') === false) {
        $linksFound = xcomix_extract_strict_links($html);

        if (!empty($linksFound)) {
            $to_process = array_slice($linksFound, 0, 15);
            $delay = (int) get_option('xcomix_stealth_delay', 300000);

            foreach ($to_process as $href) {
                $result = xcomix_process_manga($href, 'standard');
                $logs[] = $result[0];
                usleep($delay);
            }
            update_option('xcomix_deep_page', $page + 1);
            $logs[] = "<span style='color:#eab308;'>✅ Page $page Backlog processed. Moving to Page " . ($page + 1) . " next run.</span>";
        } else {
            $logs[] = "❌ End of backlog reached at page $page. 0 links found.";
        }
    } else {
        $logs[] = "❌ Failed to connect to page $page (Cloudflare Block). Please update Cookie in Settings.";
    }

    return $logs;
}

add_action('wp_ajax_xcomix_run_deep_manual', function() {
    if (!current_user_can('manage_options')) wp_die();
    set_time_limit(0);
    $logs = xcomix_run_deep_scraper();
    wp_send_json_success(['logs' => $logs]);
});

// =========================================================================
// 9. AJAX CATEGORY FETCHER
// =========================================================================
add_action('wp_ajax_xcomix_fetch_category', 'xcomix_ajax_fetch_category');
function xcomix_ajax_fetch_category() {
    if (!current_user_can('manage_options')) wp_die();
    set_time_limit(0);
    $url = esc_url_raw($_POST['cat_url']);
    $html = xcomix_fetch($url);

    if (!$html || strpos($html, 'Cloudflare') !== false || strpos($html, 'Just a moment') !== false) {
        wp_send_json_success(['links' => [], 'error' => 'Blocked by Cloudflare Anti-Bot. Update Cookie.']);
    }

    $linksFound = xcomix_extract_strict_links($html);
    wp_send_json_success(['links' => $linksFound]);
}

// =========================================================================
// 10. UI DASHBOARD
// =========================================================================
function xcomix_scraper_page_html() {
    global $wpdb;
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['save_xcomix_settings'])) {
        update_option('xcomix_post_status', sanitize_text_field($_POST['xcomix_post_status']));
        update_option('xcomix_stealth_delay', intval($_POST['xcomix_stealth_delay']));
        update_option('xcomix_cron_secret', sanitize_text_field($_POST['xcomix_cron_secret']));
        update_option('xcomix_cf_cookie', sanitize_text_field($_POST['xcomix_cf_cookie']));
        update_option('xcomix_user_agent', sanitize_text_field($_POST['xcomix_user_agent']));
        if (isset($_POST['xcomix_deep_page'])) {
            update_option('xcomix_deep_page', intval($_POST['xcomix_deep_page']));
        }
    }

    if (isset($_POST['nuke_katana_data'])) {
        $posts = $wpdb->get_results("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_katana_url'");
        foreach ($posts as $p) { wp_delete_post($p->post_id, true); }
    }

    $opt_status = get_option('xcomix_post_status', 'publish');
    $opt_delay = get_option('xcomix_stealth_delay', 300000);
    $opt_secret = get_option('xcomix_cron_secret', 'rifat_secure_cron');
    $opt_cookie = get_option('xcomix_cf_cookie', '');
    $opt_ua = get_option('xcomix_user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0.0.0 Safari/537.36');
    $current_deep_page = get_option('xcomix_deep_page', 2);

    $cron_latest = site_url() . '/?xcomix_cron=run&secret=' . $opt_secret;
    $cron_deep = site_url() . '/?xcomix_cron=deep&secret=' . $opt_secret;
    ?>
    <div class="wrap" style="font-family: system-ui, -apple-system, sans-serif; max-width: 1200px;">
        <h1 style="font-weight:900; color:#ea580c; font-size: 28px; margin-bottom: 5px;">MANGAKATANA ENGINE V9.11</h1>
        <p style="font-size: 14px; color: #666; font-weight: 600;">Cloudflare Cookie Support | Buddy Override Tool</p>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:30px;">

            <div style="background:#09090b; padding:25px; border-radius:12px; border:1px solid #ef4444; grid-column: span 2;">
                <h2 style="margin-top:0; color:white; border-bottom: 2px solid #ef4444; padding-bottom: 10px;">🛡️ Cloudflare Bypass Settings</h2>
                <p style="color:#aaa; font-size:13px; margin-bottom: 15px;">If the scraper is blocked, you must provide your browser's Clearance Cookie to continue scraping Deep Categories.</p>
                <form method="POST" style="display:flex; flex-wrap: wrap; gap: 15px;">
                    <input type="hidden" name="save_xcomix_settings" value="1">
                    <input type="hidden" name="xcomix_post_status" value="<?php echo esc_attr($opt_status); ?>">
                    <input type="hidden" name="xcomix_stealth_delay" value="<?php echo esc_attr($opt_delay); ?>">
                    <input type="hidden" name="xcomix_cron_secret" value="<?php echo esc_attr($opt_secret); ?>">

                    <div style="flex: 1 1 100%;">
                        <label style="color:#ef4444; font-weight:bold; display:block; margin-bottom:5px;">Clearance Cookie (cf_clearance)</label>
                        <input type="text" name="xcomix_cf_cookie" value="<?php echo esc_attr($opt_cookie); ?>" placeholder="e.g. cf_clearance=xYz123..." style="width:100%; padding:10px; border-radius:6px; background:#121212; border:1px solid #444; color:white;">
                    </div>
                    <div style="flex: 1 1 100%;">
                        <label style="color:#ccc; font-weight:bold; display:block; margin-bottom:5px;">Your Exact User-Agent</label>
                        <input type="text" name="xcomix_user_agent" value="<?php echo esc_attr($opt_ua); ?>" style="width:100%; padding:10px; border-radius:6px; background:#121212; border:1px solid #444; color:white;">
                    </div>
                    <div style="flex: 1 1 100%;">
                        <button type="submit" class="button button-primary" style="background:#ef4444; border:none; height:45px; font-weight:bold; width:100%; font-size:16px;">Save Evasion Parameters</button>
                    </div>
                </form>
            </div>

            <div style="background:#09090b; padding:25px; border-radius:12px; border:1px solid #333; grid-column: span 1; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
                <h2 style="margin-top:0; color:white; border-bottom: 2px solid #ea580c; padding-bottom: 10px;">🎯 Standard Importer</h2>
                <form onsubmit="startSingleScrape(event, 'standard', 'standard-btn', 'standard-terminal')">
                    <input type="url" id="standard_manga_url" placeholder="e.g. https://mangakatana.com/manga/..." style="width:100%; padding:10px; border-radius:6px; background:#121212; border:1px solid #444; color:white; margin-bottom:10px;" required>
                    <button type="submit" id="standard-btn" class="button button-primary" style="background:#ea580c; border:none; height:40px; font-weight:bold; width:100%;">Import Manga</button>
                </form>
                <div id="standard-terminal" style="margin-top:15px; background:#000; color:#ea580c; padding:15px; border-radius:8px; font-family:monospace; font-size:11px; max-height:150px; overflow-y:auto; border:1px solid #222; display:none;"></div>
            </div>

            <div style="background:#09090b; padding:25px; border-radius:12px; border:1px solid #3b82f6; grid-column: span 1; box-shadow: 0 10px 25px rgba(59, 130, 246, 0.1);">
                <h2 style="margin-top:0; color:white; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;">🔄 Buddy Override Importer</h2>
                <p style="color:#aaa; font-size:13px; margin-bottom:15px;">Takes ownership of a manga previously imported by Buddy and updates it with Katana data.</p>
                <form onsubmit="startSingleScrape(event, 'override', 'override-btn', 'override-terminal')">
                    <input type="url" id="override_manga_url" placeholder="e.g. https://mangakatana.com/manga/..." style="width:100%; padding:10px; border-radius:6px; background:#121212; border:1px solid #444; color:white; margin-bottom:10px;" required>
                    <button type="submit" id="override-btn" class="button button-primary" style="background:#3b82f6; border:none; height:40px; font-weight:bold; width:100%;">Override Buddy Manga</button>
                </form>
                <div id="override-terminal" style="margin-top:15px; background:#000; color:#3b82f6; padding:15px; border-radius:8px; font-family:monospace; font-size:11px; max-height:150px; overflow-y:auto; border:1px solid #222; display:none;"></div>
            </div>

            <div style="background:#09090b; padding:25px; border-radius:12px; border:1px solid #a855f7; grid-column: span 1; box-shadow: 0 10px 25px rgba(168, 85, 247, 0.1);">
                <h2 style="margin-top:0; color:white; border-bottom: 2px solid #a855f7; padding-bottom: 10px;">☢️ Nuke & Rebuild Override</h2>
                <form onsubmit="startSingleScrape(event, 'nuke', 'nuke-btn', 'nuke-terminal')">
                    <input type="url" id="nuke_manga_url" placeholder="e.g. https://mangakatana.com/manga/..." style="width:100%; padding:10px; border-radius:6px; background:#121212; border:1px solid #444; color:white; margin-bottom:10px;" required>
                    <button type="submit" id="nuke-btn" class="button button-primary" style="background:#a855f7; border:none; height:40px; font-weight:bold; width:100%;">NUKE & REBUILD</button>
                </form>
                <div id="nuke-terminal" style="margin-top:15px; background:#000; color:#a855f7; padding:15px; border-radius:8px; font-family:monospace; font-size:11px; max-height:150px; overflow-y:auto; border:1px solid #222; display:none;"></div>
            </div>

            <div style="background:#fff; padding:25px; border-radius:12px; border:1px solid #e5e7eb;">
                <h2 style="margin-top:0; border-bottom: 2px solid #22c55e; padding-bottom: 10px;">🤖 Latest Update Autopilot</h2>
                <div style="background:#f3f4f6; padding:12px; border-radius:8px; margin-bottom:15px;">
                    <strong style="display:block; margin-bottom:5px; font-size:12px;">CRON-JOB.ORG URL:</strong>
                    <code style="word-break: break-all; color:#ea580c; background:#111; padding:8px; display:block; border-radius:4px; font-size:11px;"><?php echo esc_url($cron_latest); ?></code>
                </div>
                <form onsubmit="runManualAutopilot(event, 'xcomix_run_autopilot_manual', 'auto-btn', 'auto-terminal')">
                    <button type="submit" id="auto-btn" class="button button-primary" style="background:#22c55e; border:none; height:40px; width:100%; font-weight:bold;">Run Latest Scanner Now</button>
                </form>
                <div id="auto-terminal" style="margin-top:15px; background:#111; color:#22c55e; padding:10px; border-radius:6px; font-family:monospace; font-size:11px; height:150px; overflow-y:auto; border:1px solid #333; display:none;"></div>
            </div>

            <div style="background:#fff; padding:25px; border-radius:12px; border:1px solid #e5e7eb;">
                <h2 style="margin-top:0; border-bottom: 2px solid #eab308; padding-bottom: 10px;">📚 Deep Backlog Crawler</h2>
                <div style="background:#f3f4f6; padding:12px; border-radius:8px; margin-bottom:15px;">
                    <strong style="display:block; margin-bottom:5px; font-size:12px;">CRON-JOB.ORG URL:</strong>
                    <code style="word-break: break-all; color:#ea580c; background:#111; padding:8px; display:block; border-radius:4px; font-size:11px;"><?php echo esc_url($cron_deep); ?></code>
                </div>
                <form onsubmit="runManualAutopilot(event, 'xcomix_run_deep_manual', 'deep-btn', 'deep-terminal')">
                    <button type="submit" id="deep-btn" class="button button-primary" style="background:#eab308; border:none; height:40px; width:100%; font-weight:bold;">Run Backlog Crawler Now</button>
                </form>
                <div id="deep-terminal" style="margin-top:15px; background:#111; color:#eab308; padding:10px; border-radius:6px; font-family:monospace; font-size:11px; height:150px; overflow-y:auto; border:1px solid #333; display:none;"></div>
            </div>

            <div style="background:#09090b; padding:25px; border-radius:12px; border:1px solid #333; grid-column: span 1;">
                <h2 style="margin-top:0; color:white; border-bottom: 2px solid #ea580c; padding-bottom: 10px;">🚀 AJAX Deep Category Scraper</h2>
                <form id="deep-scrape-form" style="display:flex; flex-wrap: wrap; gap: 15px; margin-top: 15px;" onsubmit="startDeepScrape(event)">
                    <div style="flex: 1 1 100%;"><label style="color:#ccc; font-weight:bold; display:block; margin-bottom:5px;">Category Base URL</label><input type="url" id="deep_url" placeholder="e.g., https://mangakatana.com/genre/manhua" style="width:100%; padding:10px; border-radius:6px; background:#121212; border:1px solid #444; color:white;" required></div>
                    <div style="flex: 1;"><label style="color:#ccc; font-weight:bold; display:block; margin-bottom:5px;">Start Page</label><input type="number" id="deep_start" value="1" min="1" style="width:100%; padding:10px; border-radius:6px; background:#121212; border:1px solid #444; color:white;" required></div>
                    <div style="flex: 1;"><label style="color:#ccc; font-weight:bold; display:block; margin-bottom:5px;">End Page</label><input type="number" id="deep_end" value="5" min="1" style="width:100%; padding:10px; border-radius:6px; background:#121212; border:1px solid #444; color:white;" required></div>
                    <div style="flex: 1;"><label style="color:#ccc; font-weight:bold; display:block; margin-bottom:5px;">Delay (ms)</label><input type="number" id="deep_delay" value="1000" min="500" step="100" style="width:100%; padding:10px; border-radius:6px; background:#121212; border:1px solid #444; color:white;" required></div>
                    <div style="flex: 1 1 100%;"><button type="submit" id="deep-scrape-btn" class="button button-primary" style="background:#ea580c; border:none; height:45px; font-weight:bold; width:100%; font-size:16px;">Start Deep Scrape Protocol</button></div>
                </form>
                <div id="deep-terminal" style="margin-top:20px; background:#000; color:#22c55e; padding:15px; border-radius:8px; font-family:monospace; font-size:13px; line-height: 1.6; height:300px; overflow-y:auto; border:1px solid #333; display:none;"></div>
            </div>

            <div style="background:#fff; padding:25px; border-radius:12px; border:1px solid #e5e7eb; grid-column: span 1;">
                <h2 style="margin-top:0; border-bottom: 2px solid #ea580c; padding-bottom: 10px;">⚙️ Engine Settings & Options</h2>
                <form method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="save_xcomix_settings" value="1">
                    <input type="hidden" name="xcomix_cf_cookie" value="<?php echo esc_attr($opt_cookie); ?>">
                    <input type="hidden" name="xcomix_user_agent" value="<?php echo esc_attr($opt_ua); ?>">

                    <select name="xcomix_post_status" style="width:100%; padding:8px; border-radius:6px; margin-bottom: 15px;"><option value="publish" <?php selected($opt_status, 'publish'); ?>>Auto-Publish</option><option value="draft" <?php selected($opt_status, 'draft'); ?>>Draft</option></select>
                    <select name="xcomix_stealth_delay" style="width:100%; padding:8px; border-radius:6px; margin-bottom: 15px;"><option value="100000" <?php selected($opt_delay, 100000); ?>>Fast (0.1s)</option><option value="300000" <?php selected($opt_delay, 300000); ?>>Normal (0.3s)</option><option value="1000000" <?php selected($opt_delay, 1000000); ?>>Safe (1.0s)</option></select>
                    <input type="number" name="xcomix_deep_page" value="<?php echo esc_attr($current_deep_page); ?>" style="width:100%; padding:8px; border-radius:6px; margin-bottom: 15px;">
                    <input type="text" name="xcomix_cron_secret" value="<?php echo esc_attr($opt_secret); ?>" style="width:100%; padding:8px; border-radius:6px; margin-bottom: 15px;" required>
                    <button type="submit" class="button button-primary" style="background:#09090b; border-color:#09090b; width:100%;">Save Settings</button>
                </form>
                <hr style="margin:20px 0;">
                <form method="POST" onsubmit="return confirm('Wipe all Katana Engine data?');">
                    <input type="hidden" name="nuke_katana_data" value="1">
                    <button type="submit" class="button" style="background:#ef4444; color:white; border:none; height:40px; width:100%; font-weight:bold;">🗑️ NUKE ALL KATANA DATA</button>
                </form>
            </div>

        </div>
    </div>

    <script>
        async function runManualAutopilot(e, actionName, btnId, termId) {
            e.preventDefault();
            const btn = document.getElementById(btnId); const term = document.getElementById(termId);
            btn.disabled = true; btn.innerText = 'Scanning...';
            term.style.display = 'block'; term.innerHTML = '> 🤖 Connecting to matrix...';

            try {
                let fd = new FormData(); fd.append('action', actionName);
                let res = await fetch(ajaxurl, { method: 'POST', body: fd }); let data = await res.json();

                if (data.success && data.data.logs) {
                    term.innerHTML = '';
                    data.data.logs.forEach(log => { term.innerHTML += `<div>${log}</div>`; });
                    term.innerHTML += `<div style="margin-top:10px; color:#eab308;">> ✅ Scan Complete. Processed ${data.data.logs.length} updates.</div>`;
                }
            } catch (err) { term.innerHTML += `<div style="color:#ef4444;">> ❌ Fatal Error executing query.</div>`; }
            btn.disabled = false; btn.innerText = 'Run Scanner Now'; term.scrollTop = term.scrollHeight;
        }

        async function startSingleScrape(e, actionType, btnId, termId) {
            e.preventDefault();
            const btn = document.getElementById(btnId); const term = document.getElementById(termId);
            let urlInputId = actionType === 'nuke' ? 'nuke_manga_url' : (actionType === 'override' ? 'override_manga_url' : 'standard_manga_url');
            const url = document.getElementById(urlInputId).value;

            btn.disabled = true; btn.style.opacity = '0.5';
            btn.innerText = 'Processing...';
            term.style.display = 'block';
            term.innerHTML = '<div style="color:#ea580c;">> 🚀 Executing ' + actionType + ' process...</div>';

            try {
                let mFd = new FormData();
                mFd.append('action', 'xcomix_process_single');
                mFd.append('manga_url', url);
                mFd.append('action_type', actionType);

                let mRes = await fetch(ajaxurl, { method: 'POST', body: mFd }); let mData = await mRes.json();

                if (mData.success) { term.innerHTML += `<div style="margin-top:10px;">${mData.data.log}</div>`; }
                else { term.innerHTML += `<div style="color:#ef4444; margin-top:10px;">> ❌ Error processing manga.</div>`; }
            } catch (err) { term.innerHTML += `<div style="color:#ef4444; margin-top:10px;">> [FATAL] Server connection error.</div>`; }

            btn.disabled = false; btn.style.opacity = '1';
            btn.innerText = 'Run Tool';
            document.getElementById(urlInputId).value = ''; term.scrollTop = term.scrollHeight;
        }

        async function startDeepScrape(e) {
            e.preventDefault();
            const btn = document.getElementById('deep-scrape-btn'); const term = document.getElementById('deep-terminal');

            let rawUrl = document.getElementById('deep_url').value;
            let baseUrl = rawUrl.replace(/\/page\/[0-9]+/, '').replace(/\/$/, '');

            const startPage = parseInt(document.getElementById('deep_start').value);
            const endPage = parseInt(document.getElementById('deep_end').value);
            const delay = parseInt(document.getElementById('deep_delay').value);

            btn.disabled = true; btn.style.opacity = '0.5'; btn.innerText = 'Scraping in Progress...';
            term.style.display = 'block'; term.innerHTML = '<div style="color:#eab308;">> 🚀 Initiating Deep Scrape Protocol...</div>';

            for (let p = startPage; p <= endPage; p++) {
                let pageUrl = p === 1 ? baseUrl : `${baseUrl}/page/${p}`;
                term.innerHTML += `<div style="color:#fff; margin-top:20px; padding-top:10px; border-top:1px dashed #444;">> <strong>[PAGE ${p} OF ${endPage}]</strong> Fetching Catalog: <a href="${pageUrl}" target="_blank" style="color:#3b82f6;">${pageUrl}</a></div>`;
                term.scrollTop = term.scrollHeight;

                try {
                    let fd = new FormData(); fd.append('action', 'xcomix_fetch_category'); fd.append('cat_url', pageUrl);
                    let res = await fetch(ajaxurl, { method: 'POST', body: fd }); let data = await res.json();

                    if (data.success && data.data.links && data.data.links.length > 0) {
                        term.innerHTML += `<div style="color:#3b82f6;">> Found ${data.data.links.length} strict manga entries.</div>`;
                        term.scrollTop = term.scrollHeight;

                        for (let i = 0; i < data.data.links.length; i++) {
                            let mangaUrl = data.data.links[i];
                            term.innerHTML += `<div style="color:#888;">> Extracting [${i+1}/${data.data.links.length}]: ${mangaUrl}</div>`;
                            term.scrollTop = term.scrollHeight;

                            let mFd = new FormData(); mFd.append('action', 'xcomix_process_single'); mFd.append('manga_url', mangaUrl); mFd.append('action_type', 'standard');
                            let mRes = await fetch(ajaxurl, { method: 'POST', body: mFd }); let mData = await mRes.json();

                            if (mData.success) { term.innerHTML += `<div style="color:#22c55e;">${mData.data.log}</div>`; }
                            else { term.innerHTML += `<div style="color:#ef4444;">> ❌ Error processing manga.</div>`; }
                            term.scrollTop = term.scrollHeight;

                            await new Promise(r => setTimeout(r, delay));
                        }
                    } else {
                        let errMsg = data.data && data.data.error ? data.data.error : `Reached end of category.`;
                        term.innerHTML += `<div style="color:#ef4444;">> [STOP] ${errMsg}</div>`; break;
                    }
                } catch(err) { term.innerHTML += `<div style="color:#ef4444;">> [FATAL] Error on page ${p}.</div>`; }

                if (p < endPage) {
                    term.innerHTML += `<div style="color:#a855f7;">> ✔️ Page ${p} complete. Waiting 3 seconds before next page to prevent bans...</div>`;
                    await new Promise(r => setTimeout(r, 3000));
                } else {
                    term.innerHTML += `<div style="color:#22c55e;">> ✔️ Page ${p} complete. Reached final target.</div>`;
                }
            }
            btn.disabled = false; btn.style.opacity = '1'; btn.innerText = 'Start Deep Scrape Protocol';
            term.innerHTML += '<div style="color:#eab308; margin-top:20px; font-weight:bold;">> 🏁 Protocol Completed!</div>'; term.scrollTop = term.scrollHeight;
        }
    </script>
    <?php
}
