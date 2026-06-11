<?php
get_header();
$genre = get_queried_object();
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
?>

<section class="pt-20 min-h-screen">
    <div class="max-w-[1400px] mx-auto px-4 lg:px-6">
        <div class="flex items-center gap-3 mb-6">
            <span class="h-8 px-3 bg-mv-accent/20 text-mv-accent text-xs font-bold rounded-full flex items-center">GENRE</span>
            <h1 class="text-2xl lg:text-3xl font-bold"><?php echo esc_html($genre->name); ?></h1>
            <span class="text-sm text-gray-500"><?php echo $genre->count; ?> series</span>
        </div>

        <?php if ($genre->description): ?>
        <p class="text-gray-400 text-sm mb-6 max-w-2xl"><?php echo esc_html($genre->description); ?></p>
        <?php endif; ?>

        <?php if (have_posts()): ?>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4">
            <?php while (have_posts()): the_post();
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
            <?php the_posts_pagination(); ?>
        </div>
        <?php else: ?>
        <div class="text-center py-16">
            <p class="text-gray-500">No manga in this genre yet.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
