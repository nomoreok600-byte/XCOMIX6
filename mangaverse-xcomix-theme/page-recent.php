<?php 
/* Template Name: Recent Updates */
get_header();

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$chapters = get_posts([
    'post_type' => 'chapter', 
    'posts_per_page' => 36, 
    'paged' => $paged,
    'orderby' => 'date', 
    'order' => 'DESC'
]);
?>

<section class="pt-20 min-h-screen">
    <div class="max-w-[1400px] mx-auto px-4 lg:px-6">
        <div class="flex items-center gap-3 mb-8">
            <svg class="w-7 h-7 text-mv-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h1 class="text-2xl lg:text-3xl font-bold">Recent Updates</h1>
            <span class="text-sm text-gray-500 ml-2"><?php echo wp_count_posts('chapter')->publish; ?> chapters</span>
        </div>

        <?php if ($chapters): ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
            <?php foreach ($chapters as $ch):
                $manga_id = get_post_meta($ch->ID, '_mv_parent_manga', true);
                $manga = $manga_id ? get_post($manga_id) : null;
                if (!$manga) continue;
                $ch_num = get_post_meta($ch->ID, '_mv_chapter_number', true);
                $cover = mv_get_cover($manga->ID, 'medium');
            ?>
            <a href="<?php echo esc_url(get_permalink($ch->ID)); ?>" class="group block card-hover">
                <div class="relative rounded-xl overflow-hidden bg-[#1a1a22] aspect-[2/3] mb-2">
                    <img src="<?php echo esc_url($cover); ?>" alt="" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
                    <div class="absolute top-2 left-2 bg-green-500/90 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                        Ch. <?php echo esc_html($ch_num); ?>
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-3">
                        <span class="text-xs font-semibold text-white">Read Now</span>
                    </div>
                </div>
                <h3 class="text-[13px] font-semibold truncate leading-tight group-hover:text-mv-accent transition"><?php echo esc_html($manga->post_title); ?></h3>
                <p class="text-[11px] text-gray-500"><?php echo human_time_diff(get_the_time('U', $ch), current_time('timestamp')); ?> ago</p>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-16">
            <p class="text-gray-500">No chapters available yet.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
