<?php
/**
 * Plugin Name: Private File Link 2
 * Description: Gestisci file da Dropbox e Google Drive nella Media Library.
 * Version: 1.0
 * Author: Il tuo nome
 */
 // Aggiungi questa riga tra gli altri require_once
require_once plugin_dir_path(__FILE__) . 'pfl_gfoto_api.php';

// Aggiungi questo endpoint AJAX insieme agli altri
add_action('wp_ajax_pfl_get_gfoto_files', 'pfl_ajax_get_gfoto_files');
function pfl_ajax_get_gfoto_files() {
    check_ajax_referer('pfl-nonce', 'nonce');
    
    try {
        $token = get_option('pfl_gdrive_token');
        if (!$token) {
            throw new Exception('Token non disponibile');
        }

        $api = new PFL_Google_Photos_API($token);
        $page_token = isset($_POST['page_token']) ? sanitize_text_field($_POST['page_token']) : null;
        $response = $api->get_files($page_token);

        if ($response === false) {
            throw new Exception('Impossibile recuperare i file da Google Foto');
        }

        wp_send_json_success($response);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}


add_action('admin_enqueue_scripts', function($hook) {
    if ( in_array($hook, ['post.php','post-new.php','upload.php']) ) {
        wp_enqueue_media(); // assicura le dipendenze
        wp_enqueue_script(
            'custom-media-tab',
            plugin_dir_url(__FILE__) . '/js/js.js',
            ['media-views'], // Backbone, Underscore, wp.media
            null,
            true
        );
    }
});


if (!defined('ABSPATH')) {
    exit;
}

// Carica tutte le dipendenze
require_once plugin_dir_path(__FILE__) . 'admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'tabs-media-library.php';
require_once plugin_dir_path(__FILE__) . 'pfl_dropbox_api.php';
require_once plugin_dir_path(__FILE__) . 'pfl_gdrive_api.php';
require_once plugin_dir_path(__FILE__) . 'pfl_dropbox_auth.php';
require_once plugin_dir_path(__FILE__) . 'pfl_gdrive_auth.php';

// Menu admin
add_action('admin_menu', function () {
    add_menu_page(
        'Private File Link Settings',
        'Private File Link',
        'manage_options',
        'private-file-link-settings',
        'pfl_render_settings_page'
    );
});

// Registrazione impostazioni
add_action('admin_init', function () {
    register_setting('pfl_settings_group', 'pfl_dropbox_client_id');
    register_setting('pfl_settings_group', 'pfl_dropbox_client_secret');
    register_setting('pfl_settings_group', 'pfl_gdrive_client_id');
    register_setting('pfl_settings_group', 'pfl_gdrive_client_secret');
});

// Caricamento assets
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'upload.php') {
        wp_enqueue_style('pfl-admin-style', plugins_url('assets/css/pfl-admin.css', __FILE__));
        wp_enqueue_script('pfl-media-script', plugins_url('assets/js/pfl-media.js', __FILE__), ['jquery', 'media-views'], null, true);
        
        wp_localize_script('pfl-media-script', 'pfl_vars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pfl-nonce')
        ]);
    }
});

// Endpoint AJAX
add_action('wp_ajax_pfl_get_dropbox_files', 'pfl_ajax_get_dropbox_files');
add_action('wp_ajax_pfl_get_gdrive_files', 'pfl_ajax_get_gdrive_files');

function pfl_ajax_get_dropbox_files() {
    check_ajax_referer('pfl-nonce', 'nonce');
    
    $token = get_option('pfl_dropbox_token');
    if (!$token) wp_send_json_error('Non autenticato');
    
    $api = new PFL_Dropbox_API($token);
    wp_send_json_success($api->get_files());
}

function pfl_ajax_get_gdrive_files() {
    check_ajax_referer('pfl-nonce', 'nonce');
    
    $api = new PFL_Google_Drive_API();
    if (!$api->access_token && !$api->refresh_token()) {
        wp_send_json_error('Non autenticato');
    }
    
    wp_send_json_success($api->get_files());
}
?>
