<?php
/*
Plugin Name: R2 Media Offload
Plugin URI: https://github.com/andrejsrna/R2-Media-Offload
Description: Offload WordPress media uploads to R2-compatible object storage for efficient and cost-effective storage.
Version: 1.0
Author: Andrej Srna
Author URI: https://andrejsrna.sk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: r2-media-offload
Domain Path: /languages
Tags: R2, media, storage, offload, S3-compatible

== Screenshots ==
1. **Media Upload Configuration**  
   Screenshot of the settings page where users can configure their R2-compatible storage credentials.

== Description ==
R2 Media Offload helps WordPress users seamlessly offload their media uploads to R2-compatible object storage solutions. By doing so, it reduces server load and leverages cost-effective and globally distributed object storage.

**Features:**
- Automatically upload media files to R2-compatible storage upon upload.
- Configure and manage storage buckets directly from WordPress.
- Support for S3-compatible APIs for streamlined integration.
- Reduce server storage usage and improve performance.

**Visit the [author's website](https://andrejsrna.sk) for more details and updates.**

== Installation ==
1. Download and install the plugin via the WordPress dashboard or manually upload it to your `wp-content/plugins` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to 'Settings > R2 Media Offload' to configure your API credentials and bucket settings.
4. Save changes and start offloading media to R2-compatible storage.

*/


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Fallback for direct installation without Composer
    if (!class_exists('Aws\S3\S3Client')) {
        require __DIR__ . '/aws-sdk/aws-autoloader.php';
    }
}

use Aws\S3\S3Client;

// Hook to add settings page
add_action('admin_menu', 'cloudflare_r2_offload_settings_menu');

function cloudflare_r2_offload_settings_menu() {
    add_options_page(
        'R2 Offload',
        'R2 Offload',
        'manage_options',
        'cloudflare-r2-offload',
        'cloudflare_r2_offload_settings_page'
    );
}

// Render settings page
function cloudflare_r2_offload_settings_page() {
    ?>
    <div class="wrap">
       <?php echo '<h1>' . esc_html__('R2 Offload Settings', 'r2-media-offload') . '</h1>'; ?>
        <?php if (isset($_GET['cloudflare_r2_migration']) && $_GET['cloudflare_r2_migration'] == 'success'): ?>
            <div id="message" class="updated notice is-dismissible">
<?php echo '<p>' . esc_html__('Media migration to R2 completed successfully.', 'r2-media-offload') . '</p>'; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['cloudflare_r2_local_deletion']) && $_GET['cloudflare_r2_local_deletion'] == 'success'): ?>
            <div id="message" class="updated notice is-dismissible">
<?php echo '<p>' . esc_html__('Local media files have been deleted successfully.', 'r2-media-offload') . '</p>'; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('cloudflare_r2_offload_settings');
            do_settings_sections('cloudflare_r2_offload_settings');
            submit_button();
            ?>
        </form>
        <hr>
<?php echo '<h2>' . esc_html__('Migrate Existing Media', 'cloudflare-r2-offload') . '</h2>'; ?>
<?php echo '<p>' . esc_html__('You can migrate your existing media library to R2.', 'r2-media-offload') . '</p>'; ?>
        <form method="post">
            <?php wp_nonce_field('cloudflare_r2_migrate_media', 'cloudflare_r2_migrate_media_nonce'); ?>
<?php submit_button(
    esc_html__('Migrate Media to R2', 'r2-media-offload'),
    'primary',
    'cloudflare_r2_migrate_media'
); ?>
        </form>
        <hr>
