<?php
/**
 * X COMIX - MASTER ROUTER & MGEKO ENGINE (Client-Side Distributed Scraper Build)
 */

$current_post_id = get_queried_object_id();
$mgeko_url = trim(get_post_meta($current_post_id, '_mgeko_url', true));
if (!empty($mgeko_url) && strpos($mgeko_url, 'http') !== 0) {
    $mgeko_url = 'https://' . ltrim($mgeko_url, '/');
}
$chapter_num = mvx_chapter_number($current_post_id);
$manga_id = wp_get_post_parent_id($current_post_id);
$manga_title = get_the_title($manga_id);

// =========================================================================
// PROGRESS TRACKING LOGIC
// =========================================================================
if (is_user_logged_in() && $manga_id) {
    $current_user = wp_get_current_user();
    $raw_history = get_user_meta($current_user->ID, '_xcomix_history', true);
    if (!is_array($raw_history)) { $raw_history = []; }

    $raw_history[$manga_id] = [
        'id' => $manga_id,
        'chapter_num' => $chapter_num,
        'url' => get_permalink($current_post_id),
        'timestamp' => time()
    ];
    update_user_meta($current_user->ID, '_xcomix_history', $raw_history);
}
?>
<script>
    // Guest Tracking
    document.addEventListener("DOMContentLoaded", function() {
        let isLogged = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
        if (!isLogged) {
            let mId = "<?php echo esc_js($manga_id); ?>";
            let cNum = "<?php echo esc_js($chapter_num); ?>";
            let cUrl = "<?php echo esc_js(get_permalink($current_post_id)); ?>";
            if (mId && cNum) {
                let history = JSON.parse(localStorage.getItem('xcomix_guest_history')) || {};
                history[mId] = { id: mId, chapter_num: cNum, url: cUrl, timestamp: Date.now() };
                localStorage.setItem('xcomix_guest_history', JSON.stringify(history));
            }
        }
    });
</script>
<?php
// =========================================================================
// MGEKO CHAT RENDER FUNCTION
// =========================================================================
if (!function_exists('xcomix_chapter_chat_render')) {
    function xcomix_chapter_chat_render($chat, $is_logged_in, $current_user) {
        $is_mine = ($is_logged_in && $chat->user_id == $current_user->ID);
        $author_name = $chat->comment_author;
        $author_avatar = get_user_meta($chat->user_id, '_xcomix_avatar', true) ?: 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($author_name);

        $user_obj = get_userdata($chat->user_id);
        $role_badge = ''; $name_color = 'text-gray-200';
        if ($user_obj) {
            if (in_array('administrator', $user_obj->roles)) {
                $role_badge = '<span class="bg-[#e8783a]/20 text-[#e8783a] text-[8px] font-black uppercase px-1.5 py-0.5 rounded ml-1">Admin</span>';
                $name_color = 'text-[#e8783a]';
            } elseif (in_array('author', $user_obj->roles) || in_array('editor', $user_obj->roles)) {
                $role_badge = '<span class="bg-[#3b82f6]/20 text-[#3b82f6] text-[8px] font-black uppercase px-1.5 py-0.5 rounded ml-1">Uploader</span>';
                $name_color = 'text-[#3b82f6]';
            }
        }

        $reply_html = '';
        if ($chat->comment_parent > 0) {
            $parent_comment = get_comment($chat->comment_parent);
            if ($parent_comment) {
                $p_author = esc_html($parent_comment->comment_author);
                $p_text = wp_trim_words(esc_html($parent_comment->comment_content), 8, '...');
                $reply_html = '<div class="flex items-center gap-1.5 text-[11px] text-gray-500 mb-1 pl-2 border-l-2 border-[#3a3a48] select-none"><svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg><span class="truncate">Replying to <b class="text-gray-400">@'.$p_author.'</b>: '.$p_text.'</span></div>';
            }
        }

        $raw_content = esc_html($chat->comment_content);
        if ($is_logged_in) {
            $raw_content = preg_replace('/(@' . preg_quote($current_user->display_name, '/') . ')/i', '<span class="mention-highlight">$1</span>', $raw_content);
        }
        $raw_content = preg_replace('/\*\*(.*?)\*\*/is', '<strong class="text-white">$1</strong>', $raw_content);
        $raw_content = preg_replace('/\_(.*?)\_/is', '<em class="text-gray-300 italic">$1</em>', $raw_content);
        $raw_content = preg_replace('/\|\|(.*?)\|\|/is', '<span class="spoiler-block" title="Tap to reveal">$1</span>', $raw_content);

        $raw_content = preg_replace('/(https?:\/\/[^\s"\'<>]+?\.(?:jpg|jpeg|png|gif|webp))/i', '|||IMG|||$1|||', $raw_content);
        $raw_content = make_clickable($raw_content);
        $raw_content = str_replace('<a href=', '<a class="text-[#3b82f6] hover:underline break-all" target="_blank" href=', $raw_content);
        $raw_content = preg_replace('/\|\|\|IMG\|\|\|(.*?)\|\|\|/i', '<br><img src="$1" onclick="window.openLightbox(\'$1\')" class="max-w-full md:max-w-[250px] max-h-[200px] w-auto h-auto rounded-xl mt-2 mb-1 border border-[#3a3a48] shadow-md hover:opacity-80 transition object-contain cursor-pointer">', $raw_content);

        $raw_content = nl2br($raw_content);
        $likes = (int) get_comment_meta($chat->comment_ID, '_chat_likes', true);
        $exact_time = date('M j, Y g:i A', strtotime($chat->comment_date));
        ?>
        <div class="flex gap-3 md:gap-4 group hover:bg-[#1a1a22] focus-within:bg-[#1a1a22] -mx-4 px-4 py-3 rounded-xl transition relative cursor-pointer md:cursor-default" tabindex="0" id="chat-msg-<?php echo $chat->comment_ID; ?>">
            <img src="<?php echo esc_url($author_avatar); ?>" class="w-10 h-10 rounded-full border border-white/5 bg-[#252530] shrink-0 mt-1 object-cover cursor-pointer" onclick="window.insertText('@<?php echo esc_js($author_name); ?>')">
            <div class="flex flex-col min-w-0 w-full pointer-events-none md:pointer-events-auto">
                <?php echo $reply_html; ?>
                <div class="flex items-baseline gap-2 mb-0.5">
                    <span class="text-[14px] font-bold <?php echo $name_color; ?>"><?php echo esc_html($author_name); ?></span>
                    <?php echo $role_badge; ?>
                    <span class="text-[10px] text-gray-500 font-medium cursor-help pointer-events-auto" title="<?php echo esc_attr($exact_time); ?>">
                        <?php echo human_time_diff(strtotime($chat->comment_date), current_time('timestamp')); ?> ago
                    </span>
                </div>

                <div class="text-[14px] text-gray-300 leading-relaxed font-medium break-words pointer-events-auto" id="chat-content-<?php echo $chat->comment_ID; ?>" data-raw="<?php echo esc_attr($chat->comment_content); ?>">
                    <?php echo $raw_content; ?>
                </div>

                <div class="mt-2 flex items-center gap-2 pointer-events-auto">
                    <button onclick="window.likeChat(<?php echo $chat->comment_ID; ?>)" class="flex items-center gap-1.5 bg-[#252530] border border-[#2a2a35] hover:border-pink-500/50 hover:bg-pink-500/10 px-2 py-1 rounded-md transition group/btn">
                        <svg class="w-3.5 h-3.5 text-gray-500 group-hover/btn:text-pink-500 transition <?php echo ($likes > 0) ? 'text-pink-500' : ''; ?>" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                        <span class="text-[10px] font-bold text-gray-400 group-hover/btn:text-pink-400" id="like-count-<?php echo $chat->comment_ID; ?>"><?php echo $likes > 0 ? $likes : 'Like'; ?></span>
                    </button>
                </div>
            </div>

            <div class="absolute right-4 top-4 flex gap-1 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition bg-[#1a1a22]/90 backdrop-blur-sm md:bg-[#1a1a22] rounded-lg shadow-lg border border-[#3a3a48] z-10 p-0.5">
                <button onclick="window.copyChatText(<?php echo $chat->comment_ID; ?>)" class="hover:bg-[#252530] hover:text-white text-gray-400 p-1.5 rounded-lg transition" title="Copy Text">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </button>
                <?php if ($is_logged_in) { ?>
                <button onclick="window.triggerReply('<?php echo esc_js($author_name); ?>', <?php echo $chat->comment_ID; ?>)" class="hover:bg-[#252530] hover:text-[#3b82f6] text-gray-400 p-1.5 rounded-lg transition" title="Reply">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                </button>
                <?php } ?>
                <?php if ($is_mine) { ?>
                <button onclick="window.editChat(<?php echo $chat->comment_ID; ?>)" class="hover:bg-[#252530] hover:text-green-500 text-gray-400 p-1.5 rounded-lg transition" title="Edit Message">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
                <?php } ?>
                <?php if ($is_mine || current_user_can('manage_options')) { ?>
                <button onclick="window.deleteChat(<?php echo $chat->comment_ID; ?>)" class="hover:bg-[#252530] hover:text-red-500 text-gray-400 p-1.5 rounded-lg transition" title="Delete Message">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
                <?php } ?>
            </div>
        </div>
        <?php
    }
}

