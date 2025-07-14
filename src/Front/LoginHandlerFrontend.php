<?php
namespace SmartShield\Front;

use SmartShield\Modules\LoginHandler\LoginHandler;
use SmartShield\Helper\IPBlockerHelper;

class LoginHandlerFrontend {
    private $loginHandler;
    
    public function __construct() {
        // Only initialize if login protection is enabled
        if (get_option('ss_login_enabled', false)) {
            $this->loginHandler = new LoginHandler();
            add_action('init', [$this, 'init']);
        }
    }
    
    /**
     * Initialize frontend hooks
     */
    public function init() {
        // Add custom login messages
        add_filter('login_message', [$this, 'add_login_messages']);
        
        // Add custom login errors
        add_filter('wp_login_errors', [$this, 'customize_login_errors'], 10, 2);
        
        // Add login form customizations
        add_action('login_form', [$this, 'add_login_form_elements']);
        
        // Add login head customizations
        add_action('login_head', [$this, 'add_login_head_styles']);
        
        // Add security headers
        add_action('login_init', [$this, 'add_security_headers']);
    }
    
    /**
     * Add custom login messages
     */
    public function add_login_messages($message) {
        $ip_address = $this->get_client_ip();
        
        // Check if IP is blocked
        if (IPBlockerHelper::is_blocked($ip_address)) {
            $block_details = IPBlockerHelper::get_block_details($ip_address);
            $message .= $this->get_blocked_message($block_details);
            return $message;
        }
        
        // Show remaining attempts warning
        $failed_attempts = $this->loginHandler->get_failed_attempts_count($ip_address);
        if ($failed_attempts > 0) {
            $max_attempts = get_option('ss_login_max_attempts', 5);
            $remaining = $max_attempts - $failed_attempts;
            
            if ($remaining <= 2) {
                $message .= $this->get_warning_message($remaining);
            }
        }
        
        return $message;
    }
    
    /**
     * Customize login errors
     */
    public function customize_login_errors($errors, $redirect_to) {
        $ip_address = $this->get_client_ip();
        
        // Check if IP is blocked
        if (IPBlockerHelper::is_blocked($ip_address)) {
            $block_details = IPBlockerHelper::get_block_details($ip_address);
            $errors->add('ip_blocked', $this->get_blocked_error_message($block_details));
            return $errors;
        }
        
        return $errors;
    }
    
    /**
     * Add login form elements
     */
    public function add_login_form_elements() {
        $ip_address = $this->get_client_ip();
        $failed_attempts = $this->loginHandler->get_failed_attempts_count($ip_address);
        
        if ($failed_attempts > 0) {
            $max_attempts = get_option('ss_login_max_attempts', 5);
            $remaining = $max_attempts - $failed_attempts;
            
            echo '<div class="smart-shield-login-info">';
            echo '<p class="smart-shield-attempts-remaining">';
            echo sprintf(
                'Security Notice: %d failed attempt%s. %d attempt%s remaining before IP block.',
                $failed_attempts,
                $failed_attempts !== 1 ? 's' : '',
                $remaining,
                $remaining !== 1 ? 's' : ''
            );
            echo '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Add login head styles
     */
    public function add_login_head_styles() {
        ?>
        <style>
            .smart-shield-login-info {
                margin: 16px 0;
                padding: 12px;
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 4px;
            }
            
            .smart-shield-attempts-remaining {
                color: #856404;
                font-size: 13px;
                margin: 0;
                text-align: center;
            }
            
            .smart-shield-blocked-message {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
                padding: 16px;
                margin: 16px 0;
                border-radius: 4px;
                text-align: center;
            }
            
            .smart-shield-warning-message {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
                padding: 12px;
                margin: 16px 0;
                border-radius: 4px;
                text-align: center;
            }
            
            .smart-shield-countdown {
                font-weight: bold;
                font-size: 14px;
                margin-top: 8px;
            }
            
            .smart-shield-security-notice {
                font-size: 12px;
                color: #666;
                text-align: center;
                margin-top: 16px;
                font-style: italic;
            }
        </style>
        <?php
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        // Add security headers to login page
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    
    /**
     * Get blocked message
     */
    private function get_blocked_message($block_details) {
        $time_remaining = $block_details['time_remaining'] ?? 'some time';
        $reason = $block_details['reason'] ?? 'multiple failed login attempts';
        
        $message = '<div class="smart-shield-blocked-message">';
        $message .= '<h3>🛡️ Access Temporarily Blocked</h3>';
        $message .= '<p>Your IP address has been temporarily blocked due to ' . esc_html($reason) . '.</p>';
        
        if (!$block_details['is_permanent']) {
            $message .= '<p class="smart-shield-countdown">Time remaining: ' . esc_html($time_remaining) . '</p>';
        } else {
            $message .= '<p class="smart-shield-countdown">This is a permanent block.</p>';
        }
        
        $message .= '<p>If you believe this is an error, please contact the site administrator.</p>';
        $message .= '</div>';
        
        return $message;
    }
    
    /**
     * Get warning message
     */
    private function get_warning_message($remaining) {
        $message = '<div class="smart-shield-warning-message">';
        $message .= '<p>⚠️ Security Warning: ' . $remaining . ' login attempt' . ($remaining !== 1 ? 's' : '') . ' remaining before IP block.</p>';
        $message .= '</div>';
        
        return $message;
    }
    
    /**
     * Get blocked error message
     */
    private function get_blocked_error_message($block_details) {
        $time_remaining = $block_details['time_remaining'] ?? 'some time';
        
        if ($block_details['is_permanent']) {
            return 'Your IP address has been permanently blocked from accessing this site.';
        }
        
        return sprintf(
            'Your IP address has been temporarily blocked due to multiple failed login attempts. Please try again in %s.',
            $time_remaining
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
} 