<?php
/**
 * X COMIX - Elite App Single Manga Page V9.2 (Header Clearance & Clickable Genres)
 */

$manga_id = get_the_ID();
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$is_logged_in = is_user_logged_in();

// =========================================================================
// INLINE AJAX: BUNDLED LIBRARY SAVING
// =========================================================================
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['xcomix_save_library'])) {
        while (ob_get_level()) ob_end_clean();
        $status = sanitize_text_field($_POST['plan_status']);
        $f_id = sanitize_text_field($_POST['folder_id']);

        $plans = get_user_meta($user_id, '_xcomix_reading_plans', true) ?: [];
        $bookmarks = get_user_meta($user_id, '_xcomix_bookmarks', true) ?: [];
        $manga_folders = get_user_meta($user_id, '_xcomix_manga_folders', true) ?: [];

        $plans[$manga_id] = $status; update_user_meta($user_id, '_xcomix_reading_plans', $plans); update_user_meta($user_id, '_mv_reading_plans', $plans);
        if (!in_array($manga_id, $bookmarks)) { $bookmarks[] = $manga_id; update_user_meta($user_id, '_xcomix_bookmarks', $bookmarks); update_user_meta($user_id, '_mv_bookmarks', $bookmarks); }
        if ($f_id === 'none') { unset($manga_folders[$manga_id]); } else { $manga_folders[$manga_id] = $f_id; }
        update_user_meta($user_id, '_xcomix_manga_folders', $manga_folders);
        echo wp_json_encode(['success' => true]); exit;
    }

    if (isset($_POST['xcomix_folder_action']) && $_POST['xcomix_folder_action'] === 'add') {
        while (ob_get_level()) ob_end_clean();
        $folder_name = sanitize_text_field($_POST['folder_name']);
        if (!empty($folder_name)) {
            $existing_custom = get_user_meta($user_id, '_xcomix_custom_folders', true) ?: [];
            $folder_id = 'folder_' . substr(md5(time() . $folder_name), 0, 8);
            $existing_custom[$folder_id] = $folder_name; update_user_meta($user_id, '_xcomix_custom_folders', $existing_custom);
            echo wp_json_encode(['success' => true, 'folder_id' => $folder_id, 'folder_name' => $folder_name]);
        } else { echo wp_json_encode(['success' => false]); }
        exit;
    }
}

// =========================================================================
// CHAT INTERCEPTORS
// =========================================================================
if ($is_logged_in) {
    if (isset($_POST['custom_chat_submit'])) {
        while (ob_get_level()) ob_end_clean();
        $post_id = intval($_POST['comment_post_ID']); $parent_id = intval($_POST['comment_parent']); $content = sanitize_text_field($_POST['comment_content']);
        if (!empty($content)) { wp_insert_comment(['comment_post_ID' => $post_id, 'comment_author' => $current_user->display_name, 'comment_author_email' => $current_user->user_email, 'comment_content' => $content, 'comment_parent' => $parent_id, 'user_id' => $user_id, 'comment_date' => current_time('mysql'), 'comment_approved' => 1]); echo wp_json_encode(['success' => true]); } else { echo wp_json_encode(['success' => false, 'error' => 'Empty message.']); }
        exit;
    }
    if (isset($_FILES['chat_image'])) {
        while (ob_get_level()) ob_end_clean(); require_once(ABSPATH . 'wp-admin/includes/file.php');
        $movefile = wp_handle_upload($_FILES['chat_image'], array('test_form' => false));
        if ($movefile && !isset($movefile['error'])) { echo wp_json_encode(['success' => true, 'url' => $movefile['url']]); } else { echo wp_json_encode(['success' => false, 'error' => $movefile['error']]); }
        exit;
    }
    if (isset($_POST['delete_chat_id'])) {
        while (ob_get_level()) ob_end_clean(); $cid = intval($_POST['delete_chat_id']); $comment = get_comment($cid);
        if ($comment && ($comment->user_id == $user_id || current_user_can('manage_options'))) { wp_delete_comment($cid, true); echo 'SUCCESS'; } exit;
    }
    if (isset($_POST['edit_chat_id']) && isset($_POST['new_content'])) {
        while (ob_get_level()) ob_end_clean(); $cid = intval($_POST['edit_chat_id']); $new_content = sanitize_text_field($_POST['new_content']); $comment = get_comment($cid);
        if ($comment && $comment->user_id == $user_id) { wp_update_comment(['comment_ID' => $cid, 'comment_content' => $new_content]); echo 'SUCCESS'; } exit;
    }
    if (isset($_POST['like_chat_id'])) {
        while (ob_get_level()) ob_end_clean(); $cid = intval($_POST['like_chat_id']); $likes = (int) get_comment_meta($cid, '_chat_likes', true); update_comment_meta($cid, '_chat_likes', $likes + 1); echo $likes + 1; exit;
    }
}
if (isset($_POST['fetch_live_chats']) && isset($_POST['manga_id'])) {
    while (ob_get_level()) ob_end_clean(); $fetch_manga_id = intval($_POST['manga_id']);
    $manga_chats = get_comments(['post_id' => $fetch_manga_id, 'status' => 'approve', 'number' => 50]);
    foreach($manga_chats as $chat) { xcomix_manga_chat_render($chat, $is_logged_in, $current_user); } exit;
}

// =========================================================================
// BULLETPROOF IMAGE WRAPPER
// =========================================================================
if (!function_exists('xcomix_get_cover')) {
    function xcomix_get_cover($post_id, $size = 'medium') {
        return mv_get_cover($post_id, $size);
    }
}

