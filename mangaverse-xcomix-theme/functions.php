<?php
/**
 * MangaVerse XCOMIX - Complete Manga Platform Theme
 * Version: 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// ============================================================================
// 1. THEME CONSTANTS
// ============================================================================
define('MV_VERSION', '2.1.0');
define('MV_DIR', get_template_directory());
define('MV_URI', get_template_directory_uri());

require_once MV_DIR . '/inc/xcomix-integration.php';
require_once MV_DIR . '/scraper.php';

// ============================================================================
// 2. THEME SETUP
// ============================================================================
add_action('after_setup_theme', 'mv_theme_setup');
function mv_theme_setup() {
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);
    add_theme_support('custom-logo');
    add_theme_support('automatic-feed-links');
    add_theme_support('responsive-embeds');
    
    // Disable Gutenberg for manga CPTs
    add_filter('use_block_editor_for_post_type', function($use, $type) {
        if (in_array($type, ['manga', 'chapter'])) return false;
        return $use;
    }, 10, 2);
    
    // Image sizes
    add_image_size('mv_cover_small', 150, 225, true);
    add_image_size('mv_cover_medium', 300, 450, true);
    add_image_size('mv_cover_large', 600, 900, true);
    add_image_size('mv_banner', 1200, 400, true);
}

// ============================================================================
// 3. ENQUEUE ASSETS
// ============================================================================
add_action('wp_enqueue_scripts', 'mv_enqueue_assets');
function mv_enqueue_assets() {
    // Google Fonts
    wp_enqueue_style('mv-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap', [], null);
    
    // Tailwind CSS via CDN
    wp_enqueue_script('mv-tailwind', 'https://cdn.tailwindcss.com', [], null);
    
    // Swiper for sliders
    wp_enqueue_style('mv-swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], '11');
    wp_enqueue_script('mv-swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], '11', true);
    
    // Main theme script
    wp_enqueue_script('mv-main', MV_URI . '/assets/js/main.js', ['jquery'], MV_VERSION, true);
    
    // Localize script for AJAX
    wp_localize_script('mv-main', 'mvData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'restUrl' => rest_url('mv/v1/'),
        'nonce'   => wp_create_nonce('mv_nonce'),
        'isLogged' => is_user_logged_in(),
        'userId'  => get_current_user_id(),
        'siteUrl' => site_url(),
        'themeUrl' => MV_URI,
    ]);
}

// ============================================================================
// 4. CUSTOM POST TYPES
// ============================================================================
add_action('init', 'mv_register_cpts');
function mv_register_cpts() {
    // Manga CPT
    register_post_type('manga', [
        'labels' => [
            'name' => 'Manga', 'singular_name' => 'Manga', 'add_new' => 'Add New',
            'add_new_item' => 'Add New Manga', 'edit_item' => 'Edit Manga',
            'new_item' => 'New Manga', 'view_item' => 'View Manga',
            'search_items' => 'Search Manga', 'not_found' => 'No manga found',
        ],
        'public' => true, 'has_archive' => true, 'menu_icon' => 'dashicons-book',
        'supports' => ['title', 'editor', 'thumbnail', 'comments'],
        'rewrite' => ['slug' => 'manga', 'with_front' => false],
        'show_in_rest' => true,
    ]);
    
    // Chapter CPT
    register_post_type('chapter', [
        'labels' => [
            'name' => 'Chapters', 'singular_name' => 'Chapter', 'add_new' => 'Add New',
            'add_new_item' => 'Add New Chapter', 'edit_item' => 'Edit Chapter',
        ],
        'public' => true, 'has_archive' => false, 'menu_icon' => 'dashicons-media-document',
        'supports' => ['title', 'editor', 'custom-fields', 'comments'],
        'rewrite' => ['slug' => 'read', 'with_front' => false],
        'show_in_rest' => true,
    ]);
}

// ============================================================================
// 5. TAXONOMIES
// ============================================================================
add_action('init', 'mv_register_taxonomies');
function mv_register_taxonomies() {
    // Genre
    register_taxonomy('genre', ['manga'], [
        'labels' => [
            'name' => 'Genres', 'singular_name' => 'Genre', 'search_items' => 'Search Genres',
            'all_items' => 'All Genres', 'edit_item' => 'Edit Genre',
            'add_new_item' => 'Add New Genre', 'menu_name' => 'Genres',
        ],
        'hierarchical' => false,
        'public' => true, 'show_ui' => true, 'show_admin_column' => true,
        'query_var' => true, 'show_in_rest' => true,
        'rewrite' => ['slug' => 'genre'],
    ]);
    
    // Type (Manga/Manhwa/Manhua)
    register_taxonomy('manga_type', ['manga'], [
        'labels' => [
            'name' => 'Types', 'singular_name' => 'Type', 'menu_name' => 'Types',
        ],
        'hierarchical' => false, 'public' => true, 'show_ui' => true,
        'query_var' => true, 'show_in_rest' => true,
        'rewrite' => ['slug' => 'type'],
    ]);
    
    // Status
    register_taxonomy('manga_status', ['manga'], [
        'labels' => [
            'name' => 'Statuses', 'singular_name' => 'Status', 'menu_name' => 'Status',
        ],
        'hierarchical' => false, 'public' => true, 'show_ui' => true,
        'query_var' => true, 'show_in_rest' => true,
        'rewrite' => ['slug' => 'status'],
    ]);
}

// ============================================================================
// 6. MANGA META BOXES
// ============================================================================
add_action('add_meta_boxes', 'mv_add_meta_boxes');
function mv_add_meta_boxes() {
    add_meta_box('mv_manga_details', 'Manga Details', 'mv_manga_meta_box', 'manga', 'normal', 'high');
    add_meta_box('mv_chapter_details', 'Chapter Details', 'mv_chapter_meta_box', 'chapter', 'normal', 'high');
}

function mv_manga_meta_box($post) {
    wp_nonce_field('mv_meta_box', 'mv_meta_nonce');
    $fields = [
        '_mv_author' => 'Author',
        '_mv_artist' => 'Artist',
        '_mv_alt_title' => 'Alternative Title',
        '_mv_release_year' => 'Release Year',
        '_mv_score' => 'Score (0-10)',
        '_mv_cover_url' => 'Cover Image URL',
        '_mv_is_18_plus' => '18+ Content',
    ];
    foreach ($fields as $key => $label) {
        $value = get_post_meta($post->ID, $key, true);
        $type = ($key === '_mv_is_18_plus') ? 'checkbox' : 'text';
        echo '<p><label><strong>' . esc_html($label) . ':</strong></label> ';
        if ($type === 'checkbox') {
            echo '<input type="checkbox" name="' . esc_attr($key) . '" value="1" ' . checked($value, '1', false) . ' />';
        } else {
            echo '<input type="text" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" style="width:100%;padding:6px;margin-top:4px;background:#1a1a22;border:1px solid #333;color:#fff;border-radius:4px;" />';
        }
        echo '</p>';
    }
}

function mv_chapter_meta_box($post) {
    wp_nonce_field('mv_meta_box', 'mv_meta_nonce');
    $manga_id = get_post_meta($post->ID, '_mv_parent_manga', true);
    $chap_num = get_post_meta($post->ID, '_mv_chapter_number', true);
    $source_url = get_post_meta($post->ID, '_mv_source_url', true);
    
    // Parent manga dropdown
    $mangas = get_posts(['post_type' => 'manga', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
    echo '<p><label><strong>Parent Manga:</strong></label><br>';
    echo '<select name="_mv_parent_manga" style="width:100%;padding:6px;background:#1a1a22;border:1px solid #333;color:#fff;border-radius:4px;">';
    echo '<option value="">-- Select Manga --</option>';
    foreach ($mangas as $m) {
        $selected = ($manga_id == $m->ID) ? 'selected' : '';
        echo '<option value="' . $m->ID . '" ' . $selected . '>' . esc_html($m->post_title) . '</option>';
    }
    echo '</select></p>';
    
    echo '<p><label><strong>Chapter Number:</strong></label><br>';
    echo '<input type="number" step="0.1" name="_mv_chapter_number" value="' . esc_attr($chap_num) . '" style="width:100%;padding:6px;background:#1a1a22;border:1px solid #333;color:#fff;border-radius:4px;" /></p>';
    
    echo '<p><label><strong>Source URL:</strong></label><br>';
    echo '<input type="url" name="_mv_source_url" value="' . esc_attr($source_url) . '" style="width:100%;padding:6px;background:#1a1a22;border:1px solid #333;color:#fff;border-radius:4px;" /></p>';
}

add_action('save_post', 'mv_save_meta_boxes');
function mv_save_meta_boxes($post_id) {
    if (!isset($_POST['mv_meta_nonce']) || !wp_verify_nonce($_POST['mv_meta_nonce'], 'mv_meta_box')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    
    $fields = ['_mv_author', '_mv_artist', '_mv_alt_title', '_mv_release_year', '_mv_score', '_mv_cover_url', '_mv_parent_manga', '_mv_chapter_number', '_mv_source_url'];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }
    
    // Checkbox
    update_post_meta($post_id, '_mv_is_18_plus', isset($_POST['_mv_is_18_plus']) ? '1' : '0');
    
    // Auto-set chapter parent
    if (get_post_type($post_id) === 'chapter') {
        $parent_manga = get_post_meta($post_id, '_mv_parent_manga', true);
        if ($parent_manga) {
            wp_update_post(['ID' => $post_id, 'post_parent' => intval($parent_manga)]);
        }
    }
}

// ============================================================================
// 7. COVER IMAGE HELPER
// ============================================================================
if (!function_exists('mv_get_cover')) {
    function mv_get_cover($post_id, $size = 'medium') {
        $cover_url = function_exists('mvx_manga_meta')
            ? mvx_manga_meta($post_id, 'cover_url')
            : get_post_meta($post_id, '_mv_cover_url', true);
        if (!empty($cover_url)) {
            return function_exists('mvx_proxy_image_url') ? mvx_proxy_image_url($cover_url, 'katana') : esc_url($cover_url);
        }
        
        $thumb = get_the_post_thumbnail_url($post_id, $size);
        if (!empty($thumb)) return esc_url($thumb);
        
        return MV_URI . '/assets/img/placeholder-cover.jpg';
    }
}

// ============================================================================
// 8. AJAX HANDLERS - Bookmarks, History, Chat, Messaging
// ============================================================================
// --- Bookmarks ---
add_action('wp_ajax_mv_toggle_bookmark', 'mv_ajax_toggle_bookmark');
function mv_ajax_toggle_bookmark() {
    check_ajax_referer('mv_nonce', 'nonce');
    $user_id = get_current_user_id();
    $manga_id = intval($_POST['manga_id']);
    if (!$user_id || !$manga_id) wp_send_json_error('Invalid data');
    
    $bookmarks = get_user_meta($user_id, '_mv_bookmarks', true) ?: [];
    if (in_array($manga_id, $bookmarks)) {
        $bookmarks = array_diff($bookmarks, [$manga_id]);
        $status = 'removed';
    } else {
        $bookmarks[] = $manga_id;
        $status = 'added';
    }
    $bookmarks = array_values($bookmarks);
    update_user_meta($user_id, '_mv_bookmarks', $bookmarks);
    update_user_meta($user_id, '_xcomix_bookmarks', $bookmarks);
    wp_send_json_success(['status' => $status]);
}

// --- Reading History ---
add_action('wp_ajax_mv_log_history', 'mv_ajax_log_history');
function mv_ajax_log_history() {
    check_ajax_referer('mv_nonce', 'nonce');
    $user_id = get_current_user_id();
    $manga_id = intval($_POST['manga_id']);
    $chapter_id = intval($_POST['chapter_id']);
    $chapter_num = sanitize_text_field($_POST['chapter_num']);
    if (!$user_id || !$manga_id) wp_send_json_error('Invalid data');
    
    $history = get_user_meta($user_id, '_mv_history', true) ?: [];
    $entry = [
        'id' => $manga_id,
        'chapter_id' => $chapter_id,
        'chapter_num' => $chapter_num,
        'url' => get_permalink($chapter_id),
        'timestamp' => current_time('timestamp'),
    ];
    $history[$manga_id] = $entry;
    uasort($history, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
    if (count($history) > 100) $history = array_slice($history, 0, 100, true);
    update_user_meta($user_id, '_mv_history', $history);
    $x_history = get_user_meta($user_id, '_xcomix_history', true) ?: [];
    $x_history[$manga_id] = $entry;
    update_user_meta($user_id, '_xcomix_history', $x_history);
    wp_send_json_success('History logged');
}

add_action('wp_ajax_mv_remove_history', 'mv_ajax_remove_history');
function mv_ajax_remove_history() {
    check_ajax_referer('mv_nonce', 'nonce');
    $user_id = get_current_user_id();
    $manga_id = intval($_POST['manga_id']);
    $history = get_user_meta($user_id, '_mv_history', true) ?: [];
    unset($history[$manga_id]);
    update_user_meta($user_id, '_mv_history', $history);
    $x_history = get_user_meta($user_id, '_xcomix_history', true) ?: [];
    unset($x_history[$manga_id]);
    update_user_meta($user_id, '_xcomix_history', $x_history);
    wp_send_json_success('Removed');
}

// --- Reading Plan/Status ---
add_action('wp_ajax_mv_update_reading_plan', 'mv_ajax_update_reading_plan');
function mv_ajax_update_reading_plan() {
    check_ajax_referer('mv_nonce', 'nonce');
    $user_id = get_current_user_id();
    $manga_id = intval($_POST['manga_id']);
    $status = sanitize_text_field($_POST['status']);
    if (!$user_id || !$manga_id) wp_send_json_error('Invalid');
    
    $plans = get_user_meta($user_id, '_mv_reading_plans', true) ?: [];
    $plans[$manga_id] = $status;
    update_user_meta($user_id, '_mv_reading_plans', $plans);
    update_user_meta($user_id, '_xcomix_reading_plans', $plans);
    wp_send_json_success('Updated');
}

// --- Chat ---
add_action('wp_ajax_mv_send_chat', 'mv_ajax_send_chat');
add_action('wp_ajax_nopriv_mv_send_chat', fn() => wp_send_json_error('Login required'));
function mv_ajax_send_chat() {
    check_ajax_referer('mv_nonce', 'nonce');
    $user = wp_get_current_user();
    $post_id = intval($_POST['post_id']);
    $content = sanitize_textarea_field($_POST['content']);
    $parent = intval($_POST['parent'] ?? 0);
    if (!$post_id || empty($content)) wp_send_json_error('Invalid');
    
    $comment_id = wp_insert_comment([
        'comment_post_ID' => $post_id,
        'comment_author' => $user->display_name,
        'comment_author_email' => $user->user_email,
        'comment_content' => $content,
        'comment_parent' => $parent,
        'user_id' => $user->ID,
        'comment_approved' => 1,
    ]);
    
    // Award XP for chatting
    mv_add_user_xp($user->ID, 2);
    wp_send_json_success(['id' => $comment_id]);
}

add_action('wp_ajax_mv_get_chats', 'mv_ajax_get_chats');
function mv_ajax_get_chats() {
    $post_id = intval($_POST['post_id']);
    if (!$post_id) wp_send_json_error('Invalid');
    
    $comments = get_comments(['post_id' => $post_id, 'status' => 'approve', 'number' => 50, 'orderby' => 'comment_date', 'order' => 'DESC']);
    $html = '';
    foreach ($comments as $c) $html .= mv_render_chat_message($c);
    wp_send_json_success(['html' => $html, 'count' => count($comments)]);
}

add_action('wp_ajax_mv_delete_chat', 'mv_ajax_delete_chat');
function mv_ajax_delete_chat() {
    check_ajax_referer('mv_nonce', 'nonce');
    $cid = intval($_POST['comment_id']);
    $comment = get_comment($cid);
    if ($comment && ($comment->user_id == get_current_user_id() || current_user_can('manage_options'))) {
        wp_delete_comment($cid, true);
        wp_send_json_success('Deleted');
    }
    wp_send_json_error('Permission denied');
}

add_action('wp_ajax_mv_like_chat', 'mv_ajax_like_chat');
function mv_ajax_like_chat() {
    $cid = intval($_POST['comment_id']);
    $likes = intval(get_comment_meta($cid, '_mv_likes', true)) + 1;
    update_comment_meta($cid, '_mv_likes', $likes);
    wp_send_json_success(['likes' => $likes]);
}

// --- Private Messages ---
add_action('wp_ajax_mv_send_message', 'mv_ajax_send_message');
function mv_ajax_send_message() {
    check_ajax_referer('mv_nonce', 'nonce');
    $sender_id = get_current_user_id();
    $recipient_id = intval($_POST['recipient_id']);
    $content = sanitize_textarea_field($_POST['content']);
    if (!$sender_id || !$recipient_id || empty($content)) wp_send_json_error('Invalid');
    
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'mv_messages', [
        'sender_id' => $sender_id,
        'recipient_id' => $recipient_id,
        'content' => $content,
        'created_at' => current_time('mysql'),
        'is_read' => 0,
    ]);
    wp_send_json_success('Sent');
}

add_action('wp_ajax_mv_get_messages', 'mv_ajax_get_messages');
function mv_ajax_get_messages() {
    check_ajax_referer('mv_nonce', 'nonce');
    $user_id = get_current_user_id();
    $other_id = intval($_POST['user_id']);
    
    global $wpdb;
    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}mv_messages 
        WHERE (sender_id = %d AND recipient_id = %d) OR (sender_id = %d AND recipient_id = %d)
        ORDER BY created_at DESC LIMIT 50",
        $user_id, $other_id, $other_id, $user_id
    ));
    
    // Mark as read
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}mv_messages SET is_read = 1 WHERE recipient_id = %d AND sender_id = %d",
        $user_id, $other_id
    ));
    
    wp_send_json_success(array_reverse($messages));
}

add_action('wp_ajax_mv_get_conversations', 'mv_ajax_get_conversations');
function mv_ajax_get_conversations() {
    check_ajax_referer('mv_nonce', 'nonce');
    $user_id = get_current_user_id();
    
    global $wpdb;
    $conversations = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            CASE WHEN sender_id = %d THEN recipient_id ELSE sender_id END as other_id,
            MAX(created_at) as last_message_time,
            (SELECT content FROM {$wpdb->prefix}mv_messages m2 
             WHERE ((m2.sender_id = m1.sender_id AND m2.recipient_id = m1.recipient_id) 
             OR (m2.sender_id = m1.recipient_id AND m2.recipient_id = m1.sender_id))
             ORDER BY m2.created_at DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM {$wpdb->prefix}mv_messages m3 
             WHERE m3.recipient_id = %d AND m3.sender_id = 
             CASE WHEN m1.sender_id = %d THEN m1.recipient_id ELSE m1.sender_id END 
             AND m3.is_read = 0) as unread_count
        FROM {$wpdb->prefix}mv_messages m1
        WHERE sender_id = %d OR recipient_id = %d
        GROUP BY other_id
        ORDER BY last_message_time DESC",
        $user_id, $user_id, $user_id, $user_id, $user_id
    ));
    wp_send_json_success($conversations);
}

add_action('wp_ajax_mv_get_unread_count', 'mv_ajax_get_unread_count');
function mv_ajax_get_unread_count() {
    $user_id = get_current_user_id();
    global $wpdb;
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}mv_messages WHERE recipient_id = %d AND is_read = 0",
        $user_id
    ));
    wp_send_json_success(['count' => intval($count)]);
}

// --- Privacy Settings ---
add_action('wp_ajax_mv_save_privacy', 'mv_ajax_save_privacy');
function mv_ajax_save_privacy() {
    check_ajax_referer('mv_nonce', 'nonce');
    $user_id = get_current_user_id();
    $privacy = sanitize_text_field($_POST['privacy']);
    if ($user_id && in_array($privacy, ['public', 'private'])) {
        update_user_meta($user_id, '_mv_profile_privacy', $privacy);
        wp_send_json_success('Saved');
    }
    wp_send_json_error('Invalid');
}

// --- Get conversation user data ---
add_action('wp_ajax_mv_get_conversations', 'mv_ajax_get_conversations_with_data');
function mv_ajax_get_conversations_with_data() {
    check_ajax_referer('mv_nonce', 'nonce');
    $user_id = get_current_user_id();
    global $wpdb;
    $conversations = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            CASE WHEN sender_id = %d THEN recipient_id ELSE sender_id END as other_id,
            MAX(created_at) as last_message_time,
            (SELECT content FROM {$wpdb->prefix}mv_messages m2 
             WHERE ((m2.sender_id = m1.sender_id AND m2.recipient_id = m1.recipient_id) 
             OR (m2.sender_id = m1.recipient_id AND m2.recipient_id = m1.sender_id))
             ORDER BY m2.created_at DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM {$wpdb->prefix}mv_messages m3 
             WHERE m3.recipient_id = %d AND m3.sender_id = 
             CASE WHEN m1.sender_id = %d THEN m1.recipient_id ELSE m1.sender_id END 
             AND m3.is_read = 0) as unread_count
        FROM {$wpdb->prefix}mv_messages m1
        WHERE sender_id = %d OR recipient_id = %d
        GROUP BY other_id
        ORDER BY last_message_time DESC",
        $user_id, $user_id, $user_id, $user_id, $user_id
    ));
    
    $result = [];
    foreach ($conversations as $conv) {
        $user = get_userdata($conv->other_id);
        $result[] = [
            'other_id' => $conv->other_id,
            'last_message' => $conv->last_message,
            'unread_count' => $conv->unread_count,
            'avatar' => get_avatar_url($conv->other_id),
            'other_data' => $user ? [
                'display_name' => $user->display_name,
                'user_login' => $user->user_login,
            ] : null,
        ];
    }
    wp_send_json_success($result);
}

// ============================================================================
// 9. MESSAGE TABLE CREATION
// ============================================================================
add_action('after_switch_theme', 'mv_create_tables');
function mv_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mv_messages (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        sender_id bigint(20) NOT NULL,
        recipient_id bigint(20) NOT NULL,
        content text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        is_read tinyint(1) DEFAULT 0,
        PRIMARY KEY (id),
        KEY sender_id (sender_id),
        KEY recipient_id (recipient_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Store db version
    update_option('mv_db_version', '2.0.0');
}

// ============================================================================
// 10. CHAT MESSAGE RENDERER
// ============================================================================
if (!function_exists('mv_render_chat_message')) {
    function mv_render_chat_message($chat) {
        $user = get_userdata($chat->user_id);
        $is_mine = (is_user_logged_in() && $chat->user_id == get_current_user_id());
        $role = $user ? $user->roles[0] : '';
        $badge = '';
        if ($role === 'administrator') $badge = '<span class="text-[9px] bg-[#e8783a]/20 text-[#e8783a] px-1.5 py-0.5 rounded ml-1">Admin</span>';
        elseif ($role === 'author' || $role === 'editor') $badge = '<span class="text-[9px] bg-blue-500/20 text-blue-400 px-1.5 py-0.5 rounded ml-1">Mod</span>';
        
        $avatar = get_avatar_url($chat->user_id) ?: MV_URI . '/assets/img/default-avatar.png';
        $likes = intval(get_comment_meta($chat->comment_ID, '_mv_likes', true));
        $time = human_time_diff(strtotime($chat->comment_date), current_time('timestamp')) . ' ago';
        
        $content = esc_html($chat->comment_content);
        $content = make_clickable($content);
        $content = preg_replace('/\*\*(.*?)\*\*/is', '<strong class="text-white">$1</strong>', $content);
        $content = preg_replace('/\|\|(.*?)⁠\|\|/is', '<span class="spoiler-block" onclick="this.style.color=\'#fff\';this.style.background=\'transparent\'">$1</span>', $content);
        
        ob_start();
        ?>
        <div class="flex gap-3 group hover:bg-[#16161e] -mx-3 px-3 py-2.5 rounded-lg transition" id="chat-<?php echo $chat->comment_ID; ?>">
            <img src="<?php echo esc_url($avatar); ?>" class="w-8 h-8 rounded-full border border-white/5 shrink-0 object-cover mt-0.5">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-0.5">
                    <span class="text-[13px] font-semibold text-white"><?php echo esc_html($chat->comment_author); ?></span>
                    <?php echo $badge; ?>
                    <span class="text-[10px] text-gray-500"><?php echo $time; ?></span>
                </div>
                <div class="text-[13px] text-gray-300 leading-relaxed break-words"><?php echo $content; ?></div>
                <div class="mt-1.5 flex items-center gap-2">
                    <button onclick="likeChat(<?php echo $chat->comment_ID; ?>)" class="flex items-center gap-1 text-gray-500 hover:text-pink-400 text-[11px] transition">
                        <svg class="w-3.5 h-3.5" fill="<?php echo $likes ? '#ec4899' : 'none'; ?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        <?php echo $likes ?: ''; ?>
                    </button>
                    <?php if ($is_mine || current_user_can('manage_options')): ?>
                    <button onclick="deleteChat(<?php echo $chat->comment_ID; ?>)" class="text-gray-600 hover:text-red-400 text-[11px] transition opacity-0 group-hover:opacity-100">Delete</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// ============================================================================
