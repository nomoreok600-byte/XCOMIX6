<?php
/**
 * MangaVerse + XCOMIX compatibility layer.
 *
 * Keeps MangaVerse as the presentation theme while preserving the XCOMIX
 * scraper engines, metadata names, history/bookmark AJAX actions, and image
 * proxy behavior used by imported MangaKatana/ManhwaBuddy/Mgeko chapters.
 */

if (!defined('ABSPATH')) exit;

define('MVX_VERSION', '2.1.0');

if (!function_exists('mvx_first_meta')) {
    function mvx_first_meta($post_id, array $keys, $default = '') {
        foreach ($keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if ($value !== '' && $value !== null && $value !== []) {
                return $value;
            }
        }
        return $default;
    }
}

if (!function_exists('mvx_manga_meta')) {
    function mvx_manga_meta($post_id, $field, $default = '') {
        $map = [
            'author'       => ['_mv_author', '_manga_author'],
            'artist'       => ['_mv_artist', '_manga_artist'],
            'alt_title'    => ['_mv_alt_title', '_manga_alt_title'],
            'release_year' => ['_mv_release_year', '_manga_release_year'],
            'score'        => ['_mv_score', '_manga_score'],
            'cover_url'    => ['_mv_cover_url', '_manga_cover_url'],
            'type'         => ['_manga_type'],
            'status'       => ['_manga_status'],
            'is_18_plus'   => ['_mv_is_18_plus', '_is_18_plus'],
        ];

        return mvx_first_meta($post_id, $map[$field] ?? ['_mv_' . $field, '_manga_' . $field], $default);
    }
}

if (!function_exists('mvx_chapter_number')) {
    function mvx_chapter_number($chapter_id, $default = '') {
        return mvx_first_meta($chapter_id, ['_mv_chapter_number', '_chapter_number'], $default);
    }
}

if (!function_exists('mvx_parent_manga_id')) {
    function mvx_parent_manga_id($chapter_id) {
        $parent_id = wp_get_post_parent_id($chapter_id);
        if ($parent_id) return (int) $parent_id;

        return (int) mvx_first_meta($chapter_id, ['_mv_parent_manga', '_parent_manga_id'], 0);
    }
}

