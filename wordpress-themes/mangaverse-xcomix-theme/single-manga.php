<?php 
get_header();
$manga_id = get_the_ID();
$title = get_the_title();
$content = wp_trim_words(get_the_content(), 80);
$cover = mv_get_cover($manga_id, 'large');
$genres = wp_get_post_terms($manga_id, 'genre', ['fields' => 'names']);
$type_terms = wp_get_post_terms($manga_id, 'manga_type', ['fields' => 'names']);
$status_terms = wp_get_post_terms($manga_id, 'manga_status', ['fields' => 'names']);
$type = mvx_manga_meta($manga_id, 'type', $type_terms[0] ?? 'Manga');
$status = mvx_manga_meta($manga_id, 'status', $status_terms[0] ?? 'Unknown');
$author = mvx_manga_meta($manga_id, 'author', 'Unknown');
$artist = mvx_manga_meta($manga_id, 'artist', $author);
$alt_title = mvx_manga_meta($manga_id, 'alt_title');
$year = mvx_manga_meta($manga_id, 'release_year');
$score = mvx_manga_meta($manga_id, 'score', 'N/A');

$is_bookmarked = false;
if (is_user_logged_in()) {
    $bookmarks = array_unique(array_merge(
        get_user_meta(get_current_user_id(), '_mv_bookmarks', true) ?: [],
        get_user_meta(get_current_user_id(), '_xcomix_bookmarks', true) ?: []
    ));
    $is_bookmarked = in_array($manga_id, $bookmarks);
}

$chapters = mvx_get_chapters($manga_id, 'DESC');

$related = get_posts([
    'post_type' => 'manga', 
    'posts_per_page' => 6,
    'post__not_in' => [$manga_id],
    'tax_query' => $genres ? [['taxonomy' => 'genre', 'field' => 'name', 'terms' => array_slice($genres, 0, 2)]] : [],
]);

$comments = get_comments(['post_id' => $manga_id, 'status' => 'approve']);

$last_read = null;
if (is_user_logged_in()) {
    $history = mvx_user_history(get_current_user_id());
    if (isset($history[$manga_id])) $last_read = $history[$manga_id];
}
?>

