<?php
/* Template Name: Home Page */
get_header();

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$genre_filter = sanitize_text_field($_GET['genre'] ?? '');
$type_filter = sanitize_text_field($_GET['type'] ?? '');
$status_filter = sanitize_text_field($_GET['status'] ?? '');
$sort = sanitize_text_field($_GET['sort'] ?? 'recent');

if (!function_exists('mvx_latest_chapters_for_manga')) {
    function mvx_latest_chapters_for_manga($manga_id, $limit = 1) {
        $chapters = mvx_get_chapters($manga_id, 'DESC');
        return array_slice($chapters, 0, $limit);
    }
}

if (!function_exists('mvx_render_home_card')) {
    function mvx_render_home_card($manga_id, $variant = 'grid') {
        $title = get_the_title($manga_id);
        $cover = mv_get_cover($manga_id, 'medium');
        $type = mvx_manga_meta($manga_id, 'type', 'Manga');
        $status = mvx_manga_meta($manga_id, 'status', 'Ongoing');
        $score = mvx_manga_meta($manga_id, 'score', 'N/A');
        $is_18 = mvx_manga_meta($manga_id, 'is_18_plus');
        $genres = wp_get_post_terms($manga_id, 'genre', ['fields' => 'names']);
        $latest = mvx_latest_chapters_for_manga($manga_id, 1);
        $latest_num = $latest ? mvx_chapter_number($latest[0]->ID) : '';
        ?>
        <a href="<?php echo esc_url(get_permalink($manga_id)); ?>" class="group block card-hover">
            <div class="relative rounded-2xl overflow-hidden bg-[#1a1a22] aspect-[2/3] mb-2 border border-white/[0.04] shadow-lg">
                <img src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr($title); ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" loading="lazy" decoding="async">
                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/10 to-transparent opacity-70 group-hover:opacity-95 transition"></div>
                <div class="absolute top-2 left-2 flex flex-col gap-1.5">
                    <span class="bg-black/70 backdrop-blur text-white text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md border border-white/10"><?php echo esc_html($type); ?></span>
                    <?php if ($is_18): ?><span class="bg-red-600/90 text-white text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md">18+</span><?php endif; ?>
                </div>
                <div class="absolute top-2 right-2 bg-mv-accent/95 text-white text-[10px] font-bold px-2 py-1 rounded-full shadow-lg">
                    <?php echo esc_html($score); ?>
                </div>
                <div class="absolute bottom-2 left-2 right-2 flex items-center justify-between gap-2">
                    <span class="text-[10px] font-bold text-gray-200 truncate"><?php echo $latest_num ? 'Ch. ' . esc_html($latest_num) : 'No chapters'; ?></span>
                    <span class="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-gray-200">
                        <span class="w-1.5 h-1.5 rounded-full <?php echo strtolower($status) === 'completed' || strtolower($status) === 'complete' ? 'bg-blue-500' : 'bg-green-500 animate-pulse'; ?>"></span>
                        <?php echo esc_html($status); ?>
                    </span>
                </div>
            </div>
            <h3 class="text-[13px] font-bold leading-tight line-clamp-2 group-hover:text-mv-accent transition"><?php echo esc_html($title); ?></h3>
            <?php if ($genres): ?><p class="text-[11px] text-gray-500 truncate mt-0.5"><?php echo esc_html(implode(', ', array_slice($genres, 0, 2))); ?></p><?php endif; ?>
        </a>
        <?php
    }
}

$tax_query = [];
if ($genre_filter) $tax_query[] = ['taxonomy' => 'genre', 'field' => 'slug', 'terms' => $genre_filter];
if ($type_filter) $tax_query[] = ['taxonomy' => 'manga_type', 'field' => 'slug', 'terms' => $type_filter];
if ($status_filter) $tax_query[] = ['taxonomy' => 'manga_status', 'field' => 'slug', 'terms' => $status_filter];

