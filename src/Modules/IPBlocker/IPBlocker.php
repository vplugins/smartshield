<?php
namespace SmartShield\Modules\IPBlocker;

use SmartShield\Admin\Logger;

class IPBlocker {
    private $table_name;
    private $logger;
    
    // Block durations in seconds
    const DURATION_1_HOUR = 3600;
    const DURATION_6_HOURS = 21600;
    const DURATION_24_HOURS = 86400;
    const DURATION_7_DAYS = 604800;
    const DURATION_30_DAYS = 2592000;
    const DURATION_PERMANENT = -1;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'smart_shield_blocked_ips';
        $this->logger = new Logger();
        
        // Initialize hooks
        add_action('init', [$this, 'init']);
        
        // Setup cron
        add_action('smart_shield_cleanup_expired_blocks', [$this, 'cleanup_expired_blocks']);
        
        // Create table if it doesn't exist
        $this->create_table();
        
        // Schedule cron if not already scheduled
        $this->schedule_cron_job();
    }
    
    /**
     * Initialize the module
     */
    public function init() {
        // Register admin menu hooks if needed
        if (is_admin()) {
            add_action('admin_init', [$this, 'admin_init']);
        }
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Admin-specific initialization
    }
    
    /**
     * Create the blocked IPs table
     */
    private function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            blocked_at datetime DEFAULT CURRENT_TIMESTAMP,
            duration int(11) NOT NULL COMMENT 'Duration in seconds, -1 for permanent',
            expires_at datetime NULL COMMENT 'When the block expires, NULL for permanent',
            reason text,
            blocked_by varchar(50) DEFAULT 'system',
            status enum('active', 'expired', 'manually_removed') DEFAULT 'active',
            attempts_count int(11) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_active_ip (ip_address, status),
            KEY ip_address (ip_address),
            KEY status (status),
            KEY expires_at (expires_at),
            KEY blocked_at (blocked_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Block an IP address
     */
    public function block_ip($ip_address, $duration = self::DURATION_24_HOURS, $reason = '', $blocked_by = 'system') {
        global $wpdb;
        
        // Validate IP address
        if (!$this->is_valid_ip($ip_address)) {
            return false;
        }
        
        // Check if IP is already blocked
        $existing_block = $this->get_active_block($ip_address);
        
        if ($existing_block) {
            // Update existing block
            $this->update_block($existing_block->id, $duration, $reason);
            return $existing_block->id;
        }
        
        // Calculate expiry date
        $expires_at = null;
        if ($duration !== self::DURATION_PERMANENT) {
            $expires_at = date('Y-m-d H:i:s', time() + $duration);
        }
        
        // Insert new block
        $result = $wpdb->insert(
            $this->table_name,
            [
                'ip_address' => $ip_address,
                'blocked_at' => current_time('mysql'),
                'duration' => $duration,
                'expires_at' => $expires_at,
                'reason' => $reason,
                'blocked_by' => $blocked_by,
                'status' => 'active'
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );
        
        if ($result) {
            $block_id = $wpdb->insert_id;
            
            // Log the blocking event
            $duration_text = $this->get_duration_text($duration);
            $this->logger->log_event(
                'ip_block',
                "IP {$ip_address} blocked for {$duration_text}. Reason: {$reason}",
                'blocked'
            );
            
            return $block_id;
        }
        
        return false;
    }
    
    /**
     * Unblock an IP address
     */
    public function unblock_ip($ip_address, $reason = 'manually_removed') {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_name,
            [
                'status' => 'manually_removed',
                'updated_at' => current_time('mysql')
            ],
            [
                'ip_address' => $ip_address,
                'status' => 'active'
            ],
            ['%s', '%s'],
            ['%s', '%s']
        );
        
        if ($result) {
            // Log the unblocking event
            $this->logger->log_event(
                'ip_unblock',
                "IP {$ip_address} unblocked. Reason: {$reason}",
                'allowed'
            );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if an IP address is currently blocked
     */
    public function is_blocked($ip_address) {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE ip_address = %s 
            AND status = 'active' 
            AND (expires_at IS NULL OR expires_at > %s)
            ORDER BY blocked_at DESC
            LIMIT 1",
            $ip_address,
            current_time('mysql')
        ));
        
        return $result !== null;
    }
    
    /**
     * Get active block for an IP
     */
    public function get_active_block($ip_address) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE ip_address = %s 
            AND status = 'active' 
            AND (expires_at IS NULL OR expires_at > %s)
            ORDER BY blocked_at DESC
            LIMIT 1",
            $ip_address,
            current_time('mysql')
        ));
    }
    
    /**
     * Get block details for an IP
     */
    public function get_block_details($ip_address) {
        $block = $this->get_active_block($ip_address);
        
        if (!$block) {
            return null;
        }
        
        $details = [
            'id' => $block->id,
            'ip_address' => $block->ip_address,
            'blocked_at' => $block->blocked_at,
            'expires_at' => $block->expires_at,
            'duration' => $block->duration,
            'reason' => $block->reason,
            'blocked_by' => $block->blocked_by,
            'is_permanent' => $block->duration === self::DURATION_PERMANENT,
            'time_remaining' => $this->get_time_remaining($block)
        ];
        
        return $details;
    }
    
    /**
     * Get all blocked IPs
     */
    public function get_blocked_ips($status = 'active', $limit = 100, $offset = 0) {
        global $wpdb;
        
        $where_clause = $status ? "WHERE status = %s" : "";
        $params = $status ? [$status, $limit, $offset] : [$limit, $offset];
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            {$where_clause}
            ORDER BY blocked_at DESC 
            LIMIT %d OFFSET %d",
            ...$params
        ));
        
        return $results;
    }
    
    /**
     * Clean up expired blocks (called by cron)
     */
    public function cleanup_expired_blocks() {
        global $wpdb;
        
        $expired_count = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} 
            SET status = 'expired', updated_at = %s
            WHERE status = 'active' 
            AND expires_at IS NOT NULL 
            AND expires_at <= %s",
            current_time('mysql'),
            current_time('mysql')
        ));
        
        if ($expired_count > 0) {
            $this->logger->log_event(
                'ip_cleanup',
                "Cleaned up {$expired_count} expired IP blocks",
                'system'
            );
        }
        
        return $expired_count;
    }
    
    
    
    /**
     * Schedule the cleanup cron job
     */
    private function schedule_cron_job() {
        if (!wp_next_scheduled('smart_shield_cleanup_expired_blocks')) {
            wp_schedule_event(time(), 'hourly', 'smart_shield_cleanup_expired_blocks');
        }
    }
    
    /**
     * Unschedule cron job (for deactivation)
     */
    public function unschedule_cron_job() {
        wp_clear_scheduled_hook('smart_shield_cleanup_expired_blocks');
    }
    
    /**
     * Get client IP address
     */
    public function get_client_ip() {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
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
     * Validate IP address
     */
    private function is_valid_ip($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * Get human-readable duration text
     */
    private function get_duration_text($duration) {
        switch ($duration) {
            case self::DURATION_1_HOUR:
                return '1 hour';
            case self::DURATION_6_HOURS:
                return '6 hours';
            case self::DURATION_24_HOURS:
                return '24 hours';
            case self::DURATION_7_DAYS:
                return '7 days';
            case self::DURATION_30_DAYS:
                return '30 days';
            case self::DURATION_PERMANENT:
                return 'permanent';
            default:
                $hours = round($duration / 3600, 1);
                return $hours . ' hour' . ($hours != 1 ? 's' : '');
        }
    }
    
    /**
     * Get remaining time for a block
     */
    private function get_time_remaining($block) {
        if ($block->duration === self::DURATION_PERMANENT) {
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
    
    /**
     * Update an existing block
     */
    private function update_block($block_id, $duration, $reason) {
        global $wpdb;
        
        $expires_at = null;
        if ($duration !== self::DURATION_PERMANENT) {
            $expires_at = date('Y-m-d H:i:s', time() + $duration);
        }
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} 
            SET duration = %d, expires_at = %s, reason = %s, attempts_count = attempts_count + 1, updated_at = %s
            WHERE id = %d",
            $duration,
            $expires_at,
            $reason,
            current_time('mysql'),
            $block_id
        ));
    }
    
    /**
     * Get statistics about blocked IPs
     */
    public function get_statistics() {
        global $wpdb;
        
        $stats = [];
        
        // Total active blocks
        $stats['active_blocks'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'active'"
        );
        
        // Total blocks today
        $stats['blocks_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(blocked_at) = %s",
            current_time('Y-m-d')
        ));
        
        // Blocks by reason
        $stats['by_reason'] = $wpdb->get_results(
            "SELECT reason, COUNT(*) as count FROM {$this->table_name} 
            WHERE status = 'active' AND reason IS NOT NULL AND reason != ''
            GROUP BY reason ORDER BY count DESC LIMIT 10"
        );
        
        // Recent blocks
        $stats['recent_blocks'] = $wpdb->get_results(
            "SELECT ip_address, blocked_at, reason FROM {$this->table_name} 
            WHERE status = 'active' ORDER BY blocked_at DESC LIMIT 10"
        );
        
        return $stats;
    }
} 