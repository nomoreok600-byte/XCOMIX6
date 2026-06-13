<?php 
/* Template Name: Messages */
if (!is_user_logged_in()) { wp_redirect(home_url('/auth')); exit; }
get_header();

$current_user_id = get_current_user_id();
$conversation_user_id = intval($_GET['user'] ?? 0);
$conversation_user = $conversation_user_id ? get_userdata($conversation_user_id) : null;
?>

<section class="pt-20 min-h-screen">
    <div class="max-w-[1000px] mx-auto px-4 lg:px-6 h-[calc(100vh-80px)]">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-0 bg-[#1a1a22] rounded-2xl border border-[#2a2a35] overflow-hidden h-full">
            <!-- Conversations List -->
            <div class="lg:col-span-1 border-r border-[#2a2a35] flex flex-col">
                <div class="p-4 border-b border-[#2a2a35]">
                    <h2 class="font-bold flex items-center gap-2">
                        <svg class="w-5 h-5 text-mv-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                        Messages
                    </h2>
                </div>
                <div id="conversationList" class="flex-1 overflow-y-auto chat-scroll">
                    <div class="p-4 text-center text-gray-500 text-sm" id="conversationLoading">Loading conversations...</div>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="lg:col-span-2 flex flex-col">
                <?php if ($conversation_user): ?>
                <!-- Header -->
                <div class="p-4 border-b border-[#2a2a35] flex items-center gap-3">
                    <img src="<?php echo get_avatar_url($conversation_user_id); ?>" class="w-9 h-9 rounded-full object-cover">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate"><?php echo esc_html($conversation_user->display_name); ?></p>
                        <p class="text-[10px] text-gray-500"><?php echo esc_html($conversation_user->user_login); ?></p>
                    </div>
                    <a href="<?php echo esc_url(home_url('/user/' . $conversation_user->user_login)); ?>" class="text-xs text-mv-accent hover:underline shrink-0">View Profile</a>
                </div>
                <!-- Messages -->
                <div id="messageArea" class="flex-1 overflow-y-auto chat-scroll p-4 space-y-3" data-user="<?php echo $conversation_user_id; ?>">
                    <div class="text-center text-gray-500 text-sm">Loading messages...</div>
                </div>
                <!-- Input -->
                <form id="messageForm" onsubmit="sendMessage(event)" class="p-4 border-t border-[#2a2a35]">
                    <div class="flex items-end gap-2">
                        <textarea id="messageInput" rows="1" placeholder="Type a message..."
                            class="flex-1 bg-[#252530] border border-[#2a2a35] rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-500 resize-none focus:border-mv-accent transition outline-none min-h-[42px] max-h-[120px]"
                            onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage(event);}"></textarea>
                        <button type="submit" class="h-[42px] w-[42px] bg-mv-accent hover:bg-mv-accentHover rounded-xl flex items-center justify-center shrink-0 transition">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div class="flex-1 flex items-center justify-center">
                    <div class="text-center text-gray-500">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <p class="font-semibold mb-1">Select a conversation</p>
                        <p class="text-sm">Choose a user to start messaging</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