// 11. USER XP / LEVELING SYSTEM
// ============================================================================
function mv_add_user_xp($user_id, $amount) {
    $xp = intval(get_user_meta($user_id, '_mv_xp', true));
    $xp += $amount;
    update_user_meta($user_id, '_mv_xp', $xp);
    
    // Check level up
    $new_level = floor(sqrt($xp / 10)) + 1;
    $old_level = intval(get_user_meta($user_id, '_mv_level', true)) ?: 1;
    if ($new_level > $old_level) {
        update_user_meta($user_id, '_mv_level', $new_level);
    }
}

function mv_get_user_level_data($user_id) {
    $xp = intval(get_user_meta($user_id, '_mv_xp', true));
    $level = intval(get_user_meta($user_id, '_mv_level', true)) ?: max(1, floor(sqrt($xp / 10)) + 1);
    $xp_for_level = 10 * pow($level, 2);
    $xp_for_prev = 10 * pow($level - 1, 2);
    $progress = min(100, max(0, (($xp - $xp_for_prev) / max(1, $xp_for_level - $xp_for_prev)) * 100));
    
    $ranks = [
        1 => ['Novice Reader', 'from-gray-400 to-gray-600'],
        10 => ['Adept Scroller', 'from-blue-400 to-blue-600'],
        25 => ['Elite Weeb', 'from-[#e8783a] to-orange-500'],
        50 => ['Manga Lord', 'from-purple-500 to-pink-500'],
        80 => ['God Tier', 'from-yellow-400 to-yellow-600'],
    ];
    $rank_name = 'Novice Reader';
    $rank_color = 'from-gray-400 to-gray-600';
    foreach ($ranks as $lvl => $data) {
        if ($level >= $lvl) { $rank_name = $data[0]; $rank_color = $data[1]; }
    }
    
    return compact('xp', 'level', 'progress', 'rank_name', 'rank_color', 'xp_for_level');
}

