<?php
namespace SmartShield\Admin;

class EmailSettings {
    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings() {
        register_setting( 'smart_shield_email_group', 'ss_email_enabled' );
        register_setting( 'smart_shield_email_group', 'ss_email_enable_spam_warning' );
        
        // Add AJAX handlers for testing
        add_action('wp_ajax_ss_test_email_spam', [$this, 'ajax_test_email_spam']);
        add_action('wp_ajax_ss_get_email_stats', [$this, 'ajax_get_email_stats']);
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
            
            <?php if (get_option('ss_email_enabled')): ?>
                <div class="ss-email-testing" style="margin-top: 30px;">
                    <h2>Email Spam Testing</h2>
                    <p>Test the email spam detection functionality below.</p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Test Subject</th>
                            <td>
                                <input type="text" id="ss_test_subject" class="regular-text" placeholder="Enter email subject to test" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Test Message</th>
                            <td>
                                <textarea id="ss_test_message" rows="5" cols="50" placeholder="Enter email message to test"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Test Result</th>
                            <td>
                                <button type="button" id="ss_test_email_btn" class="button">Test Email Spam Detection</button>
                                <div id="ss_test_result" style="margin-top: 10px;"></div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="ss-email-stats" style="margin-top: 30px;">
                    <h2>Email Spam Statistics</h2>
                    <button type="button" id="ss_refresh_stats" class="button">Refresh Statistics</button>
                    <div id="ss_email_stats" style="margin-top: 10px;">
                        <p>Loading statistics...</p>
                    </div>
                </div>
                
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Test email spam detection
                    $('#ss_test_email_btn').click(function() {
                        var subject = $('#ss_test_subject').val();
                        var message = $('#ss_test_message').val();
                        
                        if (!subject || !message) {
                            alert('Please enter both subject and message');
                            return;
                        }
                        
                        $('#ss_test_result').html('<p>Testing...</p>');
                        
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'ss_test_email_spam',
                                subject: subject,
                                message: message,
                                nonce: '<?php echo wp_create_nonce('ss_admin_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    var result = response.data.result;
                                    var color = result === 'SPAM' ? 'red' : 'green';
                                    $('#ss_test_result').html('<p style="color: ' + color + '; font-weight: bold;">Result: ' + result + '</p>');
                                } else {
                                    $('#ss_test_result').html('<p style="color: red;">Error: ' + response.data.message + '</p>');
                                }
                            },
                            error: function() {
                                $('#ss_test_result').html('<p style="color: red;">Error testing email spam detection</p>');
                            }
                        });
                    });
                    
                    // Load email statistics
                    function loadEmailStats() {
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'ss_get_email_stats',
                                nonce: '<?php echo wp_create_nonce('ss_admin_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    var stats = response.data;
                                    var html = '<table class="widefat">';
                                    html += '<tr><th>Metric</th><th>Count</th></tr>';
                                    html += '<tr><td>Total Email Spam Blocked</td><td>' + stats.total_email_spam_blocked + '</td></tr>';
                                    html += '<tr><td>Email Spam Blocked Today</td><td>' + stats.email_spam_blocked_today + '</td></tr>';
                                    html += '<tr><td>Email Spam Blocked This Week</td><td>' + stats.email_spam_blocked_week + '</td></tr>';
                                    html += '<tr><td>Email Warnings Sent</td><td>' + stats.email_warnings_sent + '</td></tr>';
                                    html += '</table>';
                                    $('#ss_email_stats').html(html);
                                } else {
                                    $('#ss_email_stats').html('<p>Error loading statistics</p>');
                                }
                            },
                            error: function() {
                                $('#ss_email_stats').html('<p>Error loading statistics</p>');
                            }
                        });
                    }
                    
                    // Refresh statistics
                    $('#ss_refresh_stats').click(function() {
                        loadEmailStats();
                    });
                    
                    // Load stats on page load
                    loadEmailStats();
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for testing email spam detection
     */
    public function ajax_test_email_spam() {
        // Check nonce and permissions
        if (!current_user_can('manage_options') || !check_ajax_referer('ss_admin_nonce', 'nonce', false)) {
            wp_die('Unauthorized access');
        }
        
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (empty($subject) || empty($message)) {
            wp_send_json_error(['message' => 'Subject and message are required']);
        }
        
        // Test email spam detection
        $emailHandler = new \SmartShield\Modules\EmailHandler\EmailHandler();
        $result = $emailHandler->test_email_spam_detection($subject, $message);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX handler for getting email statistics
     */
    public function ajax_get_email_stats() {
        // Check nonce and permissions
        if (!current_user_can('manage_options') || !check_ajax_referer('ss_admin_nonce', 'nonce', false)) {
            wp_die('Unauthorized access');
        }
        
        $emailHandler = new \SmartShield\Modules\EmailHandler\EmailHandler();
        $stats = $emailHandler->get_statistics();
        wp_send_json_success($stats);
    }
} 