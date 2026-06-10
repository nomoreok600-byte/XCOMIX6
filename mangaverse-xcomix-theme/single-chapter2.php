<?php
/**
 * X COMIX - Kagane Premium V11 (Working Scroll + Persistent Nav + Upgraded Chapter List)
 */

// =========================================================================
// 1. CHAT RENDER FUNCTION
// =========================================================================
if (!function_exists('xcomix_chapter_chat_render')) {
    function xcomix_chapter_chat_render($chat, $is_logged_in, $current_user) {
        $author_name = $chat->comment_author;
        $author_avatar = get_user_meta($chat->user_id, '_xcomix_avatar', true) ?: 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($author_name);
        $user_obj = get_userdata($chat->user_id);
        $role_badge = ''; 
        $name_color = 'text-gray-200';
        
        if ($user_obj) {
            if (in_array('administrator', $user_obj->roles)) {
                $role_badge = '<span class="bg-accent/20 text-accent text-[8px] font-black uppercase px-1.5 py-0.5 rounded ml-1">Admin</span>';
                $name_color = 'text-accent';
            } elseif (in_array('author', $user_obj->roles) || in_array('editor', $user_obj->roles)) {
                $role_badge = '<span class="bg-purple-500/20 text-purple-400 text-[8px] font-black uppercase px-1.5 py-0.5 rounded ml-1">Uploader</span>';
                $name_color = 'text-purple-400';
            }
        }
        
        $raw_content = nl2br(esc_html($chat->comment_content));
        ?>
        <div class="flex gap-3 p-4 border-b border-white/5 hover:bg-white/5 transition" id="chat-msg-<?php echo $chat->comment_ID; ?>">
            <img src="<?php echo esc_url($author_avatar); ?>" class="w-10 h-10 rounded-full bg-surface shrink-0 object-cover shadow-md" alt="Avatar">
            <div class="flex flex-col w-full">
                <div class="flex items-baseline justify-between mb-1">
                    <div class="flex items-center gap-2">
                        <span class="text-[13px] font-bold <?php echo $name_color; ?>"><?php echo esc_html($author_name); ?></span>
                        <?php echo $role_badge; ?>
                    </div>
                    <span class="text-[10px] text-gray-500 font-medium"><?php echo human_time_diff(strtotime($chat->comment_date), current_time('timestamp')); ?> ago</span>
                </div>
                <div class="text-[13px] text-gray-300 leading-relaxed font-medium break-words"><?php echo $raw_content; ?></div>
            </div>
        </div>
        <?php
    }
}

// =========================================================================
// 2. AJAX INTERCEPTORS
// =========================================================================
if (isset($_POST['fetch_live_chats']) && isset($_POST['chapter_id'])) {
    while (ob_get_level()) ob_end_clean();
    $chap_chats = get_comments(['post_id' => intval($_POST['chapter_id']), 'status' => 'approve', 'number' => 50]);
    foreach($chap_chats as $chat) { xcomix_chapter_chat_render($chat, is_user_logged_in(), wp_get_current_user()); } 
    exit;
}
if (isset($_POST['custom_chat_submit']) && is_user_logged_in()) {
    while (ob_get_level()) ob_end_clean();
    $user = wp_get_current_user();
    $inserted = wp_insert_comment([
        'comment_post_ID'      => intval($_POST['comment_post_ID']), 
        'comment_author'       => $user->display_name, 
        'comment_author_email' => $user->user_email,
        'comment_content'      => sanitize_text_field($_POST['comment_content']), 
        'user_id'              => $user->ID, 
        'comment_date'         => current_time('mysql'), 
        'comment_approved'     => 1
    ]);
    echo wp_json_encode(['success' => (bool)$inserted]); 
    exit;
}

// =========================================================================
// 3. WP HEADER & PREP
// =========================================================================
add_filter('show_admin_bar', '__return_false');
get_header(); 
the_post();

$current_post_id = get_the_ID();
$manga_id = wp_get_post_parent_id($current_post_id);

// Fetched extra data for the chapter list modal
$manga_title = get_the_title($manga_id);
$manga_permalink = get_permalink($manga_id);
$manga_thumb = get_the_post_thumbnail_url($manga_id, 'medium') ?: ''; 
$katana_url = trim(get_post_meta($current_post_id, '_katana_url', true));
$chapter_num = mvx_chapter_number($current_post_id);

$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();

if ($is_logged_in && $manga_id) {
    $raw_history = get_user_meta($current_user->ID, '_xcomix_history', true);
    if (!is_array($raw_history)) { $raw_history = []; }
    $raw_history[$manga_id] = [
        'id'          => $manga_id,
        'chapter_num' => $chapter_num,
        'url'         => get_permalink($current_post_id),
        'timestamp'   => time()
    ];
    update_user_meta($current_user->ID, '_xcomix_history', $raw_history);
}

$all_chaps = mvx_get_chapters($manga_id, 'DESC');
$total_chapters = count($all_chaps);
$prev_chap = null; $next_chap = null;
foreach ($all_chaps as $i => $c) {
    if ($c->ID === $current_post_id) {
        $next_chap = isset($all_chaps[$i - 1]) ? $all_chaps[$i - 1] : null; 
        $prev_chap = isset($all_chaps[$i + 1]) ? $all_chaps[$i + 1] : null; 
        break;
    }
}

// SCRAPER ENGINE
$images = []; $diagnostic_error = ''; $force_rescan = isset($_GET['clear_cache']);
$cached_images = get_post_meta($current_post_id, '_xcomix_cached_images', true);

