<?php 
/* Template Name: Browse */
get_header();

$letter = sanitize_text_field($_GET['letter'] ?? '');
$genre_filter = sanitize_text_field($_GET['genre'] ?? '');
$status_filter = sanitize_text_field($_GET['status'] ?? '');
$type_filter = sanitize_text_field($_GET['type'] ?? '');
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

$args = ['post_type' => 'manga', 'posts_per_page' => 36, 'paged' => $paged, 'orderby' => 'title', 'order' => 'ASC'];

$tax_query = [];
if ($genre_filter) $tax_query[] = ['taxonomy' => 'genre', 'field' => 'slug', 'terms' => $genre_filter];
if ($type_filter) $tax_query[] = ['taxonomy' => 'manga_type', 'field' => 'slug', 'terms' => $type_filter];
if ($status_filter) $tax_query[] = ['taxonomy' => 'manga_status', 'field' => 'slug', 'terms' => $status_filter];
if ($tax_query) $args['tax_query'] = $tax_query;
if ($letter) $args['starts_with'] = $letter;

$mangas = new WP_Query($args);
$genres = get_terms(['taxonomy' => 'genre', 'hide_empty' => true, 'number' => 100]);
$types = get_terms(['taxonomy' => 'manga_type', 'hide_empty' => false]);
$statuses = get_terms(['taxonomy' => 'manga_status', 'hide_empty' => false]);

$alphabet = range('A', 'Z');
?>

<section class="pt-20 min-h-screen">
    <div class="max-w-[1400px] mx-auto px-4 lg:px-6">
        <!-- Header -->
        <div class="flex items-center gap-3 mb-6">
            <svg class="w-7 h-7 text-mv-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            <h1 class="text-2xl lg:text-3xl font-bold">Browse Directory</h1>
            <span class="text-sm text-gray-500 ml-2"><?php echo $mangas->found_posts; ?> series</span>
        </div>

        <!-- Alphabet Filter -->
        <div class="flex items-center gap-1 overflow-x-auto no-scrollbar mb-4 pb-2">
            <a href="?" class="shrink-0 px-3 py-1.5 rounded-lg <?php echo !$letter ? 'bg-mv-accent text-white' : 'bg-[#1a1a22] text-gray-400 hover:text-white'; ?> text-xs font-semibold transition">ALL</a>
            <?php foreach ($alphabet as $l): ?>
            <a href="?letter=<?php echo $l; ?>" class="shrink-0 px-3 py-1.5 rounded-lg <?php echo $letter === $l ? 'bg-mv-accent text-white' : 'bg-[#1a1a22] text-gray-400 hover:text-white'; ?> text-xs font-semibold transition"><?php echo $l; ?></a>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <form method="get" class="flex flex-wrap items-center gap-2 mb-6">
            <select name="genre" onchange="this.form.submit()" class="h-9 px-3 bg-[#1a1a22] border border-[#2a2a35] rounded-full text-xs text-gray-300 outline-none focus:border-mv-accent">
                <option value="">All Genres</option>
                <?php foreach ($genres as $g): ?>
                <option value="<?php echo esc_attr($g->slug); ?>" <?php selected($genre_filter, $g->slug); ?>><?php echo esc_html($g->name); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="type" onchange="this.form.submit()" class="h-9 px-3 bg-[#1a1a22] border border-[#2a2a35] rounded-full text-xs text-gray-300 outline-none focus:border-mv-accent">
                <option value="">All Types</option>
                <?php foreach ($types as $t): ?>
                <option value="<?php echo esc_attr($t->slug); ?>" <?php selected($type_filter, $t->slug); ?>><?php echo esc_html($t->name); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" onchange="this.form.submit()" class="h-9 px-3 bg-[#1a1a22] border border-[#2a2a35] rounded-full text-xs text-gray-300 outline-none focus:border-mv-accent">
                <option value="">All Status</option>
                <?php foreach ($statuses as $s): ?>
                <option value="<?php echo esc_attr($s->slug); ?>" <?php selected($status_filter, $s->slug); ?>><?php echo esc_html($s->name); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($letter): ?><input type="hidden" name="letter" value="<?php echo esc_attr($letter); ?>"><?php endif; ?>
        </form>

        <!-- Manga Grid -->
        <?php if ($mangas->have_posts()): ?>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4">
            <?php while ($mangas->have_posts()): $mangas->the_post(); 
                $score = get_post_meta(get_the_ID(), '_mv_score', true) ?: 'N/A';
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
                </div>
                <h3 class="text-[13px] font-semibold truncate leading-tight group-hover:text-mv-accent transition">
                    <?php the_title(); ?>
                </h3>
            </a>
            <?php endwhile; ?>
        </div>
        <div class="flex justify-center mt-10">
            <?php echo paginate_links(['total' => $mangas->max_num_pages, 'current' => $paged]); ?>
        </div>
        <?php else: ?>
        <div class="text-center py-16">
            <p class="text-gray-500">No manga found matching your criteria.</p>
        </div>
        <?php endif; wp_reset_postdata(); ?>
    </div>
</section>

<?php get_footer(); ?>