if (!function_exists('mvx_get_chapters')) {
    function mvx_get_chapters($manga_id, $order = 'DESC') {
        $chapters = get_posts([
            'post_type' => 'chapter',
            'post_parent' => (int) $manga_id,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        usort($chapters, function($a, $b) use ($order) {
            $a_num = (float) mvx_chapter_number($a->ID, 0);
            $b_num = (float) mvx_chapter_number($b->ID, 0);
            $result = $a_num <=> $b_num;
            return strtoupper($order) === 'DESC' ? -$result : $result;
        });

        return $chapters;
    }
}

if (!function_exists('mvx_user_history')) {
    function mvx_user_history($user_id) {
        $mv_history = get_user_meta($user_id, '_mv_history', true);
        $x_history = get_user_meta($user_id, '_xcomix_history', true);

        $mv_history = is_array($mv_history) ? $mv_history : [];
        $x_history = is_array($x_history) ? $x_history : [];

        return $mv_history + $x_history;
    }
}

if (!function_exists('mvx_proxy_image_url')) {
    function mvx_proxy_image_url($url, $engine = 'generic') {
        if (empty($url)) return '';

        $local_host = parse_url(site_url(), PHP_URL_HOST);
        $image_host = parse_url($url, PHP_URL_HOST);
        if ($local_host && $image_host && strtolower($local_host) === strtolower($image_host)) {
            return esc_url($url);
        }

        return add_query_arg([
            'mv_proxy_image' => sanitize_key($engine),
            'img' => rawurlencode(base64_encode($url)),
        ], home_url('/'));
    }
}

if (!function_exists('mvx_public_remote_url')) {
    function mvx_public_remote_url($url) {
        $url = esc_url_raw($url);
        $parts = wp_parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host']) || !in_array($parts['scheme'], ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return $url;
    }
}

if (!function_exists('mvx_proxy_referer')) {
    function mvx_proxy_referer($engine) {
        switch ($engine) {
            case 'katana':
                return 'https://mangakatana.com/';
            case 'buddy':
                return 'https://manhwabuddy.com/';
            case 'mgeko':
                return 'https://www.mgeko.cc/';
            default:
                return home_url('/');
        }
    }
}

add_action('template_redirect', 'mvx_image_proxy_endpoint', 0);
function mvx_image_proxy_endpoint() {
    $legacy_proxy = isset($_GET['action'], $_GET['url']) && $_GET['action'] === 'proxy';
    $encoded_proxy = isset($_GET['mv_proxy_image'], $_GET['img']);

    if (!$legacy_proxy && !$encoded_proxy) return;

    $engine = $encoded_proxy ? sanitize_key(wp_unslash($_GET['mv_proxy_image'])) : 'katana';
    $url = '';

    if ($encoded_proxy) {
        $encoded = rawurldecode(sanitize_text_field(wp_unslash($_GET['img'])));
        $decoded = base64_decode($encoded, true);
        $url = is_string($decoded) ? $decoded : '';
    } else {
        $url = rawurldecode(wp_unslash($_GET['url']));
    }

    $url = mvx_public_remote_url($url);
    if (!$url) {
        status_header(400);
        exit;
    }

    while (ob_get_level()) {
        ob_end_clean();
    }

    $headers = [
        'User-Agent' => get_option('xcomix_user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0.0.0 Safari/537.36'),
        'Referer' => mvx_proxy_referer($engine),
        'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
    ];

    $cookie = get_option('xcomix_cf_cookie', '');
    if (!empty($cookie) && $engine === 'katana') {
        $headers['Cookie'] = $cookie;
    }

    $response = wp_remote_get($url, [
        'timeout' => 20,
        'redirection' => 5,
        'headers' => $headers,
        'sslverify' => false,
    ]);

    if (is_wp_error($response)) {
        status_header(502);
        exit;
    }

    $body = wp_remote_retrieve_body($response);
    $code = wp_remote_retrieve_response_code($response);
    $content_type = wp_remote_retrieve_header($response, 'content-type') ?: 'image/jpeg';

    if ($code < 200 || $code >= 300 || $body === '') {
        status_header(502);
        exit;
    }

    status_header(200);
    header('Content-Type: ' . $content_type);
    header('Cache-Control: public, max-age=604800');
    echo $body;
    exit;
}

add_action('wp_head', 'mvx_xcomix_ajax_globals', 1);
function mvx_xcomix_ajax_globals() {
    ?>
    <script>
        window.xcomixApp = window.xcomixApp || {
            ajaxUrl: "<?php echo esc_js(admin_url('admin-ajax.php')); ?>",
            nonce: "<?php echo esc_js(wp_create_nonce('xcomix_app_nonce')); ?>"
        };
    </script>
    <?php
}

add_action('wp_ajax_xcomix_toggle_bookmark', 'mvx_ajax_toggle_bookmark');
function mvx_ajax_toggle_bookmark() {
    check_ajax_referer('xcomix_app_nonce', 'nonce');
    $user_id = get_current_user_id();
    $manga_id = isset($_POST['manga_id']) ? intval($_POST['manga_id']) : 0;
    if (!$user_id || !$manga_id) wp_send_json_error('Invalid data');

    $bookmarks = get_user_meta($user_id, '_xcomix_bookmarks', true);
    $bookmarks = is_array($bookmarks) ? $bookmarks : [];

    if (in_array($manga_id, $bookmarks, true)) {
        $bookmarks = array_values(array_diff($bookmarks, [$manga_id]));
        $status = 'removed';
    } else {
        $bookmarks[] = $manga_id;
        $status = 'added';
    }

    update_user_meta($user_id, '_xcomix_bookmarks', $bookmarks);
    update_user_meta($user_id, '_mv_bookmarks', $bookmarks);
    wp_send_json_success(['status' => $status]);
}

add_action('wp_ajax_xcomix_log_history', 'mvx_ajax_log_history');
function mvx_ajax_log_history() {
    check_ajax_referer('xcomix_app_nonce', 'nonce');
    $user_id = get_current_user_id();
    $manga_id = isset($_POST['manga_id']) ? intval($_POST['manga_id']) : 0;
    $chapter_id = isset($_POST['chapter_id']) ? intval($_POST['chapter_id']) : 0;
    $chapter_num = isset($_POST['chapter_num']) ? sanitize_text_field(wp_unslash($_POST['chapter_num'])) : '';

    if (!$user_id || !$manga_id || !$chapter_id) wp_send_json_error('Invalid data');

    $entry = [
        'id' => $manga_id,
        'chapter_id' => $chapter_id,
        'chapter_num' => $chapter_num,
        'url' => get_permalink($chapter_id),
        'timestamp' => current_time('timestamp'),
    ];

    foreach (['_xcomix_history', '_mv_history'] as $meta_key) {
        $history = get_user_meta($user_id, $meta_key, true);
        $history = is_array($history) ? $history : [];
        $history[$manga_id] = $entry;
        uasort($history, function($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });
        update_user_meta($user_id, $meta_key, array_slice($history, 0, 100, true));
    }

    wp_send_json_success('History updated');
}

add_action('wp_ajax_xcomix_remove_history', 'mvx_ajax_remove_history');
function mvx_ajax_remove_history() {
    check_ajax_referer('xcomix_app_nonce', 'nonce');
    $user_id = get_current_user_id();
    $manga_id = isset($_POST['manga_id']) ? intval($_POST['manga_id']) : 0;
    if (!$user_id || !$manga_id) wp_send_json_error('Invalid data');

    foreach (['_xcomix_history', '_mv_history'] as $meta_key) {
        $history = get_user_meta($user_id, $meta_key, true);
        if (is_array($history) && isset($history[$manga_id])) {
            unset($history[$manga_id]);
            update_user_meta($user_id, $meta_key, $history);
        }
    }

    wp_send_json_success('History removed');
}

add_action('wp_ajax_xcomix_update_reading_plan', 'mvx_ajax_update_reading_plan');
function mvx_ajax_update_reading_plan() {
    check_ajax_referer('xcomix_app_nonce', 'nonce');
    $user_id = get_current_user_id();
    $manga_id = isset($_POST['manga_id']) ? intval($_POST['manga_id']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'reading';
    if (!$user_id || !$manga_id) wp_send_json_error('Invalid data');

    $plans = get_user_meta($user_id, '_xcomix_reading_plans', true);
    $plans = is_array($plans) ? $plans : [];
    $plans[$manga_id] = $status;

    update_user_meta($user_id, '_xcomix_reading_plans', $plans);
    update_user_meta($user_id, '_mv_reading_plans', $plans);
    wp_send_json_success('Reading plan updated');
}

add_action('wp_ajax_xcomix_save_scraped_images', 'mvx_save_scraped_images_callback');
add_action('wp_ajax_nopriv_xcomix_save_scraped_images', 'mvx_save_scraped_images_callback');
function mvx_save_scraped_images_callback() {
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $raw = isset($_POST['images']) ? wp_unslash($_POST['images']) : '';
    $images = json_decode($raw, true);

    if (!$post_id || !is_array($images) || empty($images)) {
        wp_send_json_error('Invalid image payload');
    }

    $clean_images = array_values(array_filter(array_map('esc_url_raw', $images)));
    if (empty($clean_images)) {
        wp_send_json_error('No valid images');
    }

    set_transient('xcomix_mgeko_imgs_' . $post_id, $clean_images, DAY_IN_SECONDS);
    wp_send_json_success('Saved');
}

add_action('add_meta_boxes', 'mvx_add_source_meta_box');
function mvx_add_source_meta_box() {
    add_meta_box('mvx_source_details', 'XCOMIX Source Engine', 'mvx_source_meta_box', 'chapter', 'side', 'default');
}

function mvx_source_meta_box($post) {
    wp_nonce_field('mvx_source_meta_box', 'mvx_source_nonce');
    $fields = [
        '_chapter_number' => 'XCOMIX Chapter Number',
        '_katana_url' => 'MangaKatana URL',
        '_buddy_url' => 'ManhwaBuddy URL',
        '_mgeko_url' => 'Mgeko URL',
    ];

    foreach ($fields as $key => $label) {
        $value = get_post_meta($post->ID, $key, true);
        $type = strpos($key, 'url') !== false ? 'url' : 'text';
        echo '<p><label><strong>' . esc_html($label) . '</strong></label>';
        echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" style="width:100%;margin-top:4px;" /></p>';
    }
}

add_action('save_post_chapter', 'mvx_save_source_meta_box', 20);
function mvx_save_source_meta_box($post_id) {
    if (!isset($_POST['mvx_source_nonce']) || !wp_verify_nonce($_POST['mvx_source_nonce'], 'mvx_source_meta_box')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    foreach (['_katana_url', '_buddy_url', '_mgeko_url'] as $key) {
        if (isset($_POST[$key])) {
            update_post_meta($post_id, $key, esc_url_raw(wp_unslash($_POST[$key])));
        }
    }

    if (isset($_POST['_chapter_number'])) {
        $chapter_number = sanitize_text_field(wp_unslash($_POST['_chapter_number']));
        update_post_meta($post_id, '_chapter_number', $chapter_number);
        update_post_meta($post_id, '_mv_chapter_number', $chapter_number);
    }
}

add_action('save_post_chapter', 'mvx_sync_chapter_compat_meta', 30);
function mvx_sync_chapter_compat_meta($post_id) {
    $chapter_number = mvx_chapter_number($post_id);
    if ($chapter_number !== '') {
        update_post_meta($post_id, '_chapter_number', $chapter_number);
        update_post_meta($post_id, '_mv_chapter_number', $chapter_number);
    }

    $parent = mvx_parent_manga_id($post_id);
    if ($parent) {
        update_post_meta($post_id, '_mv_parent_manga', $parent);
        update_post_meta($post_id, '_parent_manga_id', $parent);
    }
}