<section class="pt-20 min-h-screen">
    <div class="max-w-[1400px] mx-auto px-4 lg:px-6">
        <!-- Manga Header -->
        <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-8 mb-10">
            <!-- Cover -->
            <div class="shrink-0 mx-auto lg:mx-0">
                <div class="w-[220px] lg:w-full rounded-2xl overflow-hidden shadow-2xl shadow-black/50 bg-[#1a1a22]">
                    <img src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr($title); ?>" class="w-full aspect-[2/3] object-cover">
                </div>
                <!-- Actions -->
                <div class="mt-4 space-y-2">
                    <?php if ($last_read): ?>
                    <a href="<?php echo get_permalink($last_read['chapter_id']); ?>" class="flex items-center justify-center gap-2 h-11 bg-mv-accent hover:bg-mv-accentHover text-white font-semibold rounded-xl transition shadow-lg shadow-mv-accent/25 text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/></svg>
                        Continue Chapter <?php echo esc_html($last_read['chapter_num']); ?>
                    </a>
                    <?php elseif ($chapters): ?>
                    <a href="<?php echo get_permalink($chapters[0]->ID); ?>" class="flex items-center justify-center gap-2 h-11 bg-mv-accent hover:bg-mv-accentHover text-white font-semibold rounded-xl transition shadow-lg shadow-mv-accent/25 text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/></svg>
                        Read First Chapter
                    </a>
                    <?php endif; ?>
                    <button onclick="toggleBookmark(<?php echo $manga_id; ?>)" id="bookmarkBtn" class="w-full flex items-center justify-center gap-2 h-10 border border-[#2a2a35] hover:border-mv-accent hover:text-mv-accent rounded-xl transition text-sm font-medium <?php echo $is_bookmarked ? 'text-mv-accent border-mv-accent' : 'text-gray-400'; ?>">
                        <svg class="w-4 h-4" fill="<?php echo $is_bookmarked ? 'currentColor' : 'none'; ?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                        <span id="bookmarkText"><?php echo $is_bookmarked ? 'Bookmarked' : 'Bookmark'; ?></span>
                    </button>
                    <button onclick="copyLink()" class="w-full flex items-center justify-center gap-2 h-10 border border-[#2a2a35] hover:border-white/20 text-gray-400 hover:text-white rounded-xl transition text-sm font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        Share
                    </button>
                </div>
            </div>

            <!-- Info -->
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <?php foreach ($genres as $g): ?>
                    <a href="<?php echo esc_url(home_url('/browse?genre=' . sanitize_title($g))); ?>" class="text-[11px] bg-[#2a2a35] hover:bg-mv-accent/20 text-gray-300 hover:text-mv-accent px-3 py-1 rounded-full transition">
                        <?php echo esc_html($g); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <h1 class="text-3xl lg:text-4xl font-extrabold mb-2"><?php echo esc_html($title); ?></h1>
                <?php if ($alt_title): ?><p class="text-gray-500 text-sm mb-3"><?php echo esc_html($alt_title); ?></p><?php endif; ?>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-4 text-sm text-gray-400">
                    <span class="flex items-center gap-1.5"><svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg> <strong class="text-white"><?php echo esc_html($score); ?></strong></span>
                    <?php if ($year): ?><span><?php echo esc_html($year); ?></span><?php endif; ?>
                    <span><?php echo esc_html($type); ?></span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold <?php echo $status === 'Ongoing' ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'; ?>"><?php echo esc_html($status); ?></span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                    <div class="bg-[#1a1a22] rounded-xl p-3 border border-[#2a2a35]">
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider mb-1">Author</p>
                        <p class="text-sm font-semibold truncate"><?php echo esc_html($author); ?></p>
                    </div>
                    <div class="bg-[#1a1a22] rounded-xl p-3 border border-[#2a2a35]">
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider mb-1">Artist</p>
                        <p class="text-sm font-semibold truncate"><?php echo esc_html($artist); ?></p>
                    </div>
                    <div class="bg-[#1a1a22] rounded-xl p-3 border border-[#2a2a35]">
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider mb-1">Chapters</p>
                        <p class="text-sm font-semibold"><?php echo count($chapters); ?></p>
                    </div>
                    <div class="bg-[#1a1a22] rounded-xl p-3 border border-[#2a2a35]">
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider mb-1">Comments</p>
                        <p class="text-sm font-semibold"><?php echo count($comments); ?></p>
                    </div>
                </div>

                <p class="text-gray-300 text-sm leading-relaxed"><?php echo esc_html($content); ?></p>
            </div>
        </div>

        <!-- Chapters -->
        <div class="bg-[#1a1a22] rounded-2xl border border-[#2a2a35] overflow-hidden mb-10">
            <div class="p-4 border-b border-[#2a2a35] flex items-center justify-between">
                <h2 class="font-bold text-lg">Chapters</h2>
                <div class="flex items-center gap-2">
                    <input type="text" id="chapterSearch" onkeyup="filterChapters()" placeholder="Search chapters..." class="h-8 px-3 bg-[#252530] border border-[#2a2a35] rounded-full text-xs text-white placeholder-gray-600 outline-none focus:border-mv-accent w-40">
                    <select id="chapterSort" onchange="sortChapters()" class="h-8 px-3 bg-[#252530] border border-[#2a2a35] rounded-full text-xs text-gray-300 outline-none">
                        <option value="desc">Newest</option>
                        <option value="asc">Oldest</option>
                    </select>
                </div>
            </div>
            <div id="chapterList" class="max-h-[500px] overflow-y-auto chat-scroll divide-y divide-[#2a2a35]">
                <?php foreach ($chapters as $ch):
                    $ch_num = mvx_chapter_number($ch->ID);
                    $ch_date = human_time_diff(get_the_time('U', $ch), current_time('timestamp'));
                ?>
                <a href="<?php echo get_permalink($ch->ID); ?>" class="chapter-item flex items-center justify-between px-5 py-3.5 hover:bg-white/[0.03] transition group" data-chapter="<?php echo esc_attr($ch_num); ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-mv-accent font-bold text-sm w-8"><?php echo esc_html($ch_num); ?></span>
                        <span class="text-sm text-gray-300 group-hover:text-white transition"><?php echo esc_html($ch->post_title); ?></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-500 hidden sm:block"><?php echo $ch_date; ?> ago</span>
                        <svg class="w-4 h-4 text-gray-600 group-hover:text-mv-accent transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Related -->
        <?php if ($related): ?>
        <div class="mb-10">
            <h2 class="text-xl font-bold mb-4">You May Also Like</h2>
            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4">
                <?php foreach ($related as $m): ?>
                <a href="<?php echo get_permalink($m->ID); ?>" class="group block card-hover">
                    <div class="relative rounded-xl overflow-hidden bg-[#1a1a22] aspect-[2/3] mb-2">
                        <img src="<?php echo mv_get_cover($m->ID, 'medium'); ?>" alt="" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
                    </div>
                    <h3 class="text-[13px] font-semibold truncate group-hover:text-mv-accent transition"><?php echo esc_html($m->post_title); ?></h3>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Comments -->
        <div class="bg-[#1a1a22] rounded-2xl border border-[#2a2a35] p-6">
            <h2 class="font-bold text-lg mb-4">Comments</h2>
            <?php if (is_user_logged_in()): ?>
            <form onsubmit="postComment(event, <?php echo $manga_id; ?>)" class="mb-6">
                <textarea id="commentInput" rows="3" placeholder="Write a comment..." class="w-full bg-[#252530] border border-[#2a2a35] rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 resize-none focus:border-mv-accent transition outline-none mb-2"></textarea>
                <button type="submit" class="h-9 px-5 bg-mv-accent hover:bg-mv-accentHover text-white text-sm font-semibold rounded-lg transition">Post Comment</button>
            </form>
            <?php endif; ?>
            <div id="mangaComments">
                <?php foreach ($comments as $c) echo mv_render_chat_message($c); ?>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
