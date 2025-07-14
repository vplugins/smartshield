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
            
            <!-- Statistics Overview -->
            <div class="dashboard-widgets-wrap">
                <div class="metabox-holder">
                    <div class="postbox-container" style="width: 100%;">
                        <div class="postbox">
                            <h2 class="hndle"><span>Statistics Overview</span></h2>
                            <div class="inside">
                                <div style="display: flex; justify-content: space-around; margin-bottom: 20px;">
                                    <div class="stat-box" style="text-align: center; padding: 10px; background: #f0f0f1; border-radius: 5px;">
                                        <h3 style="margin: 0; font-size: 24px; color: #135e96;"><?php echo number_format($stats['total']); ?></h3>
                                        <p style="margin: 5px 0 0 0;">Total Events</p>
                                    </div>
                                    <div class="stat-box" style="text-align: center; padding: 10px; background: #f0f0f1; border-radius: 5px;">
                                        <h3 style="margin: 0; font-size: 24px; color: #d63638;"><?php echo number_format($stats['recent_24h']); ?></h3>
                                        <p style="margin: 5px 0 0 0;">Last 24 Hours</p>
                                    </div>
                                    <div class="stat-box" style="text-align: center; padding: 10px; background: #f0f0f1; border-radius: 5px;">
                                        <h3 style="margin: 0; font-size: 24px; color: #00a32a;"><?php echo count($stats['top_ips']); ?></h3>
                                        <p style="margin: 5px 0 0 0;">Unique IPs</p>
                                    </div>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between;">
                                    <div style="width: 48%;">
                                        <h4>Events by Type</h4>
                                        <ul>
                                            <?php foreach ($stats['by_type'] as $type): ?>
                                                <li><strong><?php echo esc_html(ucfirst($type->event_type)); ?>:</strong> <?php echo number_format($type->count); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div style="width: 48%;">
                                        <h4>Top IP Addresses</h4>
                                        <ul>
                                            <?php foreach (array_slice($stats['top_ips'], 0, 5) as $ip): ?>
                                                <li><strong><?php echo esc_html($ip->ip_address); ?>:</strong> <?php echo number_format($ip->count); ?> events</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
        if (isset($_POST['action']) && $_POST['action'] === 'cleanup_logs') {
            $days = intval($_POST['cleanup_days']);
            if ($days > 0) {
                $this->logger->cleanup_old_logs($days);
                add_action('admin_notices', function() use ($days) {
                    echo '<div class="notice notice-success"><p>Logs older than ' . $days . ' days have been cleaned up.</p></div>';
                });
            }
        }
    }
} 