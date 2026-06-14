<?php
/**
 * Plugin Name: X COMIX - ManhwaBuddy Engine
 * Description: V5.3 18+ Force-Import Engine. Now includes Native WP-Cron (runs on user visits), Concurrency Locks, and Auto-Recovery.
 * Version: 5.3
 * Author: X COMIX
 */

if (!defined('ABSPATH')) exit;

// =========================================================================
// 1. ISOLATED CRON & ADMIN MENU
// =========================================================================
add_action('parse_request', 'buddy_external_cron_endpoint');
function buddy_external_cron_endpoint($wp) {
    if (isset($_GET['buddy_cron'])) {
        $saved_secret = get_option('buddy_cron_secret', 'rifat_secure_cron');
        if (isset($_GET['secret']) && $_GET['secret'] === $saved_secret) {
            while (ob_get_level()) ob_end_clean();
            status_header(200); header('Content-Type: application/json; charset=utf-8');
            
            // DUAL CRON ROUTER
            if ($_GET['buddy_cron'] === 'run') {
                $logs = buddy_run_autopilot(15);
                echo wp_json_encode(['status' => 'success', 'mode' => '18plus_latest', 'processed' => count($logs), 'logs' => $logs]);
            } elseif ($_GET['buddy_cron'] === 'deep') {
                $logs = buddy_run_deep_scraper(15);
                echo wp_json_encode(['status' => 'success', 'mode' => '18plus_backlog', 'processed' => count($logs), 'logs' => $logs]);
            }
            exit;
        }
    }
}

add_action('admin_menu', function() {
    add_menu_page('Buddy Engine', 'Buddy Engine', 'manage_options', 'buddy-scraper', 'buddy_scraper_page_html', 'dashicons-buddicons-friends', 21);
});

// =========================================================================
// 2. NATIVE WP-CRON (RUNS ON USER VISITS) - NEW IN V5.3
// =========================================================================
add_filter('cron_schedules', function($schedules) {
    $schedules['buddy_thirty_mins'] = [
        'interval' => 1800, // 30 minutes
        'display'  => 'Every 30 Minutes (Buddy Engine)'
    ];
    return $schedules;
});

add_action('init', function() {
    if (get_option('buddy_enable_wpcron', 'yes') === 'yes') {
        if (!wp_next_scheduled('buddy_internal_cron_latest')) {
            wp_schedule_event(time(), 'buddy_thirty_mins', 'buddy_internal_cron_latest');
        }
        if (!wp_next_scheduled('buddy_internal_cron_deep')) {
            wp_schedule_event(time(), 'hourly', 'buddy_internal_cron_deep');
        }
    } else {
        $timestamp_latest = wp_next_scheduled('buddy_internal_cron_latest');
        if ($timestamp_latest) wp_unschedule_event($timestamp_latest, 'buddy_internal_cron_latest');
        
        $timestamp_deep = wp_next_scheduled('buddy_internal_cron_deep');
        if ($timestamp_deep) wp_unschedule_event($timestamp_deep, 'buddy_internal_cron_deep');
    }
});

add_action('buddy_internal_cron_latest', function() {
    // Lock prevents overlapping processes if multiple users trigger the cron simultaneously
    if (get_transient('buddy_lock_latest')) return;
    set_transient('buddy_lock_latest', true, 15 * 60); 
    
    buddy_run_autopilot(5); // Smaller batch size (5) to prevent PHP timeout on user pageload
    
    delete_transient('buddy_lock_latest');
});

add_action('buddy_internal_cron_deep', function() {
    if (get_transient('buddy_lock_deep')) return;
    set_transient('buddy_lock_deep', true, 30 * 60);
    
    buddy_run_deep_scraper(5); // Smaller batch size (5)
    
    delete_transient('buddy_lock_deep');
});