<?php echo '<h2>' . esc_html__('Media Management', 'r2-media-offload') . '</h2>'; ?>
<?php echo '<p>' . esc_html__('You can manage your media files that have been migrated to R2.', 'r2-media-offload') . '</p>'; ?>
        <form method="post">
            <?php wp_nonce_field('cloudflare_r2_delete_local_media', 'cloudflare_r2_delete_local_media_nonce'); ?>
            <?php submit_button(
    esc_html__('Delete Local Media Files Already on R2', 'r2-media-offload'),
    'secondary',
    'cloudflare_r2_delete_local_media',
    false,
    [
        'onclick' => 'return confirm("' . esc_js(__('Are you sure you want to delete all local media files that have been migrated to R2? This action is irreversible.', 'r2-media-offload')) . '")',
    ]
); ?>
        </form>
        <hr>
        <h2><?php _e('Revert Media from R2', 'r2-media-offload'); ?></h2>
        <p><?php _e('Download all media files from R2 back to your server and revert URLs.', 'r2-media-offload'); ?></p>
        <form method="post">
            <?php wp_nonce_field('cloudflare_r2_revert_media', 'cloudflare_r2_revert_media_nonce'); ?>
            <?php submit_button(
                __('Revert Media from R2', 'r2-media-offload'),
                'secondary',
                'cloudflare_r2_revert_media',
                false,
                ['onclick' => 'return confirm("' . esc_js(__('Are you sure you want to revert all media from R2? This will download files back to your server.', 'r2-media-offload')) . '")']
            ); ?>
        </form>

        <hr>
        <h2><?php _e('Re-upload Missing Media', 'r2-media-offload'); ?></h2>
        <p><?php _e('Re-upload media files that exist locally but were removed from R2.', 'r2-media-offload'); ?></p>
        <form method="post">
            <?php wp_nonce_field('cloudflare_r2_reupload_media', 'cloudflare_r2_reupload_media_nonce'); ?>
            <?php submit_button(
                __('Re-upload Missing Media to R2', 'r2-media-offload'),
                'secondary',
                'cloudflare_r2_reupload_media'
            ); ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'cloudflare_r2_offload_settings');

function cloudflare_r2_offload_settings() {
    register_setting('cloudflare_r2_offload_settings', 'cloudflare_r2_access_key');
    register_setting('cloudflare_r2_offload_settings', 'cloudflare_r2_secret_key');
    register_setting('cloudflare_r2_offload_settings', 'cloudflare_r2_bucket_name');
    register_setting('cloudflare_r2_offload_settings', 'cloudflare_r2_endpoint');
    register_setting('cloudflare_r2_offload_settings', 'cloudflare_r2_public_bucket_url');
    register_setting('cloudflare_r2_offload_settings', 'cloudflare_r2_keep_local_media');

    // Existing add_settings_section and add_settings_field calls

    add_settings_section('cloudflare_r2_settings_section', 'R2 API Settings', null, 'cloudflare_r2_offload_settings');
    
    add_settings_field('cloudflare_r2_keep_local_media', 'Keep Local Media Files', 'cloudflare_r2_keep_local_media_callback', 'cloudflare_r2_offload_settings', 'cloudflare_r2_settings_section');
    add_settings_field('cloudflare_r2_public_bucket_url', 'Public Bucket URL', 'cloudflare_r2_public_bucket_url_callback', 'cloudflare_r2_offload_settings', 'cloudflare_r2_settings_section');
    add_settings_field('cloudflare_r2_access_key', 'Access Key', 'cloudflare_r2_access_key_callback', 'cloudflare_r2_offload_settings', 'cloudflare_r2_settings_section');
    add_settings_field('cloudflare_r2_secret_key', 'Secret Key', 'cloudflare_r2_secret_key_callback', 'cloudflare_r2_offload_settings', 'cloudflare_r2_settings_section');
    add_settings_field('cloudflare_r2_bucket_name', 'Bucket Name', 'cloudflare_r2_bucket_name_callback', 'cloudflare_r2_offload_settings', 'cloudflare_r2_settings_section');
    add_settings_field('cloudflare_r2_endpoint', 'R2 Endpoint URL', 'cloudflare_r2_endpoint_callback', 'cloudflare_r2_offload_settings', 'cloudflare_r2_settings_section');
}

function cloudflare_r2_access_key_callback() {
    $value = get_option('cloudflare_r2_access_key', '');
    echo '<input type="text" name="cloudflare_r2_access_key" value="' . esc_attr($value) . '" class="regular-text">';
}

function cloudflare_r2_secret_key_callback() {
    $value = get_option('cloudflare_r2_secret_key', '');
    echo '<input type="password" name="cloudflare_r2_secret_key" value="' . esc_attr($value) . '" class="regular-text">';
}

function cloudflare_r2_bucket_name_callback() {
    $value = get_option('cloudflare_r2_bucket_name', '');
    echo '<input type="text" name="cloudflare_r2_bucket_name" value="' . esc_attr($value) . '" class="regular-text">';
}

function cloudflare_r2_endpoint_callback() {
    $value = get_option('cloudflare_r2_endpoint', 'https://<your-account-id>.r2.cloudflarestorage.com');
    echo '<input type="text" name="cloudflare_r2_endpoint" value="' . esc_attr($value) . '" class="regular-text">';
}

