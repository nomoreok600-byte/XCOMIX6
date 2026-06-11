<?php 
/* Template Name: Leaderboard */
get_header();

$period = sanitize_text_field($_GET['period'] ?? 'all');
$leaderboard = mv_get_leaderboard(50, $period);

$rank_styles = [
    ['border-yellow-500/50 bg-yellow-500/10', 'text-yellow-400', '&#127942;'],
    ['border-gray-300/30 bg-gray-300/5', 'text-gray-300', '&#129352;'],
    ['border-amber-700/50 bg-amber-700/10', 'text-amber-600', '&#129353;'],
];
?>

<section class="pt-20 min-h-screen">
    <div class="max-w-[1000px] mx-auto px-4 lg:px-6">
        <div class="flex items-center gap-3 mb-2">
            <svg class="w-8 h-8 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
            </svg>
            <h1 class="text-2xl lg:text-3xl font-bold">Global Leaderboard</h1>
        </div>
        <p class="text-gray-500 text-sm mb-6">Top readers ranked by experience points earned from reading and community activity.</p>

        <!-- Period Filter -->
        <div class="flex items-center gap-2 mb-6">
            <?php foreach (['all' => 'All Time', 'week' => 'This Week'] as $k => $v): 
                $active = ($period === $k) ? 'bg-mv-accent text-white' : 'bg-[#1a1a22] text-gray-400 hover:text-white border border-[#2a2a35]';
            ?>
            <a href="?period=<?php echo $k; ?>" class="px-4 py-2 rounded-full text-sm font-semibold transition <?php echo $active; ?>"><?php echo $v; ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($leaderboard): ?>
        <div class="space-y-2">
            <?php foreach ($leaderboard as $i => $u): 
                $u_lvl = mv_get_user_level_data($u->ID);
                $style = $rank_styles[$i] ?? ['border-[#2a2a35] bg-transparent', 'text-gray-500', '#'];
            ?>
            <a href="<?php echo esc_url(home_url('/user/' . $u->user_login)); ?>" class="flex items-center gap-4 p-4 rounded-xl border <?php echo $style[0]; ?> hover:bg-[#252530] transition group">
                <div class="w-10 text-center font-bold text-lg <?php echo $style[1]; ?>">
                    <?php echo $i < 3 ? $style[2] : ($i + 1); ?>
                </div>
                <img src="<?php echo get_avatar_url($u->ID); ?>" class="w-11 h-11 rounded-xl object-cover shrink-0 border border-[#2a2a35]">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-white truncate"><?php echo esc_html($u->display_name); ?></span>
                        <span class="text-[10px] bg-gradient-to-r <?php echo $u_lvl['rank_color']; ?> text-white px-2 py-0.5 rounded-full shrink-0"><?php echo esc_html($u_lvl['rank_name']); ?></span>
                    </div>
                    <div class="flex items-center gap-3 mt-0.5">
                        <span class="text-xs text-gray-500">Lv.<?php echo $u_lvl['level']; ?></span>
                        <div class="w-20 h-1.5 bg-[#252530] rounded-full overflow-hidden">
                            <div class="h-full bg-mv-accent rounded-full" style="width:<?php echo $u_lvl['progress']; ?>"></div>
                        </div>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-lg font-bold text-mv-accent"><?php echo number_format(intval($u->xp)); ?></div>
                    <div class="text-[10px] text-gray-500 uppercase">XP</div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-16 bg-[#1a1a22] rounded-2xl border border-[#2a2a35]">
            <p class="text-gray-500">No data yet. Start reading to earn XP!</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