// =========================================================================
// 3. THE BACKEND FETCH ENGINE
// =========================================================================
function buddy_fetch($url, $is_post = false, $referer = 'https://manhwabuddy.com/') {
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_ENCODING => '', 
        CURLOPT_COOKIE => "wpmanga-adault=1; wpmanga-adult=1; mature_warning_accept=yes; content_safe=1;",
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0.0.0 Safari/537.36',
            'Referer: ' . $referer
        ]
    ];
    if ($is_post) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = "action=manga_get_chapters";
        $options[CURLOPT_HTTPHEADER][] = 'X-Requested-With: XMLHttpRequest';
    }
    curl_setopt_array($ch, $options);
    $res = curl_exec($ch); curl_close($ch); return $res;
}

function buddy_fetch_admin_ajax($id, $referer) {
    $ch = curl_init('https://manhwabuddy.com/wp-admin/admin-ajax.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['action' => 'manga_get_chapters', 'manga' => $id]),
        CURLOPT_COOKIE => "wpmanga-adault=1; wpmanga-adult=1; mature_warning_accept=yes; content_safe=1;",
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0.0.0 Safari/537.36',
            'Referer: ' . $referer, 'X-Requested-With: XMLHttpRequest', 'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);
    $res = curl_exec($ch); curl_close($ch); return $res;
}

// =========================================================================
// 4. CORE PHP PROCESSOR (18+ FORCE-IMPORT)
// =========================================================================
add_action('wp_ajax_buddy_process_single', 'buddy_ajax_process_single_ajax');
function buddy_ajax_process_single_ajax() {
    if (!current_user_can('manage_options')) wp_die();
    $url = esc_url_raw($_POST['manga_url']);
    $log = buddy_process_single($url);
    wp_send_json_success(['log' => $log]);
}

function buddy_process_single($url) {
    global $wpdb;
    
    if (stripos($url, '-raw') !== false) return "<span style='color:#6b7280;'>> ⏭️ Skipped: RAW URL.</span>";

    $html = buddy_fetch($url);
    if (!$html) return "<span style='color:#ef4444;'>❌ Failed to fetch: $url</span>";

    $title = 'Unknown Title';
    if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) {
        $title = html_entity_decode($m[1], ENT_QUOTES);
    }
    $title = preg_replace('/^Read\s+/i', '', $title);
    $title = preg_replace('/\s*Manhwa\s*\[Latest Chapters\]\s*\.COM/i', '', $title);
    $title = preg_replace('/\s*\[Latest Chapters\]/i', '', $title);
    $title = str_ireplace(['.COM', '- ManhwaBuddy', 'ManhwaBuddy', '|'], '', $title);
    $title = preg_replace('/\s+Manhwa$/i', '', trim($title)); 
    $title = trim($title);

    if (preg_match('/\b(raw|raws)\b/i', $title)) return "<span style='color:#6b7280;'>> ⏭️ Skipped: '$title' is a RAW.</span>";

    $desc = '';
    if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) $desc = html_entity_decode($m[1], ENT_QUOTES);

    $cover = '';
    if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) $cover = $m[1];

    $genres = [];
    $clean_html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $clean_html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $clean_html);
    $clean_html = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>', '</a>'], ' ', $clean_html);
    $plain_text = preg_replace('/\s+/', ' ', strip_tags($clean_html));
    
    if (preg_match('/(?:Genre|Genres)\s*[:-]?\s+(.{1,200}?)\s+(?:Type|Release|Status|Author|Artist|Alternative|Bookmark|Share)/i', $plain_text, $m)) {
        $genre_string = trim($m[1]);
        $parts = preg_split('/[\/,|]/', $genre_string);
        if (count($parts) === 1 && strpos($genre_string, ' ') !== false && strpos($genre_string, '/') === false && strpos($genre_string, ',') === false) {
             $parts = explode(' ', $genre_string);
        }
        foreach ($parts as $p) {
            $g = ucwords(trim($p));
            if (!empty($g) && strlen($g) < 25) $genres[] = $g;
        }
    }

    $genres = array_unique($genres);
    $type = 'Manhwa';

    foreach ($genres as $g) {
        $gl = strtolower($g);
        if($gl === 'manga') $type = 'Manga';
        if($gl === 'manhua') $type = 'Manhua';
        if(strpos($gl, 'raw') !== false) return "<span style='color:#6b7280;'>> ⏭️ Skipped RAW genre.</span>";
    }

    // FORCE 18+ TAGS
    if (!in_array('18+', $genres)) $genres[] = '18+';
    if (!in_array('Mature', $genres)) $genres[] = 'Mature';

    $slug = sanitize_title(basename(rtrim($url, '/')));
    $import_status = get_option('buddy_post_status', 'publish');

    $manga_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_buddy_url' AND meta_value = %s", $url));

    if (!$manga_id) {
        $katana_check = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_type = 'manga' AND (post_name = %s OR post_title = %s) LIMIT 1", 
            $slug, $title
        ));

        if ($katana_check) {
            return "<span style='color:#eab308; font-weight:bold;'>> ⏭️ Skipped: '{$title}' is already owned by another scraper.</span>";
        }

        $manga_id = wp_insert_post([
            'post_title' => $title, 'post_content' => $desc, 'post_status' => $import_status, 'post_type' => 'manga', 'post_name' => $slug
        ]);
        update_post_meta($manga_id, '_buddy_url', $url);
        update_post_meta($manga_id, '_manga_type', $type);
        update_post_meta($manga_id, '_is_18_plus', 1);
        update_post_meta($manga_id, '_manga_cover_url', $cover);
    }
    
    wp_set_object_terms($manga_id, $genres, 'genre');

    $internal_id = '';
    if (preg_match('/data-id=["\'](\d{2,7})["\']/i', $html, $m)) $internal_id = $m[1];
    elseif (preg_match('/manga_id.*?(\d{2,7})/i', $html, $m)) $internal_id = $m[1];
    elseif (preg_match('/rating-post-id["\'][^>]*value=["\'](\d+)["\']/i', $html, $m)) $internal_id = $m[1];
    elseif (preg_match('/<link\s+rel=[\'"]shortlink[\'"]\s+href=[\'"][^\?]+\?p=(\d+)[\'"]/i', $html, $m)) $internal_id = $m[1];

    $ch_html = '';
    if ($internal_id) $ch_html .= buddy_fetch_admin_ajax($internal_id, $url);
    $ch_html .= buddy_fetch(rtrim($url, '/') . '/ajax/chapters/', true, $url);

    $combined_html = $html . $ch_html;
    $chapter_links = [];
    $manga_slug = basename(rtrim($url, '/'));

    if (preg_match_all('/href=["\']([^"\']+(?:chapter|ch)-?\d+[^"\']*)["\']/i', $combined_html, $links)) {
        foreach ($links[1] as $link) {
            if (stripos($link, $manga_slug) !== false) {
                if (strpos($link, 'http') !== 0) $link = rtrim('https://manhwabuddy.com', '/') . '/' . ltrim($link, '/');
                $chapter_links[] = $link;
            }
        }
    }
    
    $chapter_links = array_unique($chapter_links);
    $added = 0;

    foreach ($chapter_links as $cUrl) {
        preg_match('/(?:chapter|ch)-?([0-9]+(?:\.[0-9]+)?)/i', $cUrl, $m);
        $num = (float)($m[1] ?? 0);
        
        $exists = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_buddy_url' AND meta_value = %s", $cUrl));
        
        if (!$exists) {
            $num_check = $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID FROM $wpdb->posts p 
                 INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id 
                 WHERE p.post_parent = %d AND p.post_type = 'chapter' 
                 AND pm.meta_key = '_chapter_number' AND pm.meta_value = %s LIMIT 1",
                $manga_id, $num
            ));

            if (!$num_check) {
                $cTitle = "Chapter " . $num;
                $chap_id = wp_insert_post([
                    'post_title' => $cTitle, 'post_status' => $import_status, 'post_type' => 'chapter',
                    'post_parent' => $manga_id, 'post_name' => sanitize_title($slug . '-' . $num)
                ]);
                update_post_meta($chap_id, '_chapter_number', $num);
                update_post_meta($chap_id, '_buddy_url', $cUrl);
                $added++;
            }
        }
    }
    
    if ($added > 0) wp_update_post(['ID' => $manga_id]);

    $chap_debug = ($added > 0) ? "<span style='color:#e11d48; font-weight:bold;'>+$added New</span>" : "<span style='color:#9ca3af;'>Up to date</span>";
    return "> [BUDDY-18+] <a href='$url' target='_blank' style='color:#e11d48;'><b>$title</b></a> | Chapters: $chap_debug";
}

