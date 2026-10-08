<?php
/**
 * Template Name: Ugentic Page
 * 
 * Custom template for pages managed by Tahmir AI
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Output custom CSS from visual editor
$custom_css = get_post_meta(get_the_ID(), '_ugentic_custom_css', true);
if (!empty($custom_css) && class_exists('Ugentic_Assets')) {
    Ugentic_Assets::print_inline_css('ugentic-page-custom-' . get_the_ID(), $custom_css);
}
?>

<main id="ugentic-content" class="ugentic-page-content">
    <?php
    while (have_posts()) :
        the_post();
        the_content();
    endwhile;
    ?>
</main>

<?php
get_footer();
