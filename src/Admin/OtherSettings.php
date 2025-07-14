<?php
namespace SmartShield\Admin;

class OtherSettings {
    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings() {
        register_setting( 'smart_shield_other_group', 'ss_ip_blocked_duration' );
        register_setting( 'smart_shield_other_group', 'ss_ip_whitelist' );
        register_setting( 'smart_shield_other_group', 'ss_ip_block_list' );
        register_setting( 'smart_shield_other_group', 'ss_notification_enabled' );
        register_setting( 'smart_shield_other_group', 'ss_notification_email' );
        register_setting( 'smart_shield_other_group', 'ss_ai_api_key' );
    }

    public function create_page() {
        ?>
        <div class="wrap">
            <h1>Other Settings</h1>
            <p>Configure IP blocking, notifications, and AI API settings.</p>

            <form method="post" action="options.php">
                <?php settings_fields( 'smart_shield_other_group' ); ?>
                <?php do_settings_sections( 'smart_shield_other_group' ); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">IP Block Duration</th>
                        <td>
                            <input type="number" name="ss_ip_blocked_duration" value="<?php echo esc_attr( get_option( 'ss_ip_blocked_duration' ) ); ?>" />
                            <p class="description">Enter the duration in seconds for which an IP address will be blocked. Default is 3600 seconds (1 hour).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">IP Block List</th>
                        <td>
                            <textarea name="ss_ip_block_list" rows="5" cols="50"><?php echo esc_attr( get_option( 'ss_ip_block_list' ) ); ?></textarea>
                            <p class="description">Enter the IP addresses to block. Separate multiple addresses with commas.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">IP Whitelist</th>
                        <td>
                            <textarea name="ss_ip_whitelist" rows="5" cols="50"><?php echo esc_attr( get_option( 'ss_ip_whitelist' ) ); ?></textarea>
                            <p class="description">Enter the IP addresses to whitelist. Separate multiple addresses with commas.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Enable Notification</th>
                        <td>
                            <input type="checkbox" name="ss_notification_enabled" value="1" <?php checked( get_option( 'ss_notification_enabled' ), 1 ); ?> />
                            <p class="description">Enable this to send notifications to the admin when a spam is detected.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Notification Email</th>
                        <td>
                            <input type="email" name="ss_notification_email" class="regular-text" value="<?php echo esc_attr( get_option( 'ss_notification_email' ) ); ?>" />
                            <p class="description">Enter the email address to send notifications to. This is required to send notifications.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">AI API Key</th>
                        <td>
                            <input type="text" name="ss_ai_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'ss_ai_api_key' ) ); ?>" />
                            <p class="description">Enter your Gemini AI API key. This is required to use the AI spam protection. <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Get your API key</a></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
} 