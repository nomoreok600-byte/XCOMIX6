<?php
/**
 * Template Name: User Profile
 * X COMIX - Elite App Master Profile Dashboard (Separated Folder System)
 */

if (!is_user_logged_in()) {
    wp_redirect(site_url('/auth'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// =========================================================================
// INLINE AJAX: SEAMLESS FOLDER SAVING
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['xcomix_inline_ajax'])) {
    if ($_POST['action'] === 'set_folder') {
        $manga_id = intval($_POST['manga_id']);
        $f_id = sanitize_text_field($_POST['folder_id']);
        $manga_folders = get_user_meta($user_id, '_xcomix_manga_folders', true) ?: [];
        
        if ($f_id === 'none') {
            unset($manga_folders[$manga_id]);
        } else {
            $manga_folders[$manga_id] = $f_id;
        }
        
        update_user_meta($user_id, '_xcomix_manga_folders', $manga_folders);
        echo json_encode(['success' => true]);
        exit;
    }
}

$success_msg = '';
$error_msg = '';

// =========================================================================
// CUSTOM FOLDER CREATION & DELETION LOGIC
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['xcomix_folder_action'])) {
    $action = $_POST['xcomix_folder_action'];
    $existing_custom = get_user_meta($user_id, '_xcomix_custom_folders', true) ?: [];
    
    if ($action === 'add') {
        $folder_name = sanitize_text_field($_POST['folder_name']);
        if (!empty($folder_name)) {
            $folder_id = 'folder_' . substr(md5(time() . $folder_name), 0, 8);
            $existing_custom[$folder_id] = $folder_name;
            update_user_meta($user_id, '_xcomix_custom_folders', $existing_custom);
            wp_redirect(get_permalink() . '#bookmarks');
            exit;
        }
    } elseif ($action === 'delete') {
        $folder_id = sanitize_text_field($_POST['folder_id']);
        if (isset($existing_custom[$folder_id])) {
            unset($existing_custom[$folder_id]);
            update_user_meta($user_id, '_xcomix_custom_folders', $existing_custom);
            
            // Clean up manga that were assigned to the deleted folder
            $manga_folders = get_user_meta($user_id, '_xcomix_manga_folders', true) ?: [];
            $changed = false;
            foreach ($manga_folders as $m_id => $f_id) {
                if ($f_id === $folder_id) {
                    unset($manga_folders[$m_id]);
                    $changed = true;
                }
            }
            if ($changed) update_user_meta($user_id, '_xcomix_manga_folders', $manga_folders);
            
            wp_redirect(get_permalink() . '#settings');
            exit;
        }
    }
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
// HANDLE PROFILE & PREFERENCE UPDATES
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile']) && wp_verify_nonce($_POST['profile_nonce'], 'update_profile_action')) {
    if (isset($_POST['clear_history']) && $_POST['clear_history'] === '1') {
        delete_user_meta($user_id, '_xcomix_history');
        delete_user_meta($user_id, '_mv_history');
        $success_msg = "Reading history permanently wiped.";
    } else {
        if (!empty($_POST['display_name']) && $_POST['display_name'] !== $current_user->display_name) {
            wp_update_user(['ID' => $user_id, 'display_name' => sanitize_text_field($_POST['display_name'])]);
            $success_msg = "Profile updated successfully!";
        }
        if (isset($_POST['avatar_url'])) {
            update_user_meta($user_id, '_xcomix_avatar', esc_url_raw($_POST['avatar_url']));
            $success_msg = "Profile updated successfully!";
        }

        update_user_meta($user_id, '_xcomix_pref_nsfw', isset($_POST['pref_nsfw']) ? '1' : '0');
        update_user_meta($user_id, '_xcomix_pref_notifications', isset($_POST['pref_notifications']) ? '1' : '0');
        update_user_meta($user_id, '_xcomix_pref_datasaver', isset($_POST['pref_datasaver']) ? '1' : '0');
        update_user_meta($user_id, '_xcomix_pref_zenmode', isset($_POST['pref_zenmode']) ? '1' : '0');
        
        if (!empty($_POST['new_password'])) {
            if ($_POST['new_password'] === $_POST['confirm_password']) {
                wp_set_password($_POST['new_password'], $user_id);
                $creds = ['user_login' => $current_user->user_login, 'user_password' => $_POST['new_password'], 'remember' => true];
                wp_signon($creds, false);
                $success_msg = "Password and settings saved!";
            } else {
                $error_msg = "Passwords do not match.";
            }
        }
    }
    $current_user = wp_get_current_user(); 
}

// =========================================================================
// FETCH USER DATA & FOLDERS
// =========================================================================
$username = $current_user->display_name;
$email = $current_user->user_email;
$days_active = max(1, floor((time() - strtotime($current_user->user_registered)) / (60 * 60 * 24)));

$pref_nsfw = get_user_meta($user_id, '_xcomix_pref_nsfw', true) === '1';
$pref_notifications = get_user_meta($user_id, '_xcomix_pref_notifications', true) !== '0'; 
$pref_datasaver = get_user_meta($user_id, '_xcomix_pref_datasaver', true) === '1';
$pref_zenmode = get_user_meta($user_id, '_xcomix_pref_zenmode', true) === '1';

$custom_avatar = get_user_meta($user_id, '_xcomix_avatar', true);
$avatar_url = !empty($custom_avatar) ? esc_url($custom_avatar) : esc_url(get_avatar_url($user_id));

// Library Data
$bookmark_ids = array_values(array_unique(array_merge(
    get_user_meta($user_id, '_xcomix_bookmarks', true) ?: [],
    get_user_meta($user_id, '_mv_bookmarks', true) ?: []
)));
$reading_plans = array_merge(
    get_user_meta($user_id, '_mv_reading_plans', true) ?: [],
    get_user_meta($user_id, '_xcomix_reading_plans', true) ?: []
);
$manga_folders = get_user_meta($user_id, '_xcomix_manga_folders', true) ?: [];
$custom_folders = get_user_meta($user_id, '_xcomix_custom_folders', true) ?: [];
$base_plan_labels = ['reading' => 'Reading', 'plan' => 'Plan to Read', 'completed' => 'Completed', 'dropped' => 'Dropped'];

