<?php
/*
Plugin Name: ElevenLabs Audio Reader
Description: Convert blog post content into audio read by cloned voice on ElevenLabs
Version: 1.0
Author: https://github.com/Kaonashie
*/

if (!defined('ABSPATH')) {
    exit;
}

// Adming Settings Page to Add API Key
function elar_settings_menu() {
    add_options_page(
        'ElevenLabs Audio Reader',
        'ElevenLabs Audio Reader',
        'manage_options',
        'elar-settings',
        'elar_settings_page'
        );
}
add_action('admin_menu', 'elar_settings_menu');

function elar_settings_page() {
    ?>
<div class="wrap">
    <h1>ElevenLabs Audio Reader Settings</h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('elar-settings-group');
        do_settings_sections('elar-settings-group');
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">ElevenLabs API Key</th>
                <td><input type="text" name="elar_api_key" value="<?php echo esc_attr(get_option('elar_api_key')); ?>"</td>
            </tr>
            <tr valign="top">
                <th scope="row">Voice ID</th>
                <td><input type="text" name="elar_voice_id" value="<?php echo exc_attr(get_option('elar_voice_id')); ?>"></td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
<?php
}

function elar_register_settings() {
    register_setting('elar-settings-group', 'elar_api_key');
    register_setting('elar-settings-group', 'elar_voice_id');
}
add_action('admin_init', 'elar_register_settings');

// Generate Audio for the Blog Posts
function elar_generate_audio($post_id) {R
    if (wp_is_post_revision($post_id)) {
        return;
    }
    
    $api_key = get_option('elar_api_key');
    $voice_id = get_option('elar_voice_id');
    if (empty($api_key) || empty($voice_id)){
        return;
    }
    
    $post = get_post($post_id);
    $content = wp_strip_all_tags($post->post_content);
    
    $response = wp_remote_post('https://api.elevenlabs.io/v1/text-to-speech', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json'
            ),
            'body'          => json_encode(array(
                'text'      => $content,
                'voice'     => $voice_id,
                'format'    => 'mp3'
                )),
        ));
    
    if (is_wp_error($response)) {
        return;
    }
        
    $audio_url = json_decode(wp_remote_retrieve_body($response))->audio_url;
    
    update_post_meta($post_id, '_elar_audio_url', $audio_url);
}
add_action('save_post', 'elar_generate_audio');

// Diaplay Audio Player in Blog Posts
function elar_display_audio_player($content) {
    if (is_single()) {
        $audio_url = get_post_meta(get_the_ID(), '_elar_audio_url', true);
        if ($audio_url) {
            $audio_player = '<audio controls><source src="' . esc_url($audio_url) . '" type="audio/mpeg>Your browser does not support the audio element.</audio>';        
            $content = $audio_player . $content;
        }
    }
    return $content;
}

add_filter('the_content', 'elar_display_audio_player');