function mv_get_leaderboard($limit = 20, $period = 'all') {
    global $wpdb;
    $where = '';
    if ($period === 'week') $where = "AND um.meta_key = '_mv_xp' AND u.user_registered > DATE_SUB(NOW(), INTERVAL 7 DAY)";
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID, u.display_name, u.user_login,
            MAX(CASE WHEN um.meta_key = '_mv_xp' THEN um.meta_value END) as xp,
            MAX(CASE WHEN um.meta_key = '_mv_level' THEN um.meta_value END) as level
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
        WHERE um.meta_key IN ('_mv_xp', '_mv_level')
        GROUP BY u.ID
        HAVING xp > 0
        ORDER BY CAST(xp AS UNSIGNED) DESC
        LIMIT %d",
        $limit
    ));
}

// ============================================================================
// 12. USER PROFILE PRIVACY
// ============================================================================
function mv_is_profile_public($user_id) {
    $privacy = get_user_meta($user_id, '_mv_profile_privacy', true);
    return ($privacy !== 'private');
}

function mv_get_user_stats($user_id) {
    $history = get_user_meta($user_id, '_mv_history', true) ?: [];
    $bookmarks = get_user_meta($user_id, '_mv_bookmarks', true) ?: [];
    $comment_count = get_comments(['user_id' => $user_id, 'count' => true]);
    $days = max(1, floor((time() - strtotime(get_userdata($user_id)->user_registered)) / 86400));
    
    return [
        'chapters_read' => count($history),
        'bookmarks' => count($bookmarks),
        'comments' => intval($comment_count),
        'days_active' => $days,
    ];
}