function cloudflare_r2_public_bucket_url_callback() {
    $value = get_option('cloudflare_r2_public_bucket_url', '');
    echo '<input type="text" name="cloudflare_r2_public_bucket_url" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<p class="description">e.g., https://your-public-bucket-url.com</p>';
}

function cloudflare_r2_keep_local_media_callback() {
    $value = get_option('cloudflare_r2_keep_local_media', 'yes');
    echo '<label><input type="checkbox" name="cloudflare_r2_keep_local_media" value="yes"' . checked($value, 'yes', false) . '> ' . esc_html__('Keep local copies of media files after uploading to R2', 'r2-media-offload') . '</label>';
    echo '<p class="description">' . esc_html__('Uncheck to delete local media files after uploading to R2. Be cautious, as this action is irreversible.', 'r2-media-offload') . '</p>';
}


add_filter('wp_generate_attachment_metadata', 'cloudflare_r2_upload_media', 10, 2);

function cloudflare_r2_upload_media($metadata, $attachment_id) {
    // Retrieve settings
    $access_key = get_option('cloudflare_r2_access_key');
    $secret_key = get_option('cloudflare_r2_secret_key');
    $bucket_name = get_option('cloudflare_r2_bucket_name');
    $endpoint = get_option('cloudflare_r2_endpoint');
    $public_bucket_url = rtrim(get_option('cloudflare_r2_public_bucket_url'), '/');
    $keep_local_media = get_option('cloudflare_r2_keep_local_media', 'yes');

    if (!$access_key || !$secret_key || !$bucket_name || !$endpoint || !$public_bucket_url) {
        return $metadata; // Do not proceed without necessary credentials
    }

    // Configure S3 client
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => 'auto',
        'endpoint' => $endpoint,
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key'    => $access_key,
            'secret' => $secret_key,
        ],
    ]);

    // Get upload directory info
    $upload_dir = wp_upload_dir();

    // Get file path of the original image
    $file = get_attached_file($attachment_id);

    // Create array of files to upload (original + sizes)
    $upload_files = [];

    // Add original image
    $upload_files[] = [
        'file' => $file,
        'key' => str_replace(trailingslashit($upload_dir['basedir']), '', $file),
    ];

    // Add image sizes
    if (isset($metadata['sizes']) && !empty($metadata['sizes'])) {
        $file_info = pathinfo($file);
        $base_dir = trailingslashit($file_info['dirname']);

        foreach ($metadata['sizes'] as $size) {
            $file_path = $base_dir . $size['file'];
            $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', $file_path);

            $upload_files[] = [
                'file' => $file_path,
                'key' => $object_key,
            ];
        }
    }

    foreach ($upload_files as $upload) {
        try {
            $s3Client->putObject([
                'Bucket' => $bucket_name,
                'Key'    => $upload['key'],
                'SourceFile' => $upload['file'],
                'ACL'    => 'public-read',
            ]);
        } catch (Exception $e) {
            error_log('R2 upload failed for ' . $upload['file'] . ': ' . $e->getMessage());
            // If upload fails and we're deleting local files, do not delete
            $upload_failed = true;
        }
    }

    // Update attachment meta with the R2 URL of the original image
    $r2_url = $public_bucket_url . '/' . $upload_files[0]['key'];
    update_post_meta($attachment_id, '_cloudflare_r2_url', $r2_url);

    // Delete local files if the user opted not to keep them and uploads were successful
    if ($keep_local_media !== 'yes' && empty($upload_failed)) {
        foreach ($upload_files as $upload) {
            if (file_exists($upload['file'])) {
                unlink($upload['file']);
            }
        }
        // Optionally, remove empty directories
        $upload_dir_path = dirname($upload_files[0]['file']);
        @rmdir($upload_dir_path); // Suppress warnings if directory is not empty
    }

    return $metadata;
}

function replace_media_url_with_r2($url, $attachment_id) {
    $r2_url = get_post_meta($attachment_id, '_cloudflare_r2_url', true);
    if ($r2_url) {
        return $r2_url;
    }
    return $url;
}
add_filter('wp_get_attachment_url', 'replace_media_url_with_r2', 10, 2);

add_filter('image_downsize', 'cloudflare_r2_image_downsize', 10, 3);

