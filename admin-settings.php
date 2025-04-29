<?php
if (!defined('ABSPATH')) {
    exit;
}

// Funzione che disegna la pagina settings
function pfl_render_settings_page()
{
    ?>
    <div class="wrap">
        <h1>Private File Link - Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('pfl_settings_group'); ?>
            <?php do_settings_sections('pfl_settings_group'); ?>
            
            <h2>Dropbox Settings</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Dropbox Client ID</th>
                    <td><input type="text" name="pfl_dropbox_client_id" value="<?php echo esc_attr(get_option('pfl_dropbox_client_id')); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Dropbox Client Secret</th>
                    <td><input type="text" name="pfl_dropbox_client_secret" value="<?php echo esc_attr(get_option('pfl_dropbox_client_secret')); ?>" class="regular-text" /></td>
                </tr>
            </table>

            <h2>Google Drive Settings</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Google Drive Client ID</th>
                    <td><input type="text" name="pfl_gdrive_client_id" value="<?php echo esc_attr(get_option('pfl_gdrive_client_id')); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Google Drive Client Secret</th>
                    <td><input type="text" name="pfl_gdrive_client_secret" value="<?php echo esc_attr(get_option('pfl_gdrive_client_secret')); ?>" class="regular-text" /></td>
                </tr>
            </table>
<?php
echo '<h2>Autenticazione</h2>';
echo '<p><a href="'.admin_url('admin.php?page=pfl_dropbox_auth').'" class="button">Autentica Dropbox</a></p>';
echo '<p><a href="'.admin_url('admin.php?page=pfl_gdrive_auth').'" class="button">Autentica Google Drive</a></p>';
?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
?>