// ============================================================================
// 13. PERFORMANCE OPTIMIZATIONS
// ============================================================================
// Disable emojis
add_action('init', function() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
});

// Disable embeds
add_action('init', function() {
    wp_deregister_script('wp-embed');
}, 100);

// Remove query strings from static resources
add_filter('script_loader_src', 'mv_remove_query_strings', 15);
add_filter('style_loader_src', 'mv_remove_query_strings', 15);
function mv_remove_query_strings($src) {
    if (strpos($src, '?ver=')) $src = remove_query_arg('ver', $src);
    return $src;
}

// Lazy loading for images
add_filter('wp_get_attachment_image_attributes', function($attrs) {
    $attrs['loading'] = 'lazy';
    return $attrs;
});

// ============================================================================
// 14. SEO - XML SITEMAP
// ============================================================================
add_action('init', 'mv_sitemap_rewrite');
function mv_sitemap_rewrite() {
    add_rewrite_rule('sitemap\.xml$', 'index.php?mv_sitemap=1', 'top');
}
add_filter('query_vars', function($vars) { $vars[] = 'mv_sitemap'; return $vars; });

add_action('template_redirect', 'mv_output_sitemap');
function mv_output_sitemap() {
    if (!get_query_var('mv_sitemap')) return;
    
    header('Content-Type: text/xml; charset=UTF-8');
    header('X-Robots-Tag: noindex, follow');
    
    $cache_key = 'mv_sitemap_xml';
    $cached = get_transient($cache_key);
    if ($cached) { echo $cached; exit; }
    
    ob_start();
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    ?>
    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
        <url><loc><?php echo esc_url(site_url()); ?></loc><priority>1.0</priority><changefreq>daily</changefreq></url>
        <url><loc><?php echo esc_url(site_url('/browse')); ?></loc><priority>0.9</priority><changefreq>daily</changefreq></url>
        <url><loc><?php echo esc_url(site_url('/recent')); ?></loc><priority>0.9</priority><changefreq>daily</changefreq></url>
        <?php
        $mangas = get_posts(['post_type' => 'manga', 'posts_per_page' => -1, 'post_status' => 'publish']);
        foreach ($mangas as $m) {
            $cover = mv_get_cover($m->ID);
            echo '<url>';
            echo '<loc>' . esc_url(get_permalink($m->ID)) . '</loc>';
            echo '<lastmod>' . get_the_modified_date('c', $m->ID) . '</lastmod>';
            echo '<priority>0.8</priority><changefreq>daily</changefreq>';
            if ($cover) echo '<image:image><image:loc>' . esc_url($cover) . '</image:loc><image:title>' . esc_html($m->post_title) . '</image:title></image:image>';
            echo '</url>';
        }
        $chapters = get_posts(['post_type' => 'chapter', 'posts_per_page' => 1000, 'post_status' => 'publish']);
        foreach ($chapters as $c) {
            echo '<url>';
            echo '<loc>' . esc_url(get_permalink($c->ID)) . '</loc>';
            echo '<lastmod>' . get_the_modified_date('c', $c->ID) . '</lastmod>';
            echo '<priority>0.6</priority><changefreq>weekly</changefreq>';
            echo '</url>';
        }
        ?>
    </urlset>
    <?php
    $xml = ob_get_clean();
    set_transient($cache_key, $xml, HOUR_IN_SECONDS);
    echo $xml;
    exit;
}

