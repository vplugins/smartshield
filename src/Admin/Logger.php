<?php
namespace SmartShield\Admin;

class Logger {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'smart_shield_logs';
        
        // Create table if it doesn't exist
        $this->create_table();
    }

    /**
     * Create the logs table
     */
    private function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            details text,
            status varchar(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY ip_address (ip_address),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Log a spam event
     */
    public function log_event($event_type, $details = '', $status = 'blocked') {
        global $wpdb;
        
        $ip_address = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        
        $wpdb->insert(
            $this->table_name,
            array(
                'event_type' => sanitize_text_field($event_type),
                'ip_address' => sanitize_text_field($ip_address),
                'user_agent' => $user_agent,
                'details' => sanitize_textarea_field($details),
                'status' => sanitize_text_field($status),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        // Check and cleanup if we exceed the log limit
        $this->enforce_log_limit();
    }

    /**
     * Get recent logs
     */
    public function get_recent_logs($limit = 10) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
        
        return $results;
    }

    /**
     * Get logs with pagination
     */
    public function get_logs($page = 1, $per_page = 50, $event_type = '', $status = '') {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        $where = "WHERE 1=1";
        $params = array();
        
        if (!empty($event_type)) {
            $where .= " AND event_type = %s";
            $params[] = $event_type;
        }
        
        if (!empty($status)) {
            $where .= " AND status = %s";
            $params[] = $status;
        }
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $sql = "SELECT * FROM {$this->table_name} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        
        if (!empty($params)) {
            $results = $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            $results = $wpdb->get_results($sql);
        }
        
        return $results;
    }

    /**
     * Get log statistics
     */
    public function get_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total logs
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // Logs by type
        $stats['by_type'] = $wpdb->get_results(
            "SELECT event_type, COUNT(*) as count FROM {$this->table_name} GROUP BY event_type ORDER BY count DESC"
        );
        
        // Logs by status
        $stats['by_status'] = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->table_name} GROUP BY status ORDER BY count DESC"
        );
        
        // Recent activity (last 24 hours)
        $stats['recent_24h'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        // Top IP addresses
        $stats['top_ips'] = $wpdb->get_results(
            "SELECT ip_address, COUNT(*) as count FROM {$this->table_name} GROUP BY ip_address ORDER BY count DESC LIMIT 10"
        );
        
        return $stats;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * Enforce log limit by cleaning up old entries
     */
    private function enforce_log_limit() {
        $max_logs = get_option('ss_max_logs_count', 10000);
        
        // Skip if unlimited (-1) or invalid value
        if ($max_logs == -1 || $max_logs <= 0) {
            return;
        }
        
        global $wpdb;
        
        // Get current count
        $current_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // If we exceed the limit, delete oldest entries
        if ($current_count > $max_logs) {
            $excess_count = $current_count - $max_logs;
            
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->table_name} 
                ORDER BY created_at ASC 
                LIMIT %d",
                $excess_count
            ));
        }
    }
    
    /**
     * Cleanup logs by count (remove oldest entries to stay within limit)
     */
    public function cleanup_logs_by_count($max_count = null) {
        if ($max_count === null) {
            $max_count = get_option('ss_max_logs_count', 10000);
        }
        
        // Skip if unlimited (-1) or invalid value
        if ($max_count == -1 || $max_count <= 0) {
            return 0;
        }
        
        global $wpdb;
        
        // Get current count
        $current_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        if ($current_count > $max_count) {
            $excess_count = $current_count - $max_count;
            
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->table_name} 
                ORDER BY created_at ASC 
                LIMIT %d",
                $excess_count
            ));
            
            return $deleted;
        }
        
        return 0;
    }
    
    /**
     * Clean old logs
     */
    public function cleanup_old_logs($days = 30) {
        global $wpdb;
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        return $deleted;
    }

    /**
     * Get log count information
     */
    public function get_log_count_info() {
        global $wpdb;
        
        $current_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $max_logs = get_option('ss_max_logs_count', 10000);
        
        $info = [
            'current_count' => $current_count,
            'max_logs' => $max_logs,
            'is_unlimited' => ($max_logs == -1),
            'percentage_used' => 0,
            'remaining_space' => 0,
            'is_near_limit' => false,
            'is_over_limit' => false
        ];
        
        if ($max_logs != -1 && $max_logs > 0) {
            $info['percentage_used'] = round(($current_count / $max_logs) * 100, 1);
            $info['remaining_space'] = max(0, $max_logs - $current_count);
            $info['is_near_limit'] = $info['percentage_used'] > 75;
            $info['is_over_limit'] = $current_count > $max_logs;
        }
        
        return $info;
    }
    
    /**
     * Generate sample logs for testing (can be removed in production)
     */
    public function generate_sample_logs() {
        $sample_events = array(
            array('login', 'Failed login attempt for user: admin', 'blocked'),
            array('comment', 'Spam comment detected: "Buy cheap viagra..."', 'blocked'),
            array('email', 'Suspicious email from contact form', 'blocked'),
            array('login', 'Multiple failed login attempts', 'blocked'),
            array('comment', 'Comment saved for moderation', 'pending'),
            array('email', 'Email passed spam check', 'allowed'),
            array('ai_detection', 'AI detected spam pattern', 'blocked'),
            array('ip_block', 'IP address blocked for suspicious activity', 'blocked'),
            array('login', 'Successful login after verification', 'allowed'),
            array('comment', 'Comment approved by AI', 'allowed')
        );
        
        foreach ($sample_events as $event) {
            $this->log_event($event[0], $event[1], $event[2]);
        }
    }
} 