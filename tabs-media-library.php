<?php
if (!defined('ABSPATH')) exit;

// Aggiunge i nomi ai tab personalizzati
add_filter('media_view_strings', function ($strings) {
    $strings['pfl_tab_dropbox'] = 'Dropbox';
    $strings['pfl_tab_gdrive'] = 'Google Drive';
    $strings['pfl_tab_gfoto'] = 'Google Foto';
    return $strings;
});

// Stampa i template HTML+JS necessari
add_action('print_media_templates', function () {
    ?>
    <script type="text/html" id="tmpl-pfl-tab-content">
        <div class="pfl-tab-content">
            <div class="pfl-file-list-container">
                <ul class="pfl-file-list">
                    <!-- File caricati via JS -->
                </ul>
            </div>
        </div>
    </script>

    <script>
    jQuery(function($) {
        // Oggetto per memorizzare i base64 delle immagini
        var pflImageBase64Cache = {};
        
        // Controller personalizzati
        wp.media.controller.PFL_Dropbox = wp.media.controller.State.extend({
            defaults: {
                id: 'pfl-dropbox',
                title: 'Dropbox',
                content: 'pfl-dropbox-content',
                menu: 'default',
                priority: 60
            }
        });

        wp.media.controller.PFL_GDrive = wp.media.controller.State.extend({
            defaults: {
                id: 'pfl-gdrive',
                title: 'Google Drive',
                content: 'pfl-gdrive-content',
                menu: 'default',
                priority: 70
            }
        });

        wp.media.controller.PFL_GFoto = wp.media.controller.State.extend({
            defaults: {
                id: 'pfl-gfoto',
                title: 'Google Foto',
                content: 'pfl-gfoto-content',
                menu: 'default',
                priority: 80
            }
        });

        // View personalizzate
        wp.media.view.PFL_Dropbox_Content = wp.media.View.extend({
            className: 'pfl-tab-content',
            render: function() {
                this.$el.html($('#tmpl-pfl-tab-content').html());
                loadDropboxFiles();
                return this;
            }
        });

        wp.media.view.PFL_GDrive_Content = wp.media.View.extend({
            className: 'pfl-tab-content',
            render: function() {
                this.$el.html($('#tmpl-pfl-tab-content').html());
                loadGDriveFiles();
                return this;
            }
        });

        wp.media.view.PFL_GFoto_Content = wp.media.View.extend({
            className: 'pfl-tab-content',
            render: function() {
                this.$el.html($('#tmpl-pfl-tab-content').html());
                loadGFotoFiles();
                return this;
            }
        });

        var OriginalMediaFrame = wp.media.view.MediaFrame.Select;

        wp.media.view.MediaFrame.Select = OriginalMediaFrame.extend({
            initialize: function() {
                OriginalMediaFrame.prototype.initialize.apply(this, arguments);
                this.states.add([
                    new wp.media.controller.PFL_Dropbox(),
                    new wp.media.controller.PFL_GDrive(),
                    new wp.media.controller.PFL_GFoto()
                ]);
            },

            bindHandlers: function() {
                OriginalMediaFrame.prototype.bindHandlers.apply(this, arguments);

                this.on('content:create:pfl-dropbox-content', this.createDropboxContent, this);
                this.on('content:create:pfl-gdrive-content', this.createGDriveContent, this);
                this.on('content:create:pfl-gfoto-content', this.createGFotoContent, this);
            },

            createDropboxContent: function(content) {
                content.view = new wp.media.view.PFL_Dropbox_Content({
                    controller: this
                });
            },

            createGDriveContent: function(content) {
                content.view = new wp.media.view.PFL_GDrive_Content({
                    controller: this
                });
            },

            createGFotoContent: function(content) {
                content.view = new wp.media.view.PFL_GFoto_Content({
                    controller: this
                });
            }
        });

        // Funzione per convertire l'immagine in base64
        function getImageAsBase64(url, callback) {
            // Controlla se abbiamo già il base64 in cache
            if (pflImageBase64Cache[url]) {
                callback(pflImageBase64Cache[url]);
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pfl_download_image',
                    image_url: url,
                    _ajax_nonce: '<?php echo wp_create_nonce("pfl_ajax_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Salva il base64 in cache
                        pflImageBase64Cache[url] = response.data;
                        callback(response.data);
                    } else {
                        console.error('Errore nel download dell\'immagine:', response.data);
                        callback(null);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Errore AJAX:', error);
                    callback(null);
                }
            });
        }

        // Funzione per creare un attachment WordPress
        function createWordPressAttachment(file) {
            return new Promise(function(resolve, reject) {
                if (file.is_image) {
                    getImageAsBase64(file.url, function(base64Image) {
                        if (base64Image) {
                            var attachment = wp.media.attachment({
                                url: base64Image,
                                filename: file.name,
                                type: 'image',
                                subtype: 'jpeg',
                                title: file.name,
                                caption: '',
                                description: ''
                            });
                            resolve(attachment);
                        } else {
                            reject('Errore durante il recupero dell\'immagine');
                        }
                    });
                } else {
                    var attachment = wp.media.attachment({
                        url: file.url,
                        filename: file.name,
                        type: 'file',
                        title: file.name
                    });
                    resolve(attachment);
                }
            });
        }

        // Funzioni AJAX
        function createFileItem(file) {
            var thumbnail = file.is_image ? 
                `<div class="pfl-file-thumb-loading">Caricamento anteprima...</div>` :
                `<div class="pfl-file-icon" style="background-image:url('<?php echo plugins_url("assets/file-icon.png", __FILE__); ?>')"></div>`;

            return `
                <li class="pfl-file-item" data-id="${file.id}" data-url="${file.url}" data-is-image="${file.is_image}">
                    <div class="pfl-file-thumb">${thumbnail}</div>
                    <div class="pfl-file-info">
                        <span class="pfl-file-name">${file.name}</span>
                        <button class="button pfl-insert-button">Inserisci</button>
                    </div>
                </li>
            `;
        }

        function loadImagePreview($item, fileUrl, service = 'dropbox') {
            if (service === 'gdrive' || service === 'gfoto') {
                // Per Google Drive e Google Foto usiamo direttamente l'URL di anteprima
                var thumbnailUrl = fileUrl.replace('export=download', 'thumbnail');
                $item.find('.pfl-file-thumb').html(`<img src="${thumbnailUrl}" alt="Anteprima" style="max-width:100%; height:auto;" />`);
                
                // Precarchiamo il base64 per quando sarà necessario
                getImageAsBase64(fileUrl, function(base64Image) {
                    if (base64Image) {
                        // Il base64 è già salvato in cache dalla funzione getImageAsBase64
                    }
                });
            } else {
                // Per Dropbox manteniamo il sistema attuale di download
                getImageAsBase64(fileUrl, function(base64Image) {
                    if (base64Image) {
                        $item.find('.pfl-file-thumb').html(`<img src="${base64Image}" alt="Anteprima" style="max-width:100%; height:auto;" />`);
                    } else {
                        $item.find('.pfl-file-thumb').html('<div class="pfl-file-thumb-error">Anteprima non disponibile</div>');
                    }
                });
            }
        }

        function loadDropboxFiles(cursor = null) {
            $('.pfl-file-list').html('<div class="pfl-loading">Caricamento file da Dropbox...</div>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pfl_get_dropbox_files',
                    cursor: cursor,
                    _ajax_nonce: '<?php echo wp_create_nonce("pfl_ajax_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        if (!cursor) $('.pfl-file-list').empty();

                        if (response.data.files.length === 0 && !cursor) {
                            $('.pfl-file-list').html('<div class="pfl-no-files">Nessun file trovato su Dropbox</div>');
                            return;
                        }

                        $.each(response.data.files, function(i, file) {
                            var $item = $(createFileItem(file));
                            $('.pfl-file-list').append($item);
                            
                            if (file.is_image) {
                                loadImagePreview($item, file.url, 'dropbox');
                            }
                        });

                        if (response.data.has_more) {
                            $('.pfl-file-list').after('<button class="button pfl-load-more-dropbox" data-cursor="'+response.data.cursor+'">Carica più file</button>');
                        }
                    } else {
                        $('.pfl-file-list').html('<div class="pfl-error">Errore: '+response.data+'</div>');
                    }
                }
            });
        }

        function loadGDriveFiles(page_token = null) {
            $('.pfl-file-list').html('<div class="pfl-loading">Caricamento file da Google Drive...</div>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pfl_get_gdrive_files',
                    page_token: page_token,
                    _ajax_nonce: '<?php echo wp_create_nonce("pfl_ajax_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        if (!page_token) $('.pfl-file-list').empty();

                        if (response.data.files.length === 0 && !page_token) {
                            $('.pfl-file-list').html('<div class="pfl-no-files">Nessun file trovato su Google Drive</div>');
                            return;
                        }

                        $.each(response.data.files, function(i, file) {
                            var $item = $(createFileItem(file));
                            $('.pfl-file-list').append($item);
                            
                            if (file.is_image) {
                                loadImagePreview($item, file.thumbnail_url || file.url, 'gdrive');
                            }
                        });

                        if (response.data.next_page_token) {
                            $('.pfl-file-list').after('<button class="button pfl-load-more-gdrive" data-page-token="'+response.data.next_page_token+'">Carica più file</button>');
                        }
                    } else {
                        $('.pfl-file-list').html('<div class="pfl-error">Errore: '+response.data+'</div>');
                    }
                }
            });
        }

        function loadGFotoFiles(page_token = null) {
            $('.pfl-file-list').html('<div class="pfl-loading">Caricamento file da Google Foto...</div>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pfl_get_gfoto_files',
                    page_token: page_token,
                    _ajax_nonce: '<?php echo wp_create_nonce("pfl_ajax_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        if (!page_token) $('.pfl-file-list').empty();

                        if (response.data.files.length === 0 && !page_token) {
                            $('.pfl-file-list').html('<div class="pfl-no-files">Nessun file trovato su Google Foto</div>');
                            return;
                        }

                        $.each(response.data.files, function(i, file) {
                            var $item = $(createFileItem(file));
                            $('.pfl-file-list').append($item);
                            
                            if (file.is_image) {
                                loadImagePreview($item, file.thumbnail_url || file.url, 'gfoto');
                            }
                        });

                        if (response.data.next_page_token) {
                            $('.pfl-file-list').after('<button class="button pfl-load-more-gfoto" data-page-token="'+response.data.next_page_token+'">Carica più file</button>');
                        }
                    } else {
                        $('.pfl-file-list').html('<div class="pfl-error">Errore: '+response.data+'</div>');
                    }
                }
            });
        }

        // Caricamento più file
        $(document).on('click', '.pfl-load-more-dropbox', function() {
            var cursor = $(this).data('cursor');
            $(this).remove();
            loadDropboxFiles(cursor);
        });

        $(document).on('click', '.pfl-load-more-gdrive', function() {
            var pageToken = $(this).data('page-token');
            $(this).remove();
            loadGDriveFiles(pageToken);
        });

        $(document).on('click', '.pfl-load-more-gfoto', function() {
            var pageToken = $(this).data('page-token');
            $(this).remove();
            loadGFotoFiles(pageToken);
        });

        // Inserisci file
        $(document).on('click', '.pfl-insert-button', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $item = $(this).closest('.pfl-file-item');
            var fileUrl = $item.data('url');
            var isImage = $item.data('is-image');
            var fileName = $item.find('.pfl-file-name').text();
            var file = {
                id: $item.data('id'),
                url: fileUrl,
                name: fileName,
                is_image: isImage
            };

            // Controlla se siamo in un blocco Gutenberg
            if (wp.data && wp.data.select('core/block-editor')) {
                // Controlla se stiamo sostituendo un blocco esistente
                var selectedBlock = wp.data.select('core/block-editor').getSelectedBlock();
                
                if (selectedBlock && (selectedBlock.name === 'core/image' || selectedBlock.name === 'core/file')) {
                    // Sostituisci il blocco esistente
                    var attributes = {};
                    
                    if (selectedBlock.name === 'core/image') {
                        // Usa il base64 dalla cache se disponibile
                        if (pflImageBase64Cache[fileUrl]) {
                            attributes = {
                                url: pflImageBase64Cache[fileUrl],
                                alt: fileName
                            };
                        } else {
                            // Altrimenti scarica il base64
                            getImageAsBase64(fileUrl, function(base64Image) {
                                if (base64Image) {
                                    wp.data.dispatch('core/block-editor').updateBlockAttributes(
                                        selectedBlock.clientId,
                                        {
                                            url: base64Image,
                                            alt: fileName
                                        }
                                    );
                                    
                                    if (wp.media.frame) {
                                        wp.media.frame.close();
                                    }
                                } else {
                                    alert('Errore durante il recupero dell\'immagine');
                                }
                            });
                            return;
                        }
                    } else if (selectedBlock.name === 'core/file') {
                        attributes = {
                            href: fileUrl,
                            text: fileName
                        };
                    }
                    
                    wp.data.dispatch('core/block-editor').updateBlockAttributes(
                        selectedBlock.clientId,
                        attributes
                    );
                    
                    // Chiudi la media modal se esiste
                    if (wp.media.frame) {
                        wp.media.frame.close();
                    }
                } else {
                    // Inserisci nuovo blocco
                    if (isImage) {
                        // Usa il base64 dalla cache se disponibile
                        if (pflImageBase64Cache[fileUrl]) {
                            var imageBlock = wp.blocks.createBlock('core/image', {
                                url: pflImageBase64Cache[fileUrl],
                                alt: fileName
                            });
                            
                            wp.data.dispatch('core/block-editor').insertBlocks(imageBlock);
                            
                            if (wp.media.frame) {
                                wp.media.frame.close();
                            }
                        } else {
                            // Altrimenti scarica il base64
                            getImageAsBase64(fileUrl, function(base64Image) {
                                if (base64Image) {
                                    var imageBlock = wp.blocks.createBlock('core/image', {
                                        url: base64Image,
                                        alt: fileName
                                    });
                                    
                                    wp.data.dispatch('core/block-editor').insertBlocks(imageBlock);
                                    
                                    if (wp.media.frame) {
                                        wp.media.frame.close();
                                    }
                                } else {
                                    alert('Errore durante il recupero dell\'immagine');
                                }
                            });
                        }
                    } else {
                        var fileBlock = wp.blocks.createBlock('core/file', {
                            href: fileUrl,
                            text: fileName
                        });
                        
                        wp.data.dispatch('core/block-editor').insertBlocks(fileBlock);
                        
                        if (wp.media.frame) {
                            wp.media.frame.close();
                        }
                    }
                }
            } 
            // Controlla se siamo in un media frame specifico (galleria, box file, etc.)
            else if (wp.media.frame && wp.media.frame.state().get('selection')) {
                createWordPressAttachment(file).then(function(attachment) {
                    var frame = wp.media.frame;
                    var selection = frame.state().get('selection');
                    
                    // Se stiamo sostituendo un'immagine/file esistente
                    if (frame.options && frame.options.multiple === false) {
                        selection.reset();
                        selection.add(attachment);
                        
                        // Se siamo in modalità "replace" (sostituzione)
                        if (frame.options.title === 'Replace') {
                            frame.state().get('selection').trigger('update');
                            frame.close();
                            return;
                        }
                    } else {
                        selection.add(attachment);
                    }
                    
                    // Se siamo in modalità "create gallery"
                    if (frame.state().get('library')) {
                        frame.state().get('library').add(attachment);
                    }
                }).catch(function(error) {
                    console.error(error);
                    alert(error);
                });
            } 
            // Editor classico
            else {
                var editor;
                if (typeof wpActiveEditor !== 'undefined') {
                    editor = wpActiveEditor;
                } else if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                    editor = tinymce.activeEditor.id;
                } else {
                    editor = 'content';
                }

                if (isImage) {
                    // Usa il base64 dalla cache se disponibile
                    if (pflImageBase64Cache[fileUrl]) {
                        var html = '<img src="' + pflImageBase64Cache[fileUrl] + '" alt="' + fileName + '" />';
                        
                        if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                            tinymce.activeEditor.execCommand('mceInsertContent', false, html);
                        } else {
                            var textarea = $('#' + editor);
                            textarea.val(textarea.val() + html);
                        }
                        
                        if (wp.media.frame) {
                            wp.media.frame.close();
                        }
                    } else {
                        // Altrimenti scarica il base64
                        getImageAsBase64(fileUrl, function(base64Image) {
                            if (base64Image) {
                                var html = '<img src="' + base64Image + '" alt="' + fileName + '" />';
                                
                                if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                                    tinymce.activeEditor.execCommand('mceInsertContent', false, html);
                                } else {
                                    var textarea = $('#' + editor);
                                    textarea.val(textarea.val() + html);
                                }
                                
                                if (wp.media.frame) {
                                    wp.media.frame.close();
                                }
                            } else {
                                alert('Errore durante il recupero dell\'immagine');
                            }
                        });
                    }
                } else {
                    var linkHtml = '<a href="' + fileUrl + '">' + fileName + '</a>';
                    
                    if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                        tinymce.activeEditor.execCommand('mceInsertContent', false, linkHtml);
                    } else {
                        var textarea = $('#' + editor);
                        textarea.val(textarea.val() + linkHtml);
                    }
                    
                    if (wp.media.frame) {
                        wp.media.frame.close();
                    }
                }
            }
        });
    });
    </script>
    <style>
        .pfl-file-thumb-loading {
            padding: 20px;
            text-align: center;
            color: #999;
        }
        .pfl-file-thumb-error {
            padding: 20px;
            text-align: center;
            color: #dc3232;
        }
    </style>
    <?php
});

