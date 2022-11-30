<?php
/*
Plugin Name: Bulk Add Media From URL
Plugin URI: http://www.example.com
Description: Bulk Add Media From URL
Version: 1.0
Author: Your Name
Author URI: http://www.example.com
License: GPL2
*/


// Create Option Page
function bulk_add_media_from_url_option_page() {
    add_options_page('Bulk Add Media From URL', 'Bulk Add Media From URL', 'manage_options', 'bulk-add-media-from-url', 'bulk_add_media_from_url_option_page_html');
}
add_action('admin_menu', 'bulk_add_media_from_url_option_page');

// Option Page HTML
function bulk_add_media_from_url_option_page_html() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form class="form-bulk-add-media" method="post">
            <label for="urls"><h2 class="title">URLs</h2></label>
            <textarea name="urls" id="urls" class="large-text code" rows="10"></textarea>
            <p class="description">Enter one URL per line.</p>
            <input type="hidden" name="action" value="bulk-add-media-from-url">
            <div class="btn-with-spinner" style="display:inline-block">
                <button class="button button-primary" type="submit">Submit</button>
                <span class="spinner"></span>
            </div>
            <div class="ajax-response"></div>
        </form>
    </div>
    <script>
        jQuery(document).on('submit', '.form-bulk-add-media', function (e) {
            e.preventDefault();
            var $form = jQuery(this);

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $form.serialize(),
                beforeSend: function () {
                    $form.find('.spinner').addClass('is-active');
                },
                success: function (response) {
                    $form.find('.spinner').removeClass('is-active');
                    $form.find('.ajax-response').html(response);
                },
                error: function (error) {
                    $form.find('.spinner').removeClass('is-active');
                    $form.find('.ajax-response').html(error);
                }
            });

        });

    </script>
    <?php
}


// Ajax handler
add_action('wp_ajax_bulk-add-media-from-url', 'bulk_add_media_from_url_ajax_handler');
function bulk_add_media_from_url_ajax_handler() {

    // Get URLs
    $urls = isset($_POST['urls']) ? $_POST['urls'] : '';

    // Each line make an array
    $urls = explode(PHP_EOL, $urls);

    // Check valid URLs
    $urls = array_map('esc_url_raw', $urls);

    // Remove empty lines
    $urls = array_filter($urls, 'trim');

    // Remove duplicate lines
    $urls = array_unique($urls);

    
    if (count($urls) > 0) {
        foreach ($urls as $url) {
            $attachment_id = media_sideload_image($url, 0, '', 'id');
            if (is_wp_error($attachment_id)) {
                echo '<p class="notice notice-error">Error: ' . $url . '</p>';
            } else {
                echo '<p class="notice notice-success">Success: ' . $url . '</p>';
            }
        }
    } else {
        echo '<p class="error">No valid URL found.</p>';
    }

    die();
}