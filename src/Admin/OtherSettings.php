<?php
namespace SmartShield\Admin;

use SmartShield\Modules\IPBlocker\IPBlocker;

class OtherSettings {
    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings() {
        register_setting( 'smart_shield_other_group', 'ss_default_block_duration' );
        register_setting( 'smart_shield_other_group', 'ss_max_logs_count' );
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
            <p>Configure default IP blocking duration, log storage limits, whitelisting, notifications, and AI API settings.</p>

            <form method="post" action="options.php">
                <?php settings_fields( 'smart_shield_other_group' ); ?>
                <?php do_settings_sections( 'smart_shield_other_group' ); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Default Block Duration</th>
                        <td>
                            <select name="ss_default_block_duration" class="regular-text">
                                <option value="<?php echo IPBlocker::DURATION_1_HOUR; ?>" <?php selected( get_option( 'ss_default_block_duration', IPBlocker::DURATION_24_HOURS ), IPBlocker::DURATION_1_HOUR ); ?>>1 Hour</option>
                                <option value="<?php echo IPBlocker::DURATION_6_HOURS; ?>" <?php selected( get_option( 'ss_default_block_duration', IPBlocker::DURATION_24_HOURS ), IPBlocker::DURATION_6_HOURS ); ?>>6 Hours</option>
                                <option value="<?php echo IPBlocker::DURATION_24_HOURS; ?>" <?php selected( get_option( 'ss_default_block_duration', IPBlocker::DURATION_24_HOURS ), IPBlocker::DURATION_24_HOURS ); ?>>24 Hours</option>
                                <option value="<?php echo IPBlocker::DURATION_7_DAYS; ?>" <?php selected( get_option( 'ss_default_block_duration', IPBlocker::DURATION_24_HOURS ), IPBlocker::DURATION_7_DAYS ); ?>>7 Days</option>
                                <option value="<?php echo IPBlocker::DURATION_30_DAYS; ?>" <?php selected( get_option( 'ss_default_block_duration', IPBlocker::DURATION_24_HOURS ), IPBlocker::DURATION_30_DAYS ); ?>>30 Days</option>
                                <option value="<?php echo IPBlocker::DURATION_PERMANENT; ?>" <?php selected( get_option( 'ss_default_block_duration', IPBlocker::DURATION_24_HOURS ), IPBlocker::DURATION_PERMANENT ); ?>>Permanent</option>
                            </select>
                            <p class="description">Set the default duration for automatically blocked IP addresses (spam detection, failed logins, etc.). Manual blocks in IP Blocker Management can still use different durations.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Maximum Log Entries</th>
                        <td>
                            <select name="ss_max_logs_count" class="regular-text">
                                <option value="1000" <?php selected( get_option( 'ss_max_logs_count', 10000 ), 1000 ); ?>>1,000 entries</option>
                                <option value="5000" <?php selected( get_option( 'ss_max_logs_count', 10000 ), 5000 ); ?>>5,000 entries</option>
                                <option value="10000" <?php selected( get_option( 'ss_max_logs_count', 10000 ), 10000 ); ?>>10,000 entries</option>
                                <option value="25000" <?php selected( get_option( 'ss_max_logs_count', 10000 ), 25000 ); ?>>25,000 entries</option>
                                <option value="50000" <?php selected( get_option( 'ss_max_logs_count', 10000 ), 50000 ); ?>>50,000 entries</option>
                                <option value="100000" <?php selected( get_option( 'ss_max_logs_count', 10000 ), 100000 ); ?>>100,000 entries</option>
                                <option value="-1" <?php selected( get_option( 'ss_max_logs_count', 10000 ), -1 ); ?>>Unlimited</option>
                            </select>
                            <p class="description">Set the maximum number of log entries to store in the database. When this limit is reached, older entries will be automatically deleted. Choose "Unlimited" to disable automatic cleanup.</p>
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