<?php
namespace SmartShield\Modules\LoginHandler;

use SmartShield\Helper\IPBlockerHelper;
use SmartShield\Admin\Logger;

class LoginHandler {
    private $logger;
    
    public function __construct() {
        $this->logger = new Logger();
        
        // Only initialize if login protection is enabled
        if (get_option('ss_login_enabled', false)) {
            add_action('init', [$this, 'init']);
            $this->setup_login_hooks();
        }
    }
    
    /**
     * Initialize the module
     */
    public function init() {
        // Register admin hooks if needed
        if (is_admin()) {
            add_action('admin_init', [$this, 'admin_init']);
        }
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Register settings
        register_setting('smart_shield_login_settings', 'ss_login_enabled', [
            'type' => 'boolean',
            'default' => false
        ]);
        
        register_setting('smart_shield_login_settings', 'ss_login_max_attempts', [
            'type' => 'integer',
            'default' => 5,
            'sanitize_callback' => 'absint'
        ]);
    }
    
    /**
     * Setup WordPress login hooks
     */
    private function setup_login_hooks() {
        // Track successful logins
        add_action('wp_login', [$this, 'handle_successful_login'], 10, 2);
        
        // Track failed logins
        add_action('wp_login_failed', [$this, 'handle_failed_login'], 10, 1);
        
        // Check before authentication
        add_filter('authenticate', [$this, 'check_login_attempts'], 30, 3);
    }
    
    /**
     * Handle successful login
     */
    public function handle_successful_login($user_login, $user) {
        $ip_address = $this->get_client_ip();
        
        // Clear failed attempts for this IP
        $this->clear_failed_attempts($ip_address);
        
        // Log successful login
        $this->logger->log_event(
            'successful_login',
            "Successful login for user '{$user_login}' from IP {$ip_address}",
            'security'
        );
    }
    
    /**
     * Handle failed login
     */
    public function handle_failed_login($username) {
        $ip_address = $this->get_client_ip();
        
        // Increment failed attempts
        $attempts = $this->increment_failed_attempts($ip_address);
        $max_attempts = get_option('ss_login_max_attempts', 5);
        
        // Log failed login attempt
        $this->logger->log_event(
            'failed_login',
            "Failed login attempt for user '{$username}' from IP {$ip_address}. Total attempts: {$attempts}",
            'security'
        );
        
        // Block IP if max attempts reached
        if ($attempts >= $max_attempts) {
            $this->block_ip_for_failed_login($ip_address, $attempts);
        }
    }
    
    /**
     * Check login attempts before authentication
     */
    public function check_login_attempts($user, $username, $password) {
        // Skip if empty username or password
        if (empty($username) || empty($password)) {
            return $user;
        }
        
        $ip_address = $this->get_client_ip();
        
        // Check if IP is currently blocked
        if (IPBlockerHelper::is_blocked($ip_address)) {
            $block_details = IPBlockerHelper::get_block_details($ip_address);
            return new \WP_Error(
                'ip_blocked',
                sprintf(
                    'Your IP address has been blocked due to multiple failed login attempts. Please try again in %s.',
                    $block_details['time_remaining'] ?? 'some time'
                )
            );
        }
        
        return $user;
    }
    
    /**
     * Get failed attempts count for IP
     */
    public function get_failed_attempts_count($ip_address) {
        return get_transient('ss_login_attempts_' . md5($ip_address)) ?: 0;
    }
    
    /**
     * Increment failed attempts for IP
     */
    private function increment_failed_attempts($ip_address) {
        $key = 'ss_login_attempts_' . md5($ip_address);
        $attempts = get_transient($key) ?: 0;
        $attempts++;
        
        // Store for 1 hour
        set_transient($key, $attempts, HOUR_IN_SECONDS);
        
        return $attempts;
    }
    
    /**
     * Clear failed attempts for IP
     */
    public function clear_failed_attempts($ip_address) {
        $key = 'ss_login_attempts_' . md5($ip_address);
        delete_transient($key);
    }
    
    /**
     * Block IP for failed login attempts
     */
    private function block_ip_for_failed_login($ip_address, $attempts) {
        $reason = sprintf('Blocked after %d failed login attempts', $attempts);
        
        // Block the IP using IPBlocker with 24 hour duration
        IPBlockerHelper::block_ip($ip_address, $reason, 86400);
        
        // Clear attempts since IP is now blocked
        $this->clear_failed_attempts($ip_address);
        
        // Log the blocking event
        $this->logger->log_event(
            'ip_blocked_login',
            "IP {$ip_address} blocked for {$attempts} failed login attempts",
            'security'
        );
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
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
    public function is_enabled() {
        return get_option('ss_login_enabled', false);
    }
} 