// Auto-clear sitemap cache on post save
add_action('save_post', function($post_id) {
    if (get_post_type($post_id) === 'manga' || get_post_type($post_id) === 'chapter') {
        delete_transient('mv_sitemap_xml');
    }
});

// ============================================================================
// 15. SEO - STRUCTURED DATA
// ============================================================================
add_action('wp_head', 'mv_structured_data');
function mv_structured_data() {
    if (!is_singular('manga')) return;
    $manga_id = get_the_ID();
    $title = get_the_title();
    $cover = mv_get_cover($manga_id, 'large');
    $genres = wp_get_post_terms($manga_id, 'genre', ['fields' => 'names']);
    $author = get_post_meta($manga_id, '_mv_author', true) ?: 'Unknown';
    $chapters = get_posts(['post_type' => 'chapter', 'post_parent' => $manga_id, 'posts_per_page' => -1]);
    
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'ComicSeries',
        'name' => $title,
        'description' => wp_strip_all_tags(get_the_content()),
        'author' => ['@type' => 'Person', 'name' => $author],
        'image' => $cover,
        'genre' => $genres,
        'numberOfEpisodes' => count($chapters),
        'publisher' => ['@type' => 'Organization', 'name' => get_bloginfo('name')],
    ];
    echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
}

// ============================================================================
// 16. SEO - META TAGS
// ============================================================================
add_action('wp_head', 'mv_seo_meta_tags');
function mv_seo_meta_tags() {
    $desc = '';
    $og_image = '';
    
    if (is_singular('manga')) {
        $desc = wp_trim_words(get_the_content(), 30);
        $og_image = mv_get_cover(get_the_ID(), 'large');
    } elseif (is_singular('chapter')) {
        $manga_id = wp_get_post_parent_id(get_the_ID());
        $desc = 'Read ' . get_the_title($manga_id) . ' - ' . get_the_title();
        $og_image = mv_get_cover($manga_id, 'large');
    } elseif (is_home() || is_front_page()) {
        $desc = get_bloginfo('description');
    }
    
    if ($desc) echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
    if ($og_image) {
        echo '<meta property="og:image" content="' . esc_url($og_image) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr(wp_get_document_title()) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    }
}

// ============================================================================
// 17. ROBOTS TXT
// ============================================================================
add_filter('robots_txt', 'mv_robots_txt', 10, 2);
function mv_robots_txt($output, $public) {
    $output .= "Sitemap: " . site_url('/sitemap.xml') . "\n";
    $output .= "Allow: /manga/\n";
    $output .= "Allow: /read/\n";
    $output .= "Allow: /browse\n";
    $output .= "Allow: /recent\n";
    return $output;
}

