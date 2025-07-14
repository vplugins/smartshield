<?php
namespace SmartShield\Admin;

class SettingsPage {
    private $loginSettings;
    private $commentSettings;
    private $emailSettings;
    private $otherSettings;
    private $logsPage;
    private $ipBlockerPage;
    private $logger;

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'wp_ajax_smart_shield_get_stats', [ $this, 'ajax_get_stats' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        
        // Initialize the sub-settings classes
        $this->loginSettings = new LoginSettings();
        $this->commentSettings = new CommentSettings();
        $this->emailSettings = new EmailSettings();
        $this->otherSettings = new OtherSettings();
        $this->logsPage = new LogsPage();
        $this->ipBlockerPage = new IPBlockerPage();
        $this->logger = new Logger();
    }

    public function add_settings_page() {
        // Main menu
        add_menu_page(
            'Smart Shield',
            'Smart Shield',
            'manage_options',
            'smart-shield',
            [ $this, 'create_dashboard_page' ],
            'dashicons-shield',
            90
        );

        // Dashboard submenu (same slug as main menu)
        add_submenu_page(
            'smart-shield',
            'Smart Shield Dashboard',
            'Dashboard',
            'manage_options',
            'smart-shield',
            [ $this, 'create_dashboard_page' ]
        );

        // Login Settings submenu
        add_submenu_page(
            'smart-shield',
            'Login Settings',
            'Login Settings',
            'manage_options',
            'smart-shield-login',
            [ $this->loginSettings, 'create_page' ]
        );

        // Comment Settings submenu
        add_submenu_page(
            'smart-shield',
            'Comment Settings',
            'Comment Settings',
            'manage_options',
            'smart-shield-comment',
            [ $this->commentSettings, 'create_page' ]
        );

        // Email Settings submenu
        add_submenu_page(
            'smart-shield',
            'Email Settings',
            'Email Settings',
            'manage_options',
            'smart-shield-email',
            [ $this->emailSettings, 'create_page' ]
        );

        // Other Settings submenu
        add_submenu_page(
            'smart-shield',
            'Other Settings',
            'Other Settings',
            'manage_options',
            'smart-shield-other',
            [ $this->otherSettings, 'create_page' ]
        );

        // Logs submenu
        add_submenu_page(
            'smart-shield',
            'Logs',
            'Logs',
            'manage_options',
            'smart-shield-logs',
            [ $this->logsPage, 'create_page' ]
        );

        // IP Blocker submenu
        add_submenu_page(
            'smart-shield',
            'IP Blocker',
            'IP Blocker',
            'manage_options',
            'smart-shield-ip-blocker',
            [ $this->ipBlockerPage, 'create_page' ]
        );
    }

