<?php
/**
 * Plugin Name: Bulk Import Media From URL
 * Plugin URI: https://github.com/akshuvo/bulk-add-media-from-url
 * Description: Bulk Import Media From URL for WordPress
 * Author: AddonMaster
 * Author URI: https://addonmaster.com
 * Version: 1.0.0
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The main plugin class
 */
final class BIMFU_Plugin {

    /**
     * Class construcotr
     */
    private function __construct() {

        // Register the plugin menu
        add_action('admin_menu', [ $this, 'menu_register' ] );

        // Ajax handler
        add_action('wp_ajax_bulk-add-media-from-url', [ $this, 'ajax_handler' ] );
    }

    /**
     * Initializes a singleton instance
     *
     * @return \BIMFU_Plugin
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Register the plugin menu
     *
     * @return void
     */
    public function menu_register() {
        add_submenu_page(
            'upload.php',
            __( 'Bulk Media From URL', 'bulk-import-media-from-url' ),
            __( 'Bulk Media From URL', 'bulk-import-media-from-url' ),
            'upload_files',
            'bulk-add-media-from-url',
            [ $this, 'menu_page' ]
        );
    }

    /**
     * The plugin menu page
     *
     * @return void
     */
    public function menu_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form class="form-bulk-add-media" method="post">
                <label for="urls"><strong class="title"><?php esc_html_e( 'Enter URLs', 'bulk-import-media-from-url' ); ?></strong></label>
                <textarea name="urls" id="urls" class="large-text code" rows="10" style=" line-height: 28px; "></textarea>
                <p class="description"><?php esc_html_e( 'Enter one URL per line or separated by comma.', 'bulk-import-media-from-url' ); ?></p>
                <input type="hidden" name="action" value="bulk-add-media-from-url">
                <div class="btn-with-spinner" style="display:inline-block">
                    <button class="button button-primary" type="submit">
                        <?php esc_html_e( 'Import Media', 'bulk-import-media-from-url' ); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
                <div class="ajax-response"></div>
                <?php wp_nonce_field( 'bulk-add-media-from-url' ); ?>
            </form>
        </div>
        <script>
            jQuery(document).on('submit', '.form-bulk-add-media', function (e) {
                e.preventDefault();
                let $form = jQuery(this);
                let $btn = $form.find('button[type="submit"]');

                $form.find('.spinner').addClass('is-active');
                $form.find('.ajax-response').html('');
                $btn.prop('disabled', true);

                jQuery.post(ajaxurl, $form.serialize(), function (res) {
                    if (res.success) {
                        let html = '';
                        res.data.forEach(function (item) {
                            html += '<p class="notice notice-' + item.status + '">' + item.msg + item.url + '</p>';
                        });
                        $form.find('.ajax-response').html(html);
                    } else {
                        $form.find('.ajax-response').html('<p class="notice notice-error">' + res.data + '</p>');
                    }

                }).always(function () {
                    $btn.prop('disabled', false);
                    $form.find('.spinner').removeClass('is-active');
                });

            });
        </script>
        <?php
    }

    /**
     * Ajax handler
     *
     * @return void
     */
    public function ajax_handler() {
        // Check nonce
        check_ajax_referer('bulk-add-media-from-url');

        // Check permission
        if ( ! current_user_can('upload_files') ) {
            wp_send_json_error( __('You do not have permission to upload files.', 'bulk-import-media-from-url') );
        }

        // Get URLs
        $urls = isset($_POST['urls']) ? sanitize_textarea_field($_POST['urls']) : '';

        // Convert comma separated URLs to line break
        $urls = str_replace(',', PHP_EOL, $urls);

        // Each line make an array
        $urls = explode(PHP_EOL, $urls);

        // Check valid URLs
        $urls = array_map('esc_url_raw', $urls);

        // Remove empty lines
        $urls = array_filter($urls, 'trim');

        // Remove duplicate lines
        $urls = array_unique($urls);

        // Response
        $response = [];

        if (count($urls) > 0) {
            foreach ($urls as $url) {
                $attachment_id = $this->media_handle_sideload($url, 0, '', 'id');
                if (is_wp_error($attachment_id)) {
                    $response[] = [
                        'status' => 'error',
                        'msg' => esc_html( $attachment_id->get_error_message() ),
                        'url' => esc_url($url)
                    ];
                } else {
                    $response[] = [
                        'status' => 'success',
                        'msg' => esc_html__('Success: ', 'bulk-import-media-from-url'),
                        'url' => esc_url($url),
                        'attachment_id' => $attachment_id,
                    ];
                }
            }
        } else {
            wp_send_json_error( __('No URL found.', 'bulk-import-media-from-url') );
        }

        wp_send_json_success( $response );
    }


    /**
     * Downloads an file from the specified URL, 
     * saves it as an attachment, 
     * and optionally attaches it to a post.
     */
    public function media_handle_sideload( $file, $post_id = 0, $desc = null, $return_type = 'id' ) {

        if ( ! empty( $file ) ) {

            $allowed_mime_types = get_allowed_mime_types();
    
            $allowed_extensions = [];
            foreach ( $allowed_mime_types as $ext => $mime ) {
                $allowed_extensions = array_merge($allowed_extensions, explode( '|', $ext ));
            }

            $allowed_extensions = array_map( 'preg_quote', $allowed_extensions );

            // Set variables for storage, fix file filename for query strings.
            preg_match( '/[^\?]+\.(' . implode( '|', $allowed_extensions ) . ')\b/i', $file, $matches );

            if ( ! $matches ) {
                return new WP_Error( 'upload_failed', __( 'Invalid file URL.' ) );
            }

            $file_array         = array();
            $file_array['name'] = wp_basename( $matches[0] );

            // Download file to temp location.
            $file_array['tmp_name'] = download_url( $file );

            // If error storing temporarily, return the error.
            if ( is_wp_error( $file_array['tmp_name'] ) ) {
                return $file_array['tmp_name'];
            }

            // Do the validation and storage stuff.
            $id = media_handle_sideload( $file_array, $post_id, $desc );

            // If error storing permanently, unlink.
            if ( is_wp_error( $id ) ) {
                @unlink( $file_array['tmp_name'] );
                return $id;
            }

            // Store the original attachment source in meta.
            add_post_meta( $id, '_source_url', $file );

            // If attachment ID was requested, return it.
            if ( 'id' === $return_type ) {
                return $id;
            }

            $src = wp_get_attachment_url( $id );
        }

        // Finally, check to make sure the file has been saved, then return the HTML.
        if ( ! empty( $src ) ) {
            if ( 'src' === $return_type ) {
                return $src;
            }

            $alt  = isset( $desc ) ? esc_attr( $desc ) : '';
            $html = "<img src='$src' alt='$alt' />";

            return $html;
        } else {
            return new WP_Error( 'upload_failed' );
        }
    }


}

/**
 * Initializes the main plugin
 *
 * @return \BIMFU_Plugin
 */
function bimfu_plugin() {
    return BIMFU_Plugin::init();
}

// run
bimfu_plugin();