// Handler AJAX per Dropbox
add_action('wp_ajax_pfl_get_dropbox_files', function () {
    check_ajax_referer('pfl_ajax_nonce', '_ajax_nonce');

    $token = get_option('pfl_dropbox_token');
    if (!$token) {
        wp_send_json_error('Non autenticato con Dropbox');
    }

    require_once plugin_dir_path(__FILE__) . 'pfl_dropbox_api.php';
    $dropbox_api = new PFL_Dropbox_API($token);

    $cursor = isset($_POST['cursor']) ? sanitize_text_field($_POST['cursor']) : null;
    $response = $dropbox_api->get_files($cursor);

    if ($response === false) {
        wp_send_json_error('Impossibile recuperare i file da Dropbox');
    }

    wp_send_json_success($response);
});

// Handler AJAX per Google Drive
add_action('wp_ajax_pfl_get_gdrive_files', function () {
    check_ajax_referer('pfl_ajax_nonce', '_ajax_nonce');

    require_once plugin_dir_path(__FILE__) . 'pfl_gdrive_api.php';
    $gdrive_api = new PFL_Google_Drive_API();

    $page_token = isset($_POST['page_token']) ? sanitize_text_field($_POST['page_token']) : null;
    $response = $gdrive_api->get_files($page_token);

    if ($response === false) {
        if ($gdrive_api->refresh_token()) {
            $response = $gdrive_api->get_files($page_token);
        } else {
            wp_send_json_error('Errore di autenticazione con Google Drive');
        }
    }

    if ($response === false) {
        wp_send_json_error('Impossibile recuperare i file da Google Drive');
    }

    wp_send_json_success($response);
});

