<?php
namespace SmartShield\Admin;

class LogsPage {
    private $logger;

    public function __construct() {
        $this->logger = new Logger();
    }

    public function create_page() {
        // Handle bulk actions
        $this->handle_bulk_actions();
        
        // Get current page and filters
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $event_type = isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $per_page = 50;
        
        // Get logs
        $logs = $this->logger->get_logs($current_page, $per_page, $event_type, $status);
        $stats = $this->logger->get_stats();
        
        ?>
        <div class="wrap">
            <h1>Smart Shield Logs</h1>
            <p>View the logs of events detected by Smart Shield. Use the filters to narrow down the results.</p>
            <div class="dashboard-widgets-wrap">
                <div class="metabox-holder">
                    <div class="postbox-container full-width">
                        <div class="postbox">
                            <h2 class="hndle stats-header">
                                <span>📈 Statistics Overview</span>
                            </h2>
                            <div class="inside stats-inside">

                                <!-- Stat Cards Row -->
                                <div class="stat-cards-row">
                                    <?php
                                    $stat_cards = [
                                        ['label' => 'Total Events', 'value' => number_format($stats['total']), 'color' => '#135e96'],
                                        ['label' => 'Last 24 Hours', 'value' => number_format($stats['recent_24h']), 'color' => '#d63638'],
                                        ['label' => 'Unique IPs', 'value' => count($stats['top_ips']), 'color' => '#00a32a'],
                                    ];
                                    ?>
                                    <?php foreach ($stat_cards as $card): ?>
                                        <div class="stat-card" style="--stat-color: <?php echo $card['color']; ?>;">
                                            <h3 class="stat-value"><?php echo $card['value']; ?></h3>
                                            <p class="stat-label"><?php echo $card['label']; ?></p>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Storage Limit -->
                                    <?php
                                    $max_logs = get_option('ss_max_logs_count', 10000);
                                    $limit_text = ($max_logs == -1) ? 'Unlimited' : number_format($max_logs);
                                    $percentage = ($max_logs != -1 && $max_logs > 0) ? round(($stats['total'] / $max_logs) * 100, 1) : 0;
                                    $limit_color = ($percentage > 90) ? '#d63638' : (($percentage > 75) ? '#f56e28' : '#00a32a');
                                    ?>
                                    <div class="stat-card" style="--stat-color: <?php echo $limit_color; ?>;">
                                        <h3 class="stat-value"><?php echo $limit_text; ?></h3>
                                        <p class="stat-label">
                                            Storage Limit
                                            <?php if ($max_logs != -1): ?>
                                                <span class="stat-subtext"> · <?php echo $percentage; ?>% used</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Data Tables -->
                                <div class="stats-table-section">
                                    <div class="stats-table-box">
                                        <table class="widefat striped">
                                            <thead>
                                                <tr>
                                                    <th>Event Type</th>
                                                    <th>Count</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($stats['by_type'] as $type): ?>
                                                    <tr>
                                                        <td><?php echo esc_html(ucfirst($type->event_type)); ?></td>
                                                        <td><?php echo number_format($type->count); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="stats-table-box">
                                        <table class="widefat striped">
                                            <thead>
                                                <tr>
                                                    <th>Top IP Addresses</th>
                                                    <th>Events</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_slice($stats['top_ips'], 0, 5) as $ip): ?>
                                                    <tr>
                                                        <td><?php echo esc_html($ip->ip_address); ?></td>
                                                        <td><?php echo number_format($ip->count); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div> <!-- inside -->
                        </div> <!-- postbox -->
                    </div>
                </div> <!-- postbox-container -->
            </div> <!-- dashboard-widgets-wrap -->

            <!-- Filters -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="smart-shield-logs" />
                        