// History Data
$raw_history = mvx_user_history($user_id);
$theme_history = get_user_meta($user_id, 'wp_manga_history', true);
if (is_array($theme_history)) {
    foreach ($theme_history as $m_id => $data) {
        if (!isset($raw_history[$m_id])) {
            $raw_history[$m_id] = [
                'id' => $m_id,
                'chapter_num' => is_array($data) && isset($data['c']) ? preg_replace('/[^0-9.]/', '', $data['c']) : '1',
                'url' => get_permalink($m_id),
                'timestamp' => is_array($data) && isset($data['t']) ? $data['t'] : time() - 86400
            ];
        }
    }
}
if (!empty($raw_history)) { uasort($raw_history, function($a, $b) { return $b['timestamp'] <=> $a['timestamp']; }); }
$history_data = $raw_history;

// =========================================================================
// HARDCORE RPG LEVELING & DYNAMIC BADGES
// =========================================================================
$total_chapters_read = count($history_data);
$user_comment_count = get_comments(['user_id' => $user_id, 'count' => true]);

$total_xp = $total_chapters_read + ($user_comment_count * 5);
$user_level = floor(sqrt($total_xp / 3)) + 1;

$xp_current_tier = 3 * pow($user_level - 1, 2);
$xp_next_tier = 3 * pow($user_level, 2);
$xp_progress_percent = (($total_xp - $xp_current_tier) / max(1, ($xp_next_tier - $xp_current_tier))) * 100;
$xp_progress_percent = min(100, max(0, $xp_progress_percent));

if ($user_level < 10) { 
    $unified_rank = "Novice Reader"; $rank_color = "from-gray-400 to-gray-600"; $rank_icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>';
} elseif ($user_level < 25) { 
    $unified_rank = "Adept Scroller"; $rank_color = "from-blue-400 to-blue-600"; $rank_icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>';
} elseif ($user_level < 50) { 
    $unified_rank = "Elite Weeb"; $rank_color = "from-[#ea580c] to-[#f59e0b]"; $rank_icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/>';
} elseif ($user_level < 80) { 
    $unified_rank = "Manga Lord"; $rank_color = "from-purple-500 to-pink-500"; $rank_icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>';
} else { 
    $unified_rank = "God Tier"; $rank_color = "from-yellow-400 to-yellow-600"; $rank_icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>';
}

$bookmarks_query = null;
if (!empty($bookmark_ids)) {
    $bookmarks_query = new WP_Query(['post_type' => 'manga', 'post__in' => $bookmark_ids, 'posts_per_page' => -1]);
}

$new_updates_count = 0;
$recent_chapters = null;
if (!empty($bookmark_ids)) {
    $recent_chapters = new WP_Query([
        'post_type' => 'chapter',
        'post_parent__in' => array_map('intval', $bookmark_ids),
        'posts_per_page' => 5,
        'orderby' => 'date', 'order' => 'DESC'
    ]);
    $new_updates_count = $recent_chapters->found_posts > 5 ? '5+' : $recent_chapters->found_posts;
}

