<?php
/*
Plugin Name: ElevenLabs Post Reader
Description: Convert blog post content into audio read by cloned voice on ElevenLabs
Version: 1.0
Author: https://github.com/Kaonashie
*/


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Admin Settings Page to Add API Key and Voice ID
function elvc_settings_menu() {
    add_menu_page(
        'ElenvenLabs Post Reader',      // Page title
        'EL Post Reader',        		// Menu title
        'manage_options',              	// Capability required to view
        'elvc-settings',               	// Menu slug
        'elvc_settings_page',          	// Function that displays the settings page
        'dashicons-admin-generic',     	// Icon for the menu
        6                              	// Position in the admin menu
    );
}
add_action('admin_menu', 'elvc_settings_menu');

function elvc_settings_page() {
    ?>
    <div class="wrap">
        <h1>ElevenLabs Voice Clone Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('elvc-settings-group');
            do_settings_sections('elvc-settings-group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">ElevenLabs API Key</th>
                    <td><input type="text" name="elvc_api_key" value="<?php echo esc_attr(get_option('elvc_api_key')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Voice ID</th>
                    <td><input type="text" name="elvc_voice_id" value="<?php echo esc_attr(get_option('elvc_voice_id')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function elvc_register_settings() {
    register_setting('elvc-settings-group', 'elvc_api_key');
    register_setting('elvc-settings-group', 'elvc_voice_id');
}
add_action('admin_init', 'elvc_register_settings');

// Generate Audio for Blog Posts
function elvc_generate_audio($post_id) {
    if (wp_is_post_revision($post_id)) {
        return;
    }

    $api_key = get_option('elvc_api_key');
    $voice_id = get_option('elvc_voice_id');
    if (empty($api_key) || empty($voice_id)) {
        return;
    }

    $post = get_post($post_id);
    $content = wp_strip_all_tags($post->post_content);

    // Get the upload directory
    $upload_dir = wp_upload_dir();
    $post_audio_pattern = 'elvc_audio_' . $post_id . '_*.mp3'; // Pattern for audio files

    // Delete old audio files
    $old_files = glob($upload_dir['path'] . '/' . $post_audio_pattern);
    if ($old_files) {
        foreach ($old_files as $old_file) {
            if (is_file($old_file)) {
                unlink($old_file); // Delete the file
            }
        }
    }

    // Prepare cURL request
    $url = 'https://api.elevenlabs.io/v1/text-to-speech/' . $voice_id;
    $headers = array(
        'Content-Type: application/json',
        'xi-api-key: ' . $api_key
    );
    $data = json_encode(array(
        'text' => $content,
        'model_id' => 'eleven_multilingual_v1',
        'voice_settings' => array(
            'stability' => 0.5,
            'similarity_boost' => 0.5
        )
    ));

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => $headers,
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        error_log('cURL Error: ' . $err);
        return;
    }

    // Create a unique filename for the new audio file
    $filename = 'elvc_audio_' . $post_id . '_' . time() . '.mp3';
    $file_path = $upload_dir['path'] . '/' . $filename;

    // Save the new audio file
    $file_handle = fopen($file_path, 'wb');
    if ($file_handle === false) {
        error_log('Failed to open file for writing: ' . $file_path);
        return;
    }

    fwrite($file_handle, $response);
    fclose($file_handle);

    // Ensure the file is saved correctly and update the post meta
    if (filesize($file_path) > 0) {
        $file_url = $upload_dir['url'] . '/' . $filename;
        update_post_meta($post_id, '_elvc_audio_url', $file_url);
    } else {
        error_log('Audio file saved but size is 0 bytes: ' . $file_path);
    }
}
add_action('save_post', 'elvc_generate_audio');

// Display Audio Player in Blog Posts
function elvc_display_audio_player($content) {
    if (is_single()) {
        $audio_url = get_post_meta(get_the_ID(), '_elvc_audio_url', true);
        if ($audio_url) {
            $audio_player = '
            <div class="elvc-audio-player">
                <audio controls>
                    <source src="' . esc_url($audio_url) . '" type="audio/mpeg">
                    Your browser does not support the audio element.
                </audio>
            </div>';
            $content = $audio_player . $content;
        } else {
            error_log('No audio URL found for post ID: ' . get_the_ID());
        }
    }
    return $content;
}
add_filter('the_content', 'elvc_display_audio_player');

// Enqueue custom CSS for the plugin
function elvc_enqueue_styles() {
    wp_enqueue_style('elvc-custom-style', plugins_url('style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'elvc_enqueue_styles');
