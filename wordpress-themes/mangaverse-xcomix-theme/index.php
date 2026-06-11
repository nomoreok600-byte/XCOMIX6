<?php get_header(); ?>

<?php if (get_option('mv_show_hero', '1') === '1'): ?>
<!-- Hero Section -->
<section class="relative min-h-screen flex items-center justify-center overflow-hidden pt-14">
    <!-- Background -->
    <div class="absolute inset-0 z-0">
        <img src="<?php echo esc_url(get_option('mv_hero_bg', 'https://images.unsplash.com/photo-1541562232579-512a21360020?auto=format&fit=crop&w=2000&q=80')); ?>" 
             alt="" class="w-full h-full object-cover opacity-30" fetchpriority="high">
        <div class="absolute inset-0 bg-gradient-to-b from-[#0f0f13]/60 via-[#0f0f13]/80 to-[#0f0f13]"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-[#0f0f13] via-transparent to-[#0f0f13]"></div>
    </div>

    <!-- Content -->
    <div class="relative z-10 text-center px-4 max-w-4xl mx-auto">
        <p class="text-mv-accent text-sm font-semibold tracking-[0.25em] uppercase mb-4 animate-fade-in" style="animation-delay:0.1s">
            Free Manga Reading Platform
        </p>
        <h1 class="text-4xl sm:text-5xl md:text-7xl font-extrabold leading-[1.1] mb-6 animate-fade-in" style="animation-delay:0.2s">
            <span class="text-gradient"><?php echo esc_html(get_option('mv_hero_title', 'Discover stories drawn by imagination')); ?></span>
        </h1>
        <p class="text-gray-400 text-base sm:text-lg max-w-2xl mx-auto mb-8 leading-relaxed animate-fade-in" style="animation-delay:0.3s">
            <?php echo esc_html(get_option('mv_hero_subtitle', 'Follow your favorite series, track new chapters, and dive into worlds created by talented artists. All for free.')); ?>
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4 animate-fade-in" style="animation-delay:0.4s">
            <a href="<?php echo esc_url(home_url('/browse')); ?>" class="h-12 px-8 bg-mv-accent hover:bg-mv-accentHover text-white font-semibold rounded-full shadow-lg shadow-mv-accent/25 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <?php echo esc_html(get_option('mv_hero_button', 'Start Reading')); ?>
            </a>
            <a href="<?php echo esc_url(home_url('/auth?register=1')); ?>" class="h-12 px-8 bg-transparent hover:bg-white/5 border border-white/10 text-white font-semibold rounded-full transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
                Create Account
            </a>
        </div>
    </div>

    <!-- Scroll Indicator -->
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce z-10">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
        </svg>
    </div>
</section>
<?php endif; ?>

<?php if (get_option('mv_show_stats', '1') === '1'): ?>
<!-- Stats Section -->
<section class="py-16 lg:py-20 border-b border-[#2a2a35]/30">
    <div class="max-w-[1400px] mx-auto px-4 lg:px-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            <?php
            $manga_count = wp_count_posts('manga')->publish;
            $chapter_count = wp_count_posts('chapter')->publish;
            $user_count = count_users()['total_users'];
            $genre_count = wp_count_terms('genre');
            $stats = [
                ['number' => $manga_count, 'label' => 'Manga Series', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>'],
                ['number' => $chapter_count, 'label' => 'Chapters Released', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>'],
                ['number' => $user_count, 'label' => 'Active Readers', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                ['number' => $genre_count, 'label' => 'Genres', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>'],
            ];
            foreach ($stats as $i => $stat):
                $formatted = $stat['number'] >= 1000 ? round($stat['number']/1000, 1).'K' : ($stat['number'] >= 1000000 ? round($stat['number']/1000000, 1).'M' : $stat['number']);
            ?>
            <div class="text-center animate-fade-in-up" style="animation-delay:<?php echo 0.1 * $i; ?>s">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-[#1a1a22] border border-[#2a2a35] mb-4">
                    <svg class="w-7 h-7 text-mv-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <?php echo $stat['icon']; ?>
                    </svg>
                </div>
                <div class="text-3xl lg:text-4xl font-extrabold text-white mb-1" data-count="<?php echo $stat['number']; ?>">
                    <?php echo $formatted; ?>
                </div>
                <div class="text-sm text-gray-500"><?php echo esc_html($stat['label']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (get_option('mv_show_featured', '1') === '1'):
    $featured = get_option('mv_featured_manga', []);
    if (!empty($featured)):
        $featured_mangas = get_posts(['post_type' => 'manga', 'post__in' => $featured, 'posts_per_page' => 8]);
        if (!empty($featured_mangas)):
?>
<!-- Featured Slider -->
<section class="py-12 lg:py-16">
    <div class="max-w-[1400px] mx-auto px-4 lg:px-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl lg:text-2xl font-bold">Featured Series</h2>
            <a href="<?php echo esc_url(home_url('/browse')); ?>" class="text-sm text-mv-accent hover:underline">View All</a>
        </div>
        <div class="swiper featuredSwiper !overflow-visible">
            <div class="swiper-wrapper">
                <?php foreach ($featured_mangas as $manga): 
                    $genres = wp_get_post_terms($manga->ID, 'genre', ['fields' => 'names']);
                    $score = get_post_meta($manga->ID, '_mv_score', true) ?: 'N/A';
                ?>
                <div class="swiper-slide !w-[200px] lg:!w-[220px]">
                    <a href="<?php echo esc_url(get_permalink($manga->ID)); ?>" class="group block card-hover">
                        <div class="relative rounded-xl overflow-hidden bg-[#1a1a22] aspect-[2/3] mb-3">
                            <img src="<?php echo mv_get_cover($manga->ID, 'medium'); ?>" 
                                 alt="<?php echo esc_attr($manga->post_title); ?>" 
                                 class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                 loading="lazy">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <div class="absolute top-2 right-2 bg-mv-accent/90 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                                <?php echo esc_html($score); ?>
                            </div>
                        </div>
                        <h3 class="text-sm font-semibold truncate mb-1 group-hover:text-mv-accent transition">
                            <?php echo esc_html($manga->post_title); ?>
                        </h3>
                        <?php if ($genres): ?>
                        <p class="text-[11px] text-gray-500 truncate"><?php echo esc_html(implode(', ', array_slice($genres, 0, 2))); ?></p>
                        <?php endif; ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<script>document.addEventListener('DOMContentLoaded', function() {
    new Swiper('.featuredSwiper', { slidesPerView: 'auto', spaceBetween: 16, freeMode: true, grabCursor: true });
});</script>
<?php endif; endif; endif; ?>

<?php if (get_option('mv_show_recent', '1') === '1'): ?>
<!-- Recently Added -->
<section class="py-12 lg:py-16 border-t border-[#2a2a35]/30">
    <div class="max-w-[1400px] mx-auto px-4 lg:px-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl lg:text-2xl font-bold">Recently Added</h2>
            <a href="<?php echo esc_url(home_url('/recent')); ?>" class="text-sm text-mv-accent hover:underline">View All</a>
        </div>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Main Grid -->
            <div class="flex-1">
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 xl:grid-cols-6 gap-4">
                    <?php
                    $recent = get_posts([
                        'post_type' => 'manga', 
                        'posts_per_page' => get_option('mv_recent_count', 12),
                        'orderby' => 'date', 
                        'order' => 'DESC'
                    ]);
                    foreach ($recent as $manga):
                        $genres = wp_get_post_terms($manga->ID, 'genre', ['fields' => 'names']);
                        $score = get_post_meta($manga->ID, '_mv_score', true) ?: 'N/A';
                        $last_ch = get_posts([
                            'post_type' => 'chapter', 
                            'post_parent' => $manga->ID,
                            'posts_per_page' => 1, 
                            'orderby' => 'date', 
                            'order' => 'DESC'
                        ]);
                        $chap_num = $last_ch ? mvx_chapter_number($last_ch[0]->ID) : '';
                    ?>
                    <a href="<?php echo esc_url(get_permalink($manga->ID)); ?>" class="group block card-hover">
                        <div class="relative rounded-xl overflow-hidden bg-[#1a1a22] aspect-[2/3] mb-2">
                            <img src="<?php echo mv_get_cover($manga->ID, 'medium'); ?>" 
                                 alt="<?php echo esc_attr($manga->post_title); ?>" 
                                 class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                 loading="lazy">
                            <div class="absolute top-2 right-2 bg-mv-accent/90 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                                <?php echo esc_html($score); ?>
                            </div>
                        </div>
                        <h3 class="text-[13px] font-semibold truncate leading-tight group-hover:text-mv-accent transition">
                            <?php echo esc_html($manga->post_title); ?>
                        </h3>
                        <?php if ($chap_num): ?>
                        <p class="text-[11px] text-gray-500">Chapter <?php echo esc_html($chap_num); ?></p>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Popular Sidebar -->
            <?php if (get_option('mv_show_popular', '1') === '1'): ?>
            <div class="w-full lg:w-72 xl:w-80 shrink-0">
                <div class="bg-[#1a1a22] rounded-2xl border border-[#2a2a35] p-5 sticky top-20">
                    <h3 class="text-base font-bold mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-mv-accent" fill="currentColor" viewBox="0 0 20 20"><path d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z"/></svg>
                        Popular Chapters
                    </h3>
                    <div class="space-y-3">
                        <?php
                        $period = get_option('mv_popular_period', 'week');
                        $date_query = [];
                        if ($period === 'day') $date_query = ['after' => '1 day ago'];
                        elseif ($period === 'week') $date_query = ['after' => '7 days ago'];
                        elseif ($period === 'month') $date_query = ['after' => '30 days ago'];
                        
                        $popular = get_posts([
                            'post_type' => 'chapter', 
                            'posts_per_page' => 8,
                            'orderby' => 'comment_count', 
                            'order' => 'DESC',
                            'date_query' => $date_query ?: null,
                        ]);
                        foreach ($popular as $ch):
                            $manga_id = mvx_parent_manga_id($ch->ID);
                            $manga = $manga_id ? get_post($manga_id) : null;
                            $ch_num = mvx_chapter_number($ch->ID);
                        ?>
                        <a href="<?php echo esc_url(get_permalink($ch->ID)); ?>" class="flex items-center gap-3 group hover:bg-white/[0.03] -mx-2 px-2 py-2 rounded-lg transition">
                            <img src="<?php echo $manga ? mv_get_cover($manga->ID, 'mv_cover_small') : ''; ?>" 
                                 class="w-10 h-14 rounded-md object-cover shrink-0" loading="lazy">
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-semibold truncate group-hover:text-mv-accent transition">
                                    <?php echo $manga ? esc_html($manga->post_title) : 'Unknown'; ?>
                                </p>
                                <p class="text-[11px] text-gray-500">Ch. <?php echo esc_html($ch_num); ?></p>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sidebar Ad -->
                <?php mv_ad('sidebar'); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CTA Banner -->
<section class="py-12 lg:py-16">
    <div class="max-w-[1400px] mx-auto px-4 lg:px-6">
        <div class="relative bg-gradient-to-r from-[#1a1a22] to-[#252530] rounded-3xl p-8 lg:p-12 border border-[#2a2a35] overflow-hidden">
            <div class="absolute top-0 right-0 w-72 h-72 bg-mv-accent/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/4"></div>
            <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div>
                    <h2 class="text-2xl lg:text-3xl font-bold mb-2">Never miss a chapter update</h2>
                    <p class="text-gray-400 text-sm lg:text-base max-w-lg">Create a free account to get instant notifications when new chapters of your bookmarked manga are released. Track your reading progress and join the community.</p>
                </div>
                <a href="<?php echo esc_url(home_url('/auth?register=1')); ?>" class="shrink-0 h-12 px-8 bg-mv-accent hover:bg-mv-accentHover text-white font-semibold rounded-full shadow-lg shadow-mv-accent/25 transition-all flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    Create Free Account
                </a>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