get_header(); 
?>

    <main class="max-w-[1050px] mx-auto px-4 pt-[85px] pb-24 relative bg-[#09090b] min-h-screen selection:bg-[#ea580c] selection:text-white">
        
        <?php if (!empty($success_msg)) : ?>
            <div class="bg-[#22c55e]/10 border border-[#22c55e]/20 text-[#22c55e] text-[11px] font-black uppercase tracking-widest p-4 rounded-xl mb-6 text-center shadow-lg flex items-center justify-center gap-2 animate-[fadeIn_0.3s_ease] z-50 relative">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <?php echo esc_html($success_msg); ?>
            </div>
        <?php endif; ?>

        <header class="flex items-center justify-between mb-8 mt-2">
            <div class="flex items-center gap-4 w-full md:w-auto">
                <div class="relative w-16 h-16 rounded-full bg-gradient-to-tr <?php echo $rank_color; ?> p-0.5 shadow-[0_0_20px_rgba(0,0,0,0.5)] shrink-0 group">
                    <img src="<?php echo esc_url($avatar_url); ?>" onerror="this.src='https://placehold.co/100x100/1a1a1a/444444?text=<?php echo substr($username, 0, 1); ?>'" class="w-full h-full rounded-full border-[3px] border-[#121212] object-cover bg-[#1a1a1a]">
                    <span class="absolute bottom-0 right-0 w-4 h-4 bg-green-500 border-[3px] border-[#121212] rounded-full z-20 shadow-[0_0_5px_#22c55e]"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-[20px] md:text-[24px] font-black text-white leading-tight drop-shadow-md truncate"><?php echo esc_html($username); ?></h1>
                    <div class="flex flex-wrap items-center gap-2 mt-1 mb-1.5">
                        <span class="flex items-center gap-1 bg-gradient-to-r <?php echo $rank_color; ?> text-white text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-md shadow-md">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?php echo $rank_icon; ?></svg>
                            Lvl <?php echo $user_level; ?> • <?php echo $unified_rank; ?>
                        </span>
                        <span class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                            <?php echo $total_xp; ?> / <?php echo $xp_next_tier; ?> XP
                        </span>
                    </div>
                    <div class="w-full max-w-[220px] h-1.5 bg-[#1a1a1a] rounded-full overflow-hidden border border-white/5 shadow-inner">
                        <div class="h-full bg-gradient-to-r <?php echo $rank_color; ?> rounded-full transition-all duration-1000" style="width: <?php echo $xp_progress_percent; ?>%;"></div>
                    </div>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">
            <div class="bg-[#1a1a1a] border border-white/5 rounded-2xl p-4 text-center shadow-lg relative overflow-hidden group hover:border-[#3b82f6]/50 transition">
                <div class="absolute -right-4 -top-4 w-16 h-16 bg-[#3b82f6] rounded-full blur-[30px] opacity-10 group-hover:opacity-20 transition pointer-events-none"></div>
                <span class="block text-2xl font-black text-white drop-shadow-md relative z-10"><?php echo $total_chapters_read; ?></span>
                <span class="text-[9px] font-black text-gray-500 uppercase tracking-widest relative z-10">Chapters Read</span>
            </div>
            <div class="bg-[#1a1a1a] border border-white/5 rounded-2xl p-4 text-center shadow-lg relative overflow-hidden group hover:border-[#ea580c]/50 transition">
                <div class="absolute -bottom-4 -left-4 w-16 h-16 bg-[#ea580c] rounded-full blur-[30px] opacity-10 group-hover:opacity-20 transition pointer-events-none"></div>
                <span class="block text-2xl font-black text-[#ea580c] drop-shadow-[0_0_10px_rgba(234,88,12,0.3)] relative z-10"><?php echo count($bookmark_ids); ?></span>
                <span class="text-[9px] font-black text-gray-500 uppercase tracking-widest relative z-10">Saved Library</span>
            </div>
            <div class="bg-[#1a1a1a] border border-white/5 rounded-2xl p-4 text-center shadow-lg relative overflow-hidden group hover:border-[#a855f7]/50 transition">
                <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-[#a855f7] rounded-full blur-[30px] opacity-10 group-hover:opacity-20 transition pointer-events-none"></div>
                <span class="block text-2xl font-black text-[#a855f7] drop-shadow-[0_0_10px_rgba(168,85,247,0.3)] relative z-10"><?php echo $user_comment_count; ?></span>
                <span class="text-[9px] font-black text-gray-500 uppercase tracking-widest relative z-10">Comments</span>
            </div>
            <div class="bg-[#1a1a1a] border border-white/5 rounded-2xl p-4 text-center shadow-lg relative overflow-hidden group hover:border-[#22c55e]/50 transition">
                <div class="absolute -top-4 -left-4 w-16 h-16 bg-[#22c55e] rounded-full blur-[30px] opacity-10 group-hover:opacity-20 transition pointer-events-none"></div>
                <span class="block text-2xl font-black text-[#22c55e] drop-shadow-[0_0_10px_rgba(34,197,94,0.3)] relative z-10"><?php echo $days_active; ?></span>
                <span class="text-[9px] font-black text-gray-500 uppercase tracking-widest relative z-10">Days Active</span>
            </div>
        </div>

        <section class="mb-8">
            <h2 class="text-[13px] font-black text-gray-400 uppercase tracking-widest mb-3 px-1 flex items-center gap-2">
                <span class="w-1.5 h-4 bg-[#ea580c] rounded-full shadow-[0_0_10px_#ea580c]"></span> New Chapter Alerts
            </h2>
            <div class="bg-[#1a1a1a] rounded-2xl border border-white/5 p-2 shadow-xl">
                <?php 
                if (!empty($bookmark_ids) && $recent_chapters && $recent_chapters->have_posts()) {
                    while ($recent_chapters->have_posts()) : $recent_chapters->the_post(); ?>
                        <a href="<?php the_permalink(); ?>" class="flex items-center gap-4 p-3 hover:bg-[#222] rounded-xl transition group">
                            <div class="w-10 h-10 rounded-xl bg-[#121212] flex items-center justify-center text-[#ea580c] shrink-0 border border-white/5 shadow-inner group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] font-bold text-gray-200 group-hover:text-white line-clamp-1 transition"><?php the_title(); ?></p>
                                <p class="text-[9px] text-[#ea580c] font-black uppercase tracking-widest mt-0.5"><?php echo human_time_diff(get_the_time('U'), current_time('timestamp')); ?> ago</p>
                            </div>
                            <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse shadow-[0_0_5px_red] shrink-0 mr-2"></div>
                        </a>
                    <?php endwhile; wp_reset_postdata();
                } else { ?>
                    <div class="p-5 text-center">
                        <p class="text-[11px] text-gray-500 font-bold uppercase tracking-widest">You're all caught up!</p>
                    </div>
                <?php } ?>
            </div>
        </section>

        <div class="flex bg-[#1a1a1a] border border-white/5 p-1.5 rounded-full mb-6 shadow-inner relative gap-1">
            <button onclick="window.switchTab('history')" id="tab-btn-history" class="tab-btn flex-1 py-3 rounded-full text-[11px] font-black uppercase tracking-widest text-white bg-gradient-to-r from-[#ea580c] to-[#f59e0b] shadow-[0_4px_10px_rgba(234,88,12,0.3)] transition-all z-10 text-center">History</button>
            <button onclick="window.switchTab('bookmarks')" id="tab-btn-bookmarks" class="tab-btn flex-1 py-3 rounded-full text-[11px] font-black uppercase tracking-widest text-gray-400 hover:text-white transition-all z-10 text-center">Library</button>
            <button onclick="window.switchTab('settings')" id="tab-btn-settings" class="tab-btn flex-1 py-3 rounded-full text-[11px] font-black uppercase tracking-widest text-gray-400 hover:text-white transition-all z-10 text-center">Settings</button>
        </div>

        <div id="tab-content-history" class="tab-content block animate-[fadeIn_0.3s_ease]">
            <?php if (!empty($history_data)) : ?>
                <div class="flex flex-col gap-3">
                    <?php foreach ($history_data as $m_id => $data) : 
                        $manga_post = get_post($m_id);
                        if (!$manga_post || $manga_post->post_type !== 'manga') continue;
                        $progress = rand(40, 95); 
                    ?>
                        <div class="history-item flex items-center gap-4 bg-[#1a1a1a] p-3 rounded-2xl border border-white/5 hover:border-[#ea580c]/50 transition-all duration-300 group" data-id="<?php echo $m_id; ?>">
                            <a href="<?php echo get_permalink($m_id); ?>" class="w-16 h-24 shrink-0 rounded-xl overflow-hidden bg-[#121212] relative shadow-md">
                                <img src="<?php echo xcomix_get_cover($m_id); ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                <div class="absolute bottom-0 left-0 w-full h-1 bg-black/80">
                                    <div class="h-full bg-[#ea580c] rounded-r-full shadow-[0_0_5px_#ea580c]" style="width: <?php echo $progress; ?>%;"></div>
                                </div>
                            </a>
                            <div class="flex-1 min-w-0 py-1">
                                <a href="<?php echo get_permalink($m_id); ?>" class="text-[14px] font-bold text-gray-200 group-hover:text-white transition line-clamp-1 mb-1 tracking-tight"><?php echo get_the_title($m_id); ?></a>
                                <p class="text-[10px] text-gray-500 font-bold mb-3 uppercase tracking-widest flex items-center gap-1">
                                    <svg class="w-3 h-3 text-[#ea580c]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Read: Ch. <?php echo esc_html($data['chapter_num']); ?>
                                </p>
                                
                                <div class="flex items-center justify-between gap-2 pr-2">
                                    <?php $chapter_link = !empty($data['url']) ? esc_url($data['url']) : (isset($data['chapter_id']) ? get_permalink($data['chapter_id']) : get_permalink($m_id)); ?>
                                    <a href="<?php echo $chapter_link; ?>" class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-white bg-[#121212] border border-white/10 hover:border-[#ea580c] hover:bg-[#ea580c] px-4 py-2 rounded-lg transition">
                                        Resume <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                    <button onclick="window.removeRealHistory(this, <?php echo $m_id; ?>)" class="w-8 h-8 bg-black/40 text-gray-500 rounded-lg flex items-center justify-center hover:bg-red-500 hover:text-white transition shadow-sm border border-white/5" title="Remove">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="text-center py-16 bg-[#1a1a1a] rounded-3xl border border-white/5 shadow-lg">
                    <div class="w-16 h-16 bg-[#121212] rounded-full flex items-center justify-center mx-auto mb-4 border border-white/5">
                        <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    </div>
                    <span class="text-gray-400 text-[11px] font-black uppercase tracking-widest block">No history yet. Start reading!</span>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-content-bookmarks" class="tab-content hidden animate-[fadeIn_0.3s_ease]">
            
            <?php if ($bookmarks_query && $bookmarks_query->have_posts()) : ?>
            
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar mb-4 pb-1">
                <button onclick="window.filterLibrary('all', 'all', this)" class="lib-filter-btn active px-4 py-2 rounded-xl text-[11px] font-black uppercase tracking-widest text-white bg-gradient-to-r from-[#ea580c] to-[#f59e0b] shadow-[0_4px_10px_rgba(234,88,12,0.3)] shrink-0 transition">All Saved</button>
                
                <div class="w-px h-5 bg-white/10 shrink-0 mx-1"></div>
                
                <?php foreach($base_plan_labels as $val => $label): ?>
                    <button onclick="window.filterLibrary('status', '<?php echo esc_attr($val); ?>', this)" class="lib-filter-btn px-4 py-2 rounded-xl text-[11px] font-black uppercase tracking-widest text-gray-400 bg-[#1a1a1a] border border-white/5 hover:text-white shrink-0 transition shadow-sm"><?php echo esc_html($label); ?></button>
                <?php endforeach; ?>
                
                <?php if(!empty($custom_folders)): ?>
                    <div class="w-px h-5 bg-white/10 shrink-0 mx-1"></div>
                    <?php foreach($custom_folders as $fid => $fname): ?>
                        <button onclick="window.filterLibrary('folder', '<?php echo esc_attr($fid); ?>', this)" class="lib-filter-btn px-4 py-2 rounded-xl text-[11px] font-black uppercase tracking-widest text-[#a855f7] bg-[#a855f7]/10 border border-[#a855f7]/20 hover:bg-[#a855f7] hover:text-white shrink-0 transition shadow-sm flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            <?php echo esc_html($fname); ?>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="w-px h-5 bg-white/10 shrink-0 mx-1"></div>
                <button onclick="document.getElementById('new-folder-modal').style.display='flex'" class="px-4 py-2 rounded-xl text-[11px] font-black uppercase tracking-widest text-gray-400 bg-[#121212] border border-dashed border-white/20 hover:text-white hover:border-white/50 shrink-0 transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    New Folder
                </button>
            </div>

            <div class="mb-6 relative">
                <input type="text" id="library-search" onkeyup="window.applyLibraryFilters()" placeholder="Search your library..." class="w-full bg-[#1a1a1a] border border-white/5 rounded-2xl pl-12 pr-5 py-3.5 text-sm text-white focus:outline-none focus:border-[#ea580c] transition shadow-inner placeholder:text-gray-600">
                <svg class="w-5 h-5 text-gray-500 absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <?php endif; ?>

            <?php if ($bookmarks_query && $bookmarks_query->have_posts()) : ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-x-4 gap-y-6" id="library-grid">
                    <?php while ($bookmarks_query->have_posts()) : $bookmarks_query->the_post(); 
                        $m_id = get_the_ID(); 
                        $title = get_the_title();
                        $current_status = isset($reading_plans[$m_id]) ? $reading_plans[$m_id] : 'reading';
                        $status_label = isset($base_plan_labels[$current_status]) ? $base_plan_labels[$current_status] : 'Reading';
                        $current_folder = isset($manga_folders[$m_id]) ? $manga_folders[$m_id] : 'none';
                        $type = mvx_manga_meta($m_id, 'type', 'Manga');
                        $is_18 = mvx_manga_meta($m_id, 'is_18_plus');
                    ?>
                        <div class="bookmark-item flex flex-col group relative" data-id="<?php echo $m_id; ?>" data-title="<?php echo esc_attr(strtolower($title)); ?>" data-status="<?php echo esc_attr($current_status); ?>" data-folder="<?php echo esc_attr($current_folder); ?>">
                            
                            <div class="relative aspect-[3/4] block overflow-hidden rounded-[16px] mb-2.5 bg-[#121212] border border-white/5 shadow-md">
                                <a href="<?php echo get_permalink(); ?>" class="absolute inset-0 z-10"></a>
                                <img src="<?php echo xcomix_get_cover($m_id); ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition duration-300 z-10 pointer-events-none"></div>
                                
                                <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300 z-20 pointer-events-none">
                                    <span class="bg-[#ea580c] text-white text-[11px] font-black uppercase tracking-widest px-4 py-2 rounded-full shadow-lg transform translate-y-4 group-hover:translate-y-0 transition duration-300">Read Now</span>
                                </div>
                                
                                <div class="absolute top-2 right-2 flex flex-col gap-1.5 items-end z-20 pointer-events-none">
                                    <span class="bg-black/60 backdrop-blur-md text-gray-100 text-[8px] font-black px-2 py-1 rounded-md uppercase tracking-widest border border-white/10"><?php echo esc_html($type); ?></span>
                                    <?php if($is_18) : ?>
                                        <span class="bg-[#e11d48] text-white text-[8px] font-black px-2 py-1 rounded-md uppercase tracking-widest shadow-md">18+</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <h3 class="text-[13px] font-bold text-gray-200 line-clamp-1 group-hover:text-[#ea580c] transition px-1 mb-2 tracking-tight">
                                <a href="<?php echo get_permalink(); ?>"><?php echo $title; ?></a>
                            </h3>
                            
                            <div class="px-1 pb-1 relative flex gap-1.5">
                                
                                <div class="relative flex-1 custom-dropdown" data-manga-id="<?php echo $m_id; ?>">
                                    <button type="button" onclick="window.toggleDropdownMenu(this)" class="dropdown-trigger flex items-center justify-between w-full h-[34px] bg-[#1a1a1a] border border-white/5 hover:border-[#ea580c] text-gray-200 text-[9px] font-black uppercase tracking-widest rounded-lg px-2.5 transition shadow-inner">
                                        <span class="selected-text truncate mr-1"><?php echo esc_html($status_label); ?></span>
                                        <svg class="w-3 h-3 text-gray-500 shrink-0 pointer-events-none transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    
                                    <div class="dropdown-menu absolute hidden bottom-full mb-1 left-0 w-[140px] bg-[#121212] border border-[#333] rounded-xl shadow-2xl z-50 overflow-hidden text-[10px] font-black uppercase tracking-widest origin-bottom">
                                        <div class="px-3 py-2 bg-[#1a1a1a] text-gray-500 text-[8px] border-b border-[#222]">Set Status</div>
                                        <?php foreach($base_plan_labels as $val => $label): ?>
                                            <div onclick="window.setStatusOption(this, '<?php echo esc_attr($val); ?>', '<?php echo esc_js($label); ?>')" class="px-3 py-2.5 text-gray-400 hover:text-white hover:bg-[#ea580c] cursor-pointer transition flex items-center justify-between <?php echo ($current_status == $val) ? 'text-white bg-white/5' : ''; ?>">
                                                <?php echo esc_html($label); ?>
                                                <?php if($current_status == $val): ?>
                                                    <svg class="w-3 h-3 text-[#ea580c]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="relative folder-dropdown" data-manga-id="<?php echo $m_id; ?>">
                                    <button type="button" onclick="window.toggleDropdownMenu(this)" class="dropdown-trigger w-8 h-[34px] bg-[#1a1a1a] rounded-lg flex items-center justify-center transition border border-white/5 shadow-sm shrink-0 <?php echo ($current_folder !== 'none') ? 'text-[#a855f7] border-[#a855f7]/30 bg-[#a855f7]/10' : 'text-gray-500 hover:text-white hover:border-white/20'; ?>" title="Add to Folder">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                    </button>
                                    
                                    <div class="dropdown-menu absolute hidden bottom-full mb-1 right-0 w-[160px] bg-[#121212] border border-[#333] rounded-xl shadow-2xl z-50 overflow-hidden text-[10px] font-black uppercase tracking-widest origin-bottom">
                                        <div class="px-3 py-2 bg-[#1a1a1a] text-gray-500 text-[8px] border-b border-[#222]">Move to Folder</div>
                                        <div onclick="window.setFolderOption(this, 'none')" class="px-3 py-2.5 text-gray-400 hover:text-white hover:bg-[#a855f7] cursor-pointer transition flex items-center justify-between <?php echo ($current_folder == 'none') ? 'text-white bg-white/5' : ''; ?>">
                                            [ No Folder ]
                                            <?php if($current_folder == 'none'): ?><svg class="w-3 h-3 text-[#a855f7]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg><?php endif; ?>
                                        </div>
                                        <?php foreach($custom_folders as $fid => $fname): ?>
                                            <div onclick="window.setFolderOption(this, '<?php echo esc_attr($fid); ?>')" class="px-3 py-2.5 text-gray-400 hover:text-white hover:bg-[#a855f7] cursor-pointer transition flex items-center justify-between <?php echo ($current_folder == $fid) ? 'text-white bg-white/5' : ''; ?>">
                                                <span class="truncate"><?php echo esc_html($fname); ?></span>
                                                <?php if($current_folder == $fid): ?><svg class="w-3 h-3 text-[#a855f7] shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <button onclick="window.removeRealBookmark(this, <?php echo $m_id; ?>)" class="w-8 h-[34px] bg-black/40 rounded-lg flex items-center justify-center text-gray-500 hover:text-white hover:bg-red-500 hover:border-red-500 transition border border-white/5 shadow-sm shrink-0" title="Remove Bookmark">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                                </button>

                            </div>
                        </div>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            <?php else : ?>
                <div class="text-center py-16 bg-[#1a1a1a] rounded-3xl border border-white/5 shadow-lg">
                    <div class="w-16 h-16 bg-[#121212] rounded-full flex items-center justify-center mx-auto mb-4 border border-white/5">
                        <svg class="w-8 h-8 text-gray-600" fill="currentColor" viewBox="0 0 24 24"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                    </div>
                    <span class="text-gray-400 text-[11px] font-black uppercase tracking-widest block">Library is empty.</span>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-content-settings" class="tab-content hidden animate-[fadeIn_0.3s_ease]">
            <form method="POST" class="space-y-6">
                <input type="hidden" name="update_profile" value="1">
                <?php wp_nonce_field('update_profile_action', 'profile_nonce'); ?>
                
                <div class="bg-[#1a1a1a] border border-white/5 rounded-3xl p-6 md:p-8 shadow-xl">
                    <h3 class="text-white font-black uppercase tracking-widest text-[13px] flex items-center gap-2 mb-6">
                        <span class="w-1.5 h-4 bg-[#a855f7] rounded-full"></span> Custom Folders
                    </h3>
                    
                    <?php if(empty($custom_folders)): ?>
                        <p class="text-[11px] text-gray-500 font-bold uppercase tracking-widest">You haven't created any custom folders yet. Go to your Library tab to create one.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach($custom_folders as $f_id => $f_name): ?>
                                <div class="flex items-center justify-between bg-[#09090b] border border-white/5 p-3.5 rounded-2xl">
                                    <span class="text-[13px] font-bold text-gray-200 pl-2 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-[#a855f7]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                        <?php echo esc_html($f_name); ?>
                                    </span>
                                    <form method="POST" onsubmit="return confirm('Delete this folder? Manga inside will simply be removed from the folder, but stay in your library.');" class="m-0 p-0">
                                        <input type="hidden" name="xcomix_folder_action" value="delete">
                                        <input type="hidden" name="folder_id" value="<?php echo esc_attr($f_id); ?>">
                                        <button type="submit" class="text-red-500 hover:text-white hover:bg-red-500 w-9 h-9 rounded-xl flex items-center justify-center transition border border-red-500/20 hover:border-red-500">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bg-[#1a1a1a] border border-white/5 rounded-3xl p-6 md:p-8 shadow-xl">
                    <h3 class="text-white font-black uppercase tracking-widest text-[13px] flex items-center gap-2 mb-6">
                        <span class="w-1.5 h-4 bg-[#3b82f6] rounded-full"></span> Account Info
                    </h3>
                    <div class="space-y-5">
                        <div>
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest block mb-2 ml-1">Display Name</label>
                            <input type="text" name="display_name" value="<?php echo esc_attr($username); ?>" class="w-full bg-[#09090b] border border-white/5 rounded-2xl px-5 py-3.5 text-sm text-white focus:outline-none focus:border-[#ea580c] transition shadow-inner">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest block mb-2 ml-1">Avatar URL</label>
                            <input type="url" name="avatar_url" value="<?php echo esc_attr($custom_avatar); ?>" placeholder="https://example.com/avatar.jpg" class="w-full bg-[#09090b] border border-white/5 rounded-2xl px-5 py-3.5 text-sm text-white focus:outline-none focus:border-[#ea580c] transition placeholder:text-gray-700 shadow-inner">
                        </div>
                        <div class="pt-2">
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest block mb-2 ml-1">Change Password</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <input type="password" name="new_password" placeholder="New Password" class="w-full bg-[#09090b] border border-white/5 rounded-2xl px-5 py-3.5 text-sm text-white focus:outline-none focus:border-[#ea580c] transition placeholder:text-gray-700 shadow-inner">
                                <input type="password" name="confirm_password" placeholder="Confirm Password" class="w-full bg-[#09090b] border border-white/5 rounded-2xl px-5 py-3.5 text-sm text-white focus:outline-none focus:border-[#ea580c] transition placeholder:text-gray-700 shadow-inner">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-[#1a1a1a] border border-white/5 rounded-3xl p-6 md:p-8 shadow-xl">
                    <h3 class="text-white font-black uppercase tracking-widest text-[13px] flex items-center gap-2 mb-6">
                        <span class="w-1.5 h-4 bg-[#eab308] rounded-full"></span> App Preferences
                    </h3>
                    <div class="space-y-4">
                        <label class="flex items-center justify-between cursor-pointer p-4 bg-[#09090b] rounded-2xl border border-white/5 hover:border-white/10 transition">
                            <div>
                                <span class="text-[12px] font-black text-white uppercase tracking-wider block mb-1">Show 18+ Content</span>
                                <span class="text-[10px] font-medium text-gray-500 block">Display NSFW and mature manga tags.</span>
                            </div>
                            <div class="relative">
                                <input type="checkbox" name="pref_nsfw" class="sr-only peer" <?php checked($pref_nsfw, true); ?>>
                                <div class="w-11 h-6 bg-[#1a1a1a] border border-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#ea580c]"></div>
                            </div>
                        </label>

                        <label class="flex items-center justify-between cursor-pointer p-4 bg-[#09090b] rounded-2xl border border-white/5 hover:border-white/10 transition">
                            <div>
                                <span class="text-[12px] font-black text-white uppercase tracking-wider block mb-1">Push Notifications</span>
                                <span class="text-[10px] font-medium text-gray-500 block">Receive alerts for new bookmark chapters.</span>
                            </div>
                            <div class="relative">
                                <input type="checkbox" name="pref_notifications" class="sr-only peer" <?php checked($pref_notifications, true); ?>>
                                <div class="w-11 h-6 bg-[#1a1a1a] border border-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#ea580c]"></div>
                            </div>
                        </label>
                        
                        <label class="flex items-center justify-between cursor-pointer p-4 bg-[#09090b] rounded-2xl border border-white/5 hover:border-white/10 transition">
                            <div>
                                <span class="text-[12px] font-black text-white uppercase tracking-wider block mb-1">Data Saver Mode</span>
                                <span class="text-[10px] font-medium text-gray-500 block">Load lower resolution images to save bandwidth.</span>
                            </div>
                            <div class="relative">
                                <input type="checkbox" name="pref_datasaver" class="sr-only peer" <?php checked($pref_datasaver, true); ?>>
                                <div class="w-11 h-6 bg-[#1a1a1a] border border-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#ea580c]"></div>
                            </div>
                        </label>
                        
                        <label class="flex items-center justify-between cursor-pointer p-4 bg-[#09090b] rounded-2xl border border-white/5 hover:border-white/10 transition">
                            <div>
                                <span class="text-[12px] font-black text-white uppercase tracking-wider block mb-1">Zen Mode Reader</span>
                                <span class="text-[10px] font-medium text-gray-500 block">Hide sidebar and comments for distraction-free reading.</span>
                            </div>
                            <div class="relative">
                                <input type="checkbox" name="pref_zenmode" class="sr-only peer" <?php checked($pref_zenmode, true); ?>>
                                <div class="w-11 h-6 bg-[#1a1a1a] border border-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#ea580c]"></div>
                            </div>
                        </label>

                    </div>
                    
                    <div class="mt-8 pt-6 border-t border-white/5">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-[12px] font-black text-red-500 uppercase tracking-wider block mb-1">Danger Zone</span>
                                <span class="text-[10px] font-medium text-gray-500 block">XP is gained per chapter. 1 Read = 1 XP.</span>
                            </div>
                            <button type="submit" name="clear_history" value="1" class="bg-black/40 hover:bg-red-500 text-red-500 hover:text-white border border-red-500/30 hover:border-red-500 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition shadow-sm shrink-0 ml-4">
                                Nuke History
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" class="bg-gradient-to-r from-[#ea580c] to-[#f59e0b] hover:scale-105 text-white font-black uppercase tracking-widest text-[11px] px-10 py-4 rounded-full transition-transform shadow-[0_4px_15px_rgba(234,88,12,0.4)] w-full md:w-auto">Save All Settings</button>
                </div>
            </form>
        </div>

        <div class="mt-8 md:hidden">
            <a href="<?php echo wp_logout_url(site_url()); ?>" class="flex items-center justify-center gap-2 w-full bg-[#1a1a1a] text-gray-400 hover:text-[#e11d48] border border-white/5 py-4 rounded-2xl text-[11px] font-black uppercase tracking-widest transition shadow-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Sign Out
            </a>
        </div>

        <div id="new-folder-modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4 animate-[fadeIn_0.2s_ease]">
            <div class="bg-[#1a1a1a] border border-white/10 rounded-3xl p-6 w-full max-w-sm shadow-2xl relative">
                <h3 class="text-white font-black text-xl mb-1">Create Folder</h3>
                <p class="text-gray-400 text-xs font-medium mb-5">Organize your library exactly how you want.</p>
                <form method="POST">
                    <input type="hidden" name="xcomix_folder_action" value="add">
                    <input type="text" name="folder_name" placeholder="e.g. Action Masterpieces..." class="w-full bg-[#09090b] border border-white/5 rounded-xl px-4 py-3.5 text-[13px] text-white focus:outline-none focus:border-[#ea580c] transition shadow-inner mb-5" required maxlength="30" autocomplete="off">
                    <div class="flex gap-3 justify-end">
                        <button type="button" onclick="document.getElementById('new-folder-modal').style.display='none'" class="px-5 py-3 rounded-xl text-xs font-bold text-gray-400 hover:text-white hover:bg-white/5 transition">Cancel</button>
                        <button type="submit" class="px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest text-white bg-gradient-to-r from-[#ea580c] to-[#f59e0b] shadow-[0_4px_10px_rgba(234,88,12,0.3)] hover:scale-105 transition">Create</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="bottom-nav"></div>

        <script>
            // --- TAB LOGIC ---
            window.switchTab = function(tabName) {
                if(history.pushState) history.pushState(null, null, '#' + tabName);
                else location.hash = '#' + tabName;

                document.querySelectorAll('.tab-content').forEach(el => el.classList.replace('block', 'hidden'));
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.className = "tab-btn flex-1 py-3 rounded-full text-[11px] font-black uppercase tracking-widest text-gray-400 hover:text-white transition-all z-10 text-center";
                });

                const targetContent = document.getElementById('tab-content-' + tabName);
                if (targetContent) targetContent.classList.replace('hidden', 'block');
                
                const targetBtn = document.getElementById('tab-btn-' + tabName);
                if (targetBtn) {
                    targetBtn.className = "tab-btn flex-1 py-3 rounded-full text-[11px] font-black uppercase tracking-widest text-white bg-gradient-to-r from-[#ea580c] to-[#f59e0b] shadow-[0_4px_10px_rgba(234,88,12,0.3)] transition-all z-10 text-center";
                }
            };

            function checkHashAndSwitch() {
                const hash = window.location.hash.replace('#', '');
                if (['history', 'bookmarks', 'settings'].includes(hash)) window.switchTab(hash);
            }
            document.addEventListener('DOMContentLoaded', checkHashAndSwitch);
            window.addEventListener('hashchange', checkHashAndSwitch);

            // --- LIBRARY FOLDER & SEARCH LOGIC ---
            window.currentFilterType = 'status'; // 'status' or 'folder'
            window.currentFilterValue = 'all';

            window.filterLibrary = function(type, value, btnEl) {
                window.currentFilterType = type;
                window.currentFilterValue = value;
                
                document.querySelectorAll('.lib-filter-btn').forEach(btn => {
                    btn.className = 'lib-filter-btn px-4 py-2 rounded-xl text-[11px] font-black uppercase tracking-widest text-gray-400 bg-[#1a1a1a] border border-white/5 hover:text-white shrink-0 transition shadow-sm';
                });
                
                if (type === 'folder') {
                    btnEl.className = 'lib-filter-btn active px-4 py-2 rounded-xl text-[11px] font-black uppercase tracking-widest text-white bg-gradient-to-r from-[#a855f7] to-[#9333ea] shadow-[0_4px_10px_rgba(168,85,247,0.3)] shrink-0 transition flex items-center gap-1';
                } else {
                    btnEl.className = 'lib-filter-btn active px-4 py-2 rounded-xl text-[11px] font-black uppercase tracking-widest text-white bg-gradient-to-r from-[#ea580c] to-[#f59e0b] shadow-[0_4px_10px_rgba(234,88,12,0.3)] shrink-0 transition';
                }
                
                window.applyLibraryFilters();
            };

            window.applyLibraryFilters = function() {
                let searchInput = document.getElementById('library-search') ? document.getElementById('library-search').value.toLowerCase() : '';
                let items = document.querySelectorAll('.bookmark-item');
                
                items.forEach(item => {
                    let title = item.getAttribute('data-title');
                    let status = item.getAttribute('data-status');
                    let folder = item.getAttribute('data-folder');
                    
                    let matchesSearch = title.includes(searchInput);
                    let matchesFilter = true;
                    
                    if (window.currentFilterType === 'status' && window.currentFilterValue !== 'all') {
                        matchesFilter = (status === window.currentFilterValue);
                    } else if (window.currentFilterType === 'folder') {
                        matchesFilter = (folder === window.currentFilterValue);
                    }
                    
                    if(matchesSearch && matchesFilter) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            };

            // --- DROPDOWN UI LOGIC ---
            window.toggleDropdownMenu = function(btn) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    if (menu !== btn.nextElementSibling) {
                        menu.classList.add('hidden');
                        const icon = menu.previousElementSibling.querySelector('svg');
                        if (icon) icon.classList.remove('rotate-180');
                    }
                });
                const menu = btn.nextElementSibling;
                const icon = btn.querySelector('svg');
                menu.classList.toggle('hidden');
                if (icon) icon.classList.toggle('rotate-180');
            };

            // Setting Reading Status (Reading, Completed...)
            window.setStatusOption = function(optionEl, value, label) {
                const dropdownWrapper = optionEl.closest('.custom-dropdown');
                const btn = dropdownWrapper.querySelector('.dropdown-trigger');
                const menu = dropdownWrapper.querySelector('.dropdown-menu');
                const mangaId = dropdownWrapper.getAttribute('data-manga-id');
                const bookmarkItem = dropdownWrapper.closest('.bookmark-item');

                btn.querySelector('.selected-text').innerText = label;
                const originalBorder = btn.style.borderColor;
                btn.style.borderColor = '#ea580c';
                setTimeout(() => btn.style.borderColor = originalBorder, 500);

                menu.classList.add('hidden');
                btn.querySelector('svg').classList.remove('rotate-180');

                menu.querySelectorAll('div').forEach(div => {
                    div.classList.remove('text-white', 'bg-white/5');
                    const svg = div.querySelector('svg');
                    if(svg) svg.remove();
                });
                optionEl.classList.add('text-white', 'bg-white/5');
                optionEl.innerHTML += `<svg class="w-3 h-3 text-[#ea580c]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>`;

                bookmarkItem.setAttribute('data-status', value);
                window.applyLibraryFilters();

                if (window.xcomixApp) {
                    fetch(window.xcomixApp.ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=xcomix_update_reading_plan&manga_id=${mangaId}&status=${value}&nonce=${window.xcomixApp.nonce}`
                    });
                }
            };

            // Setting Custom Folders
            window.setFolderOption = async function(optionEl, value) {
                const dropdownWrapper = optionEl.closest('.folder-dropdown');
                const btn = dropdownWrapper.querySelector('.dropdown-trigger');
                const menu = dropdownWrapper.querySelector('.dropdown-menu');
                const mangaId = dropdownWrapper.getAttribute('data-manga-id');
                const bookmarkItem = dropdownWrapper.closest('.bookmark-item');

                // Visual Button Change
                if (value === 'none') {
                    btn.className = 'dropdown-trigger w-8 h-[34px] bg-[#1a1a1a] rounded-lg flex items-center justify-center transition border border-white/5 shadow-sm shrink-0 text-gray-500 hover:text-white hover:border-white/20';
                } else {
                    btn.className = 'dropdown-trigger w-8 h-[34px] rounded-lg flex items-center justify-center transition border shadow-sm shrink-0 text-[#a855f7] border-[#a855f7]/30 bg-[#a855f7]/10';
                }

                menu.classList.add('hidden');

                // Update Menu Selection visuals
                menu.querySelectorAll('div').forEach(div => {
                    div.classList.remove('text-white', 'bg-white/5');
                    const svg = div.querySelector('svg');
                    if(svg) svg.remove();
                });
                optionEl.classList.add('text-white', 'bg-white/5');
                optionEl.innerHTML += `<svg class="w-3 h-3 text-[#a855f7] shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>`;

                bookmarkItem.setAttribute('data-folder', value);
                window.applyLibraryFilters();

                // Custom Inline AJAX trigger to current page URL
                let fd = new FormData();
                fd.append('xcomix_inline_ajax', '1');
                fd.append('action', 'set_folder');
                fd.append('manga_id', mangaId);
                fd.append('folder_id', value);
                await fetch(window.location.href, { method: 'POST', body: fd });
            };

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.custom-dropdown') && !e.target.closest('.folder-dropdown')) {
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.classList.add('hidden');
                        const icon = menu.previousElementSibling.querySelector('svg');
                        if (icon) icon.classList.remove('rotate-180');
                    });
                }
            });

            // --- SEAMLESS AJAX TRIGGERS ---
            window.removeRealHistory = function(btn, mangaId) {
                const item = btn.closest('.history-item');
                item.style.opacity = '0';
                item.style.transform = 'scale(0.95)';
                setTimeout(() => item.remove(), 300);
                if (window.xcomixApp) {
                    fetch(window.xcomixApp.ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=xcomix_remove_history&manga_id=${mangaId}&nonce=${window.xcomixApp.nonce}`
                    });
                }
            };

            window.removeRealBookmark = function(btn, mangaId) {
                const item = btn.closest('.bookmark-item');
                item.style.opacity = '0';
                item.style.transform = 'scale(0.95)';
                setTimeout(() => item.remove(), 300);
                if (window.xcomixApp) {
                    fetch(window.xcomixApp.ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=xcomix_toggle_bookmark&manga_id=${mangaId}&nonce=${window.xcomixApp.nonce}`
                    });
                }
            };
        </script>
        <style>
            @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
            .dropdown-menu::-webkit-scrollbar { width: 4px; }
            .dropdown-menu::-webkit-scrollbar-track { background: transparent; }
            .dropdown-menu::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
        </style>
    </main>

<?php get_footer(); ?>