    public function create_dashboard_page() {
        $stats = $this->logger->get_stats();
        $recent_logs = $this->logger->get_recent_logs(4);
        ?>
        <div class="wrap smart-shield-dashboard">
            <h1>Smart Shield Dashboard</h1>
            <p>Welcome to Smart Shield - Your comprehensive spam protection solution.</p>

            <!-- Statistics Cards -->
            <div class="dashboard-widgets-wrap">
                <div class="metabox-holder">
                    <div class="postbox-container" style="width: 100%;">
                        <div id="stats-overview" class="postbox">
                            <div class="smart-shield-postbox-header">
                                <h2 class="hndle"><span>Statistics Overview</span></h2>
                                <button type="button" class="button smart-shield-refresh-btn" data-refresh="stats">
                                    <span class="dashicons dashicons-update"></span> Refresh
                                </button>
                            </div>
                            <div class="inside">
                                <div class="smart-shield-stats-grid">
                                    <div class="smart-shield-stat-card total-events">
                                        <h3 id="total-events"><?php echo number_format($stats['total']); ?></h3>
                                        <p>Total Events</p>
                                    </div>
                                    <div class="smart-shield-stat-card recent-24h">
                                        <h3 id="recent-24h"><?php echo number_format($stats['recent_24h']); ?></h3>
                                        <p>Last 24 Hours</p>
                                    </div>
                                    <div class="smart-shield-stat-card unique-ips">
                                        <h3 id="unique-ips"><?php echo count($stats['top_ips']); ?></h3>
                                        <p>Unique IPs</p>
                                    </div>
                                    <div class="smart-shield-stat-card blocked-today">
                                        <h3 id="blocked-today">
                                            <?php 
                                            global $wpdb;
                                            $blocked_today = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}smart_shield_logs WHERE status = 'blocked' AND DATE(created_at) = CURDATE()");
                                            echo number_format($blocked_today);
                                            ?>
                                        </h3>
                                        <p>Blocked Today</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="dashboard-widgets-wrap">
                <div class="metabox-holder">
                    <div class="postbox-container">
                        <div class="postbox">
                            <div class="smart-shield-postbox-header">
                                <h2 class="hndle"><span>Quick Settings</span></h2>
                            </div>
                            <div class="inside">
                                <div class="smart-shield-settings-grid">
                                    <a href="<?php echo admin_url('admin.php?page=smart-shield-login'); ?>" class="smart-shield-setting-card login">
                                        <h4>🔐 Login Settings</h4>
                                        <p>Configure login spam protection</p>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=smart-shield-comment'); ?>" class="smart-shield-setting-card comment">
                                        <h4>💬 Comment Settings</h4>
                                        <p>Configure comment spam protection</p>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=smart-shield-email'); ?>" class="smart-shield-setting-card email">
                                        <h4>📧 Email Settings</h4>
                                        <p>Configure email spam protection</p>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=smart-shield-other'); ?>" class="smart-shield-setting-card other">
                                        <h4>⚙️ Other Settings</h4>
                                        <p>IP blocking, notifications, AI API</p>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="postbox-container">
                        <div class="postbox">
                            <div class="smart-shield-postbox-header">
                                <h2 class="hndle"><span>Protection Status</span></h2>
                            </div>
                            <div class="inside" id="protection-status">
                                <div class="smart-shield-status-grid">
                                    <div class="smart-shield-status-item">
                                        <span><strong>🔐 Login Protection</strong></span>
                                        <span class="smart-shield-status-badge <?php echo get_option('ss_login_enabled') ? 'active' : 'inactive'; ?>">
                                            <span class="smart-shield-status-indicator <?php echo get_option('ss_login_enabled') ? 'active' : 'inactive'; ?>"></span>
                                            <?php echo get_option('ss_login_enabled') ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                    <div class="smart-shield-status-item">
                                        <span><strong>💬 Comment Protection</strong></span>
                                        <span class="smart-shield-status-badge <?php echo get_option('ss_comment_enabled') ? 'active' : 'inactive'; ?>">
                                            <span class="smart-shield-status-indicator <?php echo get_option('ss_comment_enabled') ? 'active' : 'inactive'; ?>"></span>
                                            <?php echo get_option('ss_comment_enabled') ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                    <div class="smart-shield-status-item">
                                        <span><strong>📧 Email Protection</strong></span>
                                        <span class="smart-shield-status-badge <?php echo get_option('ss_email_enabled') ? 'active' : 'inactive'; ?>">
                                            <span class="smart-shield-status-indicator <?php echo get_option('ss_email_enabled') ? 'active' : 'inactive'; ?>"></span>
                                            <?php echo get_option('ss_email_enabled') ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                    <div class="smart-shield-status-item">
                                        <span><strong>🤖 AI Protection</strong></span>
                                        <span class="smart-shield-status-badge <?php echo get_option('ss_ai_api_key') ? 'configured' : 'not-configured'; ?>">
                                            <span class="smart-shield-status-indicator <?php echo get_option('ss_ai_api_key') ? 'configured' : 'not-configured'; ?>"></span>
                                            <?php echo get_option('ss_ai_api_key') ? 'Configured' : 'Not Configured'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="postbox-container">
                        <div class="postbox">
                            <div class="smart-shield-postbox-header">
                                <h2 class="hndle"><span>Recent Activity</span></h2>
                                <a href="<?php echo admin_url('admin.php?page=smart-shield-logs'); ?>" class="button button-secondary">View All Logs</a>
                            </div>
                            <div class="inside" id="recent-logs">
                                <?php if (empty($recent_logs)): ?>
                                    <div class="smart-shield-no-activity">
                                        <span class="dashicons dashicons-info"></span>
                                        <p>No recent activity</p>
                                    </div>
                                <?php else: ?>
                                    <div class="smart-shield-recent-logs-list">
                                        <?php foreach ($recent_logs as $log): ?>
                                            <div class="smart-shield-log-item event-<?php echo esc_attr($log->event_type); ?>">
                                                <div class="smart-shield-log-content">
                                                    <div class="smart-shield-log-left">
                                                        <span class="smart-shield-event-type">
                                                            <?php echo esc_html(ucfirst($log->event_type)); ?>
                                                        </span>
                                                        <br>
                                                        <small class="smart-shield-log-ip">
                                                            <?php echo esc_html($log->ip_address); ?>
                                                        </small>
                                                    </div>
                                                    <div class="smart-shield-log-right">
                                                        <span class="smart-shield-log-status <?php echo esc_attr($log->status); ?>">
                                                            <?php echo esc_html(ucfirst($log->status)); ?>
                                                        </span>
                                                        <br>
                                                        <small class="smart-shield-log-time">
                                                            <?php echo esc_html(date('M j, g:i A', strtotime($log->created_at))); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'smart-shield') === false) {
            return;
        }

        wp_enqueue_style('smart-shield-admin', plugin_dir_url(__FILE__) . '../assets/admin.css', array(), '1.0.0');
        wp_enqueue_script('smart-shield-admin', plugin_dir_url(__FILE__) . '../assets/admin.js', array('jquery'), '1.0.0', true);
        wp_localize_script('smart-shield-admin', 'smart_shield_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smart_shield_ajax_nonce'),
        ));
    }

    public function ajax_get_stats() {
        check_ajax_referer('smart_shield_ajax_nonce', 'nonce');
        
        $type = sanitize_text_field($_POST['type']);
        $response = array();
        
        switch ($type) {
            case 'stats':
                $stats = $this->logger->get_stats();
                global $wpdb;
                $blocked_today = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}smart_shield_logs WHERE status = 'blocked' AND DATE(created_at) = CURDATE()");
                
                $response = array(
                    'total_events' => number_format($stats['total']),
                    'recent_24h' => number_format($stats['recent_24h']),
                    'unique_ips' => count($stats['top_ips']),
                    'blocked_today' => number_format($blocked_today)
                );
                break;
                
            case 'status':
                $response = array(
                    'login_enabled' => get_option('ss_login_enabled'),
                    'comment_enabled' => get_option('ss_comment_enabled'),
                    'email_enabled' => get_option('ss_email_enabled'),
                    'ai_configured' => !empty(get_option('ss_ai_api_key'))
                );
                break;
                
            case 'logs':
                $recent_logs = $this->logger->get_recent_logs(5);
                $logs_html = '';
                
                if (empty($recent_logs)) {
                    $logs_html = '<div class="smart-shield-no-activity">';
                    $logs_html .= '<span class="dashicons dashicons-info"></span>';
                    $logs_html .= '<p>No recent activity</p>';
                    $logs_html .= '</div>';
                } else {
                    $logs_html = '<div class="smart-shield-recent-logs-list">';
                    foreach ($recent_logs as $log) {
                        $logs_html .= '<div class="smart-shield-log-item event-' . esc_attr($log->event_type) . '">';
                        $logs_html .= '<div class="smart-shield-log-content">';
                        $logs_html .= '<div class="smart-shield-log-left">';
                        $logs_html .= '<span class="smart-shield-event-type">' . esc_html(ucfirst($log->event_type)) . '</span><br>';
                        $logs_html .= '<small class="smart-shield-log-ip">' . esc_html($log->ip_address) . '</small>';
                        $logs_html .= '</div>';
                        $logs_html .= '<div class="smart-shield-log-right">';
                        $logs_html .= '<span class="smart-shield-log-status ' . esc_attr($log->status) . '">' . esc_html(ucfirst($log->status)) . '</span><br>';
                        $logs_html .= '<small class="smart-shield-log-time">' . esc_html(date('M j, g:i A', strtotime($log->created_at))) . '</small>';
                        $logs_html .= '</div>';
                        $logs_html .= '</div>';
                        $logs_html .= '</div>';
                    }
                    $logs_html .= '</div>';
                    $logs_html .= '<div class="smart-shield-view-all-logs">';
                    $logs_html .= '<a href="' . admin_url('admin.php?page=smart-shield-logs') . '" class="button button-secondary">View All Logs</a>';
                    $logs_html .= '</div>';
                }
                
                $response = array('html' => $logs_html);
                break;
        }
        
        wp_send_json_success($response);
    }
}