<?php
namespace SmartShield\Admin;

use SmartShield\Modules\IPBlocker\IPBlocker;

class IPBlockerPage {
    private $ipBlocker;

    public function __construct() {
        $this->ipBlocker = new IPBlocker();
    }

    public function create_page() {
        // Handle form submissions
        $this->handle_form_submissions();
        
        // Get current page and filters
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'active';
        $per_page = 50;
        $offset = ($current_page - 1) * $per_page;
        
        // Get blocked IPs and statistics
        $blocked_ips = $this->ipBlocker->get_blocked_ips($status, $per_page, $offset);
        $stats = $this->ipBlocker->get_statistics();
        
        ?>
        <div class="wrap">
            <h1>IP Blocker Management</h1>
            <p>Manage blocked IP addresses and monitor security threats.</p>

            <!-- Add New Block Form -->
            <div class="postbox" style="margin-top: 20px;">
                <div class="inside">
                    <form method="post" action="">
                        <?php wp_nonce_field('smart_shield_block_ip', 'block_ip_nonce'); ?>
                        <input type="hidden" name="action" value="block_ip">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ip_address">IP Address</label>
                                </th>
                                <td>
                                    <input type="text" id="ip_address" name="ip_address" class="regular-text" 
                                           placeholder="192.168.1.1" required 
                                           pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$" />
                                    <p class="description">Enter a valid IPv4 address to block.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="duration">Block Duration</label>
                                </th>
                                <td>
                                    <select id="duration" name="duration" class="regular-text">
                                        <option value="<?php echo IPBlocker::DURATION_1_HOUR; ?>">1 Hour</option>
                                        <option value="<?php echo IPBlocker::DURATION_6_HOURS; ?>">6 Hours</option>
                                        <option value="<?php echo IPBlocker::DURATION_24_HOURS; ?>" selected>24 Hours</option>
                                        <option value="<?php echo IPBlocker::DURATION_7_DAYS; ?>">7 Days</option>
                                        <option value="<?php echo IPBlocker::DURATION_30_DAYS; ?>">30 Days</option>
                                        <option value="<?php echo IPBlocker::DURATION_PERMANENT; ?>">Permanent</option>
                                    </select>
                                    <p class="description">Select how long the IP should be blocked.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="reason">Reason</label>
                                </th>
                                <td>
                                    <textarea id="reason" name="reason" class="large-text" rows="3" 
                                              placeholder="Reason for blocking this IP address..."></textarea>
                                    <p class="description">Provide a reason for blocking this IP address.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="Block IP Address" />
                        </p>
                    </form>
                </div>
            </div>

            <!-- Filters and Actions -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="smart-shield-ip-blocker" />
                        
                        <select name="status">
                            <option value="active" <?php selected($status, 'active'); ?>>Active Blocks</option>
                            <option value="expired" <?php selected($status, 'expired'); ?>>Expired Blocks</option>
                            <option value="manually_removed" <?php selected($status, 'manually_removed'); ?>>Manually Removed</option>
                            <option value="" <?php selected($status, ''); ?>>All Blocks</option>
                        </select>
                        
                        <input type="submit" class="button" value="Filter" />
                        <a href="<?php echo admin_url('admin.php?page=smart-shield-ip-blocker'); ?>" class="button">Clear</a>
                    </form>
                </div>
                
                <div class="alignright actions">
                    <form method="post" style="display: inline-block;">
                        <?php wp_nonce_field('smart_shield_cleanup_blocks', 'cleanup_nonce'); ?>
                        <input type="hidden" name="action" value="cleanup_expired">
                        <input type="submit" class="button" value="Clean Up Expired Blocks" 
                               onclick="return confirm('This will mark all expired blocks as cleaned up. Continue?')" />
                    </form>
                </div>
            </div>

            <!-- Blocked IPs Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width: 60px;">ID</th>
                        <th scope="col">IP Address</th>
                        <th scope="col">Status</th>
                        <th scope="col">Duration</th>
                        <th scope="col">Expires At</th>
                        <th scope="col">Reason</th>
                        <th scope="col">Blocked At</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($blocked_ips)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">No blocked IPs found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($blocked_ips as $ip): ?>
                            <tr>
                                <td><?php echo esc_html($ip->id); ?></td>
                                <td>
                                    <code><?php echo esc_html($ip->ip_address); ?></code>
                                </td>
                                <td>
                                    <span class="smart-shield-status-badge <?php echo esc_attr($ip->status); ?>" 
                                          style="padding: 2px 8px; border-radius: 3px; font-size: 12px; font-weight: 500;">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $ip->status))); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if ($ip->duration == IPBlocker::DURATION_PERMANENT) {
                                        echo '<strong>Permanent</strong>';
                                    } else {
                                        echo esc_html($this->get_duration_text($ip->duration));
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($ip->expires_at): ?>
                                        <time datetime="<?php echo esc_attr($ip->expires_at); ?>">
                                            <?php echo esc_html(date('M j, Y g:i A', strtotime($ip->expires_at))); ?>
                                        </time>
                                        <br>
                                        <small style="color: #666;">
                                            <?php echo esc_html($this->get_time_remaining($ip)); ?>
                                        </small>
                                    <?php else: ?>
                                        <em>Never</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ip->reason): ?>
                                        <details>
                                            <summary style="cursor: pointer;">View Reason</summary>
                                            <div style="margin-top: 5px; max-width: 200px;">
                                                <?php echo esc_html($ip->reason); ?>
                                            </div>
                                        </details>
                                    <?php else: ?>
                                        <em>No reason provided</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <time datetime="<?php echo esc_attr($ip->blocked_at); ?>">
                                        <?php echo esc_html(date('M j, Y g:i A', strtotime($ip->blocked_at))); ?>
                                    </time>
                                </td>
                                <td>
                                    <?php if ($ip->status === 'active'): ?>
                                        <form method="post" style="display: inline-block;">
                                            <?php wp_nonce_field('smart_shield_unblock_ip', 'unblock_ip_nonce'); ?>
                                            <input type="hidden" name="action" value="unblock_ip">
                                            <input type="hidden" name="ip_address" value="<?php echo esc_attr($ip->ip_address); ?>">
                                            <input type="submit" class="button button-small" value="Unblock" 
                                                   onclick="return confirm('Are you sure you want to unblock this IP address?')" />
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #666; font-style: italic;">No actions</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Recent Block Reasons -->
            <?php if (!empty($stats['by_reason'])): ?>
                <div class="postbox" style="margin-top: 20px;">
                    <h2 class="hndle"><span>Common Block Reasons</span></h2>
                    <div class="inside">
                        <table class="wp-list-table widefat">
                            <thead>
                                <tr>
                                    <th>Reason</th>
                                    <th style="width: 100px;">Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['by_reason'] as $reason): ?>
                                    <tr>
                                        <td><?php echo esc_html($reason->reason); ?></td>
                                        <td><strong><?php echo number_format($reason->count); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .smart-shield-status-badge.active {
                background-color: #d63638;
                color: white;
            }
            .smart-shield-status-badge.expired {
                background-color: #646970;
                color: white;
            }
            .smart-shield-status-badge.manually_removed {
                background-color: #00a32a;
                color: white;
            }
            details summary {
                outline: none;
            }
            details[open] summary {
                margin-bottom: 5px;
            }
        </style>
        <?php
    }

    private function handle_form_submissions() {
        if (!isset($_POST['action'])) {
            return;
        }

        $action = sanitize_text_field($_POST['action']);

        switch ($action) {
            case 'block_ip':
                $this->handle_block_ip();
                break;
            case 'unblock_ip':
                $this->handle_unblock_ip();
                break;
            case 'cleanup_expired':
                $this->handle_cleanup_expired();
                break;
        }
    }

    private function handle_block_ip() {
        if (!wp_verify_nonce($_POST['block_ip_nonce'], 'smart_shield_block_ip')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $ip_address = sanitize_text_field($_POST['ip_address']);
        $duration = intval($_POST['duration']);
        $reason = sanitize_textarea_field($_POST['reason']);

        if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Invalid IP address format.</p></div>';
            });
            return;
        }

        $result = $this->ipBlocker->block_ip($ip_address, $duration, $reason, 'admin_manual');

        if ($result) {
            add_action('admin_notices', function() use ($ip_address) {
                echo '<div class="notice notice-success"><p>IP address ' . esc_html($ip_address) . ' has been blocked successfully.</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Failed to block IP address. Please try again.</p></div>';
            });
        }
    }

    private function handle_unblock_ip() {
        if (!wp_verify_nonce($_POST['unblock_ip_nonce'], 'smart_shield_unblock_ip')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $ip_address = sanitize_text_field($_POST['ip_address']);

        $result = $this->ipBlocker->unblock_ip($ip_address, 'admin_manual');

        if ($result) {
            add_action('admin_notices', function() use ($ip_address) {
                echo '<div class="notice notice-success"><p>IP address ' . esc_html($ip_address) . ' has been unblocked successfully.</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Failed to unblock IP address. It may not be currently blocked.</p></div>';
            });
        }
    }

    private function handle_cleanup_expired() {
        if (!wp_verify_nonce($_POST['cleanup_nonce'], 'smart_shield_cleanup_blocks')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $cleaned_count = $this->ipBlocker->cleanup_expired_blocks();

        add_action('admin_notices', function() use ($cleaned_count) {
            echo '<div class="notice notice-success"><p>Cleaned up ' . number_format($cleaned_count) . ' expired IP blocks.</p></div>';
        });
    }

    private function get_duration_text($duration) {
        switch ($duration) {
            case IPBlocker::DURATION_1_HOUR:
                return '1 hour';
            case IPBlocker::DURATION_6_HOURS:
                return '6 hours';
            case IPBlocker::DURATION_24_HOURS:
                return '24 hours';
            case IPBlocker::DURATION_7_DAYS:
                return '7 days';
            case IPBlocker::DURATION_30_DAYS:
                return '30 days';
            case IPBlocker::DURATION_PERMANENT:
                return 'Permanent';
            default:
                $hours = round($duration / 3600, 1);
                return $hours . ' hour' . ($hours != 1 ? 's' : '');
        }
    }

    private function get_time_remaining($block) {
        if ($block->duration == IPBlocker::DURATION_PERMANENT) {
            return 'Permanent';
        }

        if (!$block->expires_at) {
            return 'Unknown';
        }

        $remaining_seconds = strtotime($block->expires_at) - time();

        if ($remaining_seconds <= 0) {
            return 'Expired';
        }

        $days = floor($remaining_seconds / 86400);
        $hours = floor(($remaining_seconds % 86400) / 3600);
        $minutes = floor(($remaining_seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) $parts[] = $days . ' day' . ($days != 1 ? 's' : '');
        if ($hours > 0) $parts[] = $hours . ' hour' . ($hours != 1 ? 's' : '');
        if ($minutes > 0 && $days == 0) $parts[] = $minutes . ' minute' . ($minutes != 1 ? 's' : '');

        return implode(', ', $parts) ?: 'Less than 1 minute';
    }
} 