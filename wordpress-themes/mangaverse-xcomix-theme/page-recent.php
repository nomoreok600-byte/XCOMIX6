<?php
/**
 * Template Name: Recent Updates
 * X COMIX - Elite App-Style Recent Page (Pro Edition)
 */


// =========================================================================
// BULLETPROOF IMAGE WRAPPER (Strict Local-Only Mode)
// =========================================================================
if (!function_exists('xcomix_get_cover')) {
    function xcomix_get_cover($post_id, $size = 'medium') {
        return mv_get_cover($post_id, $size);
    }
}

get_header(); 

$is_logged = is_user_logged_in();

// =========================================================================
// NSFW FILTER LOGIC
// =========================================================================
$show_nsfw = false;
if ($is_logged) {
    $show_nsfw = get_user_meta(get_current_user_id(), '_xcomix_pref_nsfw', true) === '1';
} else {
    $show_nsfw = isset($_COOKIE['xcomix_nsfw']) && $_COOKIE['xcomix_nsfw'] === '1';
}

$nsfw_meta = [];
if (!$show_nsfw) {
    $nsfw_meta = [
        'relation' => 'OR',
        ['key' => '_is_18_plus', 'compare' => 'NOT EXISTS'],
        ['key' => '_is_18_plus', 'value' => '1', 'compare' => '!=']
    ];
}

// =========================================================================
// QUERY LOGIC
// =========================================================================
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$recent_args = [
    'post_type'      => 'manga',
    'posts_per_page' => 24,
    'paged'          => $paged,
    'orderby'        => 'modified',
    'order'          => 'DESC'
];

if (!empty($nsfw_meta)) {
    $recent_args['meta_query'] = $nsfw_meta;
}

$recent_query = new WP_Query($recent_args);
?>

