<?php
// Questo file va incluso nel tuo plugin Private File Link

add_action('admin_menu', function() {
    add_submenu_page(
        null,
        'Autenticazione Google Drive',
        'Autenticazione Google Drive',
        'manage_options',
        'pfl_gdrive_auth',
        'pfl_gdrive_auth_callback'
    );
});

function pfl_gdrive_auth_callback() {
    if (!current_user_can('manage_options')) {
        wp_die('Non hai i permessi per accedere a questa pagina.');
    }

    $client_id = get_option('pfl_gdrive_client_id');
    $client_secret = get_option('pfl_gdrive_client_secret');
    $redirect_uri = admin_url('admin.php?page=pfl_gdrive_auth');

    if (isset($_GET['code'])) {
        // Ricevuto codice da Google
        $code = sanitize_text_field($_GET['code']);

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ]
        ]);

        if (is_wp_error($response)) {
            echo '<div class="notice notice-error"><p>Errore di comunicazione con Google Drive.</p></div>';
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['access_token'])) {
            update_option('pfl_gdrive_token', $body['access_token']);
            update_option('pfl_gdrive_refresh_token', $body['refresh_token']);
            echo '<div class="notice notice-success"><p>Autenticazione Google Drive completata.</p></div>';
            echo '<a href="' . admin_url('upload.php') . '" class="button-primary">Vai alla Libreria Media</a>';
        } else {
            echo '<div class="notice notice-error"><p>Errore durante l\'ottenimento del token di Google Drive.</p></div>';
        }
    } else {
        // Primo accesso: bottone autorizzazione
        $scope = urlencode('https://www.googleapis.com/auth/drive.readonly');
        $auth_url = "https://accounts.google.com/o/oauth2/v2/auth?client_id={$client_id}&redirect_uri={$redirect_uri}&response_type=code&scope={$scope}&access_type=offline&prompt=consent";

        echo '<div class="wrap">';
        echo '<h1>Autorizza Google Drive</h1>';
        echo '<a class="button-primary" href="' . esc_url($auth_url) . '">Connetti Google Drive</a>';
        echo '</div>';
    }
}
?>