// =========================================================================
// CHAT RENDER FUNCTION
// =========================================================================
if (!function_exists('xcomix_manga_chat_render')) {
    function xcomix_manga_chat_render($chat, $is_logged_in, $current_user) {
        $is_mine = ($is_logged_in && $chat->user_id == $current_user->ID);
        $author_name = $chat->comment_author;
        $author_avatar = get_user_meta($chat->user_id, '_xcomix_avatar', true) ?: 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($author_name);

        $user_obj = get_userdata($chat->user_id);
        $role_badge = ''; $name_color = 'text-gray-200';
        if ($user_obj) {
            if (in_array('administrator', $user_obj->roles)) { $role_badge = '<span class="bg-[#ea580c]/20 text-[#ea580c] text-[8px] font-black uppercase px-1.5 py-0.5 rounded ml-1">Admin</span>'; $name_color = 'text-[#ea580c]'; }
            elseif (in_array('author', $user_obj->roles) || in_array('editor', $user_obj->roles)) { $role_badge = '<span class="bg-[#3b82f6]/20 text-[#3b82f6] text-[8px] font-black uppercase px-1.5 py-0.5 rounded ml-1">Uploader</span>'; $name_color = 'text-[#3b82f6]'; }
        }

        $reply_html = '';
        if ($chat->comment_parent > 0) {
            $parent_comment = get_comment($chat->comment_parent);
            if ($parent_comment) {
                $p_author = esc_html($parent_comment->comment_author);
                $p_text = wp_trim_words(esc_html($parent_comment->comment_content), 8, '...');
                $reply_html = '<div class="flex items-center gap-1.5 text-[11px] text-gray-500 mb-1 pl-2 border-l-2 border-[#333] select-none"><svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg><span class="truncate">Replying to <b class="text-gray-400">@'.$p_author.'</b>: '.$p_text.'</span></div>';
            }
        }

        $raw_content = esc_html($chat->comment_content);
        if ($is_logged_in) { $raw_content = preg_replace('/(@' . preg_quote($current_user->display_name, '/') . ')/i', '<span class="mention-highlight">$1</span>', $raw_content); }
        $raw_content = preg_replace('/\*\*(.*?)\*\*/is', '<strong class="text-white">$1</strong>', $raw_content);
        $raw_content = preg_replace('/\_(.*?)\_/is', '<em class="text-gray-300 italic">$1</em>', $raw_content);
        $raw_content = preg_replace('/\|\|(.*?)\|\|/is', '<span class="spoiler-block" title="Tap to reveal">$1</span>', $raw_content);
        $raw_content = preg_replace('/(https?:\/\/[^\s"\'<>]+?\.(?:jpg|jpeg|png|gif|webp))/i', '|||IMG|||$1|||', $raw_content);
        $raw_content = make_clickable($raw_content);
        $raw_content = str_replace('<a href=', '<a class="text-[#3b82f6] hover:underline break-all" target="_blank" href=', $raw_content);
        $raw_content = preg_replace('/\|\|\|IMG\|\|\|(.*?)\|\|\|/i', '<br><img src="$1" onclick="openLightbox(\'$1\')" class="max-w-full md:max-w-[250px] max-h-[200px] w-auto h-auto rounded-xl mt-2 mb-1 border border-[#333] shadow-md hover:opacity-80 transition object-contain cursor-pointer">', $raw_content);
        $raw_content = nl2br($raw_content);

        $likes = (int) get_comment_meta($chat->comment_ID, '_chat_likes', true);
        $exact_time = date('M j, Y g:i A', strtotime($chat->comment_date));
        ?>
        <div class="flex gap-3 md:gap-4 group hover:bg-[#1a1a1a] focus-within:bg-[#1a1a1a] -mx-4 px-4 py-3 rounded-xl transition relative cursor-pointer md:cursor-default" tabindex="0" id="chat-msg-<?php echo $chat->comment_ID; ?>">
            <img src="<?php echo esc_url($author_avatar); ?>" alt="<?php echo esc_attr($author_name); ?>" class="w-10 h-10 rounded-full border border-white/5 bg-[#121212] shrink-0 mt-1 object-cover cursor-pointer" onclick="insertText('@<?php echo esc_js($author_name); ?>')">
            <div class="flex flex-col min-w-0 w-full pointer-events-none md:pointer-events-auto">
                <?php echo $reply_html; ?>
                <div class="flex items-baseline gap-2 mb-0.5">
                    <span class="text-[14px] font-bold <?php echo $name_color; ?>"><?php echo esc_html($author_name); ?></span>
                    <?php echo $role_badge; ?>
                    <span class="text-[10px] text-gray-500 font-medium cursor-help pointer-events-auto" title="<?php echo esc_attr($exact_time); ?>"><?php echo human_time_diff(strtotime($chat->comment_date), current_time('timestamp')); ?> ago</span>
                </div>
                <div class="text-[14px] text-gray-300 leading-relaxed font-medium break-words pointer-events-auto" id="chat-content-<?php echo $chat->comment_ID; ?>" data-raw="<?php echo esc_attr($chat->comment_content); ?>">
                    <?php echo $raw_content; ?>
                </div>
                <div class="mt-2 flex items-center gap-2 pointer-events-auto">
                    <button onclick="likeChat(<?php echo $chat->comment_ID; ?>)" class="flex items-center gap-1.5 bg-[#121212] border border-[#333] hover:border-pink-500/50 hover:bg-pink-500/10 px-2 py-1 rounded-md transition group/btn">
                        <svg class="w-3.5 h-3.5 text-gray-500 group-hover/btn:text-pink-500 transition <?php echo ($likes > 0) ? 'text-pink-500' : ''; ?>" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                        <span class="text-[10px] font-bold text-gray-400 group-hover/btn:text-pink-400" id="like-count-<?php echo $chat->comment_ID; ?>"><?php echo $likes > 0 ? $likes : 'Like'; ?></span>
                    </button>
                </div>
            </div>
            <div class="absolute right-4 top-4 flex gap-1 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition bg-[#121212]/90 backdrop-blur-sm md:bg-[#121212] rounded-lg shadow-lg border border-[#333] z-10 p-0.5">
                <button onclick="copyChatText(<?php echo $chat->comment_ID; ?>)" class="hover:bg-[#222] hover:text-white text-gray-400 p-1.5 rounded-lg transition" title="Copy Text"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></button>
                <?php if ($is_logged_in) { ?>
                <button onclick="triggerReply('<?php echo esc_js($author_name); ?>', <?php echo $chat->comment_ID; ?>)" class="hover:bg-[#222] hover:text-[#3b82f6] text-gray-400 p-1.5 rounded-lg transition" title="Reply"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg></button>
                <?php } ?>
                <?php if ($is_mine) { ?>
                <button onclick="editChat(<?php echo $chat->comment_ID; ?>)" class="hover:bg-[#222] hover:text-green-500 text-gray-400 p-1.5 rounded-lg transition" title="Edit Message"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg></button>
                <?php } ?>
                <?php if ($is_mine || current_user_can('manage_options')) { ?>
                <button onclick="deleteChat(<?php echo $chat->comment_ID; ?>)" class="hover:bg-[#222] hover:text-red-500 text-gray-400 p-1.5 rounded-lg transition" title="Delete Message"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                <?php } ?>
            </div>
        </div>
        <?php
    }
}

get_header();
the_post();

$manga_title = get_the_title();
$cover = mv_get_cover($manga_id, 'large');
$genres = wp_get_post_terms($manga_id, 'genre', ['fields' => 'names']);
$manga_type = mvx_manga_meta($manga_id, 'type', 'Manga');
$manga_status = mvx_manga_meta($manga_id, 'status', 'Ongoing');
$is_18 = mvx_manga_meta($manga_id, 'is_18_plus');

// Meta Fields
$author = mvx_manga_meta($manga_id, 'author');
$artist = mvx_manga_meta($manga_id, 'artist');
$alt_names = mvx_manga_meta($manga_id, 'alt_title');
$release_year = mvx_manga_meta($manga_id, 'release_year');
$score = mvx_manga_meta($manga_id, 'score');

// Get all chapters
$chapters = new WP_Query([
    'post_type'      => 'chapter',
    'post_parent'    => $manga_id,
    'posts_per_page' => -1,
    'meta_key'       => '_chapter_number',
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC'
]);
$total_chapters = $chapters->found_posts;

// Data Sync
$current_status = 'reading'; $current_folder = 'none';
$base_plan_labels = ['reading' => 'Reading', 'plan' => 'Plan to Read', 'completed' => 'Completed', 'dropped' => 'Dropped'];
$custom_folders = []; $continue_reading_url = ''; $continue_reading_text = 'Read First'; $chapters_read_count = 0; $progress_percentage = 0; $read_chapter_nums = []; $is_bookmarked = false;