// Handler AJAX per Google Foto
add_action('wp_ajax_pfl_get_gfoto_files', function () {
    check_ajax_referer('pfl_ajax_nonce', '_ajax_nonce');

    require_once plugin_dir_path(__FILE__) . 'pfl_gfoto_api.php';
    $gfoto_api = new PFL_Google_Foto_API();

    $page_token = isset($_POST['page_token']) ? sanitize_text_field($_POST['page_token']) : null;
    $response = $gfoto_api->get_files($page_token);

    if ($response === false) {
        wp_send_json_error('Impossibile recuperare i file da Google Foto');
    }

    wp_send_json_success($response);
});

// Handler per il download delle immagini
add_action('wp_ajax_pfl_download_image', function() {
    check_ajax_referer('pfl_ajax_nonce', '_ajax_nonce');

    $image_url = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
    
    if (empty($image_url)) {
        wp_send_json_error('URL immagine non valido');
    }

    $response = wp_remote_get($image_url, [
        'timeout' => 30,
        'sslverify' => false
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    $image_data = wp_remote_retrieve_body($response);
    if (empty($image_data)) {
        wp_send_json_error('Impossibile recuperare i dati dell\'immagine');
    }

    $image_info = getimagesizefromstring($image_data);
    if ($image_info === false) {
        wp_send_json_error('Il file non è un\'immagine valida');
    }

    $mime_type = $image_info['mime'];
    $base64 = 'data:' . $mime_type . ';base64,' . base64_encode($image_data);

    wp_send_json_success($base64);
});