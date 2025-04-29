<?php
if (!defined('ABSPATH')) exit;

class PFL_Dropbox_API {
    private $access_token;
    
    public function __construct() {
        $this->access_token = get_option('pfl_dropbox_token');
    }
    
    public function get_current_account() {
        $response = wp_remote_post('https://api.dropboxapi.com/2/users/get_current_account', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Errore di connessione a Dropbox');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            throw new Exception($body['error_summary']);
        }

        return [
            'email' => $body['email'],
            'name' => $body['name']
        ];
    }
    
    public function get_files($path = '', $cursor = null) {
        $url = $cursor ? 
            'https://api.dropboxapi.com/2/files/list_folder/continue' :
            'https://api.dropboxapi.com/2/files/list_folder';
        
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($cursor ? 
                ['cursor' => $cursor] : 
                [
                    'path' => $path,
                    'recursive' => false,
                    'include_media_info' => false,
                    'include_deleted' => false,
                    'include_has_explicit_shared_members' => false,
                    'include_mounted_folders' => true,
                    'limit' => 100
                ]
            )
        ];
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return $this->format_files($body);
    }
    
    private function is_image_file($entry) {
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        $ext = strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION));
        return in_array($ext, $image_extensions);
    }
    
    private function get_file_url($entry) {
        $response = wp_remote_post('https://api.dropboxapi.com/2/files/get_temporary_link', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'path' => $entry['path_display']
            ])
        ]);
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return $body['link'] ?? '';
        }
        
        return '';
    }
    
    private function format_files($data) {
        $formatted = [];
        
        foreach ($data['entries'] as $entry) {
            // Salta se non è un file o non è un'immagine
            if ($entry['.tag'] !== 'file' || !$this->is_image_file($entry)) {
                continue;
            }
            
            $file_url = $this->get_file_url($entry);
            
            $formatted[] = [
                'id' => $entry['id'],
                'name' => $entry['name'],
                'path' => $entry['path_display'],
                'url' => $file_url,
                'thumbnail' => $file_url, // Usiamo lo stesso URL per la miniatura
                'is_image' => true
            ];
        }
        
        return [
            'files' => $formatted,
            'has_more' => $data['has_more'] ?? false,
            'cursor' => $data['cursor'] ?? null
        ];
    }
}