if ($is_logged_in) {
    $reading_plans = get_user_meta($user_id, '_xcomix_reading_plans', true) ?: [];
    $manga_folders = get_user_meta($user_id, '_xcomix_manga_folders', true) ?: [];
    $custom_folders = get_user_meta($user_id, '_xcomix_custom_folders', true) ?: [];
    $bookmarks = array_unique(array_merge(get_user_meta($user_id, '_xcomix_bookmarks', true) ?: [], get_user_meta($user_id, '_mv_bookmarks', true) ?: []));
    $is_bookmarked = in_array($manga_id, $bookmarks);
    if (isset($reading_plans[$manga_id])) $current_status = $reading_plans[$manga_id];
    if (isset($manga_folders[$manga_id])) $current_folder = $manga_folders[$manga_id];

    $raw_history = mvx_user_history($user_id);
    if (isset($raw_history[$manga_id])) {
        $last_data = $raw_history[$manga_id]; $last_num = (float) $last_data['chapter_num'];
        $continue_reading_url = esc_url($last_data['url']); $continue_reading_text = 'Resume Ch. ' . $last_num;
        $chapters_read_count = $last_num; $read_chapter_nums[] = $last_num;
    }
}

if ($total_chapters > 0 && $chapters_read_count > 0) {
    $progress_percentage = ($chapters_read_count / $total_chapters) * 100;
    if ($progress_percentage > 100) $progress_percentage = 100;
}

$related = new WP_Query(['post_type' => 'manga', 'posts_per_page' => 10, 'post__not_in' => [$manga_id], 'orderby' => 'rand']);
$manga_chats = get_comments(['post_id' => $manga_id, 'status' => 'approve', 'number' => 50]);

// =========================================================================
// SEO STRUCTURED DATA (JSON-LD)
// =========================================================================
$schema_data = [
    "@context" => "https://schema.org",
    "@type" => "ComicSeries",
    "name" => $manga_title,
    "alternateName" => $alt_names,
    "description" => wp_strip_all_tags(get_the_content()),
    "author" => [
        "@type" => "Person",
        "name" => $author ?: "Unknown"
    ],
    "publisher" => [
        "@type" => "Organization",
        "name" => get_bloginfo('name')
    ],
    "image" => $cover,
    "genre" => $genres,
    "numberOfEpisodes" => $total_chapters,
];
if ($score) {
    $schema_data["aggregateRating"] = [
        "@type" => "AggregateRating",
        "ratingValue" => $score,
        "bestRating" => "100",
        "ratingCount" => "100"
    ];
}
echo '<script type="application/ld+json">' . wp_json_encode($schema_data) . '</script>';

// DYNAMIC BUTTON COLORS
$btn_bg = $is_bookmarked ? 'bg-[#ea580c]/10 text-[#ea580c] border-[#ea580c]' : 'bg-[#121212] border-[#333] text-gray-300 hover:text-white hover:border-[#ea580c]';
$btn_text = $is_bookmarked ? 'In Library' : 'Save Manga';
?>

