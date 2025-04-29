<?php
if (!defined('ABSPATH')) exit;

class PFL_Google_Photos_API {

     private $access_token;
    
    public function __construct($access_token) {
        $this->access_token = $access_token;
    }

    public function get_files($page_token = null) {
        try {
            $url = 'https://photoslibrary.googleapis.com/v1/mediaItems';
            $args = ['pageSize' => 50];
            
            if ($page_token) {
                $args['pageToken'] = $page_token;
            }

            $response = wp_remote_get(add_query_arg($args, $url), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->access_token,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 15
            ]);

            if (is_wp_error($response)) {
                error_log('Google Photos API Error: ' . $response->get_error_message());
                return false;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code !== 200) {
                error_log('Google Photos API HTTP Error: ' . $status_code);
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['error'])) {
                error_log('Google Photos API Error: ' . print_r($body['error'], true));
                return false;
            }

            return $this->format_files($body);
        } catch (Exception $e) {
            error_log('Google Photos Exception: ' . $e->getMessage());
            return false;
        }
    }

    public function get_current_account() {
        $body = $this->get('https://www.googleapis.com/oauth2/v1/userinfo');
        return [
            'email' => $body['email'],
            'name' => $body['name']
        ];
    }

    private function format_files($data) {
        $formatted = [];

        if (!isset($data['mediaItems'])) return [];

        foreach ($data['mediaItems'] as $item) {
            $baseUrl = $item['baseUrl'];
            $mimeType = $item['mimeType'];
            $isImage = strpos($mimeType, 'image/') === 0;

            if ($isImage) {
                $formatted[] = [
                    'id' => $item['id'],
                    'name' => $item['filename'],
                    'type' => $mimeType,
                    'thumbnail_url' => $baseUrl . "=w400-h300", // modificabile
                    'url' => $baseUrl . "=d", // download originale
                    'is_image' => true,
                    'base64' => $this->get_image_base64($baseUrl . "=d")
                ];
            }
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
        if (empty($image_data)) return null;

        $image_info = @getimagesizefromstring($image_data);
        if ($image_info === false) return null;

        return 'data:' . $image_info['mime'] . ';base64,' . base64_encode($image_data);
    }

    private function get($url, $params = []) {
        $url .= '?' . http_build_query($params);

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token
            ],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Errore richiesta API: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Errore parsing JSON dalla risposta API.');
        }

        return $body;
    }
}
