<?php get_header();
$query = get_search_query();
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

$args = [
    'post_type' => ['manga', 'chapter'],
    's' => $query,
    'posts_per_page' => 24,
    'paged' => $paged,
];
$results = new WP_Query($args);
?>

<section class="pt-20 min-h-screen">
    <div class="max-w-[1400px] mx-auto px-4 lg:px-6">
        <div class="mb-8">
            <h1 class="text-2xl lg:text-3xl font-bold mb-1">
                Search Results
            </h1>
            <p class="text-gray-500 text-sm"><?php echo $results->found_posts; ?> results for "<?php echo esc_html($query); ?>"</p>
        </div>

        <?php if ($results->have_posts()): ?>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4">
            <?php while ($results->have_posts()): $results->the_post();
                if (get_post_type() === 'chapter') {
                    $manga_id = get_post_meta(get_the_ID(), '_mv_parent_manga', true);
                    $manga = $manga_id ? get_post($manga_id) : null;
                    if (!$manga) continue;
                }
            ?>
            <a href="<?php the_permalink(); ?>" class="group block card-hover">
                <div class="relative rounded-xl overflow-hidden bg-[#1a1a22] aspect-[2/3] mb-2">
                    <?php if (get_post_type() === 'chapter' && $manga): ?>
                    <img src="<?php echo mv_get_cover($manga->ID, 'medium'); ?>" alt="" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
                    <div class="absolute top-2 left-2 bg-green-500/90 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">CH</div>
                    <?php else: ?>
                    <img src="<?php echo mv_get_cover(get_the_ID(), 'medium'); ?>" alt="" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
                    <?php endif; ?>
                </div>
                <h3 class="text-[13px] font-semibold truncate leading-tight group-hover:text-mv-accent transition">
                    <?php the_title(); ?>
                </h3>
                <?php if (get_post_type() === 'chapter' && $manga): ?>
                <p class="text-[11px] text-gray-500 truncate"><?php echo esc_html($manga->post_title); ?></p>
                <?php endif; ?>
            </a>
            <?php endwhile; ?>
        </div>
        <div class="flex justify-center mt-10">
            <?php echo paginate_links(['total' => $results->max_num_pages, 'current' => $paged]); ?>
        </div>
        <?php else: ?>
        <div class="text-center py-16">
            <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <h3 class="text-lg font-bold text-gray-400 mb-2">No results found</h3>
            <p class="text-gray-500 text-sm mb-4">Try different keywords or check the spelling.</p>
            <a href="<?php echo esc_url(home_url('/browse')); ?>" class="text-mv-accent hover:underline text-sm font-semibold">Browse all manga</a>
        </div>
        <?php endif; wp_reset_postdata(); ?>
    </div>
</section>

<?php get_footer(); ?>
