<?php
if (!defined('ABSPATH')) exit;

class PFL_Google_API_Client {
    protected $access_token;
    protected $client_id;
    protected $client_secret;
    
    public function __construct() {
        $this->access_token = get_option('pfl_gdrive_token');
        $this->client_id = get_option('pfl_gdrive_client_id');
        $this->client_secret = get_option('pfl_gdrive_client_secret');
    }
    
    protected function get($url, $args = []) {
        $response = wp_remote_get(add_query_arg($args, $url), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token
            ]
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore di connessione a Google API');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            throw new Exception($body['error']['message'] ?? 'Errore sconosciuto');
        }
        
        return $body;
    }
    
    public function refresh_token() {
        $refresh_token = get_option('pfl_gdrive_refresh_token');
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            ]
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore durante il refresh del token');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            throw new Exception($body['error_description'] ?? 'Errore durante il refresh del token');
        }
        
        if (!empty($body['access_token'])) {
            update_option('pfl_gdrive_token', $body['access_token']);
            return true;
        }
        
        throw new Exception('Nessun token di accesso ricevuto');
    }
}