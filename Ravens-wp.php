<?php
/**
 * Plugin Name: Ravens WP
 * Description: A plugin to sync WooCommerce products to an external API to Ravens.
 * Version: 1.0
 * Author: Amir Torabi
 * Author URI: https://github.com/AmiirTorabii
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add the main menu page
add_action( 'admin_menu', 'ravens_wp_menu' );
function ravens_wp_menu() {
    add_menu_page(
        'Ravens API Sync', // Page title
        'Ravens', // Menu title
        'manage_options', // Capability
        'ravens', // Menu slug
        'woocommerce_api_sync_page', // Callback function
        'https://honarmenufile.storage.iran.liara.space/Screenshot%202024-11-02%20151501.png', // Menu icon
        6 // Menu position
    );

    // Add the submenu page
    add_submenu_page(
        'ravens',
        'Ravens Settings',
        'Settings',
        'manage_options',
        'ravens-settings',
        'ravens_wp_settings_page',
        'https://honarmenufile.storage.iran.liara.space/kjzdkfjsdjk.png'
    );
}

// Callback function to display the settings page
function ravens_wp_settings_page() {
    // Check if the user has the required capability
    if (!current_user_can('manage_options')) {
        return;
    }

    // Display the settings page
    ?>
    <div class="wrap">
        <h1>Ravens WP Plugin Settings</h1>
        <form method="post" action="options.php">
            <?php
                // Output the settings fields
                settings_fields('ravens-wp-settings');
                do_settings_sections('ravens-wp-settings');
                submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register the settings
add_action('admin_init', 'ravens_wp_register_settings');
function ravens_wp_register_settings() {
    register_setting('ravens-wp-settings', 'ravens_wp_option');
    register_setting('ravens-wp-settings', 'ravens_wp_public_key');

    add_settings_section(
        'ravens-wp-settings-section',
        'Ravens تنظیمات',
        'ravens_wp_settings_section_callback',
        'ravens-wp-settings'
    );

    add_settings_field(
        'ravens_wp_option',
        'Private Key',
        'ravens_wp_option_callback',
        'ravens-wp-settings',
        'ravens-wp-settings-section'
    );

    add_settings_field(
        'ravens_wp_public_key',
        'Public Key',
        'ravens_wp_public_key_callback',
        'ravens-wp-settings',
        'ravens-wp-settings-section'
    );
}

// Callback function for the settings section
function ravens_wp_settings_section_callback() {
    echo 'تنظیمات فعال سازی بر روی سایت در این قسمت است';
}

// Callback function for the private key settings field
function ravens_wp_option_callback() {
    $option = get_option('ravens_wp_option');
    echo '<input type="text" name="ravens_wp_option" value="' . esc_attr($option) . '" />';
}

// Callback function for the public key settings field
function ravens_wp_public_key_callback() {
    $public_key = get_option('ravens_wp_public_key');
    echo '<input type="text" name="ravens_wp_public_key" value="' . esc_attr($public_key) . '" />';
}


add_action( 'save_post_product', 'sync_product_to_api', 10, 3 );

function sync_product_to_api( $post_id, $post, $update ) {
    // بررسی اینکه آیا این یک محصول است
    if ( $post->post_type != 'product' ) {
        return;
    }

    $product = wc_get_product( $post_id );

    // دریافت URL تصویر
    $image_id = $product->get_image_id();
    $img_url = wp_get_attachment_url( $image_id );

    // بررسی اینکه آیا URL تصویر معتبر است
    if ( empty( $img_url ) ) {
        error_log( 'Image URL is empty for product ID: ' . $post_id );
        update_option( 'last_sync_status', 'Error: Image URL is empty for product ID: ' . $post_id );
        return; // اگر تصویر وجود ندارد، از ارسال به API صرف‌نظر کنید
    }

    $data = array(
        'imgUrl'       => "https://honarmenufile.storage.iran.liara.space/juice.png", // استفاده از URL تصویر محصول
        'uid'          => (string) $product->get_id(), // شناسه محصول
        'title'        => $product->get_name(), // عنوان محصول
        'link'         => get_permalink( $post_id ), // لینک محصول
        'description'  => $product->get_description(), // توضیحات محصول
    );

    // دریافت کلیدهای عمومی و خصوصی
    $private_key = get_option('ravens_wp_option');
    $public_key = get_option('ravens_wp_public_key');

    // ارسال به API
    $api_url = 'https://api.ravens.ir/api/v1/objects'; // URL API شما
    $response = wp_remote_post( $api_url, array(
        'method'    => 'POST',
        'body'      => json_encode($data ),
        'headers'   => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . esc_attr($private_key) // استفاده از کلید خصوصی
        ),
    ));

    // آماده‌سازی لاگ
    $log_entry = date('Y-m-d H:i:s') . " - Product ID: $post_id\n";
    $log_entry .= "Request Data: " . json_encode($data) . "\n";

    // بررسی پاسخ API
    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        $log_entry .= 'API Error: ' . $error_message . "\n";
        file_put_contents( __DIR__ . '/api_sync_log.txt', $log_entry, FILE_APPEND );
        update_option( 'last_sync_status', 'Error: ' . $error_message );
    } else {
        $log_entry .= 'Product synced successfully: ' . $response['body'] . "\n";
        file_put_contents( __DIR__ . '/api_sync_log.txt', $log_entry, FILE_APPEND );
        update_option( 'last_sync_status', 'Success: ' . $response['body'] );
    }
}

// تابعی برای نمایش محتوای صفحه
function woocommerce_api_sync_page() {
    ?>
    <div class="wrap">
        <h1>Ravens Api Sync Plugin</h1>
        <p>اینجا می‌توانید تنظیمات مربوط به همگام‌سازی API را مدیریت کنید.</p>


        <table class="wp-list-table widefat fixed striped table-view-list">
            <thead>
                <tr>
                    <th>آیدی</th>
                    <th>عنوان</th>
                    <th>تصویر</th>
                    
                </tr>
            </thead>
            <tbody>
                <?php
                 $private_key = get_option('ravens_wp_option');
                 $public_key = get_option('ravens_wp_public_key');
                $args = array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . esc_attr($private_key)
                    )
                );
                $response = wp_remote_get('https://api.ravens.ir/api/v1/objects?page=1&limit=10', $args);
                if (is_wp_error($response)) {
                    echo '<tr><td colspan="3">Error fetching data: ' . $response->get_error_message() . '</td></tr>';
                } else {
                    $data = json_decode($response['body'], true);
                    foreach ($data['points'] as $point) {
                        echo '<tr>';
                        echo '<td>' . esc_html($point['payload']['uid']) . '</td>';
                        echo '<td>' . esc_html($point['payload']['title']) . '</td>';
                        echo '<td><img src="' . esc_attr($point['payload']['imgUrl']) . '" alt="' . esc_attr($point['payload']['title']) . '" width="150" height="150"></td>';
                        
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

add_filter('body_class', 'ravens_wp_add_body_class');

function ravens_wp_add_body_class($classes) {
    $public_key = get_option('ravens_wp_public_key');
    $classes[] = 'data-ravens-api-key=' . $public_key;
    return $classes;
}

add_action('wp_body_open', 'add_custom_script_to_body_open');

function add_custom_script_to_body_open() {
    $public_key = get_option('ravens_wp_public_key'); // Retrieve the API key from WordPress options
    if ($public_key) {
        echo '<script src="https://cdn.ravens.ir/script.js" defer></script>';
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                initializeRavens("' . esc_js($public_key) . '");
            });
        </script>';
    }
}
add_action('wp_footer', 'add_custom_script_to_body_open');