if (!empty($cached_images) && is_array($cached_images) && !$force_rescan) { 
    $images = $cached_images; 
} else if ($katana_url) {
    if (strpos($katana_url, 'http') !== 0) $katana_url = 'https://' . ltrim($katana_url, '/'); 
    $ch = curl_init($katana_url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0']]);
    $html = curl_exec($ch); curl_close($ch);
    if (preg_match('/var\s+(?:yta|thzq|qwe|tsa)\s*=\s*\[(.*?)\];/is', $html, $js_array) || preg_match_all('/var\s+[a-zA-Z0-9_]+\s*=\s*\[(.*?)\];/is', $html, $arrays)) {
        preg_match_all('/[\'"](https?:\/\/[^\'"]+)[\'"]/i', $html, $matches);
        foreach ($matches[1] as $imgUrl) {
            $cleanUrl = str_replace('\\/', '/', $imgUrl);
            if(preg_match('/\.(jpg|jpeg|png|webp)$/i', $cleanUrl) && !preg_match('/(discord|logo|banner|credit|scan|promo|recruit|join|donate|support|hivetoon|fav\.png|s\.png|\/static\/img\/)/i', $cleanUrl)) { $images[] = $cleanUrl; }
        }
        $images = array_values(array_unique($images));
        if (!empty($images)) update_post_meta($current_post_id, '_xcomix_cached_images', $images);
    } else { $diagnostic_error = "Could not find image arrays. Security block active."; }
}

$encoded_images = array_map(function($img) { return mvx_proxy_image_url($img, 'katana'); }, $images);
$comment_count = get_comments_number($current_post_id);
?>

<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { colors: { bg: '#08080c', surface: '#12121a', surface2: '#1c1c26', accent: '#7c3aed' } } } };</script>