$grid_args = ['post_type' => 'manga', 'posts_per_page' => 24, 'paged' => $paged, 'post_status' => 'publish'];
if ($sort === 'popular' || $sort === 'rating') {
    $grid_args['meta_key'] = '_mv_score';
    $grid_args['orderby'] = 'meta_value_num';
    $grid_args['order'] = 'DESC';
} elseif ($sort === 'az') {
    $grid_args['orderby'] = 'title';
    $grid_args['order'] = 'ASC';
} else {
    $grid_args['orderby'] = 'modified';
    $grid_args['order'] = 'DESC';
}
if ($tax_query) $grid_args['tax_query'] = $tax_query;
$manga_query = new WP_Query($grid_args);

$featured = get_option('mv_featured_manga', []);
if (!empty($featured)) {
    $hero_query = new WP_Query(['post_type' => 'manga', 'post__in' => array_map('intval', $featured), 'orderby' => 'post__in', 'posts_per_page' => 6]);
} else {
    $hero_query = new WP_Query(['post_type' => 'manga', 'posts_per_page' => 6, 'orderby' => 'modified', 'order' => 'DESC']);
}
$hot_query = new WP_Query(['post_type' => 'manga', 'posts_per_page' => 12, 'orderby' => 'modified', 'order' => 'DESC']);
$popular_query = new WP_Query(['post_type' => 'manga', 'posts_per_page' => 12, 'meta_key' => '_mv_score', 'orderby' => 'meta_value_num', 'order' => 'DESC']);
$completed_query = new WP_Query([
    'post_type' => 'manga',
    'posts_per_page' => 12,
    'tax_query' => [['taxonomy' => 'manga_status', 'field' => 'slug', 'terms' => ['completed', 'complete']]],
]);
$recent_chapters = new WP_Query(['post_type' => 'chapter', 'posts_per_page' => 12, 'orderby' => 'date', 'order' => 'DESC']);
$all_genres = get_terms(['taxonomy' => 'genre', 'hide_empty' => true, 'number' => 50]);
$all_types = get_terms(['taxonomy' => 'manga_type', 'hide_empty' => false]);
$all_statuses = get_terms(['taxonomy' => 'manga_status', 'hide_empty' => false]);
?>

