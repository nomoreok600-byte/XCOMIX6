<?php
/**
 * Headless REST API for the Next.js manga frontend.
 *
 * Namespace: /wp-json/xcomix/v1
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', 'mvx_register_headless_routes');
function mvx_register_headless_routes() {
    register_rest_route('xcomix/v1', '/home', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'mvx_api_home',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('xcomix/v1', '/manga/(?P<id>[a-zA-Z0-9_-]+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'mvx_api_manga',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('xcomix/v1', '/chapter/(?P<id>[a-zA-Z0-9_-]+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'mvx_api_chapter',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('xcomix/v1', '/user-state', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'mvx_api_user_state',
        'permission_callback' => 'is_user_logged_in',
    ]);
}

add_filter('rest_pre_serve_request', 'mvx_headless_cors_headers', 10, 4);
function mvx_headless_cors_headers($served, $result, $request, $server) {
    if (strpos($request->get_route(), '/xcomix/v1/') === 0) {
        header('Access-Control-Allow-Origin: ' . esc_url_raw(get_option('mv_headless_frontend_origin', '*')));
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
        header('Cache-Control: public, max-age=60');
    }
    return $served;
}

function mvx_api_post_id($id, $post_type = 'manga') {
    if (is_numeric($id)) return intval($id);

    $post = get_page_by_path(sanitize_title($id), OBJECT, $post_type);
    return $post ? intval($post->ID) : 0;
}

function mvx_api_manga_payload($manga_id) {
    $manga = get_post($manga_id);
    if (!$manga || $manga->post_type !== 'manga') return null;

    $chapters = mvx_get_chapters($manga_id, 'DESC');
    $latest = $chapters ? mvx_api_chapter_summary($chapters[0]->ID) : null;
    $genres = wp_get_post_terms($manga_id, 'genre', ['fields' => 'names']);

    return [
        'id' => strval($manga_id),
        'slug' => $manga->post_name,
        'title' => get_the_title($manga_id),
        'alt_title' => mvx_manga_meta($manga_id, 'alt_title'),
        'description' => wp_strip_all_tags(apply_filters('the_content', $manga->post_content)),
        'cover_url' => mvx_api_raw_cover($manga_id),
        'banner_url' => mvx_api_raw_cover($manga_id),
        'author' => mvx_manga_meta($manga_id, 'author', 'Unknown'),
        'artist' => mvx_manga_meta($manga_id, 'artist'),
        'type' => mvx_manga_meta($manga_id, 'type', 'Manga'),
        'status' => mvx_manga_meta($manga_id, 'status', 'Ongoing'),
        'score' => mvx_manga_meta($manga_id, 'score', 'N/A'),
        'is_18_plus' => mvx_manga_meta($manga_id, 'is_18_plus') === '1',
        'genres' => $genres ?: [],
        'latest_chapter' => $latest,
        'updatedAt' => get_post_modified_time('c', true, $manga_id),
    ];
}

function mvx_api_raw_cover($manga_id) {
    $cover = mvx_manga_meta($manga_id, 'cover_url');
    if ($cover) return esc_url_raw($cover);

    $thumb = get_the_post_thumbnail_url($manga_id, 'full');
    if ($thumb) return esc_url_raw($thumb);

    return esc_url_raw(MV_URI . '/assets/img/placeholder-cover.jpg');
}

function mvx_api_chapter_summary($chapter_id) {
    $chapter = get_post($chapter_id);
    if (!$chapter || $chapter->post_type !== 'chapter') return null;

    $manga_id = mvx_parent_manga_id($chapter_id);
    return [
        'id' => strval($chapter_id),
        'slug' => $chapter->post_name,
        'manga_id' => strval($manga_id),
        'parent' => strval($manga_id),
        'title' => get_the_title($chapter_id),
        'number' => mvx_chapter_number($chapter_id),
        'chapter_number' => mvx_chapter_number($chapter_id),
        'url' => get_permalink($chapter_id),
        'image' => $manga_id ? mvx_api_raw_cover($manga_id) : '',
        'date' => get_post_time('c', true, $chapter_id),
    ];
}

function mvx_api_query_manga($args = []) {
    $defaults = [
        'post_type' => 'manga',
        'post_status' => 'publish',
        'posts_per_page' => 12,
        'orderby' => 'modified',
        'order' => 'DESC',
        'fields' => 'ids',
    ];

    $ids = get_posts(wp_parse_args($args, $defaults));
    return array_values(array_filter(array_map('mvx_api_manga_payload', $ids)));
}

function mvx_api_home(WP_REST_Request $request) {
    $featured = get_option('mv_featured_manga', []);
    $hero = !empty($featured)
        ? mvx_api_query_manga(['post__in' => array_map('intval', $featured), 'orderby' => 'post__in', 'posts_per_page' => 6])
        : mvx_api_query_manga(['posts_per_page' => 6]);

    $hot = mvx_api_query_manga(['posts_per_page' => 24]);
    $ranked = mvx_api_query_manga(['posts_per_page' => 18, 'meta_key' => '_mv_score', 'orderby' => 'meta_value_num', 'order' => 'DESC']);
    $recent_chapters = get_posts([
        'post_type' => 'chapter',
        'post_status' => 'publish',
        'posts_per_page' => 24,
        'orderby' => 'date',
        'order' => 'DESC',
        'fields' => 'ids',
    ]);

    $comments = get_comments(['status' => 'approve', 'number' => 12, 'orderby' => 'comment_date_gmt', 'order' => 'DESC']);

    return rest_ensure_response([
        'hero' => $hero,
        'hot' => $hot,
        'trending' => [
            'day' => array_slice($ranked, 0, 6),
            'week' => array_slice($ranked, 6, 6),
            'month' => array_slice($ranked, 12, 6),
        ],
        'recent_chapters' => array_values(array_filter(array_map('mvx_api_chapter_summary', $recent_chapters))),
        'live_feed' => array_map(function($comment) {
            return [
                'id' => strval($comment->comment_ID),
                'author' => $comment->comment_author,
                'text' => wp_strip_all_tags($comment->comment_content),
                'targetTitle' => get_the_title($comment->comment_post_ID),
                'createdAt' => mysql2date('c', $comment->comment_date_gmt),
            ];
        }, $comments),
    ]);
}

function mvx_api_manga(WP_REST_Request $request) {
    $manga_id = mvx_api_post_id($request['id'], 'manga');
    $payload = mvx_api_manga_payload($manga_id);
    if (!$payload) return new WP_Error('not_found', 'Manga not found', ['status' => 404]);

    $chapter_ids = array_map(function($chapter) { return $chapter->ID; }, mvx_get_chapters($manga_id, 'DESC'));
    $genres = wp_get_post_terms($manga_id, 'genre', ['fields' => 'slugs']);
    $related = $genres ? mvx_api_query_manga([
        'posts_per_page' => 8,
        'post__not_in' => [$manga_id],
        'tax_query' => [['taxonomy' => 'genre', 'field' => 'slug', 'terms' => array_slice($genres, 0, 2)]],
    ]) : [];

    return rest_ensure_response([
        'manga' => $payload,
        'chapters' => array_values(array_filter(array_map('mvx_api_chapter_summary', $chapter_ids))),
        'related' => $related,
    ]);
}

function mvx_api_chapter(WP_REST_Request $request) {
    $chapter_id = mvx_api_post_id($request['id'], 'chapter');
    $chapter = get_post($chapter_id);
    if (!$chapter || $chapter->post_type !== 'chapter') {
        return new WP_Error('not_found', 'Chapter not found', ['status' => 404]);
    }

    $manga_id = mvx_parent_manga_id($chapter_id);
    $chapters = mvx_get_chapters($manga_id, 'DESC');
    $chapter_ids = array_map(function($item) { return intval($item->ID); }, $chapters);
    $index = array_search($chapter_id, $chapter_ids, true);

    $comments = get_comments(['post_id' => $chapter_id, 'status' => 'approve', 'number' => 50]);

    return rest_ensure_response([
        'chapter' => array_merge(mvx_api_chapter_summary($chapter_id), [
            'mangaTitle' => $manga_id ? get_the_title($manga_id) : '',
            'manga_title' => $manga_id ? get_the_title($manga_id) : '',
            'mangaCover' => $manga_id ? mvx_api_raw_cover($manga_id) : '',
            'manga_cover' => $manga_id ? mvx_api_raw_cover($manga_id) : '',
        ]),
        'images' => mvx_api_chapter_images($chapter_id),
        'chapters' => array_values(array_filter(array_map('mvx_api_chapter_summary', $chapter_ids))),
        'prev_chapter_id' => ($index !== false && isset($chapter_ids[$index + 1])) ? strval($chapter_ids[$index + 1]) : null,
        'next_chapter_id' => ($index !== false && isset($chapter_ids[$index - 1])) ? strval($chapter_ids[$index - 1]) : null,
        'comments' => array_map(function($comment) {
            return [
                'id' => strval($comment->comment_ID),
                'author' => $comment->comment_author,
                'text' => wp_strip_all_tags($comment->comment_content),
                'createdAt' => mysql2date('c', $comment->comment_date_gmt),
            ];
        }, $comments),
    ]);
}

function mvx_api_chapter_images($chapter_id) {
    $cached = get_post_meta($chapter_id, '_xcomix_cached_images', true);
    if (is_array($cached) && !empty($cached)) return array_values(array_map('esc_url_raw', $cached));

    $mgeko_cached = get_transient('xcomix_mgeko_imgs_' . $chapter_id);
    if (is_array($mgeko_cached) && !empty($mgeko_cached)) return array_values(array_map('esc_url_raw', $mgeko_cached));

    $content_urls = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', get_post_field('post_content', $chapter_id)))));
    $content_urls = array_filter($content_urls, function($url) { return strpos($url, 'http') === 0; });
    if (!empty($content_urls)) return array_values(array_map('esc_url_raw', $content_urls));

    $source_url = mvx_first_meta($chapter_id, ['_katana_url', '_buddy_url', '_mgeko_url', '_mv_source_url'], '');
    if (!$source_url || !function_exists('xcomix_fetch')) return [];

    $html = xcomix_fetch($source_url);
    if (!$html) return [];

    preg_match_all('/[\'"](https?:\/\/[^\'"]+\.(?:jpg|jpeg|png|webp)(?:\?[^\'"]*)?)[\'"]/i', $html, $matches);
    $images = [];
    foreach ($matches[1] ?? [] as $img) {
        $clean = str_replace('\\/', '/', $img);
        if (!preg_match('/(logo|banner|credit|scan|promo|recruit|join|donate|support|fav\.png|s\.png|\/static\/img\/)/i', $clean)) {
            $images[] = esc_url_raw($clean);
        }
    }

    $images = array_values(array_unique($images));
    if (!empty($images)) update_post_meta($chapter_id, '_xcomix_cached_images', $images);
    return $images;
}

function mvx_api_user_state(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $events = $request->get_param('events');
    $events = is_array($events) ? $events : [];

    foreach ($events as $event) {
        $type = sanitize_key($event['type'] ?? '');
        $manga_id = intval($event['mangaId'] ?? 0);

        if ($type === 'history' && $manga_id) {
            $entry = [
                'id' => $manga_id,
                'chapter_id' => intval($event['chapterId'] ?? 0),
                'chapter_num' => sanitize_text_field($event['chapterNumber'] ?? ''),
                'url' => esc_url_raw($event['href'] ?? ''),
                'timestamp' => current_time('timestamp'),
            ];
            foreach (['_xcomix_history', '_mv_history'] as $key) {
                $history = get_user_meta($user_id, $key, true);
                $history = is_array($history) ? $history : [];
                $history[$manga_id] = $entry;
                update_user_meta($user_id, $key, $history);
            }
        }

        if ($type === 'bookmark' && $manga_id) {
            foreach (['_xcomix_bookmarks', '_mv_bookmarks'] as $key) {
                $bookmarks = get_user_meta($user_id, $key, true);
                $bookmarks = is_array($bookmarks) ? $bookmarks : [];
                if (!in_array($manga_id, $bookmarks, true)) $bookmarks[] = $manga_id;
                update_user_meta($user_id, $key, array_values(array_unique($bookmarks)));
            }
        }
    }

    return rest_ensure_response(['ok' => true, 'accepted' => count($events)]);
}
