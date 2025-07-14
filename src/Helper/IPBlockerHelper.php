<?php
namespace SmartShield\Helper;

use SmartShield\Modules\IPBlocker\IPBlocker;

/**
 * IPBlocker Helper Class
 * 
 * Provides easy static methods for other modules to interact with the IP blocker
 */
class IPBlockerHelper {
    private static $instance = null;
    
    /**
     * Get IPBlocker instance
     */
    private static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new IPBlocker();
        }
        return self::$instance;
    }
    
    /**
     * Block an IP address with default settings
     */
    public static function block_ip($ip_address, $reason = '', $duration = IPBlocker::DURATION_24_HOURS) {
        return self::get_instance()->block_ip($ip_address, $duration, $reason, 'auto_system');
    }
    
    /**
     * Block IP for failed login attempts
     */
    public static function block_for_failed_login($ip_address, $attempts = 1) {
        $reason = "Failed login attempts: {$attempts}";
        $duration = self::get_duration_for_attempts($attempts);
        return self::get_instance()->block_ip($ip_address, $duration, $reason, 'login_protection');
    }
    
    /**
     * Block IP for spam comments
     */
    public static function block_for_spam_comment($ip_address, $comment_details = '') {
        $reason = "Spam comment detected";
        if ($comment_details) {
            $reason .= ": " . substr($comment_details, 0, 100);
        }
        return self::get_instance()->block_ip($ip_address, IPBlocker::DURATION_6_HOURS, $reason, 'comment_protection');
    }
    
    /**
     * Block IP for spam email
     */
    public static function block_for_spam_email($ip_address, $email_details = '') {
        $reason = "Spam email detected";
        if ($email_details) {
            $reason .= ": " . substr($email_details, 0, 100);
        }
        return self::get_instance()->block_ip($ip_address, IPBlocker::DURATION_6_HOURS, $reason, 'email_protection');
    }
    
    /**
     * Block IP for suspicious activity
     */
    public static function block_for_suspicious_activity($ip_address, $activity_details = '') {
        $reason = "Suspicious activity detected";
        if ($activity_details) {
            $reason .= ": " . substr($activity_details, 0, 100);
        }
        return self::get_instance()->block_ip($ip_address, IPBlocker::DURATION_24_HOURS, $reason, 'security_scanner');
    }
    
    /**
     * Block IP permanently
     */
    public static function block_permanently($ip_address, $reason = '') {
        if (!$reason) {
            $reason = "Permanently blocked for security violations";
        }
        return self::get_instance()->block_ip($ip_address, IPBlocker::DURATION_PERMANENT, $reason, 'permanent_block');
    }
    
    /**
     * Check if an IP is currently blocked
     */
    public static function is_blocked($ip_address) {
        return self::get_instance()->is_blocked($ip_address);
    }
    
    /**
     * Unblock an IP address
     */
    public static function unblock_ip($ip_address, $reason = 'System unblock') {
        return self::get_instance()->unblock_ip($ip_address, $reason);
    }
    
    /**
     * Get block details for an IP
     */
    public static function get_block_details($ip_address) {
        return self::get_instance()->get_block_details($ip_address);
    }
    
    /**
     * Get current user's IP address
     */
    public static function get_current_ip() {
        return self::get_instance()->get_client_ip();
    }
    
    /**
     * Check if current request should be blocked
     */
    public static function should_block_current_request() {
        $current_ip = self::get_current_ip();
        return self::is_blocked($current_ip);
    }
    
    /**
     * Get escalating duration based on number of attempts
     */
    private static function get_duration_for_attempts($attempts) {
        if ($attempts >= 10) {
            return IPBlocker::DURATION_7_DAYS;
        } elseif ($attempts >= 5) {
            return IPBlocker::DURATION_24_HOURS;
        } elseif ($attempts >= 3) {
            return IPBlocker::DURATION_6_HOURS;
        } else {
            return IPBlocker::DURATION_1_HOUR;
        }
    }
    
    /**
     * Auto-block based on threat level
     */
    public static function auto_block_by_threat_level($ip_address, $threat_level, $details = '') {
        switch ($threat_level) {
            case 'low':
                return self::block_ip($ip_address, "Low threat: {$details}", IPBlocker::DURATION_1_HOUR);
            case 'medium':
                return self::block_ip($ip_address, "Medium threat: {$details}", IPBlocker::DURATION_6_HOURS);
            case 'high':
                return self::block_ip($ip_address, "High threat: {$details}", IPBlocker::DURATION_24_HOURS);
            case 'critical':
                return self::block_ip($ip_address, "Critical threat: {$details}", IPBlocker::DURATION_7_DAYS);
            default:
                return self::block_ip($ip_address, "Threat detected: {$details}", IPBlocker::DURATION_24_HOURS);
        }
    }
    
    /**
     * Bulk block multiple IPs
     */
    public static function bulk_block_ips($ip_addresses, $reason = '', $duration = IPBlocker::DURATION_24_HOURS) {
        $results = [];
        foreach ($ip_addresses as $ip) {
            $results[$ip] = self::block_ip($ip, $reason, $duration);
        }
        return $results;
    }
    
    /**
     * Get statistics for dashboard
     */
    public static function get_statistics() {
        return self::get_instance()->get_statistics();
    }
    
    /**
     * Clean up expired blocks manually
     */
    public static function cleanup_expired_blocks() {
        return self::get_instance()->cleanup_expired_blocks();
    }
    
    /**
     * Check if IP is in whitelist (from settings)
     */
    public static function is_whitelisted($ip_address) {
        $whitelist = get_option('ss_ip_whitelist', '');
        if (empty($whitelist)) {
            return false;
        }
        
        $whitelisted_ips = array_map('trim', explode(',', $whitelist));
        return in_array($ip_address, $whitelisted_ips);
    }
    
    /**
     * Block IP if not whitelisted
     */
    public static function block_if_not_whitelisted($ip_address, $reason = '', $duration = IPBlocker::DURATION_24_HOURS) {
        if (self::is_whitelisted($ip_address)) {
            return false; // Don't block whitelisted IPs
        }
        
        return self::block_ip($ip_address, $reason, $duration);
    }
    
    /**
     * Get blocked IPs for external integrations
     */
    public static function get_blocked_ips($status = 'active', $limit = 100) {
        return self::get_instance()->get_blocked_ips($status, $limit, 0);
    }
    
    /**
     * Emergency unblock all IPs (for emergencies)
     */
    public static function emergency_unblock_all($reason = 'Emergency unblock') {
        global $wpdb;
        $ipBlocker = self::get_instance();
        
        $result = $wpdb->update(
            $wpdb->prefix . 'smart_shield_blocked_ips',
            [
                'status' => 'manually_removed',
                'updated_at' => current_time('mysql')
            ],
            ['status' => 'active'],
            ['%s', '%s'],
            ['%s']
        );
        
        if ($result) {
            // Log the emergency unblock
            $logger = new \SmartShield\Admin\Logger();
            $logger->log_event(
                'emergency_unblock',
                "Emergency unblock performed: {$result} IPs unblocked. Reason: {$reason}",
                'system'
            );
        }
        
        return $result;
    }
    
    /**
     * Get block history for an IP
     */
    public static function get_ip_history($ip_address) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}smart_shield_blocked_ips 
            WHERE ip_address = %s 
            ORDER BY blocked_at DESC 
            LIMIT 10",
            $ip_address
        ));
    }
    
    /**
     * Check if IP has been blocked before
     */
    public static function is_repeat_offender($ip_address) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}smart_shield_blocked_ips 
            WHERE ip_address = %s",
            $ip_address
        ));
        
        return $count > 1;
    }
    
    /**
     * Get repeat offender count
     */
    public static function get_offender_count($ip_address) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}smart_shield_blocked_ips 
            WHERE ip_address = %s",
            $ip_address
        ));
    }
} 