<main class="max-w-[1050px] mx-auto px-4 pt-[85px] pb-24 relative bg-[#09090b] min-h-screen selection:bg-[#ea580c] selection:text-white">
    
    <div class="mb-8">
        <div class="flex items-center gap-3 border-b border-[#333] pb-4">
            <span class="w-1.5 h-6 bg-[#ea580c] rounded-full shadow-[0_0_10px_#ea580c]"></span>
            <h1 class="text-xl md:text-2xl font-black text-white tracking-tight uppercase">Recent Updates</h1>
        </div>
    </div>

    <?php if ($recent_query->have_posts()) : ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-x-3 gap-y-6">
            <?php while ($recent_query->have_posts()) : $recent_query->the_post(); 
                $manga_id = get_the_ID();
                $title = get_the_title();
                $type = mvx_manga_meta($manga_id, 'type', 'Manga');
                $status = mvx_manga_meta($manga_id, 'status', 'Ongoing');
                $is_18 = mvx_manga_meta($manga_id, 'is_18_plus');

                $chapters = new WP_Query([
                    'post_type' => 'chapter', 'post_parent' => $manga_id, 'posts_per_page' => 2,
                    'meta_key' => '_chapter_number', 'orderby' => 'meta_value_num', 'order' => 'DESC'
                ]);
            ?>
            <article class="flex flex-col group update-card transition-all duration-300 hover:-translate-y-1">
                
                <div class="relative aspect-[3/4] block overflow-hidden rounded-[16px] mb-2.5 bg-[#121212] border border-white/5 shadow-md">
                    <a href="<?php echo get_permalink(); ?>" class="absolute inset-0 z-10"></a>
                    
                    <img src="<?php echo xcomix_get_cover($manga_id, 'medium'); ?>" 
                         onerror="this.onerror=null;this.src='https://placehold.co/300x400/1a1a1a/444444?text=Cover+Not+Found';" 
                         class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" 
                         loading="lazy" decoding="async">
                    
                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent z-10 pointer-events-none transition duration-300 group-hover:from-black"></div>
                    
                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300 z-20 pointer-events-none">
                        <span class="bg-[#ea580c] text-white text-[11px] font-black uppercase tracking-widest px-4 py-2 rounded-full shadow-lg transform translate-y-4 group-hover:translate-y-0 transition duration-300">Read Now</span>
                    </div>

                    <?php if ($is_logged) { ?>
                        <button title="Bookmark" onclick="event.preventDefault(); event.stopPropagation(); window.toggleBookmark(this, <?php echo $manga_id; ?>)" class="absolute top-2 left-2 z-20 bg-black/60 backdrop-blur-md text-white p-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity border border-white/10 hover:bg-[#ea580c] hover:border-[#ea580c]">
                            <svg class="w-3.5 h-3.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                        </button>
                    <?php } else { ?>
                        <a href="<?php echo site_url('/auth'); ?>" title="Login to Bookmark" onclick="event.stopPropagation();" class="absolute top-2 left-2 z-20 bg-black/60 backdrop-blur-md text-white p-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity border border-white/10 hover:bg-[#e11d48] hover:border-[#e11d48]">
                            <svg class="w-3.5 h-3.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                        </a>
                    <?php } ?>

                    <div class="absolute top-2 right-2 flex flex-col gap-1.5 items-end z-20 pointer-events-none">
                        <span class="bg-black/60 backdrop-blur-sm text-gray-100 text-[8px] font-black px-2 py-1 rounded-md uppercase tracking-widest border border-white/10 shadow-sm"><?php echo esc_html($type); ?></span>
                        <?php if($is_18) : ?>
                            <span class="bg-[#e11d48] text-white text-[8px] font-black px-2 py-1 rounded-md uppercase tracking-widest shadow-md">18+</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="absolute bottom-2 left-2 flex items-center gap-1.5 bg-black/60 backdrop-blur-md px-2 py-1 rounded-md border border-white/10 z-20 pointer-events-none">
                        <span class="w-1.5 h-1.5 rounded-full shadow-[0_0_5px_currentColor] <?php echo (strtolower($status) == 'completed' || strtolower($status) == 'complete') ? 'bg-[#3b82f6] text-[#3b82f6]' : 'bg-[#22c55e] text-[#22c55e] animate-pulse'; ?>"></span>
                        <span class="text-[8px] font-black text-gray-200 uppercase tracking-widest"><?php echo esc_html($status); ?></span>
                    </div>
                </div>
                
                <a href="<?php echo get_permalink(); ?>" class="text-[13px] md:text-[14px] font-bold text-gray-100 mb-2 line-clamp-2 leading-tight group-hover:text-[#ea580c] transition tracking-tight"><?php echo $title; ?></a>
                
                <div class="flex flex-col gap-1.5 mt-auto">
                    <?php if ($chapters->have_posts()) : 
                        while ($chapters->have_posts()) : $chapters->the_post(); 
                            $time_diff = human_time_diff(get_the_time('U'), current_time('timestamp'));
                            $is_new = (strpos($time_diff, 'min') !== false || strpos($time_diff, 'hour') !== false);
                    ?>
                        <a href="<?php echo get_permalink(); ?>" class="flex items-center justify-between bg-[#121212] hover:bg-[#1a1a1a] rounded-lg px-2.5 py-1.5 transition group/chap shadow-inner border border-transparent hover:border-white/5">
                            <span class="flex items-center gap-1.5 min-w-0">
                                <svg class="w-3.5 h-3.5 text-[#ea580c] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                <span class="text-[10px] font-black text-gray-300 group-hover/chap:text-white uppercase truncate">Ch. <?php echo mvx_chapter_number(get_the_ID()); ?></span>
                                <?php if($is_new): ?>
                                    <span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse shadow-[0_0_5px_red] shrink-0 ml-0.5" title="New Release"></span>
                                <?php endif; ?>
                            </span>
                            <span class="text-[9px] text-gray-500 font-bold shrink-0 ml-1"><?php echo $time_diff; ?></span>
                        </a>
                    <?php endwhile; wp_reset_postdata(); else : ?>
                        <span class="text-[10px] text-gray-600 font-bold uppercase tracking-widest px-2.5 py-1.5 bg-[#121212] rounded-lg text-center">No chapters</span>
                    <?php endif; ?>
                </div>
            </article>
            <?php endwhile; ?>
        </div>

        <div class="mt-16 flex justify-center">
            <nav class="pagination-shell flex items-center gap-2 bg-[#121212] p-2 rounded-2xl border border-[#333] shadow-xl">
                <?php 
                echo paginate_links([
                    'total'        => $recent_query->max_num_pages,
                    'current'      => $paged,
                    'mid_size'     => 1,
                    'prev_text'    => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>',
                    'next_text'    => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>',
                    'type'         => 'plain',
                ]);
                ?>
            </nav>
        </div>
    <?php else : ?>
        <div class="py-32 text-center flex flex-col items-center">
            <div class="w-20 h-20 bg-[#121212] rounded-full flex items-center justify-center mb-6 border border-[#333]">
                <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h2 class="text-xl font-black text-white uppercase tracking-widest italic">No Updates Found</h2>
        </div>
    <?php endif; wp_reset_postdata(); ?>

    <div id="bottom-nav"></div>

</main>

<style>
    .page-numbers { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; background: transparent; color: #666; font-weight: 900; font-size: 13px; border-radius: 12px; transition: all 0.3s; }
    .page-numbers.current { background: #ea580c; color: white; box-shadow: 0 4px 10px rgba(234,88,12,0.3); }
    a.page-numbers:hover { background: #1a1a1a; color: white; border: 1px solid rgba(255,255,255,0.1); }
</style>

<script>
    // Bookmark Toggle Logic for the Grid
    window.toggleBookmark = function(btn, mangaId) {
        if(event) { event.preventDefault(); event.stopPropagation(); }
        btn.classList.add('animate-pulse');
        if (typeof window.xcomixApp !== 'undefined') {
            fetch(window.xcomixApp.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=xcomix_toggle_bookmark&manga_id=${mangaId}&nonce=${window.xcomixApp.nonce}`
            })
            .then(res => res.json())
            .then(data => {
                btn.classList.remove('animate-pulse');
                btn.classList.toggle('text-[#ea580c]'); 
                btn.classList.toggle('bg-white/10');
            })
            .catch(() => btn.classList.remove('animate-pulse'));
        } else {
            console.error("AJAX object not found.");
            btn.classList.remove('animate-pulse');
        }
    };
</script>

<?php get_footer(); ?>