function cloudflare_r2_image_downsize($downsize, $attachment_id, $size) {
    // Ensure the $size parameter is valid
    if (!is_string($size) && !is_array($size)) {
        return false; // Fallback to default WordPress handling
    }

    // Retrieve the R2 URL for the attachment
    $r2_url = get_post_meta($attachment_id, '_cloudflare_r2_url', true);

    if (!$r2_url) {
        return false; // Fallback if no R2 URL is set
    }

    // Retrieve attachment metadata
    $meta = wp_get_attachment_metadata($attachment_id);

    if (!$meta) {
        return false; // Fallback if no metadata is found
    }

    $upload_dir = wp_upload_dir();

    // Handle full-size image
    if ($size === 'full' || (is_string($size) && !isset($meta['sizes'][$size]))) {
        $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', get_attached_file($attachment_id));
        $image_url = rtrim(get_option('cloudflare_r2_public_bucket_url'), '/') . '/' . $object_key;
        $width = $meta['width'] ?? null;
        $height = $meta['height'] ?? null;
        $is_intermediate = false;
    } elseif (is_string($size) && isset($meta['sizes'][$size])) {
        // Handle specific named sizes like 'thumbnail', 'medium', etc.
        $size_meta = $meta['sizes'][$size];
        $file_info = pathinfo(get_attached_file($attachment_id));
        $file_path = trailingslashit($file_info['dirname']) . $size_meta['file'];
        $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', $file_path);
        $image_url = rtrim(get_option('cloudflare_r2_public_bucket_url'), '/') . '/' . $object_key;
        $width = $size_meta['width'] ?? null;
        $height = $size_meta['height'] ?? null;
        $is_intermediate = true;
    } elseif (is_array($size)) {
        // Handle custom sizes defined as [width, height]
        $custom_width = $size[0] ?? 0;
        $custom_height = $size[1] ?? 0;

        // Find the closest matching size in metadata
        $matched_size = null;
        foreach ($meta['sizes'] as $name => $details) {
            if ($details['width'] == $custom_width && $details['height'] == $custom_height) {
                $matched_size = $details;
                break;
            }
        }

        if ($matched_size) {
            $file_info = pathinfo(get_attached_file($attachment_id));
            $file_path = trailingslashit($file_info['dirname']) . $matched_size['file'];
            $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', $file_path);
            $image_url = rtrim(get_option('cloudflare_r2_public_bucket_url'), '/') . '/' . $object_key;
            $width = $matched_size['width'];
            $height = $matched_size['height'];
            $is_intermediate = true;
        } else {
            // Fallback to the full-size image if no exact match is found
            $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', get_attached_file($attachment_id));
            $image_url = rtrim(get_option('cloudflare_r2_public_bucket_url'), '/') . '/' . $object_key;
            $width = $meta['width'] ?? null;
            $height = $meta['height'] ?? null;
            $is_intermediate = false;
        }
    } else {
        return false; // Fallback to default handling for unsupported $size values
    }

    return [$image_url, $width, $height, $is_intermediate];
}


function cloudflare_r2_handle_migration() {
    if (isset($_POST['cloudflare_r2_migrate_media'])) {
        // Verify nonce
        if (!isset($_POST['cloudflare_r2_migrate_media_nonce']) || !wp_verify_nonce($_POST['cloudflare_r2_migrate_media_nonce'], 'cloudflare_r2_migrate_media')) {
            wp_die('Nonce verification failed');
        }

        // Perform migration
        cloudflare_r2_migrate_existing_media();

        // Redirect to settings page with success message
        wp_redirect(add_query_arg('cloudflare_r2_migration', 'success', menu_page_url('cloudflare-r2-offload', false)));
        exit;
    }
}
add_action('admin_init', 'cloudflare_r2_handle_migration');