// =========================================================================
// 5. THE BOTTOM-UP SPLITTER
// =========================================================================
function buddy_extract_ordered_zones($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $junk_nodes = $xpath->query('//*[contains(@class, "c-sidebar")] | //*[contains(@class, "sidebar")] | //*[contains(@class, "widget")] | //*[contains(@class, "footer")] | //footer');
    if ($junk_nodes) {
        foreach ($junk_nodes as $node) {
            if ($node->parentNode) $node->parentNode->removeChild($node);
        }
    }
    $clean_html = $dom->saveHTML();

    $split_keywords = ['>Latest Update', 'class="page-content-listing', 'class="c-page-content', 'class="post-listing"'];
    $split_pos = false;

    foreach ($split_keywords as $kw) {
        $pos = stripos($clean_html, $kw);
        if ($pos !== false) {
            $split_pos = $pos;
            break;
        }
    }

    if ($split_pos !== false) {
        $top_html = substr($clean_html, 0, $split_pos); 
        $bottom_html = substr($clean_html, $split_pos); 
    } else {
        $top_html = '';
        $bottom_html = $clean_html; 
    }

    $latest_links = [];
    preg_match_all('/href=["\'](?:https:\/\/manhwabuddy\.com)?\/(manga|manhwa|manhua|comic)\/([a-zA-Z0-9\-]+)\/?["\']/i', $bottom_html, $b_matches);
    if (!empty($b_matches[2])) {
        foreach ($b_matches[2] as $idx => $slug) {
            if ($slug !== 'page' && strpos($slug, 'chapter-') === false && stripos($slug, '-raw') === false) {
                $latest_links[] = 'https://manhwabuddy.com/' . $b_matches[1][$idx] . '/' . $slug . '/';
            }
        }
    }

    $popular_links = [];
    preg_match_all('/href=["\'](?:https:\/\/manhwabuddy\.com)?\/(manga|manhwa|manhua|comic)\/([a-zA-Z0-9\-]+)\/?["\']/i', $top_html, $t_matches);
    if (!empty($t_matches[2])) {
        foreach ($t_matches[2] as $idx => $slug) {
            if ($slug !== 'page' && strpos($slug, 'chapter-') === false && stripos($slug, '-raw') === false) {
                $popular_links[] = 'https://manhwabuddy.com/' . $t_matches[1][$idx] . '/' . $slug . '/';
            }
        }
    }

    $combined = array_merge($latest_links, $popular_links);
    return array_values(array_unique($combined));
}