// AJAX Chat Interceptors
if (is_user_logged_in()) {
    if (isset($_POST['custom_chat_submit'])) {
        while (ob_get_level()) ob_end_clean();
        $user = wp_get_current_user();
        $post_id = intval($_POST['comment_post_ID']);
        $parent_id = intval($_POST['comment_parent']);
        $content = sanitize_text_field($_POST['comment_content']);

        if (!empty($content)) {
            $inserted = wp_insert_comment(['comment_post_ID' => $post_id, 'comment_author' => $user->display_name, 'comment_author_email' => $user->user_email, 'comment_author_url' => $user->user_url, 'comment_content' => $content, 'comment_parent' => $parent_id, 'user_id' => $user->ID, 'comment_date' => current_time('mysql'), 'comment_approved' => 1]);
            if ($inserted) { echo wp_json_encode(['success' => true]); } else { echo wp_json_encode(['success' => false, 'error' => 'Database failed.']); }
        } else { echo wp_json_encode(['success' => false, 'error' => 'Empty message.']); }
        exit;
    }

    if (isset($_FILES['chat_image'])) {
        while (ob_get_level()) ob_end_clean();
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $movefile = wp_handle_upload($_FILES['chat_image'], array('test_form' => false));
        if ($movefile && !isset($movefile['error'])) { echo wp_json_encode(['success' => true, 'url' => $movefile['url']]); } else { echo wp_json_encode(['success' => false, 'error' => $movefile['error']]); }
        exit;
    }

    if (isset($_POST['delete_chat_id'])) {
        while (ob_get_level()) ob_end_clean();
        $cid = intval($_POST['delete_chat_id']);
        $comment = get_comment($cid);
        if ($comment && ($comment->user_id == get_current_user_id() || current_user_can('manage_options'))) { wp_delete_comment($cid, true); echo 'SUCCESS'; }
        exit;
    }

    if (isset($_POST['edit_chat_id']) && isset($_POST['new_content'])) {
        while (ob_get_level()) ob_end_clean();
        $cid = intval($_POST['edit_chat_id']);
        $new_content = sanitize_text_field($_POST['new_content']);
        $comment = get_comment($cid);
        if ($comment && $comment->user_id == get_current_user_id()) { wp_update_comment(['comment_ID' => $cid, 'comment_content' => $new_content]); echo 'SUCCESS'; }
        exit;
    }

    if (isset($_POST['like_chat_id'])) {
        while (ob_get_level()) ob_end_clean();
        $cid = intval($_POST['like_chat_id']);
        $likes = (int) get_comment_meta($cid, '_chat_likes', true);
        update_comment_meta($cid, '_chat_likes', $likes + 1); echo $likes + 1;
        exit;
    }
}

if (isset($_POST['fetch_live_chats']) && isset($_POST['chapter_id'])) {
    while (ob_get_level()) ob_end_clean();
    $chap_id = intval($_POST['chapter_id']);
    $chap_chats = get_comments(['post_id' => $chap_id, 'status' => 'approve', 'number' => 50]);
    $current_user = wp_get_current_user();
    $is_logged_in = is_user_logged_in();
    foreach($chap_chats as $chat) { xcomix_chapter_chat_render($chat, $is_logged_in, $current_user); }
    exit;
}

add_filter('show_admin_bar', '__return_false');
get_header();
the_post();

