<?php
if (!defined('ABSPATH')) exit;

class PFL_Google_Drive_API {
    private $access_token;
    private $client_id;
    private $client_secret;
    
    public function __construct() {
        $this->access_token = get_option('pfl_gdrive_token');
        $this->client_id = get_option('pfl_gdrive_client_id');
        $this->client_secret = get_option('pfl_gdrive_client_secret');
    }
    
    public function get_current_account() {
        $response = wp_remote_get('https://www.googleapis.com/drive/v3/about?fields=user', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Errore di connessione a Google Drive');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            throw new Exception($body['error']['message'] ?? 'Errore sconosciuto');
        }

        return [
            'email' => $body['user']['emailAddress'],
            'name' => $body['user']['displayName']
        ];
    }
    
    public function get_files($page_token = null) {
        $url = 'https://www.googleapis.com/drive/v3/files';
        
        $args = [
            'fields' => 'files(id,name,mimeType,thumbnailLink,webContentLink,size),nextPageToken',
            'pageSize' => 100,
            'q' => "trashed = false and mimeType contains 'image/'"
        ];
        
        if ($page_token) {
            $args['pageToken'] = $page_token;
        }
        
        $response = wp_remote_get(add_query_arg($args, $url), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token
            ]
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore di connessione a Google Drive');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            throw new Exception($body['error']['message'] ?? 'Errore sconosciuto');
        }
        
        return $this->format_files($body);
    }
    
    private function format_files($data) {
        $formatted = [];
        
        foreach ($data['files'] as $file) {
            $download_url = $this->get_download_url($file);
            
            $formatted[] = [
                'id' => $file['id'],
                'name' => $file['name'],
                'type' => $file['mimeType'],
                'thumbnail_url' => $this->get_thumbnail_url($file),
                'url' => $download_url,
                'is_image' => true,
                'size' => isset($file['size']) ? $this->format_size($file['size']) : 'N/A',
                'base64' => $this->get_image_base64($download_url)
            ];
        }
        
        return [
            'files' => $formatted,
            'has_more' => !empty($data['nextPageToken']),
            'next_page_token' => $data['nextPageToken'] ?? null
        ];
    }
    
    private function get_image_base64($url) {
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            error_log('Errore download immagine: ' . $response->get_error_message());
            return null;
        }
        
        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            error_log('Dati immagine vuoti');
            return null;
        }
        
        $image_info = @getimagesizefromstring($image_data);
        if ($image_info === false) {
            error_log('File non è un\'immagine valida');
            return null;
        }
        
        return 'data:' . $image_info['mime'] . ';base64,' . base64_encode($image_data);
    }
    
    private function get_thumbnail_url($file) {
        if (!empty($file['thumbnailLink'])) {
            return str_replace('=s220', '=w600-h400', $file['thumbnailLink']);
        }
        return 'https://drive.google.com/thumbnail?id='.$file['id'].'&sz=w600-h400';
    }
    
    private function get_download_url($file) {
        if (!empty($file['webContentLink'])) {
            return preg_replace('/\&export=download$/', '', $file['webContentLink']);
        }
        return 'https://www.googleapis.com/drive/v3/files/'.$file['id'].'?alt=media';
    }
    
    private function format_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
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