// =========================================================================
// 6. AUTOPILOT - LATEST UPDATES ENGINE
// =========================================================================
function buddy_run_autopilot($limit = 15) {
    $target_url = 'https://manhwabuddy.com/only-18-webtoons/'; 
    $html = buddy_fetch($target_url);
    $logs = [];

    if ($html) {
        $linksFound = buddy_extract_ordered_zones($html);
        $to_process = array_slice($linksFound, 0, $limit); 
        
        foreach ($to_process as $href) {
            $logs[] = buddy_process_single($href);
            usleep(250000); // 0.25s rest
        }
    } else {
        $logs[] = "❌ Autopilot Failed: Could not connect to ManhwaBuddy.com.";
    }
    
    return $logs;
}

add_action('wp_ajax_buddy_run_autopilot_manual', function() {
    if (!current_user_can('manage_options')) wp_die();
    $logs = buddy_run_autopilot(15);
    wp_send_json_success(['logs' => $logs]);
});

// =========================================================================
// 7. AUTOPILOT - DEEP BACKLOG CRAWLER
// =========================================================================
function buddy_run_deep_scraper($limit = 15) {
    $page = (int) get_option('buddy_deep_page', 2); 
    $target_url = 'https://manhwabuddy.com/only-18-webtoons/page/' . $page . '/';
    $html = buddy_fetch($target_url);
    $logs = [];

    if ($html) {
        $linksFound = buddy_extract_ordered_zones($html);
        
        if (!empty($linksFound)) {
            $to_process = array_slice($linksFound, 0, $limit); 
            foreach ($to_process as $href) {
                $logs[] = buddy_process_single($href);
                usleep(250000); 
            }
            // Only advance page if we processed a full external limit (15) to ensure we don't skip manga during WP-Cron chunks.
            if ($limit >= 15 || count($linksFound) <= $limit) {
                update_option('buddy_deep_page', $page + 1);
                $logs[] = "<span style='color:#eab308;'>✅ Page $page Backlog processed. Moving to Page " . ($page + 1) . ".</span>";
            } else {
                $logs[] = "<span style='color:#eab308;'>⏳ Processing WP-Cron Batch. Remaining on Page $page.</span>";
            }
        } else {
            $logs[] = "❌ End of backlog reached at page $page. Or site layout changed.";
        }
    } else {
        $logs[] = "❌ Failed to connect to page $page.";
    }
    
    return $logs;
}