function cloudflare_r2_migrate_existing_media() {
    // Retrieve Cloudflare R2 settings
    $access_key = get_option('cloudflare_r2_access_key');
    $secret_key = get_option('cloudflare_r2_secret_key');
    $bucket_name = get_option('cloudflare_r2_bucket_name');
    $endpoint = get_option('cloudflare_r2_endpoint');
    $public_bucket_url = rtrim(get_option('cloudflare_r2_public_bucket_url'), '/');

    if (!$access_key || !$secret_key || !$bucket_name || !$endpoint || !$public_bucket_url) {
        return; // Do not proceed without necessary credentials
    }

    // Configure S3 client
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => 'auto',
        'endpoint' => $endpoint,
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key'    => $access_key,
            'secret' => $secret_key,
        ],
    ]);

    // Query for all attachments
    $args = [
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'meta_query'     => [
            [
                'key'     => '_cloudflare_r2_url',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ];
    $attachments = get_posts($args);

    // Process each attachment
    foreach ($attachments as $attachment) {
        $attachment_id = $attachment->ID;
        $metadata = wp_get_attachment_metadata($attachment_id);
        $file = get_attached_file($attachment_id);

        if (!$file || !file_exists($file)) {
            continue;
        }

        $upload_dir = wp_upload_dir();
        $files_to_upload = [];

        // Add main file
        $files_to_upload[] = [
            'file' => $file,
            'key' => str_replace(trailingslashit($upload_dir['basedir']), '', $file)
        ];

        // Add thumbnail files
        if (!empty($metadata['sizes'])) {
            $base_dir = dirname($file) . '/';
            foreach ($metadata['sizes'] as $size => $size_info) {
                $size_file = $base_dir . $size_info['file'];
                if (file_exists($size_file)) {
                    $files_to_upload[] = [
                        'file' => $size_file,
                        'key' => str_replace(trailingslashit($upload_dir['basedir']), '', $size_file)
                    ];
                }
            }
        }

        // Upload each file
        foreach ($files_to_upload as $upload) {
            try {
                $s3Client->putObject([
                    'Bucket' => $bucket_name,
                    'Key'    => $upload['key'],
                    'SourceFile' => $upload['file'],
                    'ACL'    => 'public-read',
                ]);
            } catch (Exception $e) {
                error_log('Cloudflare R2 upload failed: ' . $e->getMessage());
                continue;
            }
        }

        // Update the metadata with the R2 URL
        $r2_url = $public_bucket_url . '/' . $files_to_upload[0]['key'];
        update_post_meta($attachment_id, '_cloudflare_r2_url', $r2_url);

        // Update database paths
        cloudflare_r2_update_database_paths($attachment_id, $r2_url);
    }
}

function cloudflare_r2_update_database_paths($attachment_id, $r2_url) {
    global $wpdb;

    // Get the attachment file URL
    $upload_dir = wp_upload_dir();
    $file_path = get_attached_file($attachment_id);
    $old_url = $upload_dir['baseurl'] . '/' . str_replace(trailingslashit($upload_dir['basedir']), '', $file_path);

    // Update featured images
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} 
            SET meta_value = REPLACE(meta_value, %s, %s) 
            WHERE meta_key = '_thumbnail_id' AND meta_value = %d",
            $old_url,
            $r2_url,
            $attachment_id
        )
    );

    // Update post content
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->posts} 
            SET post_content = REPLACE(post_content, %s, %s)",
            $old_url,
            $r2_url
        )
    );

    // Update other meta fields that might contain the old URL
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} 
            SET meta_value = REPLACE(meta_value, %s, %s)",
            $old_url,
            $r2_url
        )
    );

    // Optional: Update custom tables if necessary
    // Add queries for custom plugins or themes that store image URLs
}


function cloudflare_r2_handle_local_deletion() {
    if (isset($_POST['cloudflare_r2_delete_local_media'])) {
        // Verify nonce
        if (!isset($_POST['cloudflare_r2_delete_local_media_nonce']) || !wp_verify_nonce($_POST['cloudflare_r2_delete_local_media_nonce'], 'cloudflare_r2_delete_local_media')) {
            wp_die('Nonce verification failed');
        }

        // Perform local media deletion
        cloudflare_r2_delete_local_media_files();

        // Redirect to settings page with success message
        wp_redirect(add_query_arg('cloudflare_r2_local_deletion', 'success', menu_page_url('cloudflare-r2-offload', false)));
        exit;
    }
}
add_action('admin_init', 'cloudflare_r2_handle_local_deletion');

