<?php 
/* Template Name: Home Page */
get_header(); 

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$tab = sanitize_text_field($_GET['tab'] ?? 'hot');
$genre_filter = sanitize_text_field($_GET['genre'] ?? '');
$type_filter = sanitize_text_field($_GET['type'] ?? '');
$status_filter = sanitize_text_field($_GET['status'] ?? '');
$sort = sanitize_text_field($_GET['sort'] ?? 'recent');

$args = ['post_type' => 'manga', 'posts_per_page' => 24, 'paged' => $paged];

// Sort
if ($sort === 'popular') {
    $args['meta_key'] = '_mv_score';
    $args['orderby'] = 'meta_value_num';
    $args['order'] = 'DESC';
} elseif ($sort === 'rating') {
    $args['meta_key'] = '_mv_score';
    $args['orderby'] = 'meta_value_num';
    $args['order'] = 'DESC';
} elseif ($sort === 'az') {
    $args['orderby'] = 'title';
    $args['order'] = 'ASC';
} else {
    $args['orderby'] = 'date';
    $args['order'] = 'DESC';
}

// Filters
$tax_query = [];
if ($genre_filter) $tax_query[] = ['taxonomy' => 'genre', 'field' => 'slug', 'terms' => $genre_filter];
if ($type_filter) $tax_query[] = ['taxonomy' => 'manga_type', 'field' => 'slug', 'terms' => $type_filter];
if ($status_filter) $tax_query[] = ['taxonomy' => 'manga_status', 'field' => 'slug', 'terms' => $status_filter];
if ($tax_query) $args['tax_query'] = $tax_query;

$manga_query = new WP_Query($args);
$all_genres = get_terms(['taxonomy' => 'genre', 'hide_empty' => true, 'number' => 50]);
$all_types = get_terms(['taxonomy' => 'manga_type', 'hide_empty' => false]);
$all_statuses = get_terms(['taxonomy' => 'manga_status', 'hide_empty' => false]);

// Hero featured manga
$featured = get_posts(['post_type' => 'manga', 'posts_per_page' => 5, 'meta_key' => '_mv_score', 'orderby' => 'meta_value_num', 'order' => 'DESC']);
?>