add_action('wp_ajax_buddy_run_deep_manual', function() {
    if (!current_user_can('manage_options')) wp_die();
    $logs = buddy_run_deep_scraper(15);
    wp_send_json_success(['logs' => $logs]);
});

// =========================================================================
// 8. AJAX CATEGORY FETCHER (Manual UI)
// =========================================================================
add_action('wp_ajax_buddy_fetch_category', 'buddy_ajax_fetch_category');
function buddy_ajax_fetch_category() {
    if (!current_user_can('manage_options')) wp_die();
    $html = buddy_fetch(esc_url_raw($_POST['cat_url']));
    if (!$html) wp_send_json_success(['links' => [], 'error' => 'Connection Failed.']);
    
    $linksFound = buddy_extract_ordered_zones($html);
    wp_send_json_success(['links' => $linksFound]);
}

// =========================================================================
// 9. UI DASHBOARD
// =========================================================================
function buddy_scraper_page_html() {
    global $wpdb;
    if (!current_user_can('manage_options')) return;
    $all_logs = [];
    
    if (isset($_POST['save_buddy_settings'])) {
        update_option('buddy_post_status', sanitize_text_field($_POST['buddy_post_status']));
        update_option('buddy_cron_secret', sanitize_text_field($_POST['buddy_cron_secret']));
        update_option('buddy_enable_wpcron', sanitize_text_field($_POST['buddy_enable_wpcron']));
        if (isset($_POST['buddy_deep_page'])) {
            update_option('buddy_deep_page', intval($_POST['buddy_deep_page']));
        }
        $all_logs[] = "💾 Settings Saved Successfully!";
    }

    if (isset($_POST['nuke_buddy_data'])) {
        $buddy_posts = $wpdb->get_results("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_buddy_url'");
        $count = 0;
        foreach ($buddy_posts as $p) {
            wp_delete_post($p->post_id, true);
            $count++;
        }
        update_option('buddy_deep_page', 1);
        $all_logs[] = "💥 MASS PURGE COMPLETE: Permanently cleared $count Manga items and reset Backlog Crawler to Page 1.";
    }
    
    $opt_status = get_option('buddy_post_status', 'publish');
    $opt_secret = get_option('buddy_cron_secret', 'rifat_secure_cron');
    $opt_wpcron = get_option('buddy_enable_wpcron', 'yes');
    $current_deep_page = get_option('buddy_deep_page', 1);
    
    $cron_latest = site_url() . '/?buddy_cron=run&secret=' . $opt_secret;
    $cron_deep = site_url() . '/?buddy_cron=deep&secret=' . $opt_secret;
    ?>
    <div class="wrap" style="font-family: system-ui, -apple-system, sans-serif; max-width: 1200px;">
        <h1 style="font-weight:900; color:#e11d48; font-size: 28px; margin-bottom: 5px;">MANHWABUDDY ENGINE V5.3</h1>
        <p style="font-size: 14px; color: #666; font-weight: 600;">Automated Background WP-Cron Integration Active</p>
        
        <?php if (!empty($all_logs)) : ?>
            <div style="background:#22c55e; color:white; padding:15px; border-radius:8px; margin-top:20px; font-weight:bold;">
                <?php foreach ($all_logs as $l) echo $l . "<br>"; ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:30px;">
            
            <div style="background:#fff; padding:25px; border-radius:12px; border:1px solid #e5e7eb;">
                <h2 style="margin-top:0; border-bottom: 2px solid #22c55e; padding-bottom: 10px;">🤖 Latest Update Autopilot</h2>
                <p style="font-size: 13px; color: #555; margin-bottom: 15px;">Scans <code>/only-18-webtoons/</code>.</p>
                
                <div style="background:#f3f4f6; padding:12px; border-radius:8px; margin-bottom:15px;">
                    <strong style="display:block; margin-bottom:5px; font-size:12px;">EXTERNAL CRON URL (Run every 30 mins):</strong>
                    <code style="word-break: break-all; color:#eab308; background:#111; padding:8px; display:block; border-radius:4px; font-size:11px;"><?php echo esc_url($cron_latest); ?></code>
                </div>

                <form onsubmit="runManualAutopilot(event, 'buddy_run_autopilot_manual', 'auto-btn', 'auto-terminal')">
                    <button type="submit" id="auto-btn" class="button button-primary" style="background:#22c55e; border:none; height:40px; width:100%; font-weight:bold;">Run Latest Scanner Now (Batch: 15)</button>
                </form>
                <div id="auto-terminal" style="margin-top:15px; background:#111; color:#22c55e; padding:10px; border-radius:6px; font-family:monospace; font-size:11px; height:150px; overflow-y:auto; border:1px solid #333; display:none;"></div>
            </div>

            <div style="background:#fff; padding:25px; border-radius:12px; border:1px solid #e5e7eb;">
                <h2 style="margin-top:0; border-bottom: 2px solid #eab308; padding-bottom: 10px;">📚 Deep Backlog Crawler</h2>
                <p style="font-size: 13px; color: #555; margin-bottom: 15px;">Automatically imports old 18+ manga. Currently tracking: <strong>Page <?php echo $current_deep_page; ?></strong></p>
                
                <div style="background:#f3f4f6; padding:12px; border-radius:8px; margin-bottom:15px;">
                    <strong style="display:block; margin-bottom:5px; font-size:12px;">EXTERNAL CRON URL (Run every 1 hour):</strong>
                    <code style="word-break: break-all; color:#eab308; background:#111; padding:8px; display:block; border-radius:4px; font-size:11px;"><?php echo esc_url($cron_deep); ?></code>
                </div>

                <form onsubmit="runManualAutopilot(event, 'buddy_run_deep_manual', 'deep-btn', 'deep-terminal')">
                    <button type="submit" id="deep-btn" class="button button-primary" style="background:#eab308; border:none; height:40px; width:100%; font-weight:bold;">Run Backlog Crawler Now (Batch: 15)</button>
                </form>
                <div id="deep-terminal" style="margin-top:15px; background:#111; color:#eab308; padding:10px; border-radius:6px; font-family:monospace; font-size:11px; height:150px; overflow-y:auto; border:1px solid #333; display:none;"></div>
            </div>

            <div style="background:#09090b; padding:25px; border-radius:12px; border:1px solid #333; grid-column: span 1;">
                <h2 style="margin-top:0; color:white; border-bottom: 2px solid #e11d48; padding-bottom: 10px;">🚀 Manual Mass Import</h2>
                <form onsubmit="startBuddyScrape(event)" style="display:flex; flex-wrap: wrap; gap: 15px; margin-top: 15px;">
                    <div style="flex: 1 1 100%;"><label style="color:#ccc; font-weight:bold; display:block; margin-bottom:5px;">Category URL</label><input type="text" id="buddy_url" value="https://manhwabuddy.com/only-18-webtoons" style="width:100%; padding:10px; background:#121212; border:1px solid #444; color:white;" required></div>
                    <div style="flex: 1;"><label style="color:#ccc; font-weight:bold; display:block; margin-bottom:5px;">Start Page</label><input type="number" id="buddy_start" value="1" min="1" style="width:100%; padding:10px; background:#121212; border:1px solid #444; color:white;" required></div>
                    <div style="flex: 1;"><label style="color:#ccc; font-weight:bold; display:block; margin-bottom:5px;">End Page</label><input type="number" id="buddy_end" value="5" min="1" style="width:100%; padding:10px; background:#121212; border:1px solid #444; color:white;" required></div>
                    <div style="flex: 1 1 100%;"><button type="submit" id="buddy-btn" class="button button-primary" style="background:#e11d48; border:none; height:45px; width:100%; font-size:16px; font-weight:bold;">Start Manual Scrape</button></div>
                </form>
                <div id="buddy-terminal" style="margin-top:20px; background:#000; color:#e11d48; padding:15px; border-radius:8px; font-family:monospace; font-size:13px; height:200px; overflow-y:auto; border:1px solid #333;">> Ready...</div>
            </div>

            <div style="background:#fff; padding:25px; border-radius:12px; border:1px solid #e5e7eb; grid-column: span 1;">
                <h2 style="margin-top:0; border-bottom: 2px solid #e11d48; padding-bottom: 10px;">⚙️ Engine Settings & Actions</h2>
                <form method="POST" style="margin-bottom: 20px;">
                    <input type="hidden" name="save_buddy_settings" value="1">
                    
                    <label style="font-weight:bold; font-size:12px; display:block; margin-bottom:5px;">Internal WP-Cron (Run in Background on User Visits)</label>
                    <select name="buddy_enable_wpcron" style="padding:8px; border-radius:6px; width:100%; margin-bottom:15px;">
                        <option value="yes" <?php selected($opt_wpcron, 'yes'); ?>>Enabled (Recommended if no external cron)</option>
                        <option value="no" <?php selected($opt_wpcron, 'no'); ?>>Disabled (I will use cron-job.org)</option>
                    </select>

                    <label style="font-weight:bold; font-size:12px; display:block; margin-bottom:5px;">Import Status</label>
                    <select name="buddy_post_status" style="padding:8px; border-radius:6px; width:100%; margin-bottom:15px;">
                        <option value="publish" <?php selected($opt_status, 'publish'); ?>>Auto-Publish</option>
                        <option value="draft" <?php selected($opt_status, 'draft'); ?>>Draft</option>
                    </select>
                    
                    <label style="font-weight:bold; font-size:12px; display:block; margin-bottom:5px;">Backlog Tracking Page (Editable)</label>
                    <input type="number" name="buddy_deep_page" value="<?php echo esc_attr($current_deep_page); ?>" min="1" style="padding:8px; border-radius:6px; width:100%; margin-bottom:15px;">

                    <label style="font-weight:bold; font-size:12px; display:block; margin-bottom:5px;">Cron Secret Key</label>
                    <input type="text" name="buddy_cron_secret" value="<?php echo esc_attr($opt_secret); ?>" style="padding:8px; border-radius:6px; width:100%; margin-bottom:15px;">
                    
                    <button type="submit" class="button button-primary" style="background:#09090b; border-color:#09090b; width:100%;">Save Settings</button>
                </form>

                <hr style="margin:20px 0;">

                <form method="POST" onsubmit="return confirm('WARNING: This will permanently delete ALL manga imported by Buddy and reset the Deep Crawler back to Page 1. Are you sure you want to start fresh?');">
                    <input type="hidden" name="nuke_buddy_data" value="1">
                    <button type="submit" class="button" style="background:#ef4444; color:white; border:none; height:45px; width:100%; font-weight:bold; font-size:13px; box-shadow: 0 4px 6px rgba(239, 68, 68, 0.3);">🗑️ WIPE ALL BUDDY DATA & START NEW</button>
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
            } catch (err) {
                term.innerHTML += `<div style="color:#ef4444;">> ❌ Fatal Error executing query.</div>`;
            }
            btn.disabled = false; btn.innerText = 'Run Scanner Manually Now';
            term.scrollTop = term.scrollHeight;
        }

        async function startBuddyScrape(e) {
            e.preventDefault();
            const btn = document.getElementById('buddy-btn'); const term = document.getElementById('buddy-terminal');
            
            let rawUrl = document.getElementById('buddy_url').value.trim(); 
            if (!rawUrl.startsWith('http')) rawUrl = 'https://' + rawUrl;
            let baseUrl = rawUrl.replace(/\/page\/[0-9]+\/?$/, '').replace(/\/$/, '');
            
            const startPage = parseInt(document.getElementById('buddy_start').value); 
            const endPage = parseInt(document.getElementById('buddy_end').value);

            btn.disabled = true; btn.style.opacity = '0.5'; btn.innerText = 'Scraping... DO NOT CLOSE!';
            term.innerHTML = '<div style="color:#e11d48;">> 🚀 Launching V5.3 Force-Importer...</div>';

            for (let p = startPage; p <= endPage; p++) {
                let pageUrl = p === 1 ? baseUrl : `${baseUrl}/page/${p}/`;
                term.innerHTML += `<div style="color:#fff; margin-top:15px; border-top:1px dashed #444; padding-top:10px;">> [PAGE ${p}] Fetching Links: ${pageUrl}</div>`;
                term.scrollTop = term.scrollHeight;

                try {
                    let fd = new FormData(); fd.append('action', 'buddy_fetch_category'); fd.append('cat_url', pageUrl);
                    let res = await fetch(ajaxurl, { method: 'POST', body: fd }); let resData = await res.json();
                    
                    if(!resData.success || !resData.data.links || resData.data.links.length === 0) {
                        term.innerHTML += `<div style="color:#ef4444;">> [STOP] Empty page returned.</div>`; break;
                    }

                    term.innerHTML += `<div style="color:#e11d48;">> Extracted ${resData.data.links.length} strict paths. Handing off to Server...</div>`;
                    term.scrollTop = term.scrollHeight;

                    for(let i=0; i<resData.data.links.length; i++) {
                        let mangaUrl = resData.data.links[i];
                        
                        let mFd = new FormData(); mFd.append('action', 'buddy_process_single'); mFd.append('manga_url', mangaUrl);
                        let mRes = await fetch(ajaxurl, { method: 'POST', body: mFd }); let mData = await mRes.json();
                        
                        if(mData.success && mData.data.log) {
                            term.innerHTML += `<div>${mData.data.log}</div>`;
                        }

                        term.scrollTop = term.scrollHeight;
                        await new Promise(r => setTimeout(r, 1000));
                    }

                } catch(err) {
                    term.innerHTML += `<div style="color:#ef4444;">> [FATAL] Client connection lost on page ${p}.</div>`;
                }
            }
            btn.disabled = false; btn.style.opacity = '1'; btn.innerText = 'Start Manual Scrape';
            term.innerHTML += '<div style="color:#eab308; margin-top:20px;">> 🏁 V5.3 Scrape Cycle Completed!</div>'; term.scrollTop = term.scrollHeight;
        }
    </script>
    <?php
}
