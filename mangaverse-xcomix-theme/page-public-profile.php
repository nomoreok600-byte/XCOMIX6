<?php 
/* Template Name: Public Profile */
$user_login = sanitize_text_field(get_query_var('author_name') ?? $_GET['username'] ?? '');
if (!$user_login) { wp_redirect(home_url()); exit; }

$user = get_user_by('login', $user_login);
if (!$user) { wp_redirect(home_url('/404')); exit; }

// Check privacy
if (!mv_is_profile_public($user->ID) && $user->ID !== get_current_user_id() && !current_user_can('manage_options')) {
    wp_redirect(home_url()); exit;
}

get_header();
$lvl = mv_get_user_level_data($user->ID);
$stats = mv_get_user_stats($user->ID);
$avatar = get_avatar_url($user->ID) ?: MV_URI . '/assets/img/default-avatar.png';
$bookmarks = array_unique(array_merge(
    get_user_meta($user->ID, '_mv_bookmarks', true) ?: [],
    get_user_meta($user->ID, '_xcomix_bookmarks', true) ?: []
));
$is_self = ($user->ID === get_current_user_id());
?>

<section class="pt-20 min-h-screen">
    <div class="max-w-[1000px] mx-auto px-4 lg:px-6">
        <!-- Profile Card -->
        <div class="bg-gradient-to-r from-[#1a1a22] to-[#252530] rounded-2xl border border-[#2a2a35] p-6 lg:p-8 mb-6">
            <div class="flex flex-col sm:flex-row items-start gap-5">
                <img src="<?php echo esc_url($avatar); ?>" class="w-24 h-24 lg:w-28 lg:h-28 rounded-2xl object-cover border-2 border-[#2a2a35] shrink-0">
                <div class="flex-1 min-w-0">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-2">
                        <h1 class="text-2xl lg:text-3xl font-bold"><?php echo esc_html($user->display_name); ?></h1>
                        <span class="shrink-0 inline-flex items-center gap-1.5 text-xs font-bold bg-gradient-to-r <?php echo $lvl['rank_color']; ?> text-white px-3 py-1 rounded-full">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <?php echo esc_html($lvl['rank_name']); ?>
                        </span>
                    </div>
                    <p class="text-sm text-gray-400 mb-1">@<?php echo esc_html($user->user_login); ?> &middot; Level <?php echo $lvl['level']; ?> &middot; Joined <?php echo date('M Y', strtotime($user->user_registered)); ?></p>
                    
                    <div class="w-full max-w-xs mb-4">
                        <div class="flex justify-between text-[10px] text-gray-500 mb-1">
                            <span>XP Progress</span>
                            <span><?php echo number_format($lvl['xp']); ?> / <?php echo number_format($lvl['xp_for_level']); ?></span>
                        </div>
                        <div class="h-2 bg-[#252530] rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-mv-accent to-yellow-500 rounded-full" style="width:<?php echo $lvl['progress']; ?>"></div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <?php if (!$is_self && is_user_logged_in()): ?>
                        <a href="<?php echo esc_url(home_url('/messages?user=' . $user->ID)); ?>" class="h-9 px-4 bg-mv-accent hover:bg-mv-accentHover text-white text-sm font-semibold rounded-full transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            Message
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Stats Grid -->
                <div class="grid grid-cols-2 gap-3 shrink-0 w-full sm:w-auto">
                    <div class="bg-[#0f0f13]/50 rounded-xl p-3 text-center border border-[#2a2a35]">
                        <div class="text-xl font-bold text-white"><?php echo number_format($stats['chapters_read']); ?></div>
                        <div class="text-[10px] text-gray-500 uppercase">Chapters</div>
                    </div>
                    <div class="bg-[#0f0f13]/50 rounded-xl p-3 text-center border border-[#2a2a35]">
                        <div class="text-xl font-bold text-white"><?php echo number_format($stats['bookmarks']); ?></div>
                        <div class="text-[10px] text-gray-500 uppercase">Bookmarks</div>
                    </div>
                    <div class="bg-[#0f0f13]/50 rounded-xl p-3 text-center border border-[#2a2a35]">
                        <div class="text-xl font-bold text-white"><?php echo number_format($stats['comments']); ?></div>
                        <div class="text-[10px] text-gray-500 uppercase">Comments</div>
                    </div>
                    <div class="bg-[#0f0f13]/50 rounded-xl p-3 text-center border border-[#2a2a35]">
                        <div class="text-xl font-bold text-white"><?php echo $stats['days_active']; ?>d</div>
                        <div class="text-[10px] text-gray-500 uppercase">Active</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Public Bookmarks -->
        <?php if ($bookmarks): ?>
        <h2 class="text-lg font-bold mb-4">Bookmarked Manga</h2>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4">
            <?php foreach ($bookmarks as $manga_id):
                $m = get_post($manga_id);
                if (!$m) continue;
            ?>
            <a href="<?php echo get_permalink($manga_id); ?>" class="group block card-hover">
                <div class="relative rounded-xl overflow-hidden bg-[#1a1a22] aspect-[2/3] mb-2">
                    <img src="<?php echo mv_get_cover($manga_id, 'medium'); ?>" alt="" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
                </div>
                <h3 class="text-[13px] font-semibold truncate group-hover:text-mv-accent transition"><?php echo esc_html($m->post_title); ?></h3>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-12 bg-[#1a1a22] rounded-2xl border border-[#2a2a35]">
            <p class="text-gray-500">No public bookmarks yet.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