                        <select name="event_type">
                            <option value="">All Event Types</option>
                            <?php foreach ($stats['by_type'] as $type): ?>
                                <option value="<?php echo esc_attr($type->event_type); ?>" <?php selected($event_type, $type->event_type); ?>>
                                    <?php echo esc_html(ucfirst($type->event_type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status">
                            <option value="">All Status</option>
                            <?php foreach ($stats['by_status'] as $stat): ?>
                                <option value="<?php echo esc_attr($stat->status); ?>" <?php selected($status, $stat->status); ?>>
                                    <?php echo esc_html(ucfirst($stat->status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="submit" class="button" value="Filter" />
                        <a href="<?php echo admin_url('admin.php?page=smart-shield-logs'); ?>" class="button">Clear</a>
                    </form>
                </div>
                
                <div class="alignright actions">
                    <form method="post" style="display: inline-block; margin-right: 10px;">
                        <?php wp_nonce_field('smart_shield_cleanup_logs', 'cleanup_nonce'); ?>
                        <input type="hidden" name="action" value="cleanup_logs_by_count">
                        <input type="submit" class="button" value="Cleanup to Limit" 
                               onclick="return confirm('This will remove old log entries to stay within the storage limit. Continue?')" />
                    </form>
                    
                    <form method="post" style="display: inline-block;">
                        <?php wp_nonce_field('smart_shield_cleanup_logs', 'cleanup_nonce'); ?>
                        <input type="hidden" name="action" value="cleanup_logs_by_days">
                        <input type="number" name="cleanup_days" value="30" min="1" max="365" style="width: 60px;" />
                        <input type="submit" class="button" value="Cleanup by Days" 
                               onclick="return confirm('This will permanently delete log entries older than the specified days. Continue?')" />
                    </form>
                </div>
            </div>

            <!-- Logs Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width: 60px;">ID</th>
                        <th scope="col">Event Type</th>
                        <th scope="col">IP Address</th>
                        <th scope="col">Status</th>
                        <th scope="col">Details</th>
                        <th scope="col">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">No logs found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log->id); ?></td>
                                <td>
                                    <span class="event-type event-<?php echo esc_attr($log->event_type); ?>" 
                                          style="padding: 2px 8px; border-radius: 3px; font-size: 12px; color: white; 
                                                 background: <?php echo $this->get_event_color($log->event_type); ?>;">
                                        <?php echo esc_html(ucfirst($log->event_type)); ?>
                                    </span>
                                </td>
                                <td>
                                    <code><?php echo esc_html($log->ip_address); ?></code>
                                </td>
                                <td>
                                    <span class="status-<?php echo esc_attr($log->status); ?>" 
                                          style="padding: 2px 8px; border-radius: 3px; font-size: 12px; color: white; 
                                                 background: <?php echo $this->get_status_color($log->status); ?>;">
                                        <?php echo esc_html(ucfirst($log->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <details>
                                        <summary style="cursor: pointer;">View Details</summary>
                                        <div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 3px;">
                                            <?php if (!empty($log->details)): ?>
                                                <p><strong>Details:</strong> <?php echo esc_html($log->details); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($log->user_agent)): ?>
                                                <p><strong>User Agent:</strong> <code><?php echo esc_html($log->user_agent); ?></code></p>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                </td>
                                <td>
                                    <time datetime="<?php echo esc_attr($log->created_at); ?>">
                                        <?php echo esc_html(date('M j, Y g:i A', strtotime($log->created_at))); ?>
                                    </time>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php
            $total_pages = ceil($stats['total'] / $per_page);
            if ($total_pages > 1):
            ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        if ($page_links) {
                            echo '<span class="displaying-num">' . sprintf(_n('%s item', '%s items', $stats['total']), number_format_i18n($stats['total'])) . '</span>';
                            echo $page_links;
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .stat-box {
                min-width: 120px;
            }
            .event-type, .status-badge {
                display: inline-block;
                font-weight: bold;
            }
            details summary {
                outline: none;
            }
            details[open] summary {
                margin-bottom: 10px;
            }
        </style>
        <?php
    }

    private function get_event_color($event_type) {
        $colors = array(
            'login' => '#d63638',
            'comment' => '#f56e28',
            'email' => '#135e96',
            'ip_block' => '#8f2d00',
            'ai_detection' => '#7b2d94'
        );
        return isset($colors[$event_type]) ? $colors[$event_type] : '#666';
    }

    private function get_status_color($status) {
        $colors = array(
            'blocked' => '#d63638',
            'allowed' => '#00a32a',
            'pending' => '#f56e28',
            'warning' => '#dba617'
        );
        return isset($colors[$status]) ? $colors[$status] : '#666';
    }

    private function handle_bulk_actions() {
        // Handle any bulk actions if needed
        if (isset($_POST['action'])) {
            // Verify nonce for security
            if (!wp_verify_nonce($_POST['cleanup_nonce'], 'smart_shield_cleanup_logs')) {
                wp_die('Security check failed');
            }
            
            if (!current_user_can('manage_options')) {
                wp_die('Insufficient permissions');
            }
            
            $action = sanitize_text_field($_POST['action']);
            
            if ($action === 'cleanup_logs_by_days') {
                $days = intval($_POST['cleanup_days']);
                if ($days > 0) {
                    $deleted = $this->logger->cleanup_old_logs($days);
                    add_action('admin_notices', function() use ($days, $deleted) {
                        echo '<div class="notice notice-success"><p>' . number_format($deleted) . ' log entries older than ' . $days . ' days have been cleaned up.</p></div>';
                    });
                }
            } elseif ($action === 'cleanup_logs_by_count') {
                $deleted = $this->logger->cleanup_logs_by_count();
                add_action('admin_notices', function() use ($deleted) {
                    if ($deleted > 0) {
                        echo '<div class="notice notice-success"><p>' . number_format($deleted) . ' old log entries have been cleaned up to stay within the storage limit.</p></div>';
                    } else {
                        echo '<div class="notice notice-info"><p>No cleanup needed. Log count is within the storage limit.</p></div>';
                    }
                });
            }
        }
    }
} 