// ============================================================================
// 18. ADMIN PANEL - THEME OPTIONS
// ============================================================================
add_action('admin_menu', 'mv_admin_menu');
function mv_admin_menu() {
    add_menu_page('MangaVerse', 'MangaVerse', 'manage_options', 'mv-settings', 'mv_admin_page', 'dashicons-book', 3);
    add_submenu_page('mv-settings', 'General Settings', 'General', 'manage_options', 'mv-settings', 'mv_admin_page');
    add_submenu_page('mv-settings', 'Homepage Builder', 'Homepage', 'manage_options', 'mv-homepage', 'mv_admin_homepage');
    add_submenu_page('mv-settings', 'Ads Manager', 'Ads', 'manage_options', 'mv-ads', 'mv_admin_ads');
    add_submenu_page('mv-settings', 'SEO Settings', 'SEO', 'manage_options', 'mv-seo', 'mv_admin_seo');
    add_submenu_page('mv-settings', 'Header & Footer', 'Header/Footer', 'manage_options', 'mv-header-footer', 'mv_admin_header_footer');
}

function mv_admin_page() {
    if (isset($_POST['mv_save_settings'])) {
        update_option('mv_site_name', sanitize_text_field($_POST['site_name']));
        update_option('mv_site_tagline', sanitize_text_field($_POST['site_tagline']));
        update_option('mv_hero_title', sanitize_text_field($_POST['hero_title']));
        update_option('mv_hero_subtitle', sanitize_textarea_field($_POST['hero_subtitle']));
        update_option('mv_hero_button', sanitize_text_field($_POST['hero_button']));
        update_option('mv_hero_bg', esc_url_raw($_POST['hero_bg']));
        update_option('mv_featured_manga', array_map('intval', $_POST['featured_manga'] ?? []));
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
    $site_name = get_option('mv_site_name', get_bloginfo('name'));
    $site_tagline = get_option('mv_site_tagline', get_bloginfo('description'));
    $hero_title = get_option('mv_hero_title', 'Discover stories drawn by imagination');
    $hero_subtitle = get_option('mv_hero_subtitle', 'Follow your favorite series, track new chapters, and dive into worlds created by talented artists.');
    $hero_button = get_option('mv_hero_button', 'Start Reading');
    $hero_bg = get_option('mv_hero_bg', '');
    $featured = get_option('mv_featured_manga', []);
    $mangas = get_posts(['post_type' => 'manga', 'posts_per_page' => 20, 'orderby' => 'modified']);
    ?>
    <div class="wrap">
        <h1 style="color:#e8783a;font-size:24px;font-weight:800;">MangaVerse - General Settings</h1>
        <form method="post">
            <table class="form-table">
                <tr><th>Site Name</th><td><input type="text" name="site_name" value="<?php echo esc_attr($site_name); ?>" class="regular-text" style="background:#1a1a22;border:1px solid #333;color:#fff;"/></td></tr>
                <tr><th>Site Tagline</th><td><input type="text" name="site_tagline" value="<?php echo esc_attr($site_tagline); ?>" class="regular-text" style="background:#1a1a22;border:1px solid #333;color:#fff;"/></td></tr>
                <tr><th>Hero Title</th><td><input type="text" name="hero_title" value="<?php echo esc_attr($hero_title); ?>" class="regular-text" style="background:#1a1a22;border:1px solid #333;color:#fff;"/></td></tr>
                <tr><th>Hero Subtitle</th><td><textarea name="hero_subtitle" rows="3" class="large-text" style="background:#1a1a22;border:1px solid #333;color:#fff;"><?php echo esc_textarea($hero_subtitle); ?></textarea></td></tr>
                <tr><th>Hero Button Text</th><td><input type="text" name="hero_button" value="<?php echo esc_attr($hero_button); ?>" class="regular-text" style="background:#1a1a22;border:1px solid #333;color:#fff;"/></td></tr>
                <tr><th>Hero Background URL</th><td><input type="url" name="hero_bg" value="<?php echo esc_url($hero_bg); ?>" class="regular-text" style="background:#1a1a22;border:1px solid #333;color:#fff;"/></td></tr>
                <tr><th>Featured Manga (Homepage)</th>
                    <td>
                        <select name="featured_manga[]" multiple style="width:300px;height:150px;background:#1a1a22;border:1px solid #333;color:#fff;">
                            <?php foreach ($mangas as $m): ?>
                            <option value="<?php echo $m->ID; ?>" <?php echo in_array($m->ID, $featured) ? 'selected' : ''; ?>><?php echo esc_html($m->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Hold Ctrl to select multiple</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings', 'primary', 'mv_save_settings'); ?>
        </form>
    </div>
    <?php
}

function mv_admin_ads() {
    if (isset($_POST['mv_save_ads'])) {
        update_option('mv_ad_header', wp_kses_post($_POST['ad_header']));
        update_option('mv_ad_footer', wp_kses_post($_POST['ad_footer']));
        update_option('mv_ad_chapter_top', wp_kses_post($_POST['ad_chapter_top']));
        update_option('mv_ad_chapter_bottom', wp_kses_post($_POST['ad_chapter_bottom']));
        update_option('mv_ad_sidebar', wp_kses_post($_POST['ad_sidebar']));
        update_option('mv_ad_enabled', isset($_POST['ad_enabled']) ? '1' : '0');
        echo '<div class="notice notice-success"><p>Ad settings saved!</p></div>';
    }
    ?>
    <div class="wrap">
        <h1 style="color:#e8783a;font-size:24px;font-weight:800;">Ads Manager</h1>
        <form method="post">
            <table class="form-table">
                <tr><th>Enable Ads</th><td><label><input type="checkbox" name="ad_enabled" <?php checked(get_option('mv_ad_enabled'), '1'); ?> /> Enable advertisement display</label></td></tr>
                <tr><th>Header Ad</th><td><textarea name="ad_header" rows="4" class="large-text code" style="background:#1a1a22;border:1px solid #333;color:#0f0;"><?php echo esc_textarea(get_option('mv_ad_header')); ?></textarea></td></tr>
                <tr><th>Footer Ad</th><td><textarea name="ad_footer" rows="4" class="large-text code" style="background:#1a1a22;border:1px solid #333;color:#0f0;"><?php echo esc_textarea(get_option('mv_ad_footer')); ?></textarea></td></tr>
                <tr><th>Chapter Top Ad</th><td><textarea name="ad_chapter_top" rows="4" class="large-text code" style="background:#1a1a22;border:1px solid #333;color:#0f0;"><?php echo esc_textarea(get_option('mv_ad_chapter_top')); ?></textarea></td></tr>
                <tr><th>Chapter Bottom Ad</th><td><textarea name="ad_chapter_bottom" rows="4" class="large-text code" style="background:#1a1a22;border:1px solid #333;color:#0f0;"><?php echo esc_textarea(get_option('mv_ad_chapter_bottom')); ?></textarea></td></tr>
                <tr><th>Sidebar Ad</th><td><textarea name="ad_sidebar" rows="4" class="large-text code" style="background:#1a1a22;border:1px solid #333;color:#0f0;"><?php echo esc_textarea(get_option('mv_ad_sidebar')); ?></textarea></td></tr>
            </table>
            <?php submit_button('Save Ads', 'primary', 'mv_save_ads'); ?>
        </form>
    </div>
    <?php
}

function mv_admin_seo() {
    if (isset($_POST['mv_save_seo'])) {
        update_option('mv_seo_title_format', sanitize_text_field($_POST['seo_title_format']));
        update_option('mv_seo_description', sanitize_textarea_field($_POST['seo_description']));
        update_option('mv_seo_keywords', sanitize_text_field($_POST['seo_keywords']));
        update_option('mv_enable_structured_data', isset($_POST['enable_structured']) ? '1' : '0');
        update_option('mv_enable_sitemap', isset($_POST['enable_sitemap']) ? '1' : '0');
        echo '<div class="notice notice-success"><p>SEO settings saved!</p></div>';
    }
    ?>
    <div class="wrap">
        <h1 style="color:#e8783a;font-size:24px;font-weight:800;">SEO Settings</h1>
        <form method="post">
            <table class="form-table">
                <tr><th>Default Title Format</th><td><input type="text" name="seo_title_format" value="<?php echo esc_attr(get_option('mv_seo_title_format', '{title} | {sitename}')); ?>" class="regular-text" style="background:#1a1a22;border:1px solid #333;color:#fff;"/></td></tr>
                <tr><th>Meta Description</th><td><textarea name="seo_description" rows="3" class="large-text" style="background:#1a1a22;border:1px solid #333;color:#fff;"><?php echo esc_textarea(get_option('mv_seo_description')); ?></textarea></td></tr>
                <tr><th>Meta Keywords</th><td><input type="text" name="seo_keywords" value="<?php echo esc_attr(get_option('mv_seo_keywords', 'manga, manhwa, manhua, read manga online, webtoon')); ?>" class="regular-text" style="background:#1a1a22;border:1px solid #333;color:#fff;"/></td></tr>
                <tr><th>Enable Structured Data</th><td><label><input type="checkbox" name="enable_structured" <?php checked(get_option('mv_enable_structured_data', '1'), '1'); ?> /> Enable JSON-LD structured data</label></td></tr>
                <tr><th>Enable Sitemap</th><td><label><input type="checkbox" name="enable_sitemap" <?php checked(get_option('mv_enable_sitemap', '1'), '1'); ?> /> Enable XML sitemap at /sitemap.xml</label></td></tr>
            </table>
            <?php submit_button('Save SEO', 'primary', 'mv_save_seo'); ?>
        </form>
    </div>
    <?php
}

function mv_admin_header_footer() {
    if (isset($_POST['mv_save_hf'])) {
        update_option('mv_custom_header_code', wp_kses_post($_POST['custom_header']));
        update_option('mv_custom_footer_code', wp_kses_post($_POST['custom_footer']));
        update_option('mv_footer_text', wp_kses_post($_POST['footer_text']));
        update_option('mv_footer_copyright', sanitize_text_field($_POST['footer_copyright']));
        update_option('mv_social_discord', esc_url_raw($_POST['social_discord']));
        update_option('mv_social_twitter', esc_url_raw($_POST['social_twitter']));
        update_option('mv_show_footer_links', isset($_POST['show_footer_links']) ? '1' : '0');
        echo '<div class="notice notice-success"><p>Header/Footer settings saved!</p></div>';
    }
    ?>
    <div class="wrap">
        <h1 style="color:#e8783a;font-size:24px;font-weight:800;">Header & Footer Settings</h1>
        <form method="post">
            <table class="form-table">
                <tr><th>Custom Header Code</th><td><textarea name="custom_header" rows="5" class="large-text code" style="background:#1a1a22;border:1px solid #333;color:#0f0;"><?php echo esc_textarea(get_option('mv_custom_header_code')); ?></textarea><p class="description">HTML/JS/CSS to inject in &lt;head&gt;</p></td></tr>
                <tr><th>Custom Footer Code</th><td><textarea name="custom_footer" rows="5" class="large-text code" style="background:#1a1a22;border:1px solid #333;color:#0f0;"><?php echo esc_textarea(get_option('mv_custom_footer_code')); ?></textarea><p class="description">HTML/JS to inject before &lt;/body&gt;</p></td></tr>
                <tr><th>Footer Text</th><td><textarea name="footer_text" rows="2" class="large-text" style="background:#1a1a22;border:1px solid #333;color:#fff;"><?php echo esc_textarea(get_option('mv_footer_text', 'Your ultimate manga reading platform. Read thousands of manga, manhwa, and manhua titles for free.')); ?></textarea></td></tr>
                <tr><th>Copyright Text</th><td><input type="text" name="footer_copyright" value="<?php echo esc_attr(get_option('mv_footer_copyright', date('Y') . ' ' . get_bloginfo('name') . '. All rights reserved.')); ?>" class="regular-text" style="background:#1a1a22;border:1px solid #333;color:#fff;"/></td></tr>
                <tr><th>Discord URL</th><td><input type="url" name="social_discord" value="<?php echo esc_url(get_option('mv_social_discord')); ?>" class="regular-text" style="background:#1a1a22;border:1px solid #333;color:#fff;"/></td></tr>
                <tr><th>Twitter URL</th><td><input type="url" name="social_twitter" value="<?php echo esc_url(get_option('mv_social_twitter')); ?>" class="regular-text" style="background:#1a1a22;border:1px solid #333;color:#fff;"/></td></tr>
                <tr><th>Show Footer Links</th><td><label><input type="checkbox" name="show_footer_links" <?php checked(get_option('mv_show_footer_links', '1'), '1'); ?> /> Show navigation links in footer</label></td></tr>
            </table>
            <?php submit_button('Save', 'primary', 'mv_save_hf'); ?>
        </form>
    </div>
    <?php
}

function mv_admin_homepage() {
    if (isset($_POST['mv_save_homepage'])) {
        update_option('mv_show_hero', isset($_POST['show_hero']) ? '1' : '0');
        update_option('mv_show_stats', isset($_POST['show_stats']) ? '1' : '0');
        update_option('mv_show_recent', isset($_POST['show_recent']) ? '1' : '0');
        update_option('mv_show_popular', isset($_POST['show_popular']) ? '1' : '0');
        update_option('mv_show_featured', isset($_POST['show_featured']) ? '1' : '0');
        update_option('mv_recent_count', intval($_POST['recent_count']));
        update_option('mv_popular_period', sanitize_text_field($_POST['popular_period']));
        echo '<div class="notice notice-success"><p>Homepage layout saved!</p></div>';
    }
    ?>
    <div class="wrap">
        <h1 style="color:#e8783a;font-size:24px;font-weight:800;">Homepage Builder</h1>
        <form method="post">
            <table class="form-table">
                <tr><th>Show Hero Section</th><td><label><input type="checkbox" name="show_hero" <?php checked(get_option('mv_show_hero', '1'), '1'); ?> /> Display hero banner</label></td></tr>
                <tr><th>Show Stats Section</th><td><label><input type="checkbox" name="show_stats" <?php checked(get_option('mv_show_stats', '1'), '1'); ?> /> Display statistics counter</label></td></tr>
                <tr><th>Show Featured Slider</th><td><label><input type="checkbox" name="show_featured" <?php checked(get_option('mv_show_featured', '1'), '1'); ?> /> Display featured manga carousel</label></td></tr>
                <tr><th>Show Recently Added</th><td><label><input type="checkbox" name="show_recent" <?php checked(get_option('mv_show_recent', '1'), '1'); ?> /> Display recently updated manga</label></td></tr>
                <tr><th>Recent Manga Count</th><td><input type="number" name="recent_count" value="<?php echo intval(get_option('mv_recent_count', 12)); ?>" min="4" max="48" style="background:#1a1a22;border:1px solid #333;color:#fff;width:80px;"/></td></tr>
                <tr><th>Show Popular Section</th><td><label><input type="checkbox" name="show_popular" <?php checked(get_option('mv_show_popular', '1'), '1'); ?> /> Display popular manga sidebar</label></td></tr>
                <tr><th>Popular Period</th><td>
                    <select name="popular_period" style="background:#1a1a22;border:1px solid #333;color:#fff;">
                        <option value="day" <?php selected(get_option('mv_popular_period', 'week'), 'day'); ?>>Today</option>
                        <option value="week" <?php selected(get_option('mv_popular_period', 'week'), 'week'); ?>>This Week</option>
                        <option value="month" <?php selected(get_option('mv_popular_period', 'week'), 'month'); ?>>This Month</option>
                    </select>
                </td></tr>
            </table>
            <?php submit_button('Save Layout', 'primary', 'mv_save_homepage'); ?>
        </form>
    </div>
    <?php
}

// ============================================================================
// 19. INJECT CUSTOM HEADER CODE
// ============================================================================
add_action('wp_head', 'mv_custom_header_code', 999);
function mv_custom_header_code() {
    $code = get_option('mv_custom_header_code', '');
    if ($code) echo $code . "\n";
}

add_action('wp_footer', 'mv_custom_footer_code', 999);
function mv_custom_footer_code() {
    $code = get_option('mv_custom_footer_code', '');
    if ($code) echo $code . "\n";
}

// ============================================================================
// 20. AD DISPLAY HELPERS
// ============================================================================
if (!function_exists('mv_ad')) {
    function mv_ad($position) {
        if (get_option('mv_ad_enabled') !== '1') return;
        $ad = get_option('mv_ad_' . $position, '');
        if ($ad) echo '<div class="mv-ad mv-ad-' . esc_attr($position) . '">' . $ad . '</div>';
    }
}

// ============================================================================
// 21. NOTIFICATION SYSTEM
// ============================================================================
function mv_get_notifications($user_id, $limit = 10) {
    $bookmarks = get_user_meta($user_id, '_mv_bookmarks', true) ?: [];
    if (empty($bookmarks)) return [];
    
    $recent = get_posts([
        'post_type' => 'chapter',
        'posts_per_page' => $limit,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => [['key' => '_mv_parent_manga', 'value' => $bookmarks, 'compare' => 'IN']],
    ]);
    
    $notifications = [];
    foreach ($recent as $ch) {
        $manga_id = get_post_meta($ch->ID, '_mv_parent_manga', true);
        $manga = get_post($manga_id);
        if ($manga) {
            $notifications[] = [
                'manga_title' => $manga->post_title,
                'manga_url' => get_permalink($manga_id),
                'chapter_title' => $ch->post_title,
                'chapter_url' => get_permalink($ch->ID),
                'chapter_num' => get_post_meta($ch->ID, '_mv_chapter_number', true),
                'time' => human_time_diff(get_the_time('U', $ch), current_time('timestamp')),
            ];
        }
    }
    return $notifications;
}

// ============================================================================
// 22. HELPER FUNCTIONS
// ============================================================================
if (!function_exists('mv_format_number')) {
    function mv_format_number($num) {
        if ($num >= 1000000) return round($num / 1000000, 1) . 'M';
        if ($num >= 1000) return round($num / 1000, 1) . 'K';
        return $num;
    }
}

if (!function_exists('mv_time_ago')) {
    function mv_time_ago($post_id) {
        return human_time_diff(get_the_time('U', $post_id), current_time('timestamp')) . ' ago';
    }
}

// Auto-set chapter title from parent manga + chapter number
add_action('save_post', 'mv_auto_chapter_title', 20);
function mv_auto_chapter_title($post_id) {
    if (get_post_type($post_id) !== 'chapter') return;
    $parent = wp_get_post_parent_id($post_id);
    $chap_num = get_post_meta($post_id, '_mv_chapter_number', true);
    if ($parent && $chap_num) {
        $manga_title = get_the_title($parent);
        $new_title = $manga_title . ' Chapter ' . $chap_num;
        wp_update_post(['ID' => $post_id, 'post_title' => $new_title]);
    }
}

// ============================================================================
// 23. RANDOM MANGA
// ============================================================================
add_action('template_redirect', 'mv_random_manga');
function mv_random_manga() {
    if (isset($_GET['random']) && $_GET['random'] === '1') {
        $random = get_posts(['post_type' => 'manga', 'posts_per_page' => 1, 'orderby' => 'rand', 'fields' => 'ids']);
        if ($random) wp_redirect(get_permalink($random[0]));
        else wp_redirect(site_url());
        exit;
    }
}

// ============================================================================
// 24. LOGIN REDIRECT
// ============================================================================
add_filter('login_url', function($url, $redirect) {
    $auth_page = get_pages(['meta_key' => '_wp_page_template', 'meta_value' => 'page-auth.php']);
    if ($auth_page) return get_permalink($auth_page[0]->ID);
    return $url;
}, 10, 2);

// ============================================================================
// 25. USER PROFILE REWRITES
// ============================================================================
add_action('init', 'mv_user_profile_rewrites');
function mv_user_profile_rewrites() {
    add_rewrite_rule('user/([^/]+)/?$', 'index.php?pagename=public-profile&username=$1', 'top');
    add_rewrite_tag('%username%', '([^/]+)');
}

// ============================================================================
// 26. FLUSH REWRITE ON ACTIVATION
// ============================================================================
add_action('after_switch_theme', 'mv_flush_rewrites');
function mv_flush_rewrites() {
    mv_register_cpts();
    mv_register_taxonomies();
    mv_sitemap_rewrite();
    mv_user_profile_rewrites();
    flush_rewrite_rules();
}

// ============================================================================
// 26. ADMIN STYLES
// ============================================================================
add_action('admin_enqueue_scripts', function() {
    echo '<style>
        .wp-menu-image.dashicons-book:before { color: #e8783a !important; }
        #adminmenu li.current a.menu-top, #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu { background: #e8783a !important; }
    </style>';
});

// Hide admin bar for all users
add_filter('show_admin_bar', '__return_false');