function cloudflare_r2_delete_local_media_files() {
    // Get all attachments that have been migrated to Cloudflare R2
    $args = [
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'meta_query'     => [
            [
                'key'     => '_cloudflare_r2_url',
                'compare' => 'EXISTS',
            ],
        ],
    ];
    $attachments = get_posts($args);

    // Process each attachment
    foreach ($attachments as $attachment) {
        $attachment_id = $attachment->ID;

        // Get the file path of the original image
        $file = get_attached_file($attachment_id);

        // Create array of files to delete (original + sizes)
        $files_to_delete = [];

        // Add original image
        if ($file && file_exists($file)) {
            $files_to_delete[] = $file;
        }

        // Get metadata
        $metadata = wp_get_attachment_metadata($attachment_id);

        // Add image sizes
        if (isset($metadata['sizes']) && !empty($metadata['sizes'])) {
            $file_info = pathinfo($file);
            $base_dir = trailingslashit($file_info['dirname']);

            foreach ($metadata['sizes'] as $size) {
                $file_path = $base_dir . $size['file'];
                if (file_exists($file_path)) {
                    $files_to_delete[] = $file_path;
                }
            }
        }

        // Delete files
        foreach ($files_to_delete as $file_path) {
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        // Optionally, remove empty directories
        $upload_dir_path = dirname($file);
        @rmdir($upload_dir_path); // Suppress warnings if directory is not empty
    }
}

add_filter('wp_calculate_image_srcset', 'cloudflare_r2_wp_calculate_image_srcset', 10, 5);

function cloudflare_r2_wp_calculate_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
    $r2_url = get_post_meta($attachment_id, '_cloudflare_r2_url', true);
    if (!$r2_url) {
        return $sources; // Use default handling if no R2 URL is set
    }

    $upload_dir = wp_upload_dir();
    $upload_baseurl = $upload_dir['baseurl'];

    foreach ($sources as $key => $source) {
        // Replace the base URL with the R2 public URL
        $r2_base_url = rtrim(get_option('cloudflare_r2_public_bucket_url'), '/');
        $original_url = $source['url'];

        // Replace the upload base URL with R2 base URL
        $new_url = str_replace($upload_baseurl, $r2_base_url, $original_url);

        // Alternatively, construct the URL based on the file path
        // Get the file path relative to the upload base directory
        $file_relative_path = str_replace(trailingslashit($upload_dir['basedir']), '', get_attached_file($attachment_id));
        $file_relative_path = dirname($file_relative_path) . '/' . basename($source['url']);

        $new_url = $r2_base_url . '/' . $file_relative_path;

        // Update the source URL
        $sources[$key]['url'] = $new_url;
    }

    return $sources;
}

// Add this filter to handle attachment image sources
add_filter('wp_get_attachment_image_src', 'cloudflare_r2_get_attachment_image_src', 10, 4);

function cloudflare_r2_get_attachment_image_src($image, $attachment_id, $size, $icon) {
    if (!$image) {
        return $image;
    }

    $r2_url = get_post_meta($attachment_id, '_cloudflare_r2_url', true);
    if (!$r2_url) {
        return $image;
    }

    $upload_dir = wp_upload_dir();
    $r2_base_url = rtrim(get_option('cloudflare_r2_public_bucket_url'), '/');

    // Replace the URL in the returned array
    $image[0] = str_replace($upload_dir['baseurl'], $r2_base_url, $image[0]);

    return $image;
}

// Add this filter to handle admin-side attachment URLs
add_filter('wp_prepare_attachment_for_js', 'cloudflare_r2_prepare_attachment_for_js', 10, 3);

function cloudflare_r2_prepare_attachment_for_js($response, $attachment, $meta) {
    if (!isset($response['sizes'])) {
        return $response;
    }

    $r2_url = get_post_meta($attachment->ID, '_cloudflare_r2_url', true);
    if (!$r2_url) {
        return $response;
    }

    $upload_dir = wp_upload_dir();
    $r2_base_url = rtrim(get_option('cloudflare_r2_public_bucket_url'), '/');

    // Replace URLs in all sizes
    foreach ($response['sizes'] as $size => $size_data) {
        $response['sizes'][$size]['url'] = str_replace(
            $upload_dir['baseurl'],
            $r2_base_url,
            $size_data['url']
        );
    }

    // Replace the main URL
    if (isset($response['url'])) {
        $response['url'] = str_replace($upload_dir['baseurl'], $r2_base_url, $response['url']);
    }

    return $response;
}

// Add this near the top of the file, after the plugin header
register_deactivation_hook(__FILE__, 'cloudflare_r2_deactivate_plugin');

function cloudflare_r2_deactivate_plugin() {
    // Get all attachments that have been offloaded to R2
    $args = [
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'meta_query'     => [
            [
                'key'     => '_cloudflare_r2_url',
                'compare' => 'EXISTS',
            ],
        ],
    ];
    
    $attachments = get_posts($args);
    
    foreach ($attachments as $attachment) {
        cloudflare_r2_revert_single_attachment($attachment->ID);
    }
}

