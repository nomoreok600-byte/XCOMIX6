<?php 
/* Template Name: Community */
get_header();

$active_tab = sanitize_text_field($_GET['tab'] ?? 'general');
$post_id = get_the_ID();
$comments = get_comments(['post_id' => $post_id, 'status' => 'approve', 'number' => 50, 'orderby' => 'comment_date', 'order' => 'DESC']);
$leaderboard = mv_get_leaderboard(10);

// Get active users
$active_users = get_users(['number' => 20, 'orderby' => 'last_login', 'order' => 'DESC']);
$online_threshold = time() - 300; // 5 minutes
?>

<section class="pt-20 min-h-screen">
    <div class="max-w-[1400px] mx-auto px-4 lg:px-6">
        <div class="flex items-center gap-3 mb-6">
            <svg class="w-7 h-7 text-mv-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
            </svg>
            <h1 class="text-2xl lg:text-3xl font-bold">Community Hub</h1>
            <span class="text-sm bg-green-500/20 text-green-400 px-3 py-1 rounded-full flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse"></span>
                Online
            </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Chat Area -->
            <div class="lg:col-span-2">
                <!-- Chat Box -->
                <div class="bg-[#1a1a22] rounded-2xl border border-[#2a2a35] overflow-hidden">
                    <!-- Chat Header -->
                    <div class="px-5 py-4 border-b border-[#2a2a35] flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <h2 class="font-bold">General Chat</h2>
                            <span class="text-[11px] text-gray-500" id="chatCount"><?php echo count($comments); ?> messages</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="loadChats()" class="h-8 px-3 rounded-full bg-[#252530] hover:bg-[#2a2a35] text-gray-400 text-xs transition flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div id="chatMessages" class="h-[500px] overflow-y-auto chat-scroll px-4 py-3 flex flex-col-reverse gap-1">
                        <?php if ($comments): 
                            foreach ($comments as $c) echo mv_render_chat_message($c);
                        else: ?>
                        <div id="emptyChat" class="flex-1 flex flex-col items-center justify-center text-gray-500">
                            <svg class="w-12 h-12 mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            <p class="text-sm">No messages yet. Be the first!</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Input -->
                    <?php if (is_user_logged_in()): ?>
                    <form id="chatForm" onsubmit="sendChat(event)" class="p-4 border-t border-[#2a2a35]">
                        <div class="flex items-end gap-2">
                            <textarea id="chatInput" rows="1" placeholder="Share your thoughts... (Use ||spoiler||, **bold**)"
                                class="flex-1 bg-[#252530] border border-[#2a2a35] rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-500 resize-none focus:border-mv-accent transition outline-none min-h-[42px] max-h-[120px]"
                                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendChat(event);}"></textarea>
                            <button type="submit" class="h-[42px] w-[42px] bg-mv-accent hover:bg-mv-accentHover rounded-xl flex items-center justify-center shrink-0 transition">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="p-4 border-t border-[#2a2a35] text-center">
                        <a href="<?php echo esc_url(home_url('/auth')); ?>" class="text-mv-accent hover:underline text-sm font-semibold">Log in to join the conversation</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Leaderboard -->
                <div class="bg-[#1a1a22] rounded-2xl border border-[#2a2a35] p-5">
                    <h3 class="font-bold mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        Top Readers
                    </h3>
                    <div class="space-y-2.5">
                        <?php foreach (array_slice($leaderboard, 0, 5) as $i => $u): 
                            $rank_colors = ['text-yellow-400', 'text-gray-300', 'text-amber-600', 'text-gray-500', 'text-gray-500'];
                        ?>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-bold w-5 <?php echo $rank_colors[$i] ?? 'text-gray-500'; ?>"><?php echo $i + 1; ?></span>
                            <img src="<?php echo get_avatar_url($u->ID); ?>" class="w-7 h-7 rounded-full object-cover">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold truncate"><?php echo esc_html($u->display_name); ?></p>
                                <p class="text-[10px] text-gray-500">Lv.<?php echo intval($u->level); ?></p>
                            </div>
                            <span class="text-[11px] text-mv-accent font-semibold"><?php echo number_format(intval($u->xp)); ?> XP</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="<?php echo esc_url(home_url('/leaderboard')); ?>" class="block mt-4 text-center text-xs text-mv-accent hover:underline">View Full Leaderboard</a>
                </div>

                <!-- Active Users -->
                <div class="bg-[#1a1a22] rounded-2xl border border-[#2a2a35] p-5">
                    <h3 class="font-bold mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/></svg>
                        Members
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <?php $all_users = get_users(['number' => 30]); 
                        foreach ($all_users as $u): ?>
                        <a href="<?php echo esc_url(home_url('/user/' . $u->user_login)); ?>" title="<?php echo esc_attr($u->display_name); ?>">
                            <img src="<?php echo get_avatar_url($u->ID); ?>" class="w-8 h-8 rounded-full object-cover border border-[#2a2a35] hover:border-mv-accent transition">
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