<section class="pt-14 relative overflow-hidden">
    <div class="max-w-[1400px] mx-auto px-4 lg:px-6 py-6">
        <div class="swiper heroSwiper rounded-3xl overflow-hidden border border-white/[0.04] bg-[#1a1a22] shadow-2xl">
            <div class="swiper-wrapper">
                <?php if ($hero_query->have_posts()): while ($hero_query->have_posts()): $hero_query->the_post();
                    $manga_id = get_the_ID();
                    $cover = mv_get_cover($manga_id, 'large');
                    $genres = wp_get_post_terms($manga_id, 'genre', ['fields' => 'names']);
                    $score = mvx_manga_meta($manga_id, 'score', 'N/A');
                    $author = mvx_manga_meta($manga_id, 'author', 'Unknown');
                    $latest = mvx_latest_chapters_for_manga($manga_id, 1);
                    $read_url = $latest ? get_permalink($latest[0]->ID) : get_permalink($manga_id);
                    $latest_num = $latest ? mvx_chapter_number($latest[0]->ID) : '';
                ?>
                <div class="swiper-slide relative">
                    <div class="h-[430px] lg:h-[540px] relative">
                        <img src="<?php echo esc_url($cover); ?>" alt="<?php the_title_attribute(); ?>" class="w-full h-full object-cover" loading="eager">
                        <div class="absolute inset-0 bg-gradient-to-r from-[#0f0f13] via-[#0f0f13]/80 to-transparent"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-[#0f0f13] via-transparent to-transparent"></div>
                        <div class="absolute inset-0 flex items-end p-6 lg:p-12">
                            <div class="max-w-2xl">
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <span class="text-[11px] bg-mv-accent text-white px-3 py-1 rounded-full font-bold uppercase tracking-wider">Featured</span>
                                    <?php foreach (array_slice($genres, 0, 4) as $g): ?><span class="text-[11px] bg-white/10 backdrop-blur text-white px-3 py-1 rounded-full"><?php echo esc_html($g); ?></span><?php endforeach; ?>
                                </div>
                                <h1 class="text-3xl lg:text-5xl font-black mb-3 leading-tight"><?php the_title(); ?></h1>
                                <p class="text-gray-400 text-sm mb-1">By <?php echo esc_html($author); ?></p>
                                <p class="text-gray-300 text-sm mb-5 line-clamp-2 max-w-xl"><?php echo esc_html(wp_trim_words(get_the_content(), 32)); ?></p>
                                <div class="flex flex-wrap items-center gap-3">
                                    <a href="<?php echo esc_url($read_url); ?>" class="h-11 px-6 bg-mv-accent hover:bg-mv-accentHover text-white text-sm font-black rounded-full transition flex items-center gap-2 shadow-lg shadow-mv-accent/25">Read <?php echo $latest_num ? 'Ch. ' . esc_html($latest_num) : 'Now'; ?></a>
                                    <a href="<?php the_permalink(); ?>" class="h-11 px-6 bg-white/10 hover:bg-white/20 text-white text-sm font-bold rounded-full transition">Details</a>
                                    <span class="text-yellow-400 text-sm font-black">★ <?php echo esc_html($score); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; wp_reset_postdata(); endif; ?>
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </div>
</section>

<script>document.addEventListener('DOMContentLoaded', function() { if (window.Swiper) new Swiper('.heroSwiper', { loop: true, autoplay: { delay: 5000, disableOnInteraction: false }, pagination: { el: '.swiper-pagination', clickable: true }, effect: 'fade', fadeEffect: { crossFade: true } }); });</script>

<main class="max-w-[1400px] mx-auto px-4 lg:px-6 pb-16">
    <section class="grid grid-cols-1 xl:grid-cols-[1fr_340px] gap-6 py-4">
        <div class="space-y-10 min-w-0">
            <?php
            $sections = [
                ['Hot Updates', 'Updated series getting fresh chapters', $hot_query, 'Hot', home_url('/recent')],
                ['Popular Manga', 'Top rated and most followed series', $popular_query, 'Popular', home_url('/browse?sort=popular')],
                ['Completed Series', 'Finished stories ready to binge', $completed_query, 'Complete', home_url('/browse?status=completed')],
            ];
            foreach ($sections as [$title, $subtitle, $query, $badge, $url]): ?>
            <section>
                <div class="flex items-end justify-between mb-4">
                    <div><h2 class="text-xl lg:text-2xl font-black flex items-center gap-2"><span class="w-1.5 h-6 bg-mv-accent rounded-full"></span><?php echo esc_html($title); ?></h2><p class="text-xs text-gray-500 mt-1"><?php echo esc_html($subtitle); ?></p></div>
                    <a href="<?php echo esc_url($url); ?>" class="text-xs font-bold uppercase tracking-widest text-mv-accent hover:underline">View all</a>
                </div>
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4 lg:gap-5">
                    <?php if ($query->have_posts()): while ($query->have_posts()): $query->the_post(); mvx_render_home_card(get_the_ID()); endwhile; wp_reset_postdata(); else: ?>
                    <p class="text-gray-500 text-sm col-span-full">No manga found.</p>
                    <?php endif; ?>
                </div>
            </section>
            <?php endforeach; ?>

            <section>
                <div class="flex items-center justify-between mb-4"><h2 class="text-xl lg:text-2xl font-black flex items-center gap-2"><span class="w-1.5 h-6 bg-green-500 rounded-full"></span>Recent Chapters</h2><a href="<?php echo esc_url(home_url('/recent')); ?>" class="text-xs font-bold uppercase tracking-widest text-mv-accent hover:underline">All updates</a></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php if ($recent_chapters->have_posts()): while ($recent_chapters->have_posts()): $recent_chapters->the_post();
                        $chapter_id = get_the_ID();
                        $manga_id = mvx_parent_manga_id($chapter_id);
                        if (!$manga_id) continue;
                        $chapter_num = mvx_chapter_number($chapter_id);
                    ?>
                    <a href="<?php the_permalink(); ?>" class="flex items-center gap-3 bg-[#1a1a22] hover:bg-[#252530] border border-[#2a2a35] rounded-2xl p-3 transition group">
                        <img src="<?php echo esc_url(mv_get_cover($manga_id, 'mv_cover_small')); ?>" class="w-12 h-16 rounded-xl object-cover shrink-0" alt="">
                        <div class="min-w-0 flex-1"><p class="text-sm font-bold truncate group-hover:text-mv-accent"><?php echo esc_html(get_the_title($manga_id)); ?></p><p class="text-xs text-gray-500 truncate">Chapter <?php echo esc_html($chapter_num); ?> • <?php echo human_time_diff(get_the_time('U'), current_time('timestamp')); ?> ago</p></div>
                    </a>
                    <?php endwhile; wp_reset_postdata(); endif; ?>
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <div class="bg-[#1a1a22] rounded-3xl border border-[#2a2a35] p-5 sticky top-20">
                <h3 class="font-black mb-4 flex items-center gap-2"><span class="w-1.5 h-5 bg-mv-accent rounded-full"></span>Browse Library</h3>
                <form method="get" class="space-y-3">
                    <select name="genre" class="w-full h-10 px-3 bg-[#252530] border border-[#2a2a35] rounded-xl text-xs text-gray-300 outline-none focus:border-mv-accent"><option value="">All Genres</option><?php foreach ($all_genres as $g): ?><option value="<?php echo esc_attr($g->slug); ?>" <?php selected($genre_filter, $g->slug); ?>><?php echo esc_html($g->name); ?></option><?php endforeach; ?></select>
                    <select name="type" class="w-full h-10 px-3 bg-[#252530] border border-[#2a2a35] rounded-xl text-xs text-gray-300 outline-none focus:border-mv-accent"><option value="">All Types</option><?php foreach ($all_types as $t): ?><option value="<?php echo esc_attr($t->slug); ?>" <?php selected($type_filter, $t->slug); ?>><?php echo esc_html($t->name); ?></option><?php endforeach; ?></select>
                    <select name="status" class="w-full h-10 px-3 bg-[#252530] border border-[#2a2a35] rounded-xl text-xs text-gray-300 outline-none focus:border-mv-accent"><option value="">All Status</option><?php foreach ($all_statuses as $s): ?><option value="<?php echo esc_attr($s->slug); ?>" <?php selected($status_filter, $s->slug); ?>><?php echo esc_html($s->name); ?></option><?php endforeach; ?></select>
                    <select name="sort" class="w-full h-10 px-3 bg-[#252530] border border-[#2a2a35] rounded-xl text-xs text-gray-300 outline-none focus:border-mv-accent"><option value="recent" <?php selected($sort, 'recent'); ?>>Recently Added</option><option value="popular" <?php selected($sort, 'popular'); ?>>Popular</option><option value="rating" <?php selected($sort, 'rating'); ?>>Top Rated</option><option value="az" <?php selected($sort, 'az'); ?>>A-Z</option></select>
                    <button class="w-full h-10 bg-mv-accent hover:bg-mv-accentHover rounded-xl text-sm font-black text-white transition">Apply Filters</button>
                </form>
            </div>
        </aside>
    </section>

    <section class="py-8">
        <div class="flex items-center justify-between mb-4"><h2 class="text-xl lg:text-2xl font-black flex items-center gap-2"><span class="w-1.5 h-6 bg-blue-500 rounded-full"></span>All Manga</h2></div>
        <?php if ($manga_query->have_posts()): ?>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8 gap-4 lg:gap-5">
            <?php while ($manga_query->have_posts()): $manga_query->the_post(); mvx_render_home_card(get_the_ID()); endwhile; ?>
        </div>
        <div class="flex justify-center mt-10"><?php echo paginate_links(['total' => $manga_query->max_num_pages, 'current' => $paged]); ?></div>
        <?php else: ?><div class="text-center py-16 bg-[#1a1a22] rounded-3xl border border-[#2a2a35]"><p class="text-gray-500">No manga found. Try changing filters.</p></div><?php endif; wp_reset_postdata(); ?>
    </section>
</main>

<?php get_footer(); ?>