<!-- Hero Featured Slider -->
<section class="pt-14 relative overflow-hidden">
    <div class="max-w-[1400px] mx-auto px-4 lg:px-6 py-6">
        <div class="swiper heroSwiper rounded-2xl overflow-hidden">
            <div class="swiper-wrapper">
                <?php foreach ($featured as $manga): 
                    $cover = mv_get_cover($manga->ID, 'large');
                    $genres = wp_get_post_terms($manga->ID, 'genre', ['fields' => 'names']);
                    $score = mvx_manga_meta($manga->ID, 'score', 'N/A');
                    $author = mvx_manga_meta($manga->ID, 'author', 'Unknown');
                    $desc = wp_trim_words($manga->post_content, 30);
                    $last_ch = get_posts([
                        'post_type' => 'chapter', 
                        'post_parent' => $manga->ID,
                        'posts_per_page' => 1, 
                        'orderby' => 'date', 
                        'order' => 'DESC'
                    ]);
                    $last_ch_url = $last_ch ? get_permalink($last_ch[0]->ID) : '#';
                    $last_ch_num = $last_ch ? mvx_chapter_number($last_ch[0]->ID) : '';
                ?>
                <div class="swiper-slide relative">
                    <div class="h-[400px] lg:h-[500px] relative">
                        <img src="<?php echo esc_url($cover); ?>" alt="" class="w-full h-full object-cover" loading="lazy">
                        <div class="absolute inset-0 bg-gradient-to-r from-[#0f0f13] via-[#0f0f13]/80 to-transparent"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-[#0f0f13] via-transparent to-transparent"></div>
                        <div class="absolute inset-0 flex items-end p-6 lg:p-10">
                            <div class="max-w-2xl">
                                <div class="flex flex-wrap gap-2 mb-3">
                                    <?php foreach (array_slice($genres, 0, 4) as $g): ?>
                                    <span class="text-[11px] bg-white/10 backdrop-blur text-white px-3 py-1 rounded-full"><?php echo esc_html($g); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <h2 class="text-2xl lg:text-4xl font-extrabold mb-2"><?php echo esc_html($manga->post_title); ?></h2>
                                <p class="text-gray-400 text-sm mb-1"><?php echo esc_html($author); ?></p>
                                <p class="text-gray-300 text-sm mb-4 line-clamp-2"><?php echo esc_html($desc); ?></p>
                                <div class="flex items-center gap-3">
                                    <a href="<?php echo esc_url($last_ch_url); ?>" class="h-10 px-6 bg-mv-accent hover:bg-mv-accentHover text-white text-sm font-semibold rounded-full transition flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/></svg>
                                        Read Now
                                    </a>
                                    <a href="<?php echo esc_url(get_permalink($manga->ID)); ?>" class="h-10 px-6 bg-white/10 hover:bg-white/20 text-white text-sm font-semibold rounded-full transition flex items-center gap-2">
                                        Details
                                    </a>
                                    <span class="flex items-center gap-1 text-yellow-400 text-sm font-bold">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        <?php echo esc_html($score); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </div>
</section>

<script>document.addEventListener('DOMContentLoaded', function() {
    new Swiper('.heroSwiper', { 
        loop: true, 
        autoplay: { delay: 5000, disableOnInteraction: false },
        pagination: { el: '.swiper-pagination', clickable: true },
        effect: 'fade',
        fadeEffect: { crossFade: true }
    });
});</script>

<!-- Main Content Area -->
<section class="py-6">
    <div class="max-w-[1400px] mx-auto px-4 lg:px-6">
        <!-- Filters Bar -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6 pb-4 border-b border-[#2a2a35]">
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1">
                <?php 
                $sorts = ['recent' => 'Recently Added', 'popular' => 'Most Popular', 'rating' => 'Top Rated', 'az' => 'A-Z'];
                foreach ($sorts as $k => $v): 
                    $active = ($sort === $k) ? 'bg-mv-accent text-white' : 'bg-[#1a1a22] text-gray-400 hover:text-white border border-[#2a2a35]';
                ?>
                <a href="?sort=<?php echo $k; ?>" class="shrink-0 px-4 py-2 rounded-full text-xs font-semibold transition <?php echo $active; ?>">
                    <?php echo $v; ?>
                </a>
                <?php endforeach; ?>
            </div>
            
            <form method="get" class="flex items-center gap-2">
                <select name="genre" onchange="this.form.submit()" class="h-9 px-3 bg-[#1a1a22] border border-[#2a2a35] rounded-full text-xs text-gray-300 outline-none focus:border-mv-accent">
                    <option value="">All Genres</option>
                    <?php foreach ($all_genres as $g): ?>
                    <option value="<?php echo esc_attr($g->slug); ?>" <?php selected($genre_filter, $g->slug); ?>><?php echo esc_html($g->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="type" onchange="this.form.submit()" class="h-9 px-3 bg-[#1a1a22] border border-[#2a2a35] rounded-full text-xs text-gray-300 outline-none focus:border-mv-accent">
                    <option value="">All Types</option>
                    <?php foreach ($all_types as $t): ?>
                    <option value="<?php echo esc_attr($t->slug); ?>" <?php selected($type_filter, $t->slug); ?>><?php echo esc_html($t->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" onchange="this.form.submit()" class="h-9 px-3 bg-[#1a1a22] border border-[#2a2a35] rounded-full text-xs text-gray-300 outline-none focus:border-mv-accent">
                    <option value="">All Status</option>
                    <?php foreach ($all_statuses as $s): ?>
                    <option value="<?php echo esc_attr($s->slug); ?>" <?php selected($status_filter, $s->slug); ?>><?php echo esc_html($s->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="sort" value="<?php echo esc_attr($sort); ?>">
            </form>
        </div>

        <!-- Manga Grid -->
        <?php if ($manga_query->have_posts()): ?>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4 lg:gap-5">
            <?php while ($manga_query->have_posts()): $manga_query->the_post(); 
                $genres = wp_get_post_terms(get_the_ID(), 'genre', ['fields' => 'names']);
                $score = mvx_manga_meta(get_the_ID(), 'score', 'N/A');
                $last_ch = get_posts([
                    'post_type' => 'chapter', 
                    'post_parent' => get_the_ID(),
                    'posts_per_page' => 1, 
                    'orderby' => 'date', 
                    'order' => 'DESC'
                ]);
                $chap_num = $last_ch ? mvx_chapter_number($last_ch[0]->ID) : '';
            ?>
            <a href="<?php the_permalink(); ?>" class="group block card-hover">
                <div class="relative rounded-xl overflow-hidden bg-[#1a1a22] aspect-[2/3] mb-2">
                    <img src="<?php echo mv_get_cover(get_the_ID(), 'medium'); ?>" 
                         alt="<?php the_title_attribute(); ?>" 
                         class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                         loading="lazy">
                    <div class="absolute top-2 right-2 bg-mv-accent/90 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                        <?php echo esc_html($score); ?>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/90 to-transparent p-3 pt-8">
                        <p class="text-[10px] text-gray-300 truncate"><?php echo $chap_num ? 'Ch. '.esc_html($chap_num) : '&nbsp;'; ?></p>
                    </div>
                </div>
                <h3 class="text-[13px] font-semibold truncate leading-tight group-hover:text-mv-accent transition">
                    <?php the_title(); ?>
                </h3>
                <?php if ($genres): ?>
                <p class="text-[11px] text-gray-500 truncate"><?php echo esc_html(implode(', ', array_slice($genres, 0, 2))); ?></p>
                <?php endif; ?>
            </a>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center mt-10">
            <?php 
            echo paginate_links([
                'total' => $manga_query->max_num_pages,
                'current' => $paged,
                'prev_text' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>',
                'next_text' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>',
            ]); 
            ?>
        </div>
        <?php else: ?>
        <div class="text-center py-16">
            <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <h3 class="text-lg font-bold text-gray-400 mb-2">No manga found</h3>
            <p class="text-gray-500 text-sm">Try adjusting your filters or check back later.</p>
        </div>
        <?php endif; wp_reset_postdata(); ?>
    </div>
</section>

<?php get_footer(); ?>
