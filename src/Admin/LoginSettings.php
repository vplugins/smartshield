<?php
namespace SmartShield\Admin;

class LoginSettings {
    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings() {
        register_setting( 'smart_shield_login_group', 'ss_login_enabled' );
        register_setting( 'smart_shield_login_group', 'ss_login_max_attempts' );
    }

    public function create_page() {
        ?>
        <div class="wrap">
            <h1>Login Settings</h1>
            <p>Configure the login spam protection settings.</p>

            <form method="post" action="options.php">
                <?php settings_fields( 'smart_shield_login_group' ); ?>
                <?php do_settings_sections( 'smart_shield_login_group' ); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Enable Login Spam Protection</th>
                        <td>
                            <input type="checkbox" name="ss_login_enabled" value="1" <?php checked( get_option( 'ss_login_enabled' ), 1 ); ?> />
                            <p class="description">Enable this to protect your site from login spam.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Max Login Attempts</th>
                        <td>
                            <input type="number" name="ss_login_max_attempts" value="<?php echo esc_attr( get_option( 'ss_login_max_attempts' ) ); ?>" />
                            <p class="description">Enter the maximum number of login attempts allowed before blocking an IP address.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
} 