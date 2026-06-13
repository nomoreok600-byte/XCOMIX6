<?php 
/* Template Name: Auth */
if (is_user_logged_in()) { wp_redirect(home_url('/profile')); exit; }
get_header();

$mode = isset($_GET['register']) ? 'register' : 'login';
$redirect = home_url('/profile');
?>

<section class="pt-20 min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <svg class="w-12 h-12 text-mv-accent mx-auto mb-3" fill="currentColor" viewBox="0 0 24 24">
                <path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.41.21.75-.19.75-.65V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/>
            </svg>
            <h1 class="text-2xl font-bold"><?php echo $mode === 'register' ? 'Create Account' : 'Welcome Back'; ?></h1>
            <p class="text-gray-500 text-sm mt-1"><?php echo $mode === 'register' ? 'Join thousands of manga readers' : 'Sign in to your account'; ?></p>
        </div>

        <div class="bg-[#1a1a22] rounded-2xl border border-[#2a2a35] p-6 lg:p-8">
            <!-- Tabs -->
            <div class="flex mb-6 bg-[#252530] rounded-full p-1">
                <a href="?" class="flex-1 py-2.5 text-center rounded-full text-sm font-semibold transition <?php echo $mode === 'login' ? 'bg-mv-accent text-white shadow-lg shadow-mv-accent/25' : 'text-gray-400 hover:text-white'; ?>">Log In</a>
                <a href="?register=1" class="flex-1 py-2.5 text-center rounded-full text-sm font-semibold transition <?php echo $mode === 'register' ? 'bg-mv-accent text-white shadow-lg shadow-mv-accent/25' : 'text-gray-400 hover:text-white'; ?>">Sign Up</a>
            </div>

            <?php if ($mode === 'login'): ?>
            <form action="<?php echo esc_url(wp_login_url()); ?>" method="post" class="space-y-4">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Username or Email</label>
                    <input type="text" name="log" required 
                        class="w-full h-11 px-4 bg-[#252530] border border-[#2a2a35] rounded-xl text-white placeholder-gray-600 focus:border-mv-accent transition outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Password</label>
                    <input type="password" name="pwd" required 
                        class="w-full h-11 px-4 bg-[#252530] border border-[#2a2a35] rounded-xl text-white placeholder-gray-600 focus:border-mv-accent transition outline-none">
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-gray-400 cursor-pointer">
                        <input type="checkbox" name="rememberme" value="forever" class="accent-mv-accent rounded">
                        Remember me
                    </label>
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="text-sm text-mv-accent hover:underline">Forgot password?</a>
                </div>
                <button type="submit" name="wp-submit" class="w-full h-11 bg-mv-accent hover:bg-mv-accentHover text-white font-semibold rounded-xl transition shadow-lg shadow-mv-accent/25">
                    Log In
                </button>
            </form>
            <?php else: ?>
            <form action="<?php echo esc_url(wp_registration_url()); ?>" method="post" class="space-y-4">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Display Name</label>
                    <input type="text" name="user_login" required 
                        class="w-full h-11 px-4 bg-[#252530] border border-[#2a2a35] rounded-xl text-white placeholder-gray-600 focus:border-mv-accent transition outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Email</label>
                    <input type="email" name="user_email" required 
                        class="w-full h-11 px-4 bg-[#252530] border border-[#2a2a35] rounded-xl text-white placeholder-gray-600 focus:border-mv-accent transition outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Password</label>
                    <input type="password" name="user_pass" required minlength="6"
                        class="w-full h-11 px-4 bg-[#252530] border border-[#2a2a35] rounded-xl text-white placeholder-gray-600 focus:border-mv-accent transition outline-none">
                </div>
                <p class="text-xs text-gray-500">By creating an account, you agree to our Terms of Service and Privacy Policy.</p>
                <button type="submit" class="w-full h-11 bg-mv-accent hover:bg-mv-accentHover text-white font-semibold rounded-xl transition shadow-lg shadow-mv-accent/25">
                    Create Account
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php get_footer(); ?>
