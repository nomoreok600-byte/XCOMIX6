    </main>

    <!-- Footer Ad -->
    <?php mv_ad('footer'); ?>

    <!-- Footer -->
    <footer class="border-t border-[#2a2a35] bg-[#0f0f13]">
        <div class="max-w-[1400px] mx-auto px-4 lg:px-6 py-12 lg:py-16">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-8">
                <!-- Brand -->
                <div class="lg:col-span-1">
                    <a href="<?php echo esc_url(home_url()); ?>" class="flex items-center gap-2 mb-4">
                        <svg class="w-8 h-8 text-mv-accent" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.41.21.75-.19.75-.65V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/>
                        </svg>
                        <span class="text-xl font-bold"><?php echo esc_html(get_option('mv_site_name', get_bloginfo('name'))); ?></span>
                    </a>
                    <p class="text-sm text-gray-500 leading-relaxed">
                        <?php echo esc_html(get_option('mv_footer_text', 'Your ultimate manga reading platform. Read thousands of manga, manhwa, and manhua titles for free.')); ?>
                    </p>
                    <div class="flex items-center gap-3 mt-5">
                        <?php if ($discord = get_option('mv_social_discord')): ?>
                        <a href="<?php echo esc_url($discord); ?>" target="_blank" class="h-9 w-9 flex items-center justify-center rounded-full bg-[#1a1a22] hover:bg-mv-accent/20 hover:text-mv-accent transition text-gray-400 border border-[#2a2a35]">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                        </a>
                        <?php endif; ?>
                        <?php if ($twitter = get_option('mv_social_twitter')): ?>
                        <a href="<?php echo esc_url($twitter); ?>" target="_blank" class="h-9 w-9 flex items-center justify-center rounded-full bg-[#1a1a22] hover:bg-mv-accent/20 hover:text-mv-accent transition text-gray-400 border border-[#2a2a35]">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(site_url('/sitemap.xml')); ?>" target="_blank" class="h-9 w-9 flex items-center justify-center rounded-full bg-[#1a1a22] hover:bg-mv-accent/20 hover:text-mv-accent transition text-gray-400 border border-[#2a2a35]" title="Sitemap">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-gray-400 mb-4">Quick Links</h3>
                    <ul class="space-y-2.5">
                        <li><a href="<?php echo esc_url(home_url()); ?>" class="text-sm text-gray-500 hover:text-mv-accent transition">Home</a></li>
                        <li><a href="<?php echo esc_url(home_url('/browse')); ?>" class="text-sm text-gray-500 hover:text-mv-accent transition">Browse</a></li>
                        <li><a href="<?php echo esc_url(home_url('/recent')); ?>" class="text-sm text-gray-500 hover:text-mv-accent transition">Recent Updates</a></li>
                        <li><a href="?random=1" class="text-sm text-gray-500 hover:text-mv-accent transition">Random Manga</a></li>
                    </ul>
                </div>

                <!-- Community -->
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-gray-400 mb-4">Community</h3>
                    <ul class="space-y-2.5">
                        <li><a href="<?php echo esc_url(home_url('/community')); ?>" class="text-sm text-gray-500 hover:text-mv-accent transition">Community Hub</a></li>
                        <li><a href="<?php echo esc_url(home_url('/leaderboard')); ?>" class="text-sm text-gray-500 hover:text-mv-accent transition">Leaderboard</a></li>
                        <li><a href="<?php echo esc_url(home_url('/profile')); ?>" class="text-sm text-gray-500 hover:text-mv-accent transition">My Profile</a></li>
                        <li><a href="<?php echo esc_url(home_url('/messages')); ?>" class="text-sm text-gray-500 hover:text-mv-accent transition">Messages</a></li>
                    </ul>
                </div>

                <!-- Info -->
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-gray-400 mb-4">Information</h3>
                    <ul class="space-y-2.5">
                        <?php if (get_option('mv_show_footer_links', '1') === '1'): ?>
                        <li><a href="<?php echo esc_url(home_url('/dmca')); ?>" class="text-sm text-gray-500 hover:text-mv-accent transition">DMCA</a></li>
                        <li><a href="<?php echo esc_url(home_url('/privacy-policy')); ?>" class="text-sm text-gray-500 hover:text-mv-accent transition">Privacy Policy</a></li>
                        <li><a href="<?php echo esc_url(home_url('/terms')); ?>" class="text-sm text-gray-500 hover:text-mv-accent transition">Terms of Service</a></li>
                        <?php endif; ?>
                        <li class="text-sm text-gray-600">
                            <span class="flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Supercharged by MangaVerse Pro v<?php echo MV_VERSION; ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Copyright -->
        <div class="border-t border-[#2a2a35] bg-[#0a0a0e]">
            <div class="max-w-[1400px] mx-auto px-4 lg:px-6 py-4 flex flex-col sm:flex-row justify-between items-center gap-2">
                <p class="text-xs text-gray-600">
                    <?php echo esc_html(get_option('mv_footer_copyright', date('Y') . ' ' . get_bloginfo('name') . '. All rights reserved.')); ?>
                </p>
                <p class="text-xs text-gray-700">
                    MangaVerse Pro - Built for speed
                </p>
            </div>
        </div>
    </footer>

    <!-- Mobile Back to Top -->
    <button id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" class="fixed bottom-6 right-6 h-10 w-10 bg-mv-accent hover:bg-mv-accentHover rounded-full shadow-lg shadow-mv-accent/30 flex items-center justify-center z-50 opacity-0 translate-y-4 transition-all duration-300 pointer-events-none">
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
    </button>

    <!-- Close dropdowns on outside click -->
    <script>
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.dropdown-menu').forEach(d => {
            if (!d.contains(e.target) && !e.target.closest('button')) d.classList.remove('active');
        });
    });

    // Back to top visibility
    const backToTop = document.getElementById('backToTop');
    if (backToTop) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 600) {
                backToTop.classList.remove('opacity-0', 'translate-y-4', 'pointer-events-none');
            } else {
                backToTop.classList.add('opacity-0', 'translate-y-4', 'pointer-events-none');
            }
        }, { passive: true });
    }
    </script>
    <?php wp_footer(); ?>
</body>
</html>
