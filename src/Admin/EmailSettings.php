<?php
namespace SmartShield\Admin;

class EmailSettings {
    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings() {
        register_setting( 'smart_shield_email_group', 'ss_email_enabled' );
        register_setting( 'smart_shield_email_group', 'ss_email_enable_spam_warning' );
    }

    public function create_page() {
        ?>
        <div class="wrap">
            <h1>Email Settings</h1>
            <p>Configure the email spam protection settings.</p>

            <form method="post" action="options.php">
                <?php settings_fields( 'smart_shield_email_group' ); ?>
                <?php do_settings_sections( 'smart_shield_email_group' ); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Enable Email Spam Protection</th>
                        <td>
                            <input type="checkbox" name="ss_email_enabled" value="1" <?php checked( get_option( 'ss_email_enabled' ), 1 ); ?> />
                            <p class="description">Enable this to protect your site from email spam.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Enable Spam Warning</th>
                        <td>
                            <input type="checkbox" name="ss_email_enable_spam_warning" value="1" <?php checked( get_option( 'ss_email_enable_spam_warning' ), 1 ); ?> />
                            <p class="description">When enabled, spam emails will be sent with "SPAM" added to the subject line. When disabled, spam emails will be completely blocked.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
} 