<style>
    .chat-scroll::-webkit-scrollbar { width: 6px; }
    .chat-scroll::-webkit-scrollbar-track { background: transparent; }
    .chat-scroll::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
    .mention-highlight { color: #ea580c; font-weight: 900; background: rgba(234, 88, 12, 0.1); padding: 0 4px; border-radius: 4px; }
    .spoiler-block { background: #333; color: transparent; padding: 0 6px; border-radius: 4px; cursor: pointer; transition: all 0.2s; user-select: none; }
    .spoiler-block:hover, .spoiler-block:active { background: rgba(255,255,255,0.1); color: #fff; }
</style>

<!-- ADDED SAFE AREA PADDING (pt-20 md:pt-28) TO CLEAR THE FIXED HEADER -->
<article itemscope itemtype="https://schema.org/ComicSeries" class="relative min-h-screen bg-[#09090b] font-sans text-gray-200 overflow-hidden pb-24 pt-20 md:pt-28">

    <!-- 1. ZERO-GAP BACKGROUND BANNER -->
    <div class="absolute top-0 left-0 w-full h-[60vh] md:h-[500px] z-0 pointer-events-none opacity-40 md:opacity-30">
        <img src="<?php echo $cover; ?>" alt="Banner" class="w-full h-full object-cover blur-[30px] scale-110">
        <div class="absolute inset-0 bg-gradient-to-b from-[#09090b]/10 via-[#09090b]/70 to-[#09090b]"></div>
    </div>

    <!-- 2. MAIN MANGA INFO (Responsive Stacked/Row) -->
    <div class="relative z-10 max-w-[1000px] mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row gap-6 md:gap-10 items-center md:items-start">

        <!-- Poster / Cover Image -->
        <div class="w-[180px] sm:w-[200px] md:w-[250px] shrink-0 mt-2 md:mt-0">
            <div class="aspect-[2/3] rounded-2xl overflow-hidden shadow-[0_15px_40px_rgba(0,0,0,0.8)] border border-white/10 bg-[#121212] relative group">
                <img itemprop="image" src="<?php echo $cover; ?>" alt="<?php echo esc_attr($manga_title); ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" loading="eager" fetchpriority="high">
                <div class="absolute top-2 right-2 flex flex-col gap-1.5 items-end">
                    <span class="bg-black/80 backdrop-blur-md text-white text-[10px] font-black px-2.5 py-1 rounded-md uppercase tracking-wider shadow-sm border border-white/10"><?php echo esc_html($manga_type); ?></span>
                    <?php if($is_18) : ?><span class="bg-red-600/90 backdrop-blur-md text-white text-[10px] font-black px-2.5 py-1 rounded-md uppercase tracking-wider shadow-sm border border-white/10">18+</span><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Meta Information -->
        <div class="flex-1 flex flex-col w-full text-center md:text-left mt-2 md:mt-6">

            <h1 itemprop="name" class="text-3xl md:text-4xl lg:text-5xl font-extrabold text-white tracking-tight leading-tight mb-2 drop-shadow-md">
                <?php echo $manga_title; ?>
            </h1>

            <?php if ($alt_names): ?>
                <p class="text-[#ea580c] font-medium text-[13px] md:text-[14px] mb-4 italic leading-relaxed opacity-90 line-clamp-2 md:line-clamp-none">
                    <?php echo esc_html($alt_names); ?>
                </p>
            <?php endif; ?>

            <!-- Sleek Stats Bar + New Share Quick Action -->
            <div class="flex flex-wrap items-center justify-center md:justify-start gap-2.5 mb-6">
                <div class="flex items-center gap-1.5 bg-[#121212] border border-white/5 px-3 py-1.5 rounded-full shadow-sm">
                    <span class="w-1.5 h-1.5 rounded-full <?php echo (strtolower($manga_status) == 'completed') ? 'bg-blue-500' : 'bg-[#22c55e] animate-pulse'; ?>"></span>
                    <span class="text-[11px] font-bold text-gray-300 uppercase tracking-widest"><?php echo esc_html($manga_status); ?></span>
                </div>

                <div class="flex items-center bg-[#121212] border border-white/5 px-3 py-1.5 rounded-full shadow-sm">
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-widest"><?php echo $total_chapters; ?> Chapters</span>
                </div>

                <?php if ($score): ?>
                <div class="flex items-center gap-1 bg-[#121212] border border-[#ea580c]/30 px-3 py-1.5 rounded-full shadow-sm">
                    <svg class="w-3.5 h-3.5 text-[#ea580c]" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <span class="text-[11px] font-bold text-gray-200"><?php echo esc_html($score); ?></span>
                </div>
                <?php endif; ?>

                <button onclick="shareManga()" id="share-btn" class="flex items-center gap-1 bg-[#121212] border border-white/5 hover:border-[#ea580c] px-3 py-1.5 rounded-full shadow-sm transition text-gray-400 hover:text-white cursor-pointer" title="Copy Link">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    <span id="share-text" class="text-[11px] font-bold uppercase tracking-widest">Share</span>
                </button>
            </div>

            <!-- Authors Block (Centered Pill Style on Mobile) -->
            <?php if ($author || $artist): ?>
            <div class="w-full flex justify-center md:justify-start mb-6">
                <div class="bg-[#121212] border border-white/5 py-3 px-6 rounded-2xl w-full max-w-[400px] md:max-w-none md:w-auto shadow-sm">
                    <div class="text-[9px] uppercase tracking-widest text-gray-500 font-black mb-1">Author / Story</div>
                    <div itemprop="author" class="text-gray-200 font-bold text-[14px]">
                        <?php echo esc_html($author); ?>
                        <?php if($artist && $artist !== $author) echo ' <span class="text-gray-500 font-normal px-1">|</span> ' . esc_html($artist); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Dynamic Reading Progress Bar -->
            <div id="dynamic-progress-container" class="w-full max-w-[400px] md:max-w-[500px] mx-auto md:mx-0 mb-5" style="display: <?php echo ($total_chapters > 0) ? 'block' : 'none'; ?>">
                <div class="flex justify-between items-end mb-2">
                    <span class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Reading Progress</span>
                    <span class="text-[11px] font-bold text-[#ea580c]"><span id="dynamic-chapters-read"><?php echo $chapters_read_count; ?></span> / <?php echo $total_chapters; ?></span>
                </div>
                <div class="w-full h-1.5 bg-[#121212] rounded-full overflow-hidden shadow-inner border border-[#222]">
                    <div id="dynamic-progress-bar" class="h-full bg-[#ea580c] rounded-full transition-all duration-1000 ease-out" style="width: <?php echo $progress_percentage; ?>%"></div>
                </div>
            </div>

            <!-- Call To Actions (Stacked cleanly on mobile) -->
            <div class="flex flex-col sm:flex-row gap-3 w-full max-w-[400px] md:max-w-[500px] mx-auto md:mx-0">
                <?php if ($chapters->have_posts()) {
                    $ch_array = $chapters->posts;
                    $first_chap = end($ch_array);
                    $default_url = get_permalink($first_chap->ID);
                ?>
                    <a href="<?php echo !empty($continue_reading_url) ? $continue_reading_url : $default_url; ?>" id="dynamic-resume-btn" data-default-url="<?php echo $default_url; ?>" class="flex-1 bg-[#ea580c] hover:bg-[#c2410c] text-white text-center py-4 rounded-2xl font-black text-[13px] uppercase tracking-widest transition-all shadow-md flex flex-col justify-center leading-tight">
                        <span id="dynamic-resume-text"><?php echo $continue_reading_text; ?></span>
                    </a>
                <?php } ?>

                <?php if ($is_logged_in) { ?>
                    <button onclick="window.openLibraryModal()" class="flex-1 flex items-center justify-center gap-2 <?php echo $btn_bg; ?> border py-4 rounded-2xl transition-all shadow-sm group">
                        <svg class="w-4 h-4 transition" fill="currentColor" viewBox="0 0 24 24"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                        <span class="text-[12px] font-black uppercase tracking-widest transition"><?php echo $btn_text; ?></span>
                    </button>
                <?php } else { ?>
                    <a href="<?php echo esc_url(home_url('/auth')); ?>" class="flex-1 flex items-center justify-center gap-2 bg-[#121212] border border-[#333] hover:border-mv-accent text-gray-400 hover:text-white py-4 rounded-2xl transition-all shadow-sm group">
                        <svg class="w-4 h-4 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                        <span class="text-[12px] font-black uppercase tracking-widest transition">Save Manga</span>
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- 3. BODY CONTENT (Genres, Synopsis, Chapters, Chat) -->
    <div class="relative z-10 max-w-[1000px] mx-auto px-4 sm:px-6 lg:px-8 mt-12 grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-8">

            <!-- Genres (NOW CLICKABLE LINKS) -->
            <?php if (!empty($genres)) { ?>
                <section aria-label="Genres">
                    <div class="flex flex-wrap justify-center md:justify-start gap-2">
                        <?php foreach($genres as $g) { ?>
                            <a href="<?php echo home_url('/browse/?genre=' . urlencode($g)); ?>" itemprop="genre" class="bg-[#121212] text-gray-400 border border-[#222] px-3.5 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-wider hover:text-white hover:border-[#ea580c] transition cursor-pointer">
                                <?php echo esc_html($g); ?>
                            </a>
                        <?php } ?>
                    </div>
                </section>
            <?php } ?>

            <!-- Expandable Synopsis -->
            <section class="bg-[#121212] border border-[#222] rounded-3xl p-6 md:p-8 shadow-xl relative">
                <h2 class="text-[14px] font-black text-white mb-4 uppercase tracking-widest flex items-center gap-2">
                    <span class="w-1.5 h-5 bg-[#ea580c] rounded-full"></span> Synopsis
                </h2>
                <div id="manga-synopsis" itemprop="description" class="text-[14px] md:text-[15px] text-gray-300 leading-relaxed font-medium line-clamp-4 transition-all">
                    <?php the_content(); ?>
                </div>
                <button onclick="toggleSynopsis()" id="synopsis-btn" class="mt-4 text-[#ea580c] text-[11px] font-black uppercase tracking-widest hover:text-[#c2410c] transition w-full text-center md:text-left">Read More</button>
            </section>

            <!-- Chapter List -->
            <section>
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-[#222]">
                    <h2 class="text-[16px] font-black text-white tracking-tight uppercase flex items-center gap-2">
                        <span class="w-1.5 h-6 bg-[#ea580c] rounded-full"></span> Chapters
                    </h2>
                    <button onclick="window.sortChapters()" id="sort-btn" class="bg-[#121212] border border-[#333] hover:border-[#ea580c] px-3 py-1.5 rounded-lg text-[10px] font-black text-gray-300 uppercase tracking-widest transition flex items-center gap-1.5 shadow-sm group">
                        <svg class="w-3.5 h-3.5 text-gray-500 group-hover:text-[#ea580c] transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
                        <span>Latest</span>
                    </button>
                </div>

                <div class="bg-[#121212] border border-[#222] rounded-3xl shadow-xl overflow-hidden">
                    <?php if (!$chapters->have_posts()) { ?>
                        <div class="p-8 text-center text-gray-500 font-bold uppercase tracking-widest text-[11px]">No chapters found.</div>
                    <?php } else { ?>
                        <div id="chapter-list" class="flex flex-col max-h-[500px] overflow-y-auto chat-scroll">
                            <?php
                            while ($chapters->have_posts()) : $chapters->the_post();
                                $chap_id = get_the_ID();
                                $chap_num = (float) mvx_chapter_number($chap_id);
                                $post_date = get_the_time('U');
                                $is_new = (current_time('timestamp') - $post_date) < (3 * 24 * 60 * 60);

                                $has_read_class = '';
                                if ($is_logged_in && !empty($read_chapter_nums) && $chap_num <= max($read_chapter_nums)) {
                                    $has_read_class = 'opacity-50 struck-through';
                                }
                            ?>
                            <a href="<?php echo get_permalink(); ?>" data-chap-num="<?php echo $chap_num; ?>" class="chapter-row flex items-center justify-between p-4 px-6 border-b border-[#1a1a1a] last:border-0 hover:bg-[#1a1a1a] hover:pl-8 transition-all duration-200 group <?php echo $has_read_class; ?>">
                                <div class="flex items-center gap-3">
                                    <span class="font-black chap-title <?php echo !empty($has_read_class) ? 'text-gray-600 line-through' : 'text-gray-200 group-hover:text-[#ea580c]'; ?> transition text-[14px] tracking-tight">
                                        Chapter <?php echo $chap_num; ?>
                                    </span>
                                    <?php if($is_new) { ?>
                                        <span class="chap-new-tag bg-[#ea580c]/20 text-[#ea580c] text-[9px] font-black px-2 py-0.5 rounded shadow-sm <?php echo !empty($has_read_class) ? 'hidden' : ''; ?>">NEW</span>
                                    <?php } ?>
                                </div>
                                <span class="text-[11px] text-gray-500 font-medium tracking-wide">
                                    <?php echo human_time_diff($post_date, current_time('timestamp')); ?> ago
                                </span>
                            </a>
                            <?php endwhile; wp_reset_postdata(); ?>
                        </div>
                    <?php } ?>
                </div>
            </section>
        </div>

        <!-- Right Column (Discussion) -->
        <aside class="flex flex-col h-[500px] lg:h-auto lg:min-h-[700px] bg-[#121212] border border-[#222] rounded-3xl shadow-xl overflow-hidden">
            <div class="p-5 border-b border-[#222] bg-[#1a1a1a] shrink-0 flex items-center justify-between">
                <h2 class="text-[14px] font-black text-white tracking-tight uppercase flex items-center gap-2">
                    <span class="w-1.5 h-5 bg-[#3b82f6] rounded-full"></span> Comments
                </h2>
                <a href="<?php echo esc_url(home_url('/community')); ?>" class="text-[10px] font-bold uppercase text-gray-500 hover:text-[#3b82f6] transition flex items-center gap-1">Global <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg></a>
            </div>

            <div class="flex-1 overflow-y-auto chat-scroll p-5 space-y-3 flex flex-col-reverse relative bg-[#09090b]" id="chat-feed-container">
                <?php if (empty($manga_chats)) { ?>
                    <div class="text-center py-10"><span class="text-gray-500 font-medium text-[12px]">Be the first to discuss!</span></div>
                <?php } else { foreach($manga_chats as $chat) { xcomix_manga_chat_render($chat, $is_logged_in, $current_user); } } ?>
            </div>

            <div class="bg-[#09090b] border-t border-[#222] shrink-0 flex flex-col relative z-20">
                <?php if ($is_logged_in) { ?>
                <div class="flex gap-3 px-4 py-2.5 bg-[#121212] border-b border-[#222] overflow-x-auto flex-nowrap no-scrollbar items-center">
                    <button type="button" onclick="toggleGifPicker()" class="text-gray-400 hover:text-[#3b82f6] font-black text-[10px] uppercase tracking-wider shrink-0 transition bg-[#1a1a1a] px-2.5 py-1 rounded border border-[#333]">GIF</button>
                    <div class="w-px h-4 bg-[#333] mx-1 shrink-0"></div>
                    <button onclick="insertText('😂')" class="hover:scale-125 transition shrink-0 text-base">😂</button>
                    <button onclick="insertText('🔥')" class="hover:scale-125 transition shrink-0 text-base">🔥</button>
                    <button onclick="insertText('👀')" class="hover:scale-125 transition shrink-0 text-base">👀</button>
                    <button onclick="insertText('💀')" class="hover:scale-125 transition shrink-0 text-base">💀</button>
                    <div class="w-px h-4 bg-[#333] mx-1 shrink-0"></div>
                    <button type="button" onclick="insertText('||spoiler||')" class="text-gray-400 hover:text-white font-bold text-[10px] uppercase tracking-wider shrink-0 transition">Spoiler</button>
                </div>
                <div id="gif-picker" class="hidden bg-[#09090b] border-b border-[#222] p-2 shrink-0">
                    <div class="flex overflow-x-auto gap-2 pb-1 snap-x no-scrollbar">
                        <img src="https://i.imgur.com/YwOaaPz.gif" alt="GIF" class="w-16 md:w-20 h-16 md:h-20 object-cover rounded shrink-0 snap-center cursor-pointer border border-[#333] hover:border-[#3b82f6]" onclick="selectGif(this.src)">
                        <img src="https://i.imgur.com/Z4Ond85.gif" alt="GIF" class="w-16 md:w-20 h-16 md:h-20 object-cover rounded shrink-0 snap-center cursor-pointer border border-[#333] hover:border-[#3b82f6]" onclick="selectGif(this.src)">
                    </div>
                </div>

                <div id="reply-preview" class="hidden px-5 pt-3 pb-1 flex justify-between items-center text-[11px] text-gray-400 font-bold bg-[#09090b]">
                    <span>Replying to <span id="reply-username" class="text-[#3b82f6]"></span></span>
                    <button type="button" onclick="cancelReply()" class="hover:text-white bg-[#1a1a1a] px-2 py-0.5 rounded">Cancel</button>
                </div>
                <div id="upload-preview" class="hidden px-5 pt-3 relative inline-block">
                    <div class="relative inline-block border-2 border-dashed border-[#3b82f6] rounded-lg p-1 bg-[#1a1a1a]">
                        <img id="upload-preview-img" src="" class="h-16 rounded object-cover">
                        <button type="button" onclick="removeUpload()" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-[10px] shadow-lg font-bold border-2 border-[#121212]">X</button>
                    </div>
                </div>
                <form id="ajax-chat-form" class="relative flex items-end bg-[#1a1a1a] m-3 border border-[#333] rounded-2xl overflow-hidden focus-within:border-[#3b82f6] transition shadow-inner">
                    <input type="file" id="chat-image-upload" accept="image/*" class="hidden" onchange="uploadChatImage(this)">
                    <input type="hidden" id="hidden_image_url" value="">
                    <input type="hidden" id="chat_comment_post_ID" value="<?php echo $manga_id; ?>" />
                    <input type="hidden" id="chat_comment_parent" value="0" />

                    <label for="chat-image-upload" id="upload-btn-label" class="cursor-pointer m-1.5 w-10 h-10 rounded-xl bg-[#222] hover:bg-[#333] text-gray-400 hover:text-[#3b82f6] flex items-center justify-center shrink-0 transition" title="Attach Image">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </label>

                    <textarea id="chat-input-box" rows="1" placeholder="Join the discussion..." class="w-full bg-transparent text-white text-[14px] py-3 pr-3 focus:outline-none resize-none no-scrollbar max-h-24 font-medium" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>

                    <button type="submit" id="chat-submit-btn" class="m-1.5 w-10 h-10 rounded-xl bg-[#3b82f6] hover:bg-[#2563eb] text-white flex items-center justify-center shrink-0 transition shadow-md">
                        <svg class="w-5 h-5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
                </form>
                <?php } else { ?>
                    <div class="m-4 bg-[#1a1a1a] border border-[#333] rounded-2xl p-4 flex items-center justify-between shadow-inner">
                        <span class="text-gray-400 text-[12px] font-medium">Log in to comment.</span>
                        <a href="<?php echo esc_url(home_url('/auth')); ?>" class="bg-[#3b82f6] text-white px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest shadow-md hover:bg-[#2563eb] transition">Sign In</a>
                    </div>
                <?php } ?>
            </div>
        </aside>
    </div>

    <!-- 4. RELATED MANGA -->
    <?php if ($related->have_posts()) : ?>
    <section class="relative z-10 max-w-[1000px] mx-auto px-4 sm:px-6 lg:px-8 mt-12">
        <h2 class="text-[18px] font-black text-white tracking-tight uppercase flex items-center gap-2 mb-6">
            <span class="w-1.5 h-6 bg-[#eab308] rounded-full"></span> Similar Series
        </h2>
        <div class="flex overflow-x-auto gap-5 pb-4 snap-x no-scrollbar">
            <?php while ($related->have_posts()) : $related->the_post();
                $rel_id = get_the_ID();
                $rel_type = mvx_manga_meta($rel_id, 'type', 'Manga');
                $rel_status = mvx_manga_meta($rel_id, 'status', 'Ongoing');
                $rel_is_18 = mvx_manga_meta($rel_id, 'is_18_plus');
            ?>
            <a href="<?php echo get_permalink(); ?>" class="w-[130px] md:w-[150px] shrink-0 snap-start block group">
                <div class="relative aspect-[2/3] block overflow-hidden rounded-[16px] mb-3 bg-[#121212] border border-white/5 shadow-lg">
                    <img src="<?php echo mv_get_cover($rel_id, 'medium'); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500" loading="lazy" decoding="async">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition duration-300"></div>

                    <div class="absolute top-2 right-2 flex flex-col gap-1.5 items-end">
                        <span class="bg-black/70 backdrop-blur-md text-white text-[9px] font-black px-2 py-1 rounded-md uppercase tracking-widest"><?php echo esc_html($rel_type); ?></span>
                        <?php if($rel_is_18) : ?>
                            <span class="bg-red-600/90 text-white text-[9px] font-black px-2 py-1 rounded-md uppercase tracking-widest shadow-md">18+</span>
                        <?php endif; ?>
                    </div>
                </div>
                <h3 class="text-[14px] font-bold text-gray-200 line-clamp-2 leading-tight group-hover:text-[#ea580c] transition tracking-tight"><?php the_title(); ?></h3>
            </a>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- MODALS & SCRIPTS -->
    <?php if ($is_logged_in) { ?>
    <div id="library-modal" class="fixed inset-0 z-[99999] bg-black/80 backdrop-blur-md hidden flex-col items-center justify-center p-4 transition-opacity">
        <div class="bg-[#121212] border border-[#333] w-full max-w-[400px] rounded-3xl overflow-hidden shadow-2xl relative flex flex-col max-h-[80vh]">

            <div class="flex border-b border-[#222] shrink-0 bg-[#09090b]">
                <button onclick="window.switchLibTab('plans')" id="tab-plans" class="flex-1 py-4 text-[12px] font-black uppercase tracking-widest text-white border-b-2 border-[#ea580c] transition">Status</button>
                <button onclick="window.switchLibTab('folders')" id="tab-folders" class="flex-1 py-4 text-[12px] font-black uppercase tracking-widest text-gray-500 border-b-2 border-transparent hover:text-white transition">Folders</button>
            </div>

            <div id="tab-content-plans" class="p-6 space-y-2 overflow-y-auto no-scrollbar flex-1">
                <?php foreach($base_plan_labels as $val => $label): ?>
                    <button onclick="window.selectStatus('<?php echo esc_attr($val); ?>', this)" class="status-btn w-full text-left px-5 py-4 rounded-xl <?php echo ($current_status == $val) ? 'bg-[#ea580c] text-white shadow-lg shadow-orange-500/20' : 'bg-[#1a1a1a] text-gray-400 hover:bg-[#222] hover:text-white'; ?> text-[13px] font-bold transition flex justify-between items-center border border-white/5">
                        <?php echo esc_html($label); ?>
                        <?php if($current_status == $val): ?><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg><?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div id="tab-content-folders" class="p-6 space-y-2 hidden flex-col flex-1 overflow-hidden">
                <div id="folder-list-container" class="space-y-2 overflow-y-auto no-scrollbar pb-2 flex-1">
                    <button onclick="window.selectFolder('none', this)" class="folder-btn w-full text-left px-5 py-4 rounded-xl <?php echo ($current_folder == 'none') ? 'bg-[#a855f7] text-white shadow-lg shadow-purple-500/20' : 'bg-[#1a1a1a] text-gray-400 hover:bg-[#222] hover:text-white'; ?> text-[13px] font-bold transition flex justify-between items-center border border-white/5">
                        No Folder
                        <?php if($current_folder == 'none'): ?><svg class="w-5 h-5 shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg><?php endif; ?>
                    </button>
                    <?php foreach($custom_folders as $fid => $fname): ?>
                        <button onclick="window.selectFolder('<?php echo esc_attr($fid); ?>', this)" class="folder-btn w-full text-left px-5 py-4 rounded-xl <?php echo ($current_folder == $fid) ? 'bg-[#a855f7] text-white shadow-lg shadow-purple-500/20' : 'bg-[#1a1a1a] text-gray-400 hover:bg-[#222] hover:text-white'; ?> text-[13px] font-bold transition flex justify-between items-center truncate border border-white/5">
                            <?php echo esc_html($fname); ?>
                            <?php if($current_folder == $fid): ?><svg class="w-5 h-5 shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg><?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                    <div id="new-folder-creator" class="pt-4 border-t border-[#333] mt-4">
                        <input type="text" id="new-folder-name" placeholder="Create new folder..." class="w-full bg-[#09090b] border border-[#333] rounded-xl px-5 py-3.5 text-white text-[13px] mb-2 focus:border-[#a855f7] outline-none transition shadow-inner">
                        <button onclick="window.createNewFolderModal(this)" class="w-full bg-gradient-to-r from-[#a855f7] to-[#9333ea] hover:from-[#9333ea] hover:to-[#a855f7] text-white py-3.5 rounded-xl text-[12px] font-black uppercase tracking-widest transition shadow-md">Create Folder</button>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 p-5 border-t border-[#222] bg-[#09090b] shrink-0">
                <button onclick="window.closeLibraryModal()" class="flex-1 py-3.5 text-gray-400 hover:text-white bg-[#1a1a1a] hover:bg-[#222] rounded-xl text-[12px] font-black uppercase tracking-widest transition border border-[#333]">Close</button>
                <button onclick="window.saveLibrarySettings(this)" class="flex-1 py-3.5 text-white bg-gradient-to-r from-[#ea580c] to-[#f97316] hover:from-[#c2410c] hover:to-[#ea580c] rounded-xl text-[12px] font-black uppercase tracking-widest transition shadow-[0_4px_15px_rgba(234,88,12,0.3)] hover:-translate-y-0.5">Save</button>
            </div>
        </div>
    </div>
    <?php } ?>

    <div id="image-lightbox" onclick="closeLightbox()" class="fixed inset-0 bg-black/95 z-[99999] hidden flex items-center justify-center cursor-zoom-out p-4 backdrop-blur-md transition-opacity">
        <img id="lightbox-img" src="" alt="Zoomed Image" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl border border-white/10">
    </div>

    <!-- Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let mangaId = <?php echo $manga_id; ?>; let totalChaps = <?php echo $total_chapters; ?>; let isLogged = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
            if (!isLogged && totalChaps > 0) {
                let localHistory = JSON.parse(localStorage.getItem('xcomix_guest_history')) || {};
                if (localHistory[mangaId]) {
                    let lastChapNum = parseFloat(localHistory[mangaId].chapter_num); let resumeUrl = localHistory[mangaId].url;
                    let percent = (lastChapNum / totalChaps) * 100; if(percent > 100) percent = 100;
                    document.getElementById('dynamic-progress-container').style.display = 'block'; document.getElementById('dynamic-chapters-read').innerText = lastChapNum; document.getElementById('dynamic-progress-bar').style.width = percent + '%';
                    let resumeBtn = document.getElementById('dynamic-resume-btn');
                    if (resumeBtn) { resumeBtn.href = resumeUrl; document.getElementById('dynamic-resume-text').innerText = 'Resume Ch. ' + lastChapNum; }
                    document.querySelectorAll('.chapter-row').forEach(row => {
                        let cNum = parseFloat(row.getAttribute('data-chap-num'));
                        if (cNum <= lastChapNum) {
                            row.classList.add('opacity-50', 'struck-through');
                            let title = row.querySelector('.chap-title'); title.classList.remove('text-gray-200', 'group-hover:text-[#ea580c]'); title.classList.add('text-gray-600', 'line-through');
                            let newTag = row.querySelector('.chap-new-tag'); if(newTag) newTag.classList.add('hidden');
                        }
                    });
                }
            }
        });

        window.shareManga = function() {
            navigator.clipboard.writeText(window.location.href);
            let btn = document.getElementById('share-btn');
            let text = document.getElementById('share-text');
            btn.classList.add('text-[#ea580c]', 'border-[#ea580c]');
            text.innerText = 'Copied!';
            setTimeout(() => {
                btn.classList.remove('text-[#ea580c]', 'border-[#ea580c]');
                text.innerText = 'Share';
            }, 2000);
        };

        window.toggleSynopsis = function() {
            let syn = document.getElementById('manga-synopsis');
            let btn = document.getElementById('synopsis-btn');
            if(syn.classList.contains('line-clamp-4')) {
                syn.classList.remove('line-clamp-4');
                btn.innerText = 'Show Less';
            } else {
                syn.classList.add('line-clamp-4');
                btn.innerText = 'Read More';
            }
        };

        window.sortChapters = function() {
            const list = document.getElementById('chapter-list'); const btn = document.getElementById('sort-btn');
            if (!list || !btn) return;
            if (list.style.flexDirection === 'column-reverse') {
                list.style.flexDirection = 'column'; btn.innerHTML = `<svg class="w-3.5 h-3.5 text-gray-500 group-hover:text-[#ea580c] transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg> <span>Latest</span>`;
            } else {
                list.style.flexDirection = 'column-reverse'; btn.innerHTML = `<svg class="w-3.5 h-3.5 text-gray-500 group-hover:text-[#ea580c] transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg> <span>Oldest</span>`;
            }
        };

        let pendingStatus = '<?php echo $current_status; ?>'; let pendingFolder = '<?php echo $current_folder; ?>';
        window.openLibraryModal = () => { document.getElementById('library-modal').classList.remove('hidden'); document.getElementById('library-modal').classList.add('flex'); };
        window.closeLibraryModal = () => { document.getElementById('library-modal').classList.add('hidden'); document.getElementById('library-modal').classList.remove('flex'); };
        window.switchLibTab = (tab) => {
            document.getElementById('tab-content-plans').classList.toggle('hidden', tab !== 'plans'); document.getElementById('tab-content-folders').classList.toggle('hidden', tab !== 'folders'); document.getElementById('tab-content-folders').classList.toggle('flex', tab === 'folders');
            let btnPlans = document.getElementById('tab-plans'); let btnFolders = document.getElementById('tab-folders');
            if (tab === 'plans') { btnPlans.className = 'flex-1 py-4 text-[12px] font-black uppercase tracking-widest text-white border-b-2 border-[#ea580c] transition'; btnFolders.className = 'flex-1 py-4 text-[12px] font-black uppercase tracking-widest text-gray-500 border-b-2 border-transparent hover:text-white transition'; }
            else { btnFolders.className = 'flex-1 py-4 text-[12px] font-black uppercase tracking-widest text-white border-b-2 border-[#a855f7] transition'; btnPlans.className = 'flex-1 py-4 text-[12px] font-black uppercase tracking-widest text-gray-500 border-b-2 border-transparent hover:text-white transition'; }
        };
        window.selectStatus = function(status, el) {
            pendingStatus = status; document.querySelectorAll('.status-btn').forEach(btn => { btn.className = 'status-btn w-full text-left px-5 py-4 rounded-xl bg-[#1a1a1a] text-gray-400 hover:bg-[#222] hover:text-white text-[13px] font-bold transition flex justify-between items-center border border-white/5'; let svg = btn.querySelector('svg'); if (svg) svg.remove(); });
            el.className = 'status-btn w-full text-left px-5 py-4 rounded-xl bg-[#ea580c] text-white shadow-lg shadow-orange-500/20 text-[13px] font-bold transition flex justify-between items-center border border-white/5'; el.innerHTML += `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>`;
        };
        window.selectFolder = function(folderId, el) {
            pendingFolder = folderId; document.querySelectorAll('.folder-btn').forEach(btn => { btn.className = 'folder-btn w-full text-left px-5 py-4 rounded-xl bg-[#1a1a1a] text-gray-400 hover:bg-[#222] hover:text-white text-[13px] font-bold transition flex justify-between items-center truncate border border-white/5'; let svg = btn.querySelector('svg'); if (svg) svg.remove(); });
            el.className = 'folder-btn w-full text-left px-5 py-4 rounded-xl bg-[#a855f7] text-white shadow-lg shadow-purple-500/20 text-[13px] font-bold transition flex justify-between items-center truncate border border-white/5'; el.innerHTML += `<svg class="w-5 h-5 shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>`;
        };
        window.createNewFolderModal = async function(btn) {
            let input = document.getElementById('new-folder-name'); let name = input.value.trim(); if(!name) return;
            let originalText = btn.innerText; btn.innerText = 'Creating...'; btn.disabled = true;
            let fd = new FormData(); fd.append('xcomix_folder_action', 'add'); fd.append('folder_name', name);
            try {
                let res = await fetch(window.location.href, { method: 'POST', body: fd }); let data = await res.json();
                if(data.success) {
                    input.value = ''; let container = document.getElementById('folder-list-container');
                    let newBtn = document.createElement('button'); newBtn.className = 'folder-btn w-full text-left px-5 py-4 rounded-xl bg-[#1a1a1a] text-gray-400 hover:bg-[#222] hover:text-white text-[13px] font-bold transition flex justify-between items-center truncate border border-white/5'; newBtn.innerText = data.folder_name; newBtn.onclick = function() { window.selectFolder(data.folder_id, this); };
                    container.insertBefore(newBtn, document.getElementById('new-folder-creator')); window.selectFolder(data.folder_id, newBtn);
                }
            } catch(e) {} btn.innerText = originalText; btn.disabled = false;
        };
        window.saveLibrarySettings = async function(btn) {
            btn.innerText = 'Saving...'; btn.disabled = true; let fd = new FormData(); fd.append('xcomix_save_library', '1'); fd.append('plan_status', pendingStatus); fd.append('folder_id', pendingFolder); await fetch(window.location.href, { method: 'POST', body: fd }); location.reload();
        };

        function triggerReply(username, parentId) { document.getElementById('reply-preview').classList.remove('hidden'); document.getElementById('reply-username').innerText = '@' + username; document.getElementById('chat_comment_parent').value = parentId; document.getElementById('chat-input-box').focus(); }
        function cancelReply() { document.getElementById('reply-preview').classList.add('hidden'); document.getElementById('chat_comment_parent').value = 0; }
        function insertText(text) { const inputBox = document.getElementById('chat-input-box'); if (inputBox) { inputBox.value += text + " "; inputBox.focus(); inputBox.style.height = inputBox.scrollHeight + 'px'; } }
        function toggleGifPicker() { document.getElementById('gif-picker').classList.toggle('hidden'); }
        function selectGif(url) { document.getElementById('hidden_image_url').value = url; document.getElementById('upload-preview-img').src = url; document.getElementById('upload-preview').classList.remove('hidden'); toggleGifPicker(); document.getElementById('chat-input-box').removeAttribute('required'); }
        function openLightbox(url) { document.getElementById('lightbox-img').src = url; document.getElementById('image-lightbox').classList.remove('hidden'); }
        function closeLightbox() { document.getElementById('image-lightbox').classList.add('hidden'); document.getElementById('lightbox-img').src = ''; }
        async function uploadChatImage(inputElement) {
            if (!inputElement.files || inputElement.files.length === 0) return;
            let formData = new FormData(); formData.append('chat_image', inputElement.files[0]); let btnLabel = document.getElementById('upload-btn-label'); let originalIcon = btnLabel.innerHTML; btnLabel.innerHTML = `<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>`;
            try { let res = await fetch(window.location.href, { method: 'POST', body: formData }); let data = await res.json(); if (data.success) { document.getElementById('hidden_image_url').value = data.url; document.getElementById('upload-preview-img').src = data.url; document.getElementById('upload-preview').classList.remove('hidden'); document.getElementById('chat-input-box').removeAttribute('required'); } } catch(e) { } btnLabel.innerHTML = originalIcon; inputElement.value = "";
        }
        function removeUpload() { document.getElementById('hidden_image_url').value = ''; document.getElementById('upload-preview').classList.add('hidden'); document.getElementById('chat-input-box').setAttribute('required', 'required'); }
        document.getElementById('ajax-chat-form')?.addEventListener('submit', async function(e) {
            e.preventDefault(); let btn = document.getElementById('chat-submit-btn'); let inputBox = document.getElementById('chat-input-box'); let textValue = inputBox.value; let hiddenImg = document.getElementById('hidden_image_url').value; let postId = document.getElementById('chat_comment_post_ID').value; let parentId = document.getElementById('chat_comment_parent').value;
            if (textValue.trim() === '' && hiddenImg === '') return;
            let finalContent = textValue; if (hiddenImg !== '') finalContent += "\n" + hiddenImg; let replyingTo = document.getElementById('reply-username').innerText; if (parentId !== '0' && replyingTo !== '' && !finalContent.includes(replyingTo)) finalContent = replyingTo + " " + finalContent;
            btn.classList.add('opacity-50', 'pointer-events-none'); let formData = new FormData(); formData.append('custom_chat_submit', '1'); formData.append('comment_content', finalContent); formData.append('comment_post_ID', postId); formData.append('comment_parent', parentId);
            inputBox.value = ''; inputBox.style.height = 'auto'; removeUpload(); cancelReply(); document.getElementById('gif-picker').classList.add('hidden');
            await fetch(window.location.href, { method: 'POST', body: formData }); btn.classList.remove('opacity-50', 'pointer-events-none'); fetchLiveChats();
        });
        async function deleteChat(commentId) { if(!confirm("Delete message?")) return; let formData = new FormData(); formData.append('delete_chat_id', commentId); await fetch(window.location.href, { method: 'POST', body: formData }); fetchLiveChats(); }
        async function likeChat(commentId) { let formData = new FormData(); formData.append('like_chat_id', commentId); let res = await fetch(window.location.href, { method: 'POST', body: formData }); let countSpan = document.getElementById('like-count-' + commentId); if(countSpan) countSpan.innerText = await res.text(); countSpan.previousElementSibling.classList.add('text-pink-500'); }
        async function fetchLiveChats() {
            let chatContainer = document.getElementById('chat-feed-container'); if(!chatContainer) return;
            let isScrolledBottom = Math.abs(chatContainer.scrollTop) < 50; let formData = new FormData(); formData.append('fetch_live_chats', '1'); formData.append('manga_id', <?php echo $manga_id; ?>);
            try { let res = await fetch(window.location.href, { method: 'POST', body: formData }); chatContainer.innerHTML = await res.text(); if(isScrolledBottom) chatContainer.scrollTop = 0; } catch(e) {}
        }
        setInterval(fetchLiveChats, 15000);
        document.getElementById('chat-input-box')?.addEventListener('keypress', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); document.getElementById('ajax-chat-form').dispatchEvent(new Event('submit')); } });
    </script>
</article>

<?php get_footer(); ?>
