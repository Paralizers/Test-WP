<?php
// Aggiunge la pagina nascosta per l'autenticazione Dropbox
add_action('admin_menu', function() {
    add_submenu_page(
        null, // Nessun menu visibile, pagina accessibile solo via URL
        'Autenticazione Dropbox', // Titolo della pagina
        'Autenticazione Dropbox', // Nome del menu (inutile perché nullo)
        'manage_options',         // Capability richiesta per accedere
        'pfl_dropbox_auth',        // Slug della pagina
        'pfl_dropbox_auth_callback'// Funzione che genera il contenuto della pagina
    );
});

function pfl_dropbox_auth_callback() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Non hai i permessi per accedere a questa pagina.'));
    }

    $client_id = get_option('pfl_dropbox_client_id');
    $client_secret = get_option('pfl_dropbox_client_secret');
    $redirect_uri = admin_url('admin.php?page=pfl_dropbox_auth');

    // 1. Gestione del callback di autenticazione
    if (isset($_GET['code'])) {
        $code = sanitize_text_field($_GET['code']);
        
        // 2. Richiesta del token a Dropbox
        $response = wp_remote_post('https://api.dropbox.com/oauth2/token', [
            'body' => [
                'code' => $code,
                'grant_type' => 'authorization_code',
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);

        // 3. Gestione della risposta
        if (is_wp_error($response)) {
            echo '<div class="notice notice-error"><p>Errore di comunicazione con Dropbox: ' . $response->get_error_message() . '</p></div>';
        } else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (!empty($body['access_token'])) {
                // 4. Salvataggio del token
                update_option('pfl_dropbox_token', $body['access_token']);
                update_option('pfl_dropbox_token_expires', time() + $body['expires_in']);
                
                // 5. Reindirizzamento alla pagina delle impostazioni con messaggio di successo
                wp_redirect(admin_url('admin.php?page=private-file-link-settings&dropbox_auth=success'));
                exit;
            } else {
                echo '<div class="notice notice-error"><p>Errore durante l\'autenticazione: ';
                if (isset($body['error_description'])) {
                    echo esc_html($body['error_description']);
                } else {
                    echo 'Risposta inattesa da Dropbox';
                }
                echo '</p></div>';
            }
        }
    }
    // 6. Se non c'è un codice, mostra il pulsante di connessione
    else {
        $auth_url = "https://www.dropbox.com/oauth2/authorize?client_id={$client_id}&redirect_uri={$redirect_uri}&response_type=code&token_access_type=offline";
        
        echo '<div class="wrap">';
        echo '<h1>Connetti Account Dropbox</h1>';
        echo '<p>Per continuare, clicca il pulsante qui sotto per autorizzare l\'accesso al tuo account Dropbox.</p>';
        echo '<a href="' . esc_url($auth_url) . '" class="button button-primary">Connetti con Dropbox</a>';
        echo '</div>';
    }
}

// 7. Aggiungi questo hook per mostrare il messaggio di successo
add_action('admin_notices', function() {
    if (isset($_GET['dropbox_auth']) && $_GET['dropbox_auth'] === 'success') {
        echo '<div class="notice notice-success is-dismissible"><p>Account Dropbox connesso con successo!</p></div>';
    }
});
?>