function cloudflare_r2_revert_single_attachment($attachment_id) {
    global $wpdb;
    
    // Get the R2 URL and original file path
    $r2_url = get_post_meta($attachment_id, '_cloudflare_r2_url', true);
    $file_path = get_attached_file($attachment_id);
    $upload_dir = wp_upload_dir();
    
    if (!$r2_url) {
        return false;
    }
    
    // Download files from R2 if they don't exist locally
    if (!file_exists($file_path)) {
        cloudflare_r2_download_from_r2($attachment_id);
    }
    
    // Get the local URL
    $local_url = $upload_dir['baseurl'] . '/' . str_replace(trailingslashit($upload_dir['basedir']), '', $file_path);
    
    // Update post content
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->posts} 
            SET post_content = REPLACE(post_content, %s, %s)",
            $r2_url,
            $local_url
        )
    );
    
    // Update post meta
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} 
            SET meta_value = REPLACE(meta_value, %s, %s)",
            $r2_url,
            $local_url
        )
    );
    
    // Remove the R2 URL meta
    delete_post_meta($attachment_id, '_cloudflare_r2_url');
    
    return true;
}

function cloudflare_r2_download_from_r2($attachment_id) {
    // Get R2 settings
    $access_key = get_option('cloudflare_r2_access_key');
    $secret_key = get_option('cloudflare_r2_secret_key');
    $bucket_name = get_option('cloudflare_r2_bucket_name');
    $endpoint = get_option('cloudflare_r2_endpoint');
    
    if (!$access_key || !$secret_key || !$bucket_name || !$endpoint) {
        return false;
    }
    
    // Configure S3 client
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => 'auto',
        'endpoint' => $endpoint,
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key'    => $access_key,
            'secret' => $secret_key,
        ],
    ]);
    
    // Get file paths
    $file_path = get_attached_file($attachment_id);
    $upload_dir = wp_upload_dir();
    $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', $file_path);
    
    // Create directory if it doesn't exist
    wp_mkdir_p(dirname($file_path));
    
    try {
        // Download original file
        $s3Client->getObject([
            'Bucket' => $bucket_name,
            'Key'    => $object_key,
            'SaveAs' => $file_path
        ]);
        
        // Download thumbnails
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata['sizes'])) {
            $base_dir = dirname($file_path) . '/';
            foreach ($metadata['sizes'] as $size => $size_info) {
                $thumb_path = $base_dir . $size_info['file'];
                $thumb_key = str_replace(trailingslashit($upload_dir['basedir']), '', $thumb_path);
                
                try {
                    $s3Client->getObject([
                        'Bucket' => $bucket_name,
                        'Key'    => $thumb_key,
                        'SaveAs' => $thumb_path
                    ]);
                } catch (Exception $e) {
                    error_log('Failed to download thumbnail from R2: ' . $e->getMessage());
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log('Failed to download file from R2: ' . $e->getMessage());
        return false;
    }
}

// Add a notice to warn users about deactivation
add_action('admin_notices', 'cloudflare_r2_deactivation_warning');

function cloudflare_r2_deactivation_warning() {
    $screen = get_current_screen();
    if ($screen->id === 'plugins') {
        ?>
        <div class="notice notice-warning">
            <p><?php _e('When deactivating R2 Media Offload, the plugin will attempt to download all media files from R2 back to your server. Please ensure you have enough disk space available.', 'r2-media-offload'); ?></p>
        </div>
        <?php
    }
}

// Add this to handle the revert and re-upload actions
function cloudflare_r2_handle_actions() {
    // Handle revert action
    if (isset($_POST['cloudflare_r2_revert_media'])) {
        if (!isset($_POST['cloudflare_r2_revert_media_nonce']) || 
            !wp_verify_nonce($_POST['cloudflare_r2_revert_media_nonce'], 'cloudflare_r2_revert_media')) {
            wp_die('Nonce verification failed');
        }

        cloudflare_r2_revert_all_media();
        wp_redirect(add_query_arg('cloudflare_r2_revert', 'success', menu_page_url('cloudflare-r2-offload', false)));
        exit;
    }

    // Handle re-upload action
    if (isset($_POST['cloudflare_r2_reupload_media'])) {
        if (!isset($_POST['cloudflare_r2_reupload_media_nonce']) || 
            !wp_verify_nonce($_POST['cloudflare_r2_reupload_media_nonce'], 'cloudflare_r2_reupload_media')) {
            wp_die('Nonce verification failed');
        }

        cloudflare_r2_reupload_missing_media();
        wp_redirect(add_query_arg('cloudflare_r2_reupload', 'success', menu_page_url('cloudflare-r2-offload', false)));
        exit;
    }
}
add_action('admin_init', 'cloudflare_r2_handle_actions');

// Add this function to revert all media
function cloudflare_r2_revert_all_media() {
    $args = [
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'meta_query'     => [
            [
                'key'     => '_cloudflare_r2_url',
                'compare' => 'EXISTS',
            ],
        ],
    ];
    
    $attachments = get_posts($args);
    
    foreach ($attachments as $attachment) {
        cloudflare_r2_revert_single_attachment($attachment->ID);
    }
}

// Add this function to re-upload missing media
function cloudflare_r2_reupload_missing_media() {
    // Get R2 settings
    $access_key = get_option('cloudflare_r2_access_key');
    $secret_key = get_option('cloudflare_r2_secret_key');
    $bucket_name = get_option('cloudflare_r2_bucket_name');
    $endpoint = get_option('cloudflare_r2_endpoint');
    
    if (!$access_key || !$secret_key || !$bucket_name || !$endpoint) {
        return false;
    }

    // Configure S3 client
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => 'auto',
        'endpoint' => $endpoint,
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key'    => $access_key,
            'secret' => $secret_key,
        ],
    ]);

    // Get all attachments
    $args = [
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'any',
    ];
    
    $attachments = get_posts($args);
    
    foreach ($attachments as $attachment) {
        $attachment_id = $attachment->ID;
        $file_path = get_attached_file($attachment_id);
        
        // Skip if local file doesn't exist
        if (!file_exists($file_path)) {
            continue;
        }

        // Check if file exists in R2
        $upload_dir = wp_upload_dir();
        $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', $file_path);
        
        try {
            $s3Client->headObject([
                'Bucket' => $bucket_name,
                'Key'    => $object_key
            ]);
            // File exists in R2, skip it
            continue;
        } catch (Exception $e) {
            // File doesn't exist in R2, upload it
            $metadata = wp_get_attachment_metadata($attachment_id);
            $files_to_upload = [];

            // Add main file
            $files_to_upload[] = [
                'file' => $file_path,
                'key' => $object_key
            ];

            // Add thumbnails
            if (!empty($metadata['sizes'])) {
                $base_dir = dirname($file_path) . '/';
                foreach ($metadata['sizes'] as $size => $size_info) {
                    $thumb_path = $base_dir . $size_info['file'];
                    if (file_exists($thumb_path)) {
                        $files_to_upload[] = [
                            'file' => $thumb_path,
                            'key' => str_replace(trailingslashit($upload_dir['basedir']), '', $thumb_path)
                        ];
                    }
                }
            }

            // Upload each file
            foreach ($files_to_upload as $upload) {
                try {
                    $s3Client->putObject([
                        'Bucket' => $bucket_name,
                        'Key'    => $upload['key'],
                        'SourceFile' => $upload['file'],
                        'ACL'    => 'public-read',
                    ]);
                } catch (Exception $e) {
                    error_log('Failed to upload file to R2: ' . $e->getMessage());
                    continue;
                }
            }

            // Update the metadata with the R2 URL
            $public_bucket_url = rtrim(get_option('cloudflare_r2_public_bucket_url'), '/');
            $r2_url = $public_bucket_url . '/' . $files_to_upload[0]['key'];
            update_post_meta($attachment_id, '_cloudflare_r2_url', $r2_url);
            cloudflare_r2_update_database_paths($attachment_id, $r2_url);
        }
    }
}

// Add success messages to the settings page
add_action('admin_notices', 'cloudflare_r2_admin_notices');

function cloudflare_r2_admin_notices() {
    $screen = get_current_screen();
    if ($screen->id !== 'settings_page_cloudflare-r2-offload') {
        return;
    }

    if (isset($_GET['cloudflare_r2_revert']) && $_GET['cloudflare_r2_revert'] === 'success') {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Successfully reverted media from R2.', 'r2-media-offload'); ?></p>
        </div>
        <?php
    }

    if (isset($_GET['cloudflare_r2_reupload']) && $_GET['cloudflare_r2_reupload'] === 'success') {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Successfully re-uploaded missing media to R2.', 'r2-media-offload'); ?></p>
        </div>
        <?php
    }
}

