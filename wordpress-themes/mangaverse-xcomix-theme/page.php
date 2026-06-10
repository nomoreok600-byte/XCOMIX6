<?php get_header(); ?>

<section class="pt-20 min-h-screen">
    <div class="max-w-[800px] mx-auto px-4 lg:px-6 py-12">
        <article class="bg-[#1a1a22] rounded-2xl border border-[#2a2a35] p-6 lg:p-10">
            <h1 class="text-2xl lg:text-3xl font-bold mb-6"><?php the_title(); ?></h1>
            <div class="prose prose-invert max-w-none text-gray-300 leading-relaxed">
                <?php 
                while (have_posts()) {
                    the_post();
                    the_content();
                }
                ?>
            </div>
        </article>
    </div>
</section>

<?php get_footer(); ?>