<style>
    /* NATIVE BODY SCROLLING */
    html, body { 
        background: var(--reader-bg, #08080c); 
        margin: 0!important; 
        padding: 0!important; 
        width: 100%; 
        min-height: 100vh;
        overscroll-behavior-y: none;
        padding-bottom: calc(60px + env(safe-area-inset-bottom)) !important; /* Space for Bottom Nav */
    }
    #wpadminbar, header, footer { display: none !important; }
    
    :root { --reader-zoom: 100%; --reader-max-width: 800px; --reader-brightness: 100%; }
    
    #kagane-app { min-height: 100vh; position: relative; }
    
    #reader-content { width: var(--reader-zoom); max-width: var(--reader-max-width); margin: 0 auto; display: flex; flex-direction: column; align-items: center; filter: brightness(var(--reader-brightness)); transition: width 0.2s, max-width 0.2s; }
    .image-wrapper { width: 100%; min-height: 300px; position: relative; display: flex; justify-content: center; }
    .reader-img { display: block; height: auto; object-fit: contain; width: 100%; }
    
    .base-screen .reader-img { width: 100%; max-width: 100%; }
    .base-image .reader-img { width: var(--reader-zoom); max-width: none; }
    .base-image #reader-content { width: 100%; max-width: none; }

    .gap-mode .image-wrapper { margin-bottom: 24px; }
    .no-gap-mode .image-wrapper { margin-bottom: -1px; }

    /* Single Mode */
    body.single-mode { overflow: hidden !important; height: 100vh !important; padding-bottom: 0 !important; }
    body.single-mode #kagane-app { height: 100vh; display: flex; align-items: center; justify-content: center; }
    body.single-mode #reader-content { width: 100%; max-width: 100%; height: 100%; justify-content: center; flex-direction: row; }
    body.single-mode .image-wrapper { display: none; height: 100%; width: 100%; align-items: center; justify-content: center; margin: 0; }
    body.single-mode .image-wrapper.active { display: flex; }
    body.single-mode.fit-contain .reader-img { max-width: 100%; max-height: 100dvh; width: auto; height: auto; }
    body.single-mode.fit-width .reader-img { width: 100%; height: auto; max-height: none; }
    body.single-mode.fit-height .reader-img { height: 100dvh; width: auto; max-width: none; }
    body.single-mode #end-chapter-flow { display: none; }

    /* UI Visibility Animations */
    .ui-layer-el { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s; }
    #top-nav.ui-hidden { transform: translateY(-100%); opacity: 0; pointer-events: none; }
    #scrubber-nav.ui-hidden { transform: translateY(100%); opacity: 0; pointer-events: none; }
    #persistent-bottom-nav.ui-hidden { transform: translateY(100%); opacity: 0; pointer-events: none; }
    #side-fabs.ui-hidden { opacity: 0; pointer-events: none; }

    /* Modals & Drawers */
    .modal-panel { position: fixed; inset: 0; background: #12121a; z-index: 100; transform: translateY(100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; }
    .modal-panel.open { transform: translateY(0); }

    .drawer-side { position: fixed; top: 0; right: 0; width: 100%; max-width: 380px; height: 100%; background: #12121a; z-index: 110; transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; border-left: 1px solid rgba(255,255,255,0.05); }
    .drawer-side.open { transform: translateX(0); }
    
    .drawer-bottom { position: fixed; bottom: 0; left: 0; width: 100%; height: 80vh; max-height: 700px; background: #12121a; z-index: 110; transform: translateY(100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; border-top-left-radius: 24px; border-top-right-radius: 24px; box-shadow: 0 -10px 40px rgba(0,0,0,0.8); }
    @media (min-width: 768px) {
        .drawer-bottom { height: 100%; max-height: 100%; width: 450px; left: auto; right: 0; border-radius: 0; border-left: 1px solid rgba(255,255,255,0.05); transform: translateX(100%); }
        .drawer-bottom.open { transform: translateX(0); }
    }
    .drawer-bottom.open { transform: translateY(0); }

    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Sliders */
    .pill-slider { -webkit-appearance: none; width: 100%; background: transparent; height: 4px; border-radius: 2px; }
    .pill-slider::-webkit-slider-runnable-track { width: 100%; height: 4px; background: #2a2a35; border-radius: 2px; }
    .pill-slider::-webkit-slider-thumb { -webkit-appearance: none; height: 16px; width: 16px; border-radius: 50%; background: #7c3aed; margin-top: -6px; cursor: pointer; box-shadow: 0 0 10px rgba(124, 58, 237, 0.5); }
    .toggle-checkbox:checked { right: 0; border-color: #7c3aed; }
    .toggle-checkbox:checked + .toggle-label { background-color: #7c3aed; }

    .style-grayscale .reader-img { filter: grayscale(100%); }
    .style-invert .reader-img { filter: invert(100%) hue-rotate(180deg); }

    .loader-spin { border: 3px solid rgba(255,255,255,0.1); border-top-color: #7c3aed; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 5; }
    @keyframes spin { 0% { transform: translate(-50%, -50%) rotate(0deg); } 100% { transform: translate(-50%, -50%) rotate(360deg); } }
</style>

<div id="kagane-app" class="font-sans text-gray-200 base-screen no-gap-mode fit-contain">
    
    <div id="reader-content">
        <?php if(empty($images)): ?>
            <div class="mt-32 text-center p-8 bg-surface border border-white/5 rounded-2xl max-w-sm mx-auto shadow-2xl relative z-20">
                <p class="text-red-500 font-bold mb-2">Scan Missing</p>
                <p class="text-gray-500 text-xs mb-4"><?php echo $diagnostic_error; ?></p>
                <a href="?clear_cache=1" class="px-6 py-2 bg-surface2 text-white font-bold rounded shadow transition">Force Re-Scan</a>
            </div>
        <?php endif; ?>
    </div>

    <div id="end-chapter-flow" class="w-full max-w-3xl mx-auto pt-16 pb-24 px-4">
        <h3 class="text-center font-black text-xl text-white mb-6">End of Chapter <?php echo (float)$chapter_num; ?></h3>
        <div class="flex items-center gap-4 w-full mb-8">
            <a href="<?php echo $prev_chap ? get_permalink($prev_chap->ID) : '#'; ?>" class="flex-1 py-4 bg-surface2 rounded-xl font-bold text-center text-gray-300 hover:text-white transition shadow-lg border border-white/5 <?php echo !$prev_chap ? 'opacity-30 pointer-events-none' : ''; ?>">Previous Chapter</a>
            <a href="<?php echo $next_chap ? get_permalink($next_chap->ID) : '#'; ?>" class="flex-[1.5] py-4 bg-accent rounded-xl font-bold text-center text-white hover:bg-purple-500 transition shadow-[0_0_20px_rgba(124,58,237,0.3)] border border-accent/50 <?php echo !$next_chap ? 'opacity-30 pointer-events-none' : ''; ?>">Next Chapter</a>
        </div>
        <div class="text-center">
            <a href="<?php echo esc_url($manga_permalink); ?>" class="inline-block px-8 py-3 bg-surface rounded-xl font-bold text-gray-400 hover:text-white transition border border-white/5 text-sm">
                Return to <?php echo esc_html($manga_title); ?> Info
            </a>
        </div>
    </div>

    <nav id="top-nav" class="ui-layer-el fixed top-0 left-0 w-full z-40 bg-surface/95 backdrop-blur-xl border-b border-white/5 shadow-md flex items-center justify-between px-4 h-16 pt-[env(safe-area-inset-top)] pointer-events-auto">
        <button onclick="window.history.back()" class="p-2 -ml-2 text-gray-400 hover:text-white transition">
            <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <div class="flex flex-col items-center flex-1 px-4 overflow-hidden">
            <h1 class="text-sm font-bold text-white truncate w-full text-center"><?php echo esc_html($manga_title); ?></h1>
            <span class="text-[10px] text-accent font-bold uppercase tracking-widest mt-0.5">Chapter <?php echo (float)$chapter_num; ?></span>
        </div>
        <button onclick="window.openModal('drawer-chapters')" class="p-2 -mr-2 text-gray-400 hover:text-white transition">
            <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </nav>

    <div id="side-fabs" class="ui-layer-el fixed inset-0 z-30 pointer-events-none">
        <div class="absolute top-1/2 left-4 -translate-y-1/2 flex flex-col gap-3 pointer-events-auto">
            <button onclick="window.zoomReader(10)" class="w-11 h-11 rounded-full bg-surface/90 backdrop-blur shadow-lg border border-white/10 flex items-center justify-center text-gray-400 hover:text-white transition"><svg style="width:20px; height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg></button>
            <button onclick="window.zoomReader(-10)" class="w-11 h-11 rounded-full bg-surface/90 backdrop-blur shadow-lg border border-white/10 flex items-center justify-center text-gray-400 hover:text-white transition"><svg style="width:20px; height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"/></svg></button>
        </div>
        <div class="absolute top-1/2 right-4 -translate-y-1/2 flex flex-col gap-3 pointer-events-auto">
            <button onclick="window.openModal('modal-settings')" class="w-11 h-11 rounded-full bg-surface/90 backdrop-blur shadow-lg border border-white/10 flex items-center justify-center text-gray-400 hover:text-white transition"><svg style="width:20px; height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></button>
            <button onclick="window.toggleAutoScroll()" id="btn-autoscroll" class="w-11 h-11 rounded-full bg-surface/90 backdrop-blur shadow-lg border border-white/10 flex items-center justify-center text-gray-400 hover:text-white transition"><svg style="width:20px; height:20px; margin-left:2px;" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></button>
        </div>
    </div>

    <div id="scrubber-nav" class="ui-layer-el fixed left-0 w-full z-40 bg-surface/95 backdrop-blur-xl border-t border-white/5 pointer-events-auto" style="bottom: calc(60px + env(safe-area-inset-bottom));">
        <div class="px-5 py-3 flex items-center gap-4 max-w-2xl mx-auto w-full">
            <span id="page-current" class="text-xs font-black text-white bg-surface2 px-3 py-1 rounded-lg border border-white/5 min-w-[36px] text-center shadow-inner">1</span>
            <input type="range" id="page-slider" min="1" max="<?php echo max(1, count($images)); ?>" value="1" class="pill-slider flex-1" oninput="window.scrollToPage(this.value)">
            <span id="page-total" class="text-xs font-bold text-gray-500 w-8 text-right"><?php echo count($images); ?></span>
        </div>
    </div>

    <nav id="persistent-bottom-nav" class="ui-layer-el fixed bottom-0 left-0 w-full bg-[#12121a] border-t border-[#2a2a35] z-50 flex items-center justify-between px-4 pb-[env(safe-area-inset-bottom)] pointer-events-auto h-[60px] shadow-[0_-10px_30px_rgba(0,0,0,0.8)]">
        <a href="<?php echo $prev_chap ? get_permalink($prev_chap->ID) : '#'; ?>" class="flex-1 flex items-center justify-center gap-2 text-gray-400 hover:text-white transition h-full <?php echo !$prev_chap ? 'opacity-30 pointer-events-none' : ''; ?>">
            <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span class="text-[12px] font-bold uppercase tracking-wider hidden sm:block">Prev</span>
        </a>
        
        <button onclick="window.openModal('drawer-comments')" class="flex-[1.5] flex items-center justify-center gap-2 text-white hover:text-accent transition h-full relative">
            <div class="flex items-center gap-2">
                <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <span class="text-[13px] font-bold">Comments</span>
            </div>
            <?php if($comment_count > 0): ?><span class="bg-accent text-white text-[10px] font-black px-2 py-0.5 rounded-full ml-1 shadow-md"><?php echo $comment_count; ?></span><?php endif; ?>
        </button>
        
        <a href="<?php echo $next_chap ? get_permalink($next_chap->ID) : '#'; ?>" class="flex-1 flex items-center justify-center gap-2 text-gray-400 hover:text-white transition h-full <?php echo !$next_chap ? 'opacity-30 pointer-events-none' : ''; ?>">
            <span class="text-[12px] font-bold uppercase tracking-wider hidden sm:block">Next</span>
            <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </nav>


    <div id="modal-overlay" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-[100] hidden opacity-0 transition-opacity duration-300" onclick="window.closeAllModals()"></div>

    <div id="drawer-comments" class="drawer-bottom">
        <div class="flex justify-between items-center p-5 border-b border-white/5 bg-surface2 shrink-0 md:pt-[env(safe-area-inset-top)] rounded-t-3xl md:rounded-none">
            <h2 class="font-bold text-white flex items-center gap-2">Comments <span class="bg-surface text-gray-400 text-xs px-2 py-0.5 rounded-full"><?php echo $comment_count; ?></span></h2>
            <button onclick="window.closeAllModals()" class="p-2 bg-surface rounded-full text-gray-400 hover:text-white"><svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        
        <div id="chat-feed-container" class="flex-1 overflow-y-auto no-scrollbar bg-bg p-2 overscroll-contain"></div>
        
        <div class="p-3 bg-surface2 border-t border-white/5 shrink-0 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] w-full">
            <?php if (is_user_logged_in()) { ?>
                <form id="ajax-chat-form" onsubmit="event.preventDefault(); window.submitChat();" class="flex gap-2">
                    <input type="hidden" id="chat_comment_post_ID" value="<?php echo $current_post_id; ?>" />
                    <input type="text" id="chat-input-box" placeholder="Write a comment..." class="flex-1 bg-bg border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-accent shadow-inner transition w-full">
                    <button type="submit" class="bg-accent text-white px-5 py-3 rounded-xl font-bold hover:bg-purple-500 transition shadow-lg flex items-center justify-center shrink-0">
                        <svg style="width:20px;height:20px;" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </form>
            <?php } else { ?>
                <div class="text-center p-4 bg-surface rounded-xl border border-white/5"><a href="/auth" class="text-accent text-sm font-bold hover:underline">Log in to join the conversation.</a></div>
            <?php } ?>
        </div>
    </div>

    <div id="drawer-chapters" class="drawer-side">
        <div class="p-5 border-b border-white/5 bg-surface2 pt-[env(safe-area-inset-top)] shrink-0 relative">
            <button onclick="window.closeAllModals()" class="absolute top-4 right-4 p-2 bg-surface rounded-full text-gray-400 hover:text-white z-10">
                <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            
            <div class="flex gap-4 items-center mt-2">
                <?php if($manga_thumb): ?>
                    <img src="<?php echo esc_url($manga_thumb); ?>" class="w-16 h-24 object-cover rounded shadow-md border border-white/10 shrink-0" alt="Cover">
                <?php else: ?>
                    <div class="w-16 h-24 bg-surface rounded shadow-md border border-white/10 shrink-0 flex items-center justify-center text-xs text-gray-500">No Img</div>
                <?php endif; ?>
                
                <div class="flex flex-col">
                    <h2 class="font-bold text-white text-sm line-clamp-2 mb-1"><?php echo esc_html($manga_title); ?></h2>
                    <span class="text-[11px] text-accent font-bold mb-2"><?php echo $total_chapters; ?> Chapters Total</span>
                    <a href="<?php echo esc_url($manga_permalink); ?>" class="text-[10px] font-bold bg-white/10 hover:bg-white/20 text-white px-3 py-1.5 rounded w-max transition">View Series Info</a>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-2 no-scrollbar pb-[env(safe-area-inset-bottom)]">
            <?php foreach($all_chaps as $ch) { $act = ($ch->ID == $current_post_id); ?>
                <a href="<?php echo get_permalink($ch->ID); ?>" class="flex justify-between items-center p-4 rounded-xl <?php echo $act?'bg-accent/10 border border-accent/20':'hover:bg-white/5'; ?> transition">
                    <div class="flex flex-col">
                        <span class="text-sm font-bold <?php echo $act?'text-accent':'text-gray-300'; ?>"><?php echo get_the_title($ch->ID); ?></span>
                    </div>
                    <?php if($act): ?><span class="text-[9px] bg-accent text-white px-2 py-0.5 rounded font-bold uppercase tracking-wider">Reading</span><?php endif; ?>
                </a>
            <?php } ?>
        </div>
    </div>

    <div id="modal-settings" class="modal-panel">
        <div class="flex justify-between items-center p-5 border-b border-white/5 pt-[env(safe-area-inset-top)] bg-surface2 shrink-0">
            <h2 class="font-bold text-white text-lg">Reader Settings</h2>
            <button onclick="window.closeAllModals()" class="p-2 text-gray-400 hover:text-white bg-surface rounded-full"><svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        
        <div class="flex px-4 border-b border-white/5 text-[13px] font-bold text-gray-400 overflow-x-auto no-scrollbar shrink-0 bg-surface2">
            <button onclick="window.switchTab('tab-main')" id="btn-tab-main" class="px-5 py-4 border-b-2 border-accent text-white whitespace-nowrap transition-colors">Main</button>
            <button onclick="window.switchTab('tab-style')" id="btn-tab-style" class="px-5 py-4 border-b-2 border-transparent hover:text-white whitespace-nowrap transition-colors">Style</button>
            <button onclick="window.switchTab('tab-perf')" id="btn-tab-perf" class="px-5 py-4 border-b-2 border-transparent hover:text-white whitespace-nowrap transition-colors">Engine</button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-8 bg-[#0a0a0f] pb-[env(safe-area-inset-bottom)]">
            <div id="tab-main" class="space-y-6 block">
                <div class="space-y-3">
                    <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">Reading Mode</span>
                    <div class="bg-surface2 p-1 rounded-xl flex">
                        <button onclick="window.setMode('scroll')" id="btn-mode-scroll" class="flex-1 py-3 text-sm font-bold bg-accent text-white rounded-lg transition shadow-md">Vertical Scroll</button>
                        <button onclick="window.setMode('single')" id="btn-mode-single" class="flex-1 py-3 text-sm font-bold text-gray-400 hover:text-white rounded-lg transition">Single Page</button>
                    </div>
                </div>

                <div class="space-y-3">
                    <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">Zoom Base</span>
                    <div class="bg-surface2 p-1 rounded-xl flex">
                        <button onclick="window.setZoomBase('screen')" id="btn-base-screen" class="flex-1 py-3 text-sm font-bold bg-accent text-white rounded-lg transition shadow-md">Screen Fit</button>
                        <button onclick="window.setZoomBase('image')" id="btn-base-image" class="flex-1 py-3 text-sm font-bold text-gray-400 hover:text-white rounded-lg transition">Original Image</button>
                    </div>
                </div>

                <div id="single-mode-options" class="hidden space-y-3">
                    <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">Single Page Fit</span>
                    <div class="bg-surface2 p-1 rounded-xl flex">
                        <button onclick="window.setFit('contain')" id="btn-fit-contain" class="flex-1 py-3 text-xs font-bold bg-accent text-white rounded-lg transition shadow-md">Best Fit</button>
                        <button onclick="window.setFit('width')" id="btn-fit-width" class="flex-1 py-3 text-xs font-bold text-gray-400 hover:text-white rounded-lg transition">Width</button>
                        <button onclick="window.setFit('height')" id="btn-fit-height" class="flex-1 py-3 text-xs font-bold text-gray-400 hover:text-white rounded-lg transition">Height</button>
                    </div>
                </div>

                <div class="bg-surface2 p-6 rounded-2xl border border-white/5 space-y-6">
                    <div>
                        <div class="flex justify-between mb-4"><span class="font-bold text-white text-sm">Zoom Amount</span><span id="zoom-val" class="font-bold text-accent text-sm">100%</span></div>
                        <input type="range" min="50" max="250" value="100" oninput="window.setZoom(this.value)" id="slider-zoom" class="pill-slider mb-5">
                        <button onclick="window.resetZoom()" class="w-full py-3 bg-surface border border-white/10 rounded-xl text-xs font-bold text-gray-400 hover:text-white transition">Reset Zoom to 100%</button>
                    </div>
                    <div class="w-full h-px bg-white/5"></div>
                    <div>
                        <div class="flex justify-between mb-4"><span class="font-bold text-white text-sm">Brightness</span><span id="bright-val" class="font-bold text-accent text-sm">100%</span></div>
                        <input type="range" min="20" max="100" value="100" id="slider-brightness" oninput="window.setBrightness(this.value)" class="pill-slider">
                    </div>
                </div>

                <div class="flex justify-between items-center bg-surface2 p-5 rounded-2xl border border-white/5">
                    <div><span class="text-sm font-bold text-white block">Page Gaps</span><span class="text-[10px] text-gray-500">Space between images</span></div>
                    <div class="relative inline-block w-12 align-middle select-none">
                        <input type="checkbox" id="toggle-gaps" onchange="window.toggleGaps(this.checked)" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 border-surface2 appearance-none cursor-pointer"/>
                        <label class="toggle-label block overflow-hidden h-6 rounded-full bg-surface2 cursor-pointer border border-white/10"></label>
                    </div>
                </div>
            </div>

            <div id="tab-style" class="space-y-6 hidden">
                <div class="space-y-3">
                    <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">Background Color</span>
                    <div class="flex gap-4">
                        <button onclick="window.setBg('#08080c')" id="bg-btn-0" class="w-16 h-16 rounded-full bg-[#08080c] border-2 border-accent shadow-lg ring-4 ring-accent/20 transition"></button>
                        <button onclick="window.setBg('#1a1a1a')" id="bg-btn-1" class="w-16 h-16 rounded-full bg-[#1a1a1a] border-2 border-transparent hover:border-white/20 transition"></button>
                        <button onclick="window.setBg('#ffffff')" id="bg-btn-2" class="w-16 h-16 rounded-full bg-[#ffffff] border-2 border-gray-300 hover:border-gray-400 transition"></button>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <div class="flex justify-between items-center bg-surface2 p-5 rounded-2xl border border-white/5">
                        <div><span class="text-sm font-bold text-white block">Grayscale</span><span class="text-[10px] text-gray-500">Remove colors</span></div>
                        <div class="relative inline-block w-12 align-middle select-none">
                            <input type="checkbox" id="toggle-grayscale" onchange="window.toggleGrayscale(this.checked)" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 border-surface2 appearance-none cursor-pointer"/>
                            <label class="toggle-label block overflow-hidden h-6 rounded-full bg-surface2 cursor-pointer border border-white/10"></label>
                        </div>
                    </div>
                    <div class="flex justify-between items-center bg-surface2 p-5 rounded-2xl border border-white/5">
                        <div><span class="text-sm font-bold text-white block">Invert Colors</span><span class="text-[10px] text-gray-500">Dark mode for light scans</span></div>
                        <div class="relative inline-block w-12 align-middle select-none">
                            <input type="checkbox" id="toggle-invert" onchange="window.toggleInvert(this.checked)" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 border-surface2 appearance-none cursor-pointer"/>
                            <label class="toggle-label block overflow-hidden h-6 rounded-full bg-surface2 cursor-pointer border border-white/10"></label>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-perf" class="space-y-6 hidden">
                <div class="flex justify-between items-center bg-surface2 p-5 rounded-2xl border border-white/5">
                    <div><span class="text-sm font-bold text-white block">Data Saver Mode</span><span class="text-[10px] text-gray-500">Request low-res images</span></div>
                    <div class="relative inline-block w-12 align-middle select-none">
                        <input type="checkbox" id="toggle-data-saver" onchange="window.toggleDataSaver(this.checked)" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 border-surface2 appearance-none cursor-pointer"/>
                        <label class="toggle-label block overflow-hidden h-6 rounded-full bg-surface2 cursor-pointer border border-white/10"></label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const imagesList = <?php echo json_encode($encoded_images); ?>;
    const readerContent = document.getElementById('reader-content');
    const slider = document.getElementById('page-slider');
    const currentPageText = document.getElementById('page-current');
    const root = document.documentElement;
    
    let uiVisible = true;
    let autoScrollInterval = null;
    let totalImages = imagesList.length;

    let readerSettings = JSON.parse(localStorage.getItem('kagane_settings')) || { 
        mode: 'scroll', fit: 'contain', zoomBase: 'screen', zoom: 100, brightness: 100, 
        gaps: false, bg: '#08080c', grayscale: false, invert: false, dataSaver: false 
    };
    function saveSettings() { localStorage.setItem('kagane_settings', JSON.stringify(readerSettings)); }

    // --- WORKING NATIVE OBSERVER ---
    class ReaderEngine {
        constructor(urls) {
            this.urls = urls; this.domElements = []; this.activeLoads = 0; this.maxConcurrent = 5; this.currentIndex = 0;
            this.initDOM(); this.setupObserver(); this.startQueue();
        }
        initDOM() {
            this.urls.forEach((url, i) => {
                let wrapper = document.createElement('div'); wrapper.className = 'image-wrapper'; wrapper.id = `wrapper-page-${i + 1}`;
                if(i===0) wrapper.classList.add('active');
                let loader = document.createElement('div'); loader.className = 'loader-spin';
                let img = document.createElement('img'); img.className = 'reader-img opacity-0 transition-opacity duration-300 relative z-10'; img.id = `img-page-${i + 1}`; img.decoding = 'async'; img.loading = i < 3 ? 'eager' : 'lazy';
                wrapper.appendChild(loader); wrapper.appendChild(img); readerContent.appendChild(wrapper);
                this.domElements.push({ wrapper, img, loader, loaded: false, url: url });
            });
        }
        setupObserver() {
            this.progressObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && entry.intersectionRatio > 0.3) {
                        const pageNum = parseInt(entry.target.id.replace('wrapper-page-', ''));
                        if(slider) slider.value = pageNum;
                        if(currentPageText) currentPageText.innerText = pageNum;
                        if (!this.domElements[pageNum - 1].loaded) this.loadSpecific(pageNum - 1);
                    }
                });
            }, { root: null, rootMargin: '0px', threshold: 0.3 });
            this.domElements.forEach(item => this.progressObserver.observe(item.wrapper));
        }
        startQueue() { while (this.activeLoads < this.maxConcurrent && this.currentIndex < this.urls.length) { this.loadNextInQueue(); } }
        loadNextInQueue() { if (this.currentIndex >= this.urls.length) return; this.loadSpecific(this.currentIndex++); }
        loadSpecific(idx) {
            let el = this.domElements[idx]; if (el.loaded) return;
            this.activeLoads++; el.loaded = true;
            let targetUrl = el.url;
            if (readerSettings.dataSaver) targetUrl += "&quality=low";

            let preloader = new Image();
            preloader.onload = () => { el.img.src = targetUrl; el.img.classList.remove('opacity-0'); if (el.loader) el.loader.style.display = 'none'; this.activeLoads--; this.startQueue(); };
            preloader.onerror = () => { el.loaded = false; this.activeLoads--; this.startQueue(); };
            preloader.src = targetUrl;
        }
    }
    if(totalImages > 0) window.readerEngine = new ReaderEngine(imagesList); 

    // --- UI TOGGLING ---
    window.toggleUI = () => {
        uiVisible = !uiVisible;
        const topNav = document.getElementById('top-nav');
        const scrubberNav = document.getElementById('scrubber-nav');
        const sideFabs = document.getElementById('side-fabs');
        const bottomNav = document.getElementById('persistent-bottom-nav');
        
        if (uiVisible) { 
            topNav.classList.remove('ui-hidden'); scrubberNav.classList.remove('ui-hidden'); sideFabs.classList.remove('ui-hidden'); bottomNav.classList.remove('ui-hidden');
        } else { 
            topNav.classList.add('ui-hidden'); scrubberNav.classList.add('ui-hidden'); sideFabs.classList.add('ui-hidden'); bottomNav.classList.add('ui-hidden');
        }
    };

    document.body.addEventListener('click', (e) => {
        if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input') || 
            e.target.closest('.modal-panel') || e.target.closest('.drawer-bottom') || 
            e.target.closest('.drawer-side') || e.target.closest('#persistent-bottom-nav') || 
            e.target.closest('#scrubber-nav') || e.target.closest('#top-nav') || e.target.closest('#end-chapter-flow')) return;
        window.toggleUI(); 
    });

    // --- MODALS & DRAWERS ---
    const modalOverlay = document.getElementById('modal-overlay');
    window.openModal = (id) => {
        modalOverlay.classList.remove('hidden'); setTimeout(() => modalOverlay.classList.remove('opacity-0'), 10);
        document.getElementById(id).classList.add('open');
        if(id === 'drawer-comments') window.fetchLiveChats();
    };
    window.closeAllModals = () => {
        modalOverlay.classList.add('opacity-0'); setTimeout(() => modalOverlay.classList.add('hidden'), 300);
        document.querySelectorAll('.modal-panel, .drawer-side, .drawer-bottom').forEach(el => el.classList.remove('open'));
    };

    // --- PURE NATIVE SCROLL BEHAVIOR ---
    window.scrollToPage = (pageNum) => {
        pageNum = parseInt(pageNum); if(pageNum < 1) pageNum = 1; if(pageNum > totalImages) pageNum = totalImages;
        if(slider) slider.value = pageNum; if(currentPageText) currentPageText.innerText = pageNum;
        
        if (readerSettings.mode === 'scroll') {
            const target = document.getElementById(`wrapper-page-${pageNum}`);
            if(target) window.scrollTo({ top: target.offsetTop, behavior: 'smooth' });
        } else {
            document.querySelectorAll('.image-wrapper').forEach(el => el.classList.remove('active'));
            document.getElementById(`wrapper-page-${pageNum}`).classList.add('active');
            window.scrollTo(0,0);
        }
    };

    // --- APPLY FULL SETTINGS ---
    const appWrapper = document.getElementById('kagane-app');
    
    window.setMode = (mode) => {
        readerSettings.mode = mode; saveSettings();
        document.getElementById('btn-mode-scroll').className = mode==='scroll' ? 'flex-1 py-3 text-sm font-bold bg-accent text-white rounded-lg' : 'flex-1 py-3 text-sm font-bold text-gray-400';
        document.getElementById('btn-mode-single').className = mode==='single' ? 'flex-1 py-3 text-sm font-bold bg-accent text-white rounded-lg' : 'flex-1 py-3 text-sm font-bold text-gray-400';
        if (mode === 'single') { document.body.classList.add('single-mode'); document.getElementById('single-mode-options').classList.remove('hidden'); } else { document.body.classList.remove('single-mode'); document.getElementById('single-mode-options').classList.add('hidden'); }
        window.scrollToPage(slider.value);
    };
    
    window.setFit = (fit) => {
        readerSettings.fit = fit; saveSettings();
        document.body.classList.remove('fit-contain', 'fit-width', 'fit-height'); document.body.classList.add(`fit-${fit}`);
        ['contain', 'width', 'height'].forEach(f => { document.getElementById(`btn-fit-${f}`).className = f === fit ? 'flex-1 py-3 text-xs font-bold bg-accent text-white rounded-lg' : 'flex-1 py-3 text-xs font-bold text-gray-400'; });
    };

    window.setZoomBase = (base) => {
        readerSettings.zoomBase = base; saveSettings();
        appWrapper.classList.remove('base-screen', 'base-image'); appWrapper.classList.add(`base-${base}`);
        document.getElementById('btn-base-screen').className = base === 'screen' ? 'flex-1 py-3 text-sm font-bold bg-accent text-white rounded-lg' : 'flex-1 py-3 text-sm font-bold text-gray-400';
        document.getElementById('btn-base-image').className = base === 'image' ? 'flex-1 py-3 text-sm font-bold bg-accent text-white rounded-lg' : 'flex-1 py-3 text-sm font-bold text-gray-400';
    };
    
    window.setZoom = (val) => {
        let nZoom = parseInt(val); readerSettings.zoom = nZoom; saveSettings();
        document.getElementById('zoom-val').innerText = nZoom + '%';
        root.style.setProperty('--reader-zoom', `${nZoom}%`); root.style.setProperty('--reader-max-width', nZoom > 100 ? 'none' : '800px');
    };
    window.zoomReader = (amount) => { let nZoom = Math.max(50, Math.min(250, readerSettings.zoom + amount)); document.getElementById('slider-zoom').value = nZoom; window.setZoom(nZoom); };
    window.resetZoom = () => { document.getElementById('slider-zoom').value = 100; window.setZoom(100); };
    
    window.setBrightness = (val) => {
        readerSettings.brightness = parseInt(val); saveSettings();
        root.style.setProperty('--reader-brightness', `${val}%`); document.getElementById('bright-val').innerText = val + '%';
    };

    window.toggleGaps = (isActive) => {
        readerSettings.gaps = isActive; saveSettings();
        if(isActive) appWrapper.classList.replace('no-gap-mode', 'gap-mode'); else appWrapper.classList.replace('gap-mode', 'no-gap-mode');
    };

    window.setBg = (color) => {
        readerSettings.bg = color; saveSettings(); root.style.setProperty('--reader-bg', color);
        [0,1,2].forEach(i => document.getElementById(`bg-btn-${i}`).classList.remove('ring-4', 'ring-accent/20', 'border-accent'));
        let activeId = color === '#08080c' ? 0 : color === '#1a1a1a' ? 1 : 2;
        document.getElementById(`bg-btn-${activeId}`).classList.add('ring-4', 'ring-accent/20', 'border-accent');
    };

    window.toggleGrayscale = (state) => { readerSettings.grayscale = state; saveSettings(); if(state) appWrapper.classList.add('style-grayscale'); else appWrapper.classList.remove('style-grayscale'); };
    window.toggleInvert = (state) => { readerSettings.invert = state; saveSettings(); if(state) appWrapper.classList.add('style-invert'); else appWrapper.classList.remove('style-invert'); };
    window.toggleDataSaver = (state) => { readerSettings.dataSaver = state; saveSettings(); };

    window.toggleAutoScroll = () => {
        if (!autoScrollInterval && readerSettings.mode === 'scroll') { autoScrollInterval = setInterval(() => { window.scrollBy(0, 1.5); }, 15); if(uiVisible) window.toggleUI(); } 
        else { clearInterval(autoScrollInterval); autoScrollInterval = null; }
    };
    window.toggleFullscreen = () => { if (!document.fullscreenElement) { document.documentElement.requestFullscreen().catch(e=>{}); } else { if (document.exitFullscreen) document.exitFullscreen(); } };

    window.switchTab = (tabId) => {
        ['tab-main', 'tab-style', 'tab-perf'].forEach(id => { document.getElementById(id).style.display = 'none'; document.getElementById('btn-' + id).classList.remove('border-accent', 'text-white'); document.getElementById('btn-' + id).classList.add('border-transparent'); });
        document.getElementById(tabId).style.display = 'block'; document.getElementById('btn-' + tabId).classList.add('border-accent', 'text-white');
    };

    // --- INIT ---
    window.initSettings = () => {
        window.setMode(readerSettings.mode); window.setFit(readerSettings.fit); window.setZoomBase(readerSettings.zoomBase);
        document.getElementById('slider-zoom').value = readerSettings.zoom; window.setZoom(readerSettings.zoom);
        document.getElementById('slider-brightness').value = readerSettings.brightness; window.setBrightness(readerSettings.brightness);
        document.getElementById('toggle-gaps').checked = readerSettings.gaps; window.toggleGaps(readerSettings.gaps);
        window.setBg(readerSettings.bg);
        document.getElementById('toggle-grayscale').checked = readerSettings.grayscale; window.toggleGrayscale(readerSettings.grayscale);
        document.getElementById('toggle-invert').checked = readerSettings.invert; window.toggleInvert(readerSettings.invert);
        document.getElementById('toggle-data-saver').checked = readerSettings.dataSaver; window.toggleDataSaver(readerSettings.dataSaver);
        
        setTimeout(() => { if (uiVisible) window.toggleUI(); }, 2000);
    };
    window.initSettings();

    // --- EMBEDDED AJAX CHAT ---
    window.fetchLiveChats = async () => {
        const fd = new FormData(); fd.append('fetch_live_chats', '1'); fd.append('chapter_id', <?php echo $current_post_id; ?>);
        try { const res = await fetch(window.location.href, {method: 'POST', body: fd}); document.getElementById('chat-feed-container').innerHTML = await res.text(); } catch(e) {}
    };
    window.submitChat = async () => {
        const box = document.getElementById('chat-input-box'); if(box.value.trim() === '') return;
        const fd = new FormData(); fd.append('custom_chat_submit', '1'); fd.append('comment_post_ID', <?php echo $current_post_id; ?>); fd.append('comment_content', box.value);
        box.value = ''; await fetch(window.location.href, {method: 'POST', body: fd}); window.fetchLiveChats();
    };
</script>

<?php get_footer(); ?>