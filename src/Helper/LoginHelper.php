<?php
namespace SmartShield\Helper;

use SmartShield\Modules\LoginHandler\LoginHandler;
use SmartShield\Helper\IPBlockerHelper;

/**
 * LoginHelper Class
 * 
 * Provides easy static methods for other modules to interact with the login handler
 */
class LoginHelper {
    private static $instance = null;
    
    /**
     * Get LoginHandler instance
     */
    private static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new LoginHandler();
        }
        return self::$instance;
    }
    
    /**
     * Get failed login attempts count for an IP
     */
    public static function get_failed_attempts_count($ip_address) {
        return get_transient('ss_login_attempts_' . md5($ip_address)) ?: 0;
    }
    
    /**
     * Clear failed attempts for an IP
     */
    public static function clear_failed_attempts($ip_address) {
        $key = 'ss_login_attempts_' . md5($ip_address);
        delete_transient($key);
    }
    
    /**
     * Get current IP address
     */
    public static function get_current_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
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
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Check if login protection is enabled
     */
    public static function is_login_protection_enabled() {
        return get_option('ss_login_enabled', false);
    }
    
    /**
     * Get login protection settings
     */
    public static function get_settings() {
        return [
            'enabled' => get_option('ss_login_enabled', false),
            'max_attempts' => get_option('ss_login_max_attempts', 5)
        ];
    }
    
    /**
     * Update login protection settings
     */
    public static function update_settings($settings) {
        if (isset($settings['enabled'])) {
            update_option('ss_login_enabled', $settings['enabled']);
        }
        
        if (isset($settings['max_attempts'])) {
            update_option('ss_login_max_attempts', max(1, min(20, intval($settings['max_attempts']))));
        }
    }
    
    /**
     * Block IP for login-related violations
     */
    public static function block_ip_for_login_violation($ip_address, $reason = '', $attempts = 0) {
        if (!self::is_login_protection_enabled()) {
            return false;
        }
        
        $full_reason = $reason ?: "Blocked after {$attempts} failed login attempts";
        
        // Block for 24 hours
        return IPBlockerHelper::block_ip($ip_address, $full_reason, 86400);
    }
    
    /**
     * Check if current request should be blocked for login
     */
    public static function should_block_current_request() {
        $ip_address = self::get_current_ip();
        
        // Check if IP is blocked
        return IPBlockerHelper::is_blocked($ip_address);
    }
    
    /**
     * Get current IP login status
     */
    public static function get_current_ip_status() {
        $ip_address = self::get_current_ip();
        $failed_attempts = self::get_failed_attempts_count($ip_address);
        $max_attempts = get_option('ss_login_max_attempts', 5);
        
        return [
            'ip_address' => $ip_address,
            'failed_attempts' => $failed_attempts,
            'max_attempts' => $max_attempts,
            'remaining_attempts' => max(0, $max_attempts - $failed_attempts),
            'is_blocked' => IPBlockerHelper::is_blocked($ip_address),
            'protection_enabled' => self::is_login_protection_enabled()
        ];
    }
    
    /**
     * Check if IP is whitelisted
     */
    public static function is_ip_whitelisted($ip_address) {
        $whitelist = get_option('ss_ip_whitelist', '');
        if (empty($whitelist)) {
            return false;
        }
        
        $whitelisted_ips = array_map('trim', explode(',', $whitelist));
        return in_array($ip_address, $whitelisted_ips);
    }
    
    /**
     * Enable login protection
     */
    public static function enable_protection() {
        update_option('ss_login_enabled', true);
    }
    
    /**
     * Disable login protection
     */
    public static function disable_protection() {
        update_option('ss_login_enabled', false);
    }
} 