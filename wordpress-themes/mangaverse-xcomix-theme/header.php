<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#0f0f13">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <?php wp_head(); ?>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        mv: {
                            bg: '#0f0f13',
                            card: '#1a1a22',
                            border: '#2a2a35',
                            text: '#e2e2e8',
                            muted: '#9ca3af',
                            accent: '#e8783a',
                            accentHover: '#d06a30',
                            glass: 'rgba(15,15,19,0.85)',
                        }
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        .nav-blur { backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); }
        .search-expand { max-width: 0; opacity: 0; overflow: hidden; transition: all 0.3s cubic-bezier(0.4,0,0.2,1); }
        .search-expand.active { max-width: 400px; opacity: 1; }
        .mobile-menu { transform: translateX(100%); transition: transform 0.3s ease; }
        .mobile-menu.active { transform: translateX(0); }
        .dropdown-menu { opacity: 0; visibility: hidden; transform: translateY(-8px) scale(0.96); transition: all 0.2s ease; }
        .dropdown-menu.active { opacity: 1; visibility: visible; transform: translateY(0) scale(1); }
    </style>
</head>
<body class="bg-mv-bg text-mv-text font-sans antialiased min-h-screen">
    <!-- Top Ad -->
    <?php mv_ad('header'); ?>

    <!-- Navigation -->
    <header class="fixed top-0 left-0 right-0 z-[1000] nav-blur bg-mv-bg/80 border-b border-white/[0.04]">
        <nav class="max-w-[1400px] mx-auto flex items-center justify-between h-14 px-3 lg:px-6">
            <!-- Left: Logo -->
            <a href="<?php echo esc_url(home_url('/home')); ?>" class="flex items-center gap-2 shrink-0" aria-label="Home">
                <svg class="w-7 h-7 text-mv-accent" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.41.21.75-.19.75-.65V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/>
                </svg>
                <span class="text-base font-bold tracking-tight hidden sm:block">
                    <?php echo esc_html(get_option('mv_site_name', get_bloginfo('name'))); ?>
                </span>
            </a>

            <!-- Center: Search Bar (Desktop) -->
            <div class="hidden lg:flex flex-1 max-w-xl mx-6">
                <form action="<?php echo esc_url(home_url('/')); ?>" method="get" class="relative w-full">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="s" placeholder="Search manga, genres, authors..." 
                        class="w-full h-9 pl-10 pr-4 bg-[#252530] border border-[#2a2a35] rounded-full text-sm text-white placeholder-gray-500 focus:border-mv-accent focus:bg-[#2a2a35] transition-all outline-none">
                    <input type="hidden" name="post_type" value="manga">
                </form>
            </div>

            <!-- Right: Actions -->
            <div class="flex items-center gap-1">
                <!-- Random Manga -->
                <a href="<?php echo esc_url(home_url('/?random=1')); ?>" class="h-8 w-8 flex items-center justify-center rounded-full hover:bg-white/5 text-gray-400 hover:text-white transition lg:h-9 lg:w-9" title="Random Manga">
                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                    </svg>
                </a>

                <!-- Browse -->
                <a href="<?php echo esc_url(home_url('/browse')); ?>" class="hidden md:flex items-center gap-1.5 h-8 px-3 rounded-full hover:bg-white/5 text-gray-400 hover:text-white transition text-sm font-medium lg:h-9">
                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    Browse
                </a>

                <!-- Search Toggle (Mobile) -->
                <button onclick="document.getElementById('mobileSearch').classList.toggle('hidden')" class="lg:hidden h-8 w-8 flex items-center justify-center rounded-full hover:bg-white/5 text-gray-400 transition">
                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </button>

                <!-- Notifications -->
                <?php if (is_user_logged_in()): $notif_count = 0; $notifs = mv_get_notifications(get_current_user_id(), 5); $notif_count = count($notifs); ?>
                <div class="relative">
                    <button onclick="document.getElementById('notifDropdown').classList.toggle('active')" class="h-8 w-8 flex items-center justify-center rounded-full hover:bg-white/5 text-gray-400 hover:text-white transition lg:h-9 lg:w-9 relative">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <?php if ($notif_count): ?>
                        <span class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-mv-accent text-white text-[9px] font-bold rounded-full flex items-center justify-center"><?php echo min(9, $notif_count); ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="notifDropdown" class="dropdown-menu absolute right-0 top-11 w-80 bg-[#1a1a22] border border-[#2a2a35] rounded-xl shadow-2xl overflow-hidden">
                        <div class="px-4 py-3 border-b border-[#2a2a35] flex justify-between items-center">
                            <span class="text-sm font-semibold text-white">Notifications</span>
                            <?php if (count($notifs) > 0): ?>
                            <a href="<?php echo esc_url(home_url('/recent')); ?>" class="text-xs text-mv-accent hover:underline">View All</a>
                            <?php endif; ?>
                        </div>
                        <div class="max-h-72 overflow-y-auto">
                            <?php if ($notifs): foreach ($notifs as $n): ?>
                            <a href="<?php echo esc_url($n['chapter_url']); ?>" class="flex gap-3 px-4 py-2.5 hover:bg-white/5 transition border-b border-[#2a2a35]/50">
                                <div class="flex-1 min-w-0">
                                    <p class="text-[12px] font-medium text-white truncate"><?php echo esc_html($n['manga_title']); ?></p>
                                    <p class="text-[11px] text-gray-500">Ch. <?php echo esc_html($n['chapter_num']); ?> - <?php echo esc_html($n['time']); ?> ago</p>
                                </div>
                            </a>
                            <?php endforeach; else: ?>
                            <div class="px-4 py-6 text-center text-gray-500 text-sm">No new chapter updates</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- User / Login -->
                <?php if (is_user_logged_in()):
                    $cu = wp_get_current_user();
                    $lvl = mv_get_user_level_data(get_current_user_id());
                    $avatar = get_avatar_url(get_current_user_id()) ?: MV_URI . '/assets/img/default-avatar.png';
                ?>
                <div class="relative ml-1">
                    <button onclick="document.getElementById('userDropdown').classList.toggle('active')" class="flex items-center gap-2 pl-2 pr-1 h-9 rounded-full hover:bg-white/5 transition">
                        <span class="text-sm font-medium hidden sm:block"><?php echo esc_html($cu->display_name); ?></span>
                        <img src="<?php echo esc_url($avatar); ?>" alt="" class="w-7 h-7 rounded-full object-cover border border-[#2a2a35]">
                    </button>
                    <div id="userDropdown" class="dropdown-menu absolute right-0 top-11 w-64 bg-[#1a1a22] border border-[#2a2a35] rounded-xl shadow-2xl overflow-hidden">
                        <div class="px-4 py-3 border-b border-[#2a2a35]">
                            <p class="text-sm font-semibold text-white"><?php echo esc_html($cu->display_name); ?></p>
                            <p class="text-xs text-gray-500"><?php echo esc_html($lvl['rank_name']); ?> - Lv.<?php echo $lvl['level']; ?></p>
                        </div>
                        <a href="<?php echo esc_url(home_url('/profile')); ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-300 hover:bg-white/5 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>My Profile</a>
                        <a href="<?php echo esc_url(home_url('/profile')); ?>#bookmarks" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-300 hover:bg-white/5 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>Bookmarks</a>
                        <a href="<?php echo esc_url(home_url('/profile')); ?>#history" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-300 hover:bg-white/5 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>History</a>
                        <a href="<?php echo esc_url(home_url('/messages')); ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-300 hover:bg-white/5 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>Messages</a>
                        <a href="<?php echo esc_url(home_url('/leaderboard')); ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-300 hover:bg-white/5 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>Leaderboard</a>
                        <?php if (current_user_can('manage_options')): ?>
                        <a href="<?php echo esc_url(admin_url()); ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-mv-accent hover:bg-white/5 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Admin Panel</a>
                        <?php endif; ?>
                        <div class="border-t border-[#2a2a35]"></div>
                        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-red-400 hover:bg-white/5 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Log Out</a>
                    </div>
                </div>
                <?php else: ?>
                <a href="<?php echo esc_url(home_url('/auth')); ?>" class="hidden md:flex items-center gap-1.5 h-8 px-4 bg-mv-accent hover:bg-mv-accentHover text-white text-sm font-semibold rounded-full transition lg:h-9">
                    Log In
                </a>
                <?php endif; ?>

                <!-- Mobile Menu Button -->
                <button onclick="document.getElementById('mobileMenu').classList.toggle('active')" class="md:hidden h-8 w-8 flex items-center justify-center rounded-full hover:bg-white/5 text-gray-400 transition ml-1">
                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </nav>

        <!-- Mobile Search -->
        <div id="mobileSearch" class="hidden lg:hidden px-3 pb-3 border-t border-white/[0.04]">
            <form action="<?php echo esc_url(home_url('/')); ?>" method="get" class="relative">
                <input type="text" name="s" placeholder="Search manga..." class="w-full h-10 pl-10 pr-4 bg-[#252530] border border-[#2a2a35] rounded-full text-sm text-white placeholder-gray-500 focus:border-mv-accent outline-none">
                <input type="hidden" name="post_type" value="manga">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </form>
        </div>
    </header>

    <!-- Mobile Menu Overlay -->
    <div id="mobileMenu" class="mobile-menu fixed inset-0 z-[999] bg-[#0f0f13]/95 nav-blur flex flex-col p-5">
        <div class="flex justify-between items-center mb-8">
            <span class="text-lg font-bold">Menu</span>
            <button onclick="document.getElementById('mobileMenu').classList.remove('active')" class="h-9 w-9 flex items-center justify-center rounded-full hover:bg-white/5">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="flex flex-col gap-2 flex-1 overflow-y-auto">
            <a href="<?php echo esc_url(home_url('/home')); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-lg"><svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>Home</a>
            <a href="<?php echo esc_url(home_url('/browse')); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-lg"><svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>Browse</a>
            <a href="<?php echo esc_url(home_url('/recent')); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-lg"><svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Recent</a>
            <a href="<?php echo esc_url(home_url('/community')); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-lg"><svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/></svg>Community</a>
            <a href="<?php echo esc_url(home_url('/leaderboard')); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-lg"><svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>Leaderboard</a>
            <div class="border-t border-[#2a2a35] my-2"></div>
            <?php if (is_user_logged_in()): ?>
            <a href="<?php echo esc_url(home_url('/profile')); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-lg"><svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>My Profile</a>
            <a href="<?php echo esc_url(home_url('/messages')); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-lg"><svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>Messages</a>
            <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 text-red-400 text-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Log Out</a>
            <?php else: ?>
            <a href="<?php echo esc_url(home_url('/auth')); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-mv-accent text-white font-semibold text-lg justify-center"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>Log In / Join</a>
            <?php endif; ?>
        </div>
    </div>

    <main>