$is_logged_in = is_user_logged_in();
$chapter_chats = get_comments(['post_id' => $current_post_id, 'status' => 'approve', 'number' => 50]);

$all_chaps = mvx_get_chapters($manga_id, 'DESC');

$prev_chap = null; $next_chap = null;
foreach ($all_chaps as $i => $c) {
    if ($c->ID === $current_post_id) {
        $next_chap = isset($all_chaps[$i - 1]) ? $all_chaps[$i - 1] : null;
        $prev_chap = isset($all_chaps[$i + 1]) ? $all_chaps[$i + 1] : null;
        break;
    }
}

// =========================================================================
// LIGHTWEIGHT DATABASE CACHE (NO PHP SCRAPING)
// =========================================================================
$cache_key = 'xcomix_mgeko_imgs_' . $current_post_id;
$images = isset($_GET['clear_cache']) ? false : get_transient($cache_key);
$needs_scraping = (false === $images || empty($images)) ? 'true' : 'false';
?>

    <main class="min-h-screen relative w-full bg-[#0f0f13]">

        <style>
            #bottom-nav, #global-nav, .site-header { display: none !important; }

            .orange-spinner { width: 24px; height: 24px; border: 3px solid #2a2a35; border-top-color: #e8783a; border-radius: 50%; animation: xcomix-spin 1s linear infinite; display: inline-block; vertical-align: middle; }
            @keyframes xcomix-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

            .progress-bar { position: fixed; top: 0; left: 0; height: 3px; z-[99999]; background: #e8783a; box-shadow: 0 0 10px #e8783a; transition: width 0.2s; }
            .drawer { transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
            .drawer.open { transform: translateX(0); }
            .overlay { opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
            .overlay.open { opacity: 1; pointer-events: auto; }
            .chat-scroll::-webkit-scrollbar { width: 6px; }
            .chat-scroll::-webkit-scrollbar-track { background: transparent; }
            .chat-scroll::-webkit-scrollbar-thumb { background: #3a3a48; border-radius: 10px; }
            .mention-highlight { color: #e8783a; font-weight: 900; background: rgba(234, 88, 12, 0.1); padding: 0 4px; border-radius: 4px; }
            .spoiler-block { background: #3a3a48; color: transparent; padding: 0 6px; border-radius: 4px; cursor: pointer; transition: all 0.2s; user-select: none; }
            .spoiler-block:hover, .spoiler-block:active { background: rgba(255,255,255,0.1); color: #fff; }

            /* Client-Side Renderer Styles */
            #pages-container { display: flex; flex-direction: column; width: 100%; min-height: 100vh; cursor: pointer; -webkit-tap-highlight-color: transparent; }
            #pages-container.mode-webtoon { gap: 0; display: block; text-align: center; padding-top: 56px; padding-bottom: 20px; }
            #pages-container.mode-webtoon .page-wrapper { max-width: 800px; margin: 0 auto; display: block; line-height: 0; font-size: 0; }
            #pages-container.mode-webtoon .chapter-page { width: 100%; height: auto; margin: 0; padding: 0; display: block; vertical-align: top; }
            #pages-container.mode-page { gap: 40px; background-color: #0f0f13; display: flex; flex-direction: column; align-items: center; padding-top: 80px; padding-bottom: 40px; }
            #pages-container.mode-page .page-wrapper { max-width: 1000px; margin: 0 auto; padding: 0 10px; display: flex; justify-content: center; }
            #pages-container.mode-page .chapter-page { max-height: 90vh; width: auto; max-width: 100%; object-fit: contain; box-shadow: 0 10px 40px rgba(0,0,0,0.8); border-radius: 8px; margin: 0 auto; }
            .page-wrapper { position: relative; width: 100%; display: block; background: #0f0f13; margin: 0; padding: 0; }
            .page-wrapper.is-loading { min-height: 400px; }
            .spinner-container { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 10; display: flex; justify-content: center; align-items: center; }
            .chapter-page { position: relative; z-index: 20; display: block; width: 100%; }
        </style>

        <nav id="reader-top-nav" class="fixed top-0 left-0 w-full z-[9999] bg-[#1a1a22]/95 backdrop-blur-md border-b border-[#3a3a48] transition-transform duration-300 shadow-sm">
            <div class="max-w-[1000px] mx-auto px-4 h-14 flex items-center justify-between gap-3">
                <a href="<?php echo get_permalink($manga_id); ?>" class="w-8 h-8 bg-[#252530] border border-[#3a3a48] rounded-full flex items-center justify-center hover:border-[#e8783a] transition shrink-0 text-white" onclick="event.stopPropagation();">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div class="flex-1 flex flex-col items-center justify-center truncate px-2">
                    <h1 class="text-[13px] font-bold text-gray-200 truncate w-full text-center tracking-tight"><?php echo esc_html($manga_title); ?></h1>
                    <span class="text-[10px] text-[#e8783a] font-black uppercase tracking-widest">Chapter <?php echo (float)$chapter_num; ?></span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" onclick="window.scrollToChat(); event.stopPropagation();" class="hidden md:flex w-8 h-8 bg-[#252530] border border-[#3a3a48] rounded-full items-center justify-center hover:text-[#3b82f6] hover:border-[#3b82f6] transition text-white" title="Go to Comments">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </button>
                    <button type="button" onclick="window.toggleReaderDrawer(); event.stopPropagation();" class="w-8 h-8 bg-[#252530] border border-[#3a3a48] rounded-full flex items-center justify-center hover:border-[#e8783a] transition text-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>
            </div>
        </nav>

        <div id="pages-container" class="mode-webtoon w-full" onclick="window.toggleReaderUI()">
            <div id="client-scraper-ui" class="py-32 text-center flex flex-col items-center justify-center w-full px-6 <?php echo $needs_scraping === 'true' ? '' : 'hidden'; ?>">
                <div class="orange-spinner" style="margin-bottom:15px; width:40px; height:40px;"></div>
                <span class="text-white font-black uppercase tracking-widest mb-1 text-[13px]">Bypassing Security...</span>
                <span id="scraper-status-text" class="text-gray-500 text-[11px] font-bold tracking-wider text-center max-w-md break-words">
                    Extracting images via client-side distributed fetch...
                </span>
            </div>

            <div id="manga-reader-content" class="w-full flex flex-col items-center">
                <?php if (!empty($images)) { ?>
                    <?php foreach ($images as $img) { ?>
                        <div class="page-wrapper page-tracker is-loading">
                            <div class="spinner-container"><div class="orange-spinner"></div></div>
                            <img src="<?php echo esc_url($img); ?>"
                                 class="chapter-page inline-image"
                                 loading="lazy"
                                 decoding="async"
                                 referrerpolicy="no-referrer"
                                 onload="this.parentElement.classList.remove('is-loading'); this.previousElementSibling.style.display='none'; window.updateProgressBar();"
                                 onerror="this.parentElement.style.display='none';">
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>

        <div id="chapter-discussion" class="w-full max-w-[800px] mx-auto pb-28 pt-8 px-4" onclick="event.stopPropagation();">
            <div class="flex flex-col h-[500px] md:h-[600px] bg-[#1a1a22] border border-[#3a3a48] rounded-2xl shadow-2xl overflow-hidden">
                <div class="p-3 md:p-4 border-b border-[#2a2a35] bg-[#252530] shrink-0 flex items-center justify-between">
                    <h2 class="text-[14px] font-black text-white tracking-tight uppercase flex items-center gap-2">
                        <span class="w-1.5 h-4 bg-[#3b82f6] rounded-full"></span>
                        Chapter <?php echo (float)$chapter_num; ?> Chat
                    </h2>
                    <span class="text-[9px] font-black uppercase text-gray-500 tracking-widest px-2 py-1 bg-[#2a2a35] rounded border border-[#3a3a48] shadow-inner">
                        <?php echo count($chapter_chats); ?> Comments
                    </span>
                </div>
                <div class="flex-1 overflow-y-auto chat-scroll p-4 md:p-6 space-y-2 flex flex-col-reverse relative bg-[#0f0f13]" id="chat-feed-container">
                    <?php if (empty($chapter_chats)) { ?>
                        <div class="text-center py-10"><span class="text-gray-500 font-bold text-[12px]">No comments yet. Start the discussion!</span></div>
                    <?php } else { foreach($chapter_chats as $chat) { xcomix_chapter_chat_render($chat, $is_logged_in, $current_user); } } ?>
                </div>
                <button id="jump-bottom-btn" type="button" onclick="window.scrollChatToBottom()" class="absolute bottom-28 right-4 z-50 w-10 h-10 bg-[#3b82f6] text-white rounded-full shadow-xl hidden items-center justify-center transition-all hover:scale-110 border border-[#2563eb]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                </button>
                <div class="bg-[#0f0f13] border-t border-[#2a2a35] shrink-0 flex flex-col relative z-20">
                    <?php if ($is_logged_in) { ?>
                    <div class="flex gap-3 px-3 py-2 bg-[#1a1a22] border-b border-[#2a2a35] overflow-x-auto flex-nowrap no-scrollbar items-center">
                        <span class="text-[9px] font-black uppercase text-gray-600 tracking-widest mr-1 shrink-0">Quick</span>
                        <button type="button" onclick="window.insertText(String.fromCodePoint(128514))" class="hover:scale-125 transition shrink-0 text-base">&#128514;</button>
                        <button type="button" onclick="window.insertText(String.fromCodePoint(128293))" class="hover:scale-125 transition shrink-0 text-base">&#128293;</button>
                        <button type="button" onclick="window.insertText(String.fromCodePoint(128064))" class="hover:scale-125 transition shrink-0 text-base">&#128064;</button>
                        <button type="button" onclick="window.insertText(String.fromCodePoint(128128))" class="hover:scale-125 transition shrink-0 text-base">&#128128;</button>
                        <div class="w-px h-4 bg-[#3a3a48] mx-1 shrink-0"></div>
                        <button type="button" onclick="window.toggleGifPicker()" class="text-gray-400 hover:text-[#3b82f6] font-black text-[10px] uppercase tracking-wider shrink-0 transition bg-[#252530] px-2 py-1 rounded border border-[#3a3a48]">GIF</button>
                        <button type="button" onclick="window.insertText('**bold**')" class="text-gray-400 hover:text-white font-bold text-[10px] uppercase tracking-wider shrink-0 transition">Bold</button>
                        <button type="button" onclick="window.insertText('||spoiler||')" class="text-gray-400 hover:text-white font-bold text-[10px] uppercase tracking-wider shrink-0 transition">Spoiler</button>
                    </div>
                    <div id="gif-picker" class="hidden bg-[#0f0f13] border-b border-[#2a2a35] p-2 shrink-0">
                        <div class="flex overflow-x-auto gap-2 pb-1 snap-x no-scrollbar">
                            <img src="https://i.imgur.com/YwOaaPz.gif" class="w-16 md:w-20 h-16 md:h-20 object-cover rounded shrink-0 snap-center cursor-pointer border border-[#3a3a48] hover:border-[#3b82f6]" onclick="window.selectGif(this.src)">
                            <img src="https://i.imgur.com/Z4Ond85.gif" class="w-16 md:w-20 h-16 md:h-20 object-cover rounded shrink-0 snap-center cursor-pointer border border-[#3a3a48] hover:border-[#3b82f6]" onclick="window.selectGif(this.src)">
                            <img src="https://i.imgur.com/1mO7e.gif" class="w-16 md:w-20 h-16 md:h-20 object-cover rounded shrink-0 snap-center cursor-pointer border border-[#3a3a48] hover:border-[#3b82f6]" onclick="window.selectGif(this.src)">
                            <img src="https://i.imgur.com/Fw5ZJ.gif" class="w-16 md:w-20 h-16 md:h-20 object-cover rounded shrink-0 snap-center cursor-pointer border border-[#3a3a48] hover:border-[#3b82f6]" onclick="window.selectGif(this.src)">
                            <img src="https://i.imgur.com/L1d4P.gif" class="w-16 md:w-20 h-16 md:h-20 object-cover rounded shrink-0 snap-center cursor-pointer border border-[#3a3a48] hover:border-[#3b82f6]" onclick="window.selectGif(this.src)">
                            <img src="https://i.imgur.com/5O29D.gif" class="w-16 md:w-20 h-16 md:h-20 object-cover rounded shrink-0 snap-center cursor-pointer border border-[#3a3a48] hover:border-[#3b82f6]" onclick="window.selectGif(this.src)">
                            <img src="https://i.imgur.com/M9y4v.gif" class="w-16 md:w-20 h-16 md:h-20 object-cover rounded shrink-0 snap-center cursor-pointer border border-[#3a3a48] hover:border-[#3b82f6]" onclick="window.selectGif(this.src)">
                        </div>
                    </div>
                    <div id="reply-preview" class="hidden px-4 pt-2 pb-1 flex justify-between items-center text-[10px] text-gray-400 font-bold bg-[#0f0f13]">
                        <span>Replying to <span id="reply-username" class="text-[#3b82f6]"></span></span>
                        <button type="button" onclick="window.cancelReply()" class="hover:text-white">Cancel</button>
                    </div>
                    <div id="upload-preview" class="hidden px-4 pt-2 relative inline-block">
                        <div class="relative inline-block border-2 border-dashed border-[#3b82f6] rounded-lg p-1 bg-[#252530]">
                            <img id="upload-preview-img" src="" class="h-14 rounded object-cover">
                            <button type="button" onclick="window.removeUpload()" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-[10px] shadow-lg font-bold border-2 border-[#1a1a22]">X</button>
                        </div>
                    </div>
                    <form id="ajax-chat-form" onsubmit="event.preventDefault(); window.submitChat();" class="relative flex items-end bg-[#252530] m-2 border border-[#3a3a48] rounded-2xl overflow-hidden focus-within:border-[#3b82f6] transition shadow-inner">
                        <input type="file" id="chat-image-upload" accept="image/*" class="hidden" onchange="window.uploadChatImage(this)">
                        <input type="hidden" id="hidden_image_url" value="">
                        <input type="hidden" id="chat_comment_post_ID" value="<?php echo $current_post_id; ?>" />
                        <input type="hidden" id="chat_comment_parent" value="0" />
                        <label for="chat-image-upload" id="upload-btn-label" class="cursor-pointer m-1.5 w-10 h-10 rounded-xl bg-[#2a2a35] hover:bg-[#3a3a48] text-gray-400 hover:text-[#3b82f6] flex items-center justify-center shrink-0 transition" title="Attach Image">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </label>
                        <textarea id="chat-input-box" rows="1" placeholder="Share your thoughts..." class="w-full bg-transparent text-white text-[14px] py-3 pr-3 focus:outline-none resize-none no-scrollbar max-h-24 font-medium" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>
                        <button type="submit" id="chat-submit-btn" class="m-1.5 w-10 h-10 rounded-xl bg-[#3b82f6] hover:bg-[#2563eb] text-white flex items-center justify-center shrink-0 transition shadow-md">
                            <svg class="w-5 h-5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        </button>
                    </form>
                <?php } else { ?>
                    <div class="m-4 bg-[#252530] border border-[#3a3a48] rounded-2xl p-4 flex items-center justify-between shadow-inner">
                        <span class="text-gray-400 text-[12px] font-medium">Log in to join the discussion.</span>
                        <a href="/auth" class="bg-[#3b82f6] text-white px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest shadow-md">Sign In</a>
                    </div>
                <?php } ?>
                </div>
            </div>
        </div>

        <div id="reader-bottom-toolbar" class="fixed bottom-0 left-0 w-full z-[9999] bg-[#1a1a22]/95 backdrop-blur-md border-t border-[#3a3a48] transition-transform duration-300 pb-safe">
            <div class="max-w-[600px] mx-auto px-4 h-16 flex items-center justify-between gap-3 md:gap-4">
                <?php if($prev_chap) { ?>
                    <a href="<?php echo get_permalink($prev_chap->ID); ?>" class="flex-1 bg-[#252530] border border-[#3a3a48] hover:border-gray-500 text-center py-3 rounded-xl font-black text-[11px] uppercase tracking-widest text-gray-300 hover:text-white transition shadow-sm">Prev</a>
                <?php } else { ?>
                    <div class="flex-1 bg-[#0f0f13] border border-[#2a2a35] text-center py-3 rounded-xl font-black text-[11px] uppercase tracking-widest text-gray-700 cursor-not-allowed">Prev</div>
                <?php } ?>
                <button type="button" onclick="window.scrollToChat();" class="bg-[#252530] border border-[#3a3a48] hover:border-[#3b82f6] hover:text-[#3b82f6] w-12 h-12 rounded-xl flex items-center justify-center text-gray-400 transition shadow-sm shrink-0" title="Comments">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </button>
                <a href="<?php echo get_permalink($manga_id); ?>" class="bg-[#e8783a] hover:bg-[#d06a30] px-5 py-3 rounded-xl flex items-center justify-center text-white font-black text-[11px] uppercase tracking-widest transition shadow-[0_4px_15px_rgba(234,88,12,0.3)] shrink-0">
                    <svg class="w-4 h-4 md:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    <span class="hidden md:inline">Info</span>
                </a>
                <?php if($next_chap) { ?>
                    <a href="<?php echo get_permalink($next_chap->ID); ?>" class="flex-1 bg-[#252530] border border-[#3a3a48] hover:border-[#e8783a] text-center py-3 rounded-xl font-black text-[11px] uppercase tracking-widest text-gray-300 hover:text-white transition shadow-sm">Next</a>
                <?php } else { ?>
                    <div class="flex-1 bg-[#0f0f13] border border-[#2a2a35] text-center py-3 rounded-xl font-black text-[11px] uppercase tracking-widest text-gray-700 cursor-not-allowed">Next</div>
                <?php } ?>
            </div>
        </div>

        <div id="reader-overlay" onclick="window.toggleReaderDrawer()" class="overlay fixed inset-0 bg-black/80 backdrop-blur-sm z-[9999]"></div>

        <div id="reader-drawer" class="drawer fixed top-0 right-0 h-full w-[280px] bg-[#0f0f13] border-l border-[#3a3a48] z-[9999] flex flex-col shadow-2xl">
            <div class="p-5 border-b border-[#3a3a48] flex items-center justify-between bg-[#1a1a22]">
                <h3 class="font-black text-white uppercase tracking-widest text-[11px] flex items-center gap-2">
                    <span class="w-1.5 h-4 bg-[#e8783a] rounded-full"></span> Options
                </h3>
                <button type="button" onclick="window.toggleReaderDrawer()" class="w-7 h-7 bg-[#252530] border border-[#3a3a48] rounded-full flex items-center justify-center hover:border-gray-500 text-white transition">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-5 border-b border-[#3a3a48]">
                <span class="text-[9px] text-gray-500 font-black uppercase tracking-widest mb-3 block">Reading Mode</span>
                <div class="flex bg-[#1a1a22] rounded-xl p-1.5 border border-[#3a3a48] shadow-inner">
                    <button type="button" onclick="window.setReaderMode('webtoon')" id="btn-webtoon" class="flex-1 py-2 text-[10px] font-black uppercase tracking-widest rounded-lg bg-[#e8783a] text-white transition shadow-sm">Webtoon</button>
                    <button type="button" onclick="window.setReaderMode('page')" id="btn-page" class="flex-1 py-2 text-[10px] font-black uppercase tracking-widest rounded-lg text-gray-500 hover:text-white transition">Page</button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto no-scrollbar p-3 space-y-1">
                <span class="text-[9px] text-gray-500 font-black uppercase tracking-widest mb-2 block px-2 mt-2">Chapter List</span>
                <?php foreach($all_chaps as $ch) { $is_active = ($ch->ID === $current_post_id); ?>
                    <a href="<?php echo get_permalink($ch->ID); ?>" class="flex items-center gap-2 px-4 py-3.5 rounded-xl font-bold text-[11px] uppercase tracking-wider transition <?php echo $is_active ? 'bg-[#e8783a] text-white shadow-md' : 'hover:bg-[#1a1a22] border border-transparent hover:border-[#3a3a48] text-gray-400 hover:text-white'; ?>">
                        <svg class="w-3.5 h-3.5 <?php echo $is_active ? 'text-white' : 'text-[#e8783a]'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Chapter <?php echo mvx_chapter_number($ch->ID); ?>
                    </a>
                <?php } ?>
            </div>
        </div>

        <div id="custom-modal-overlay" class="fixed inset-0 bg-black/80 backdrop-blur-md z-[99999] hidden flex items-center justify-center p-4 opacity-0 transition-opacity duration-300">
            <div id="custom-modal-box" class="bg-[#1a1a22] border border-[#3a3a48] rounded-2xl p-6 w-full max-w-sm shadow-2xl transform scale-95 transition-transform duration-300">
                <h3 id="custom-modal-title" class="text-white font-black text-xl mb-2 italic tracking-tight">Title</h3>
                <p id="custom-modal-text" class="text-gray-400 text-[13px] mb-5 leading-relaxed">Text goes here.</p>
                <input type="text" id="custom-modal-input" class="hidden w-full bg-[#0f0f13] border border-[#3a3a48] rounded-xl px-4 py-3 text-white text-[13px] mb-5 focus:outline-none focus:border-[#3b82f6] transition" />
                <div class="flex gap-3 justify-end">
                    <button type="button" id="custom-modal-cancel" class="px-5 py-2.5 rounded-xl font-bold text-[13px] text-gray-400 hover:text-white hover:bg-[#252530] transition">Cancel</button>
                    <button type="button" id="custom-modal-confirm" class="px-5 py-2.5 rounded-xl font-bold text-[13px] bg-[#3b82f6] text-white hover:bg-[#2563eb] shadow-md transition">Confirm</button>
                </div>
            </div>
        </div>

        <div id="image-lightbox" onclick="window.closeLightbox(); event.stopPropagation();" class="fixed inset-0 bg-black/95 z-[99999] hidden flex items-center justify-center cursor-zoom-out p-4 backdrop-blur-sm transition-opacity">
            <img id="lightbox-img" src="" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl">
        </div>

        <script>
            // =========================================================================
            // CLIENT-SIDE SCRAPING ENGINE (BYPASSES CLOUDFLARE BLOCKING)
            // =========================================================================
            document.addEventListener("DOMContentLoaded", function() {
                const needsScraping = <?php echo $needs_scraping; ?>;
                const mgekoUrl = "<?php echo esc_js($mgeko_url); ?>";
                const postId = "<?php echo esc_js($current_post_id); ?>";
                const statusText = document.getElementById('scraper-status-text');

                if (needsScraping && mgekoUrl) {
                    // Try Route 1: AllOrigins
                    fetch("https://api.allorigins.win/get?url=" + encodeURIComponent(mgekoUrl))
                        .then(res => res.ok ? res.json() : Promise.reject('Route 1 Failed'))
                        .then(data => {
                            if (data.contents.includes('Just a moment') || data.contents.includes('Cloudflare')) {
                                throw new Error('Cloudflare Intercept');
                            }
                            extractAndSave(data.contents);
                        })
                        .catch(err => {
                            if(statusText) statusText.innerHTML = "<span class='text-[#e8783a]'>Switching to fallback bridge...</span>";
                            // Try Route 2: CodeTabs Fallback
                            fetch("https://api.codetabs.com/v1/proxy?quest=" + encodeURIComponent(mgekoUrl))
                                .then(res => res.text())
                                .then(html => extractAndSave(html))
                                .catch(e => {
                                    if(statusText) statusText.innerHTML = "<span class='text-red-500 font-bold'>Source locked down. Unable to fetch at this time.</span>";
                                });
                        });
                }

                function extractAndSave(htmlString) {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(htmlString, 'text/html');
                    const images = doc.querySelectorAll('img');
                    const foundImages = [];

                    images.forEach(img => {
                        const src = img.getAttribute('data-src') || img.getAttribute('data-lazy-src') || img.getAttribute('data-original') || img.getAttribute('src');
                        if (src && src.startsWith('http')) {
                            const lowSrc = src.toLowerCase();
                            if (!lowSrc.includes('logo') && !lowSrc.includes('avatar') && !lowSrc.includes('icon') && !lowSrc.includes('banner')) {
                                if (!foundImages.includes(src)) foundImages.push(src);
                            }
                        }
                    });

                    if (foundImages.length > 0) {
                        if(statusText) statusText.innerHTML = "<span class='text-green-500'>Images secured. Locking into server database...</span>";

                        const formData = new FormData();
                        formData.append('action', 'xcomix_save_scraped_images');
                        formData.append('post_id', postId);
                        formData.append('images', JSON.stringify(foundImages));

                        fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: formData })
                            .then(() => window.location.reload()); // Reload to show cached images natively
                    } else {
                        if(statusText) statusText.innerHTML = "<span class='text-red-500 font-bold'>Connected, but no chapter images were found.</span>";
                    }
                }
            });

            // =========================================================================
            // STANDARD READER UI FUNCTIONS
            // =========================================================================
            window.initInstantNavigation = function() {
                document.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', function(e) {
                        if (this.hasAttribute('href') && this.href.startsWith('http') && this.getAttribute('target') !== '_blank') {
                            if (this.href.includes('javascript:') || this.getAttribute('href').startsWith('#')) return;
                            e.preventDefault();
                            let targetUrl = this.href;
                            window.stop();
                            window.location.href = targetUrl;
                        }
                    });
                });
            };

            window.toggleReaderUI = function() {
                const topNav = document.getElementById('reader-top-nav');
                const bottomToolbar = document.getElementById('reader-bottom-toolbar');
                if(topNav) topNav.classList.toggle('-translate-y-full');
                if(bottomToolbar) bottomToolbar.classList.toggle('translate-y-full');
            };

            window.setReaderMode = function(mode) {
                const container = document.getElementById('pages-container');
                const btnWebtoon = document.getElementById('btn-webtoon');
                const btnPage = document.getElementById('btn-page');
                if(!container) return;

                localStorage.setItem('xcomix_reader_mode', mode);
                if (mode === 'page') {
                    container.className = 'mode-page w-full';
                    btnPage.classList.add('bg-[#e8783a]', 'text-white', 'shadow-sm'); btnPage.classList.remove('text-gray-500');
                    btnWebtoon.classList.remove('bg-[#e8783a]', 'text-white', 'shadow-sm'); btnWebtoon.classList.add('text-gray-500');
                } else {
                    container.className = 'mode-webtoon w-full';
                    btnWebtoon.classList.add('bg-[#e8783a]', 'text-white', 'shadow-sm'); btnWebtoon.classList.remove('text-gray-500');
                    btnPage.classList.remove('bg-[#e8783a]', 'text-white', 'shadow-sm'); btnPage.classList.add('text-gray-500');
                }
            };

            window.updateProgressBar = function() {
                const pages = document.querySelectorAll('.page-tracker');
                const progressBar = document.getElementById('progress-bar');
                if (pages.length === 0 || !progressBar) return;
                let current = 1;
                const scrollY = window.scrollY + window.innerHeight / 2;
                pages.forEach((page, i) => { if (page.offsetTop <= scrollY) current = i + 1; });
                progressBar.style.width = (current / pages.length * 100) + '%';
            };

            window.toggleReaderDrawer = function() {
                document.getElementById('reader-drawer').classList.toggle('open');
                document.getElementById('reader-overlay').classList.toggle('open');
            };

            window.scrollToChat = function() {
                const chatBox = document.getElementById('chapter-discussion');
                if (chatBox) chatBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
                const topNav = document.getElementById('reader-top-nav');
                const bottomToolbar = document.getElementById('reader-bottom-toolbar');
                if(topNav) topNav.classList.remove('-translate-y-full');
                if(bottomToolbar) bottomToolbar.classList.remove('translate-y-full');
            };

            window.initChapterReader = function() {
                window.setReaderMode(localStorage.getItem('xcomix_reader_mode') || 'webtoon');
                window.addEventListener('scroll', window.updateProgressBar);
                window.initInstantNavigation();
                setTimeout(() => {
                    const topNav = document.getElementById('reader-top-nav');
                    const bottomToolbar = document.getElementById('reader-bottom-toolbar');
                    if(topNav && !topNav.classList.contains('-translate-y-full')) topNav.classList.add('-translate-y-full');
                    if(bottomToolbar && !bottomToolbar.classList.contains('translate-y-full')) bottomToolbar.classList.add('translate-y-full');
                }, 2000);
            };

            window.showModal = function({ title, text, type = 'alert', inputValue = '', confirmText = 'OK', cancelText = 'Cancel' }) {
                return new Promise((resolve) => {
                    const overlay = document.getElementById('custom-modal-overlay');
                    const box = document.getElementById('custom-modal-box');
                    document.getElementById('custom-modal-title').innerText = title;
                    document.getElementById('custom-modal-text').innerText = text;
                    document.getElementById('custom-modal-confirm').innerText = confirmText;
                    document.getElementById('custom-modal-cancel').innerText = cancelText;

                    const inputEl = document.getElementById('custom-modal-input');
                    if (type === 'prompt') { inputEl.style.display = 'block'; inputEl.value = inputValue; }
                    else { inputEl.style.display = 'none'; }
                    document.getElementById('custom-modal-cancel').style.display = (type === 'alert') ? 'none' : 'block';

                    overlay.classList.remove('hidden');
                    setTimeout(() => { overlay.classList.remove('opacity-0'); box.classList.remove('scale-95'); }, 10);
                    if(type === 'prompt') setTimeout(() => inputEl.focus(), 100);

                    const closeModal = () => {
                        overlay.classList.add('opacity-0'); box.classList.add('scale-95');
                        setTimeout(() => overlay.classList.add('hidden'), 300);
                    };

                    document.getElementById('custom-modal-confirm').onclick = () => { closeModal(); resolve(type === 'prompt' ? inputEl.value : true); };
                    document.getElementById('custom-modal-cancel').onclick = () => { closeModal(); resolve(false); };
                });
            };

            window.triggerReply = function(username, parentId) {
                document.getElementById('reply-preview').classList.remove('hidden');
                document.getElementById('reply-username').innerText = '@' + username;
                document.getElementById('chat_comment_parent').value = parentId;
                document.getElementById('chat-input-box').focus();
            };
            window.cancelReply = function() {
                document.getElementById('reply-preview').classList.add('hidden');
                document.getElementById('chat_comment_parent').value = 0;
            };
            window.insertText = function(text) {
                const inputBox = document.getElementById('chat-input-box');
                if (inputBox) { inputBox.value += text + " "; inputBox.focus(); inputBox.style.height = inputBox.scrollHeight + 'px'; }
            };
            window.toggleGifPicker = function() { document.getElementById('gif-picker').classList.toggle('hidden'); };
            window.selectGif = function(url) {
                document.getElementById('hidden_image_url').value = url;
                document.getElementById('upload-preview-img').src = url;
                document.getElementById('upload-preview').classList.remove('hidden');
                window.toggleGifPicker();
                document.getElementById('chat-input-box').removeAttribute('required');
            };
            window.openLightbox = function(url) {
                document.getElementById('lightbox-img').src = url;
                document.getElementById('image-lightbox').classList.remove('hidden');
            };
            window.closeLightbox = function() {
                document.getElementById('image-lightbox').classList.add('hidden');
                document.getElementById('lightbox-img').src = '';
            };
            window.copyChatText = function(commentId) {
                let el = document.getElementById('chat-content-' + commentId);
                navigator.clipboard.writeText(el.getAttribute('data-raw')).then(async () => { await window.showModal({title: 'Copied!', text: 'Message copied to clipboard.'}); });
            };

            const chatFeed = document.getElementById('chat-feed-container');
            const jumpBtn = document.getElementById('jump-bottom-btn');
            if (chatFeed && jumpBtn) {
                chatFeed.addEventListener('scroll', () => {
                    if (Math.abs(chatFeed.scrollTop) > 300) { jumpBtn.classList.remove('hidden'); jumpBtn.classList.add('flex'); }
                    else { jumpBtn.classList.add('hidden'); jumpBtn.classList.remove('flex'); }
                });
            }
            window.scrollChatToBottom = function() { if(chatFeed) chatFeed.scrollTop = 0; };

            window.uploadChatImage = async function(inputElement) {
                if (!inputElement.files || inputElement.files.length === 0) return;
                let formData = new FormData(); formData.append('chat_image', inputElement.files[0]);
                let btnLabel = document.getElementById('upload-btn-label');
                let originalIcon = btnLabel.innerHTML;
                btnLabel.innerHTML = `<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>`;
                try {
                    let res = await fetch(window.location.pathname + window.location.search, { method: 'POST', body: formData });
                    let data = await res.json();
                    if (data.success) {
                        document.getElementById('hidden_image_url').value = data.url;
                        document.getElementById('upload-preview-img').src = data.url;
                        document.getElementById('upload-preview').classList.remove('hidden');
                        document.getElementById('chat-input-box').removeAttribute('required');
                    } else await window.showModal({title: 'Upload Failed', text: data.error});
                } catch(e) { await window.showModal({title: 'Error', text: 'File may be too large.'}); }
                btnLabel.innerHTML = originalIcon; inputElement.value = "";
            };

            window.removeUpload = function() {
                document.getElementById('hidden_image_url').value = '';
                document.getElementById('upload-preview').classList.add('hidden');
                document.getElementById('chat-input-box').setAttribute('required', 'required');
            };

            window.submitChat = async function() {
                let btn = document.getElementById('chat-submit-btn');
                let inputBox = document.getElementById('chat-input-box');
                let textValue = inputBox.value;
                let hiddenImg = document.getElementById('hidden_image_url').value;
                let postId = document.getElementById('chat_comment_post_ID').value;
                let parentId = document.getElementById('chat_comment_parent').value;

                if (textValue.trim() === '' && hiddenImg === '') return;

                let finalContent = textValue;
                if (hiddenImg !== '') finalContent += "\n" + hiddenImg;

                let replyUsernameEl = document.getElementById('reply-username');
                let replyingTo = replyUsernameEl ? replyUsernameEl.innerText : '';
                if (parentId !== '0' && replyingTo !== '' && !finalContent.includes(replyingTo)) {
                    finalContent = replyingTo + " " + finalContent;
                }

                btn.classList.add('opacity-50', 'pointer-events-none');
                let formData = new FormData();
                formData.append('custom_chat_submit', '1');
                formData.append('comment_content', finalContent);
                formData.append('comment_post_ID', postId);
                formData.append('comment_parent', parentId);

                inputBox.value = ''; inputBox.style.height = 'auto'; window.removeUpload(); window.cancelReply();
                document.getElementById('gif-picker').classList.add('hidden');

                try { await fetch(window.location.pathname + window.location.search, { method: 'POST', body: formData }); }
                catch(e) { console.error('Chat submission error:', e); }

                btn.classList.remove('opacity-50', 'pointer-events-none');
                window.fetchLiveChats();
            };

            window.deleteChat = async function(commentId) {
                const confirmDelete = await window.showModal({title: 'Delete Message', text: 'Permanently delete this message?', type: 'confirm', confirmText: 'Delete'});
                if (!confirmDelete) return;
                let formData = new FormData(); formData.append('delete_chat_id', commentId);
                await fetch(window.location.pathname + window.location.search, { method: 'POST', body: formData });
                window.fetchLiveChats();
            };

            window.editChat = async function(commentId) {
                let el = document.getElementById('chat-content-' + commentId);
                let rawContent = el.getAttribute('data-raw');
                if (/(https?:\/\/[^\s]+?\.(?:jpg|jpeg|png|gif|webp))/i.test(rawContent)) {
                    await window.showModal({title: 'Action Denied', text: 'Messages containing attachments or GIFs cannot be edited. Please delete the message and resend it.'}); return;
                }
                const newContent = await window.showModal({title: 'Edit Message', text: 'Update your message below:', type: 'prompt', inputValue: rawContent, confirmText: 'Save Edit'});
                if (newContent && newContent.trim() !== '' && newContent !== rawContent) {
                    let formData = new FormData();
                    formData.append('edit_chat_id', commentId); formData.append('new_content', newContent);
                    await fetch(window.location.pathname + window.location.search, { method: 'POST', body: formData });
                    window.fetchLiveChats();
                }
            };

            window.likeChat = async function(commentId) {
                let formData = new FormData(); formData.append('like_chat_id', commentId);
                let res = await fetch(window.location.pathname + window.location.search, { method: 'POST', body: formData });
                let countSpan = document.getElementById('like-count-' + commentId);
                if(countSpan) countSpan.innerText = await res.text();
                countSpan.previousElementSibling.classList.add('text-pink-500');
            };

            window.fetchLiveChats = async function() {
                if(!chatFeed) return;
                let isScrolledBottom = Math.abs(chatFeed.scrollTop) < 50;
                let formData = new FormData(); formData.append('fetch_live_chats', '1'); formData.append('chapter_id', <?php echo $current_post_id; ?>);
                try {
                    let res = await fetch(window.location.pathname + window.location.search, { method: 'POST', body: formData });
                    chatFeed.innerHTML = await res.text();
                    if(isScrolledBottom) window.scrollChatToBottom();
                } catch(e) {}
            };

            setInterval(() => { if (document.visibilityState === 'visible') window.fetchLiveChats(); }, 30000);

            document.getElementById('chat-input-box')?.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); window.submitChat(); }
            });

            window.initChapterReader();
        </script>
    </main>

<?php get_footer(); ?>
