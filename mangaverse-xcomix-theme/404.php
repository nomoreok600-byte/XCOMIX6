<?php get_header(); ?>

<section class="pt-20 min-h-screen flex items-center justify-center px-4">
    <div class="text-center max-w-lg">
        <div class="relative mb-6">
            <div class="absolute inset-0 bg-mv-accent/20 blur-3xl rounded-full"></div>
            <svg class="relative w-32 h-32 mx-auto text-mv-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
        </div>
        <h1 class="text-6xl lg:text-8xl font-extrabold text-gradient mb-2">404</h1>
        <h2 class="text-xl lg:text-2xl font-bold mb-3">Page Not Found</h2>
        <p class="text-gray-500 mb-8">The page you're looking for doesn't exist or has been moved. Let's get you back on track.</p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="<?php echo esc_url(home_url()); ?>" class="h-11 px-6 bg-mv-accent hover:bg-mv-accentHover text-white font-semibold rounded-full transition flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Go Home
            </a>
            <a href="?random=1" class="h-11 px-6 bg-transparent hover:bg-white/5 border border-white/10 text-white font-semibold rounded-full transition flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                Random Manga
            </a>
        </div>
    </div>
</section>

<?php get_footer(); ?>
