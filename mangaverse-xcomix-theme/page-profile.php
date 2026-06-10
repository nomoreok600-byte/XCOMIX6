<?php 
/* Template Name: Profile */
if (!is_user_logged_in()) { wp_redirect(home_url('/auth')); exit; }
get_header();

$cu = wp_get_current_user();
$user_id = $cu->ID;
$lvl = mv_get_user_level_data($user_id);
$stats = mv_get_user_stats($user_id);
$avatar = get_avatar_url($user_id) ?: MV_URI . '/assets/img/default-avatar.png';
$privacy = get_user_meta($user_id, '_mv_profile_privacy', true) ?: 'public';

// Tabs
$tabs = sanitize_text_field($_GET['tab'] ?? 'overview');
$bookmarks = get_user_meta($user_id, '_mv_bookmarks', true) ?: [];
$history = get_user_meta($user_id, '_mv_history', true) ?: [];
$reading_plans = get_user_meta($user_id, '_mv_reading_plans', true) ?: [];
?>

<section class="pt-20 min-h-screen">
    <div class="max-w-[1400px] mx-auto px-4 lg:px-6">
        <!-- Profile Header -->
        <div class="bg-gradient-to-r from-[#1a1a22] to-[#252530] rounded-2xl border border-[#2a2a35] p-6 lg:p-8 mb-6">
            <div class="flex flex-col sm:flex-row items-start gap-5">
                <img src="<?php echo esc_url($avatar); ?>" class="w-20 h-20 lg:w-24 lg:h-24 rounded-2xl object-cover border-2 border-[#2a2a35] shrink-0">
                <div class="flex-1 min-w-0">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-2">
                        <h1 class="text-xl lg:text-2xl font-bold"><?php echo esc_html($cu->display_name); ?></h1>
                        <span class="shrink-0 inline-flex items-center gap-1.5 text-xs font-bold bg-gradient-to-r <?php echo $lvl['rank_color']; ?> text-white px-3 py-1 rounded-full">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <?php echo esc_html($lvl['rank_name']); ?>
                        </span>
                    </div>
                    <p class="text-sm text-gray-400 mb-3">Level <?php echo $lvl['level']; ?> &middot; <?php echo number_format($lvl['xp']); ?> XP &middot; Member since <?php echo date('M Y', strtotime($cu->user_registered)); ?></p>
                    
                    <!-- XP Bar -->
                    <div class="w-full max-w-xs">
                        <div class="flex justify-between text-[10px] text-gray-500 mb-1">
                            <span>XP</span>
                            <span><?php echo number_format($lvl['xp']); ?> / <?php echo number_format($lvl['xp_for_level']); ?></span>
                        </div>
                        <div class="h-2 bg-[#252530] rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-mv-accent to-yellow-500 rounded-full transition-all" style="width:<?php echo $lvl['progress']; ?>"></div>
                        </div>
                    </div>
                </div>
                <!-- Stats -->
                <div class="grid grid-cols-2 gap-3 shrink-0 w-full sm:w-auto">
                    <div class="bg-[#0f0f13]/50 rounded-xl p-3 text-center border border-[#2a2a35]">
                        <div class="text-lg font-bold text-white"><?php echo number_format($stats['chapters_read']); ?></div>
                        <div class="text-[10px] text-gray-500 uppercase tracking-wider">Chapters</div>
                    </div>
                    <div class="bg-[#0f0f13]/50 rounded-xl p-3 text-center border border-[#2a2a35]">
                        <div class="text-lg font-bold text-white"><?php echo number_format($stats['bookmarks']); ?></div>
                        <div class="text-[10px] text-gray-500 uppercase tracking-wider">Bookmarks</div>
                    </div>
                    <div class="bg-[#0f0f13]/50 rounded-xl p-3 text-center border border-[#2a2a35]">
                        <div class="text-lg font-bold text-white"><?php echo number_format($stats['comments']); ?></div>
                        <div class="text-[10px] text-gray-500 uppercase tracking-wider">Comments</div>
                    </div>
                    <div class="bg-[#0f0f13]/50 rounded-xl p-3 text-center border border-[#2a2a35]">
                        <div class="text-lg font-bold text-white"><?php echo $stats['days_active']; ?>d</div>
                        <div class="text-[10px] text-gray-500 uppercase tracking-wider">Active</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex items-center gap-1 overflow-x-auto no-scrollbar mb-6 pb-1">
            <?php foreach (['overview' => 'Overview', 'bookmarks' => 'Bookmarks', 'history' => 'History', 'settings' => 'Settings'] as $key => $label): 
                $active = ($tabs === $key) ? 'bg-mv-accent text-white' : 'bg-[#1a1a22] text-gray-400 hover:text-white border border-[#2a2a35]';
            ?>
            <a href="?tab=<?php echo $key; ?>" class="shrink-0 px-5 py-2 rounded-full text-sm font-semibold transition <?php echo $active; ?>">
                <?php echo $label; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ($tabs === 'overview'): ?>
        <!-- Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Reading Plan -->
            <div class="bg-[#1a1a22] rounded-2xl border border-[#2a2a35] p-5">
                <h3 class="font-bold mb-4">Reading Plan</h3>
                <?php 
                $plan_items = ['Reading' => 'bg-blue-500', 'Plan to Read' => 'bg-gray-500', 'Completed' => 'bg-green-500', 'Dropped' => 'bg-red-500'];
                foreach ($plan_items as $status => $color):
                    $count = count(array_filter($reading_plans, fn($s) => $s === $status));
                ?>
                <div class="flex items-center justify-between py-2.5 border-b border-[#2a2a35] last:border-0">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full <?php echo $color; ?>"></span>
                        <span class="text-sm"><?php echo $status; ?></span>
                    </div>
                    <span class="text-sm font-bold text-white"><?php echo $count; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <!-- Quick Stats -->
            <div class="bg-[#1a1a22] rounded-2xl border border-[#2a2a35] p-5">
                <h3 class="font-bold mb-4">Rank Progress</h3>
                <div class="space-y-3">
                    <?php 
                    $ranks = [
                        ['Novice Reader', 'text-gray-400', $lvl['level'] >= 1],
                        ['Adept Scroller', 'text-blue-400', $lvl['level'] >= 10],
                        ['Elite Weeb', 'text-orange-400', $lvl['level'] >= 25],
                        ['Manga Lord', 'text-purple-400', $lvl['level'] >= 50],
                        ['God Tier', 'text-yellow-400', $lvl['level'] >= 80],
                    ];
                    foreach ($ranks as $rank):
                    ?>
                    <div class="flex items-center gap-3">
                        <span class="w-4 flex justify-center"><?php echo $rank[2] ? '&#10003;' : '&#9675;'; ?></span>
                        <span class="text-sm <?php echo $rank[2] ? $rank[1] . ' font-semibold' : 'text-gray-500'; ?>"><?php echo $rank[0]; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php elseif ($tabs === 'bookmarks'): ?>
        <!-- Bookmarks -->
        <?php if ($bookmarks): ?>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4">
            <?php foreach ($bookmarks as $manga_id):
                $m = get_post($manga_id);
                if (!$m) continue;
                $last_ch = get_posts([
                    'post_type' => 'chapter', 
                    'post_parent' => $manga_id,
                    'posts_per_page' => 1, 
                    'orderby' => 'date', 
                    'order' => 'DESC'
                ]);
                $chap_num = $last_ch ? get_post_meta($last_ch[0]->ID, '_mv_chapter_number', true) : '';
            ?>
            <div class="group block relative">
                <a href="<?php echo get_permalink($manga_id); ?>" class="block card-hover">
                    <div class="relative rounded-xl overflow-hidden bg-[#1a1a22] aspect-[2/3] mb-2">
                        <img src="<?php echo mv_get_cover($manga_id, 'medium'); ?>" alt="" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-3">
                            <span class="text-xs font-semibold text-white">Continue</span>
                        </div>
                    </div>
                    <h3 class="text-[13px] font-semibold truncate group-hover:text-mv-accent transition"><?php echo esc_html($m->post_title); ?></h3>
                    <?php if ($chap_num): ?><p class="text-[11px] text-gray-500">Ch. <?php echo esc_html($chap_num); ?></p><?php endif; ?>
                </a>
                <button onclick="removeBookmark(<?php echo $manga_id; ?>)" class="absolute top-1 right-1 h-7 w-7 bg-black/60 hover:bg-red-500/80 rounded-lg flex items-center justify-center text-white opacity-0 group-hover:opacity-100 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-16 bg-[#1a1a22] rounded-2xl border border-[#2a2a35]">
            <svg class="w-12 h-12 mx-auto text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
            <p class="text-gray-500">No bookmarks yet</p>
            <a href="<?php echo esc_url(home_url('/browse')); ?>" class="text-mv-accent hover:underline text-sm">Browse manga</a>
        </div>
        <?php endif; ?>

        <?php elseif ($tabs === 'history'): ?>
        <!-- History -->
        <?php if ($history): ?>
        <div class="space-y-3">
            <?php foreach ($history as $manga_id => $h):
                $m = get_post($manga_id);
                if (!$m) continue;
            ?>
            <div class="flex items-center gap-4 bg-[#1a1a22] rounded-xl p-3 border border-[#2a2a35] group hover:border-[#3a3a48] transition">
                <img src="<?php echo mv_get_cover($manga_id, 'mv_cover_small'); ?>" class="w-12 h-[72px] rounded-lg object-cover shrink-0">
                <div class="flex-1 min-w-0">
                    <a href="<?php echo get_permalink($manga_id); ?>" class="text-sm font-semibold truncate block hover:text-mv-accent transition"><?php echo esc_html($m->post_title); ?></a>
                    <p class="text-[11px] text-gray-500">Chapter <?php echo esc_html($h['chapter_num']); ?> &middot; <?php echo human_time_diff($h['timestamp'], current_time('timestamp')); ?> ago</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="<?php echo get_permalink($h['chapter_id']); ?>" class="h-8 px-3 bg-mv-accent/10 text-mv-accent text-xs font-semibold rounded-lg hover:bg-mv-accent/20 transition">Continue</a>
                    <button onclick="removeHistory(<?php echo $manga_id; ?>)" class="h-8 w-8 flex items-center justify-center text-gray-500 hover:text-red-400 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-16 bg-[#1a1a22] rounded-2xl border border-[#2a2a35]">
            <p class="text-gray-500">No reading history yet. Start reading some manga!</p>
        </div>
        <?php endif; ?>

        <?php elseif ($tabs === 'settings'): ?>
        <!-- Settings -->
        <div class="max-w-xl bg-[#1a1a22] rounded-2xl border border-[#2a2a35] p-6">
            <h3 class="font-bold mb-4">Privacy Settings</h3>
            <form id="privacyForm" onsubmit="savePrivacy(event)">
                <div class="space-y-4 mb-6">
                    <label class="flex items-center justify-between p-4 bg-[#252530] rounded-xl cursor-pointer hover:bg-[#2a2a35] transition">
                        <div>
                            <p class="font-semibold text-sm">Public Profile</p>
                            <p class="text-xs text-gray-500">Allow others to view your profile</p>
                        </div>
                        <input type="radio" name="privacy" value="public" <?php checked($privacy, 'public'); ?> class="accent-mv-accent w-5 h-5">
                    </label>
                    <label class="flex items-center justify-between p-4 bg-[#252530] rounded-xl cursor-pointer hover:bg-[#2a2a35] transition">
                        <div>
                            <p class="font-semibold text-sm">Private Profile</p>
                            <p class="text-xs text-gray-500">Only you can see your profile</p>
                        </div>
                        <input type="radio" name="privacy" value="private" <?php checked($privacy, 'private'); ?> class="accent-mv-accent w-5 h-5">
                    </label>
                </div>
                <button type="submit" class="h-10 px-6 bg-mv-accent hover:bg-mv-accentHover text-white text-sm font-semibold rounded-xl transition">
                    Save Changes
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
