<?php
namespace SmartShield\Modules\EmailHandler;

use SmartShield\Modules\SpamHandler\SpamHandler;
use SmartShield\Modules\IPBlocker\IPBlocker;
use SmartShield\Admin\Logger;
use Exception;

class EmailHandler {
    private $spamHandler;
    private $ipBlocker;
    private $logger;
    
    public function __construct() {
        $this->spamHandler = new SpamHandler();
        $this->ipBlocker = new IPBlocker();
        $this->logger = new Logger();
        
        // Initialize hooks
        add_action('init', [$this, 'init']);
    }
    
    /**
     * Initialize the email handler
     */
    public function init() {
        // Hook into wp_mail function
        add_filter('wp_mail', [$this, 'check_email_spam'], 10, 1);
        add_action('wp_mail', [$this, 'log_email_attempt'], 10, 1);
    }
    
    /**
     * Check if email is spam using AI
     *
     * @param array $mail_data The email data array
     * @return array The email data (potentially modified or blocked)
     */
    public function check_email_spam($mail_data) {
        // Validate mail data structure
        if (!is_array($mail_data) || empty($mail_data)) {
            return $mail_data;
        }
        
        // Skip if email protection is disabled
        if (!get_option('ss_email_enabled')) {
            return $mail_data;
        }
        
        // Get AI API key
        $api_key = get_option('ss_ai_api_key');
        if (empty($api_key)) {
            $this->logger->log_event('email', 'AI API key not configured', 'error');
            return $mail_data;
        }
        
        // Get current IP
        $ip_address = $this->get_client_ip();
        
        // Skip if IP is whitelisted
        if ($this->is_ip_whitelisted($ip_address)) {
            $this->logger->log_event('email', "IP {$ip_address} is whitelisted", 'allowed');
            return $mail_data;
        }
        
        // Skip if IP is already blocked
        if ($this->ipBlocker->is_blocked($ip_address)) {
            $this->logger->log_event('email', "IP {$ip_address} is already blocked", 'blocked');
            return $this->handle_blocked_email($mail_data);
        }
        
        try {
            // Extract email details
            $subject = $mail_data['subject'] ?? '';
            $message = $mail_data['message'] ?? '';
            $to_email = is_array($mail_data['to']) ? implode(', ', $mail_data['to']) : $mail_data['to'];
            
            // Get sender information from headers if available
            $sender_email = $this->extract_sender_email($mail_data);
            $sender_name = $this->extract_sender_name($mail_data);
            
            // Prepare context for spam detection
            $context = [
                'subject' => $subject,
                'email' => $sender_email,
                'name' => $sender_name,
                'to' => $to_email,
                'ip' => $ip_address
            ];
            
            // Check if email is spam
            $is_spam = $this->spamHandler->is_spam($message, 'email', $context);
            
            if ($is_spam) {
                // Log spam detection
                $this->logger->log_event('email', "Spam email detected from {$ip_address}: Subject: {$subject}", 'blocked');
                
                // Block IP address
                $this->block_spam_ip($ip_address, 'email_spam');
                
                // Handle spam email based on settings
                return $this->handle_spam_email($mail_data);
            } else {
                // Log legitimate email
                $this->logger->log_event('email', "Legitimate email from {$ip_address}: Subject: {$subject}", 'allowed');
            }
            
        } catch (Exception $e) {
            // Log error
            $this->logger->log_event('email', "AI email spam detection error: " . $e->getMessage(), 'error');
            
            // Allow email on error (fail-safe)
            return $mail_data;
        }
        
        return $mail_data;
    }
    
    /**
     * Handle spam email based on settings
     *
     * @param array $mail_data The email data
     * @return array Modified email data or false to block
     */
    private function handle_spam_email($mail_data) {
        // Check if spam warning is enabled
        if (get_option('ss_email_enable_spam_warning')) {
            // Just add SPAM to subject, don't modify the email content
            $current_subject = $mail_data['subject'] ?? 'No Subject';
            $mail_data['subject'] = 'SPAM ' . $current_subject;
            
            // Log warning sent
            $this->logger->log_event('email', "Spam warning added to email: " . $mail_data['subject'], 'warning');
            
            return $mail_data;
        } else {
            // Block email completely
            $subject = $mail_data['subject'] ?? 'No Subject';
            $this->logger->log_event('email', "Email blocked: " . $subject, 'blocked');
            
            // Return false to prevent email from being sent
            return false;
        }
    }
    
    /**
     * Handle blocked email (from blocked IP)
     *
     * @param array $mail_data The email data
     * @return array|false Modified email data or false to block
     */
    private function handle_blocked_email($mail_data) {
        // Always block emails from blocked IPs
        $subject = isset($mail_data['subject']) ? $mail_data['subject'] : 'Unknown Subject';
        $this->logger->log_event('email', "Email blocked from blocked IP: " . $subject, 'blocked');
        return false;
    }
    
    /**
     * Block IP address for email spam
     *
     * @param string $ip_address The IP address to block
     * @param string $reason The reason for blocking
     */
    private function block_spam_ip($ip_address, $reason) {
        // Get block duration from settings (default 24 hours)
        $duration = get_option('ss_ip_blocked_duration', 86400);
        
        // Ensure duration is a valid integer
        if (empty($duration) || !is_numeric($duration)) {
            $duration = 86400; // Default to 24 hours (86400 seconds)
        } else {
            $duration = (int) $duration;
        }
        
        // Block the IP
        $this->ipBlocker->block_ip($ip_address, $duration, $reason, 'email_handler');
        
        // Send notification if enabled
        if (get_option('ss_notification_enabled')) {
            $this->send_spam_notification($ip_address, $reason);
        }
    }
    
    /**
     * Send spam notification email
     *
     * @param string $ip_address The blocked IP address
     * @param string $reason The reason for blocking
     */
    private function send_spam_notification($ip_address, $reason) {
        $email = get_option('ss_notification_email');
        if (empty($email)) {
            return;
        }
        
        $subject = 'Smart Shield: Email Spam Detected and IP Blocked';
        $message = "Smart Shield has detected email spam activity and blocked an IP address.\n\n";
        $message .= "IP Address: {$ip_address}\n";
        $message .= "Reason: {$reason}\n";
        $message .= "Time: " . current_time('mysql') . "\n";
        $message .= "Site: " . get_site_url() . "\n\n";
        $message .= "This is an automated message from Smart Shield.";
        
        // Use direct wp_mail to avoid recursion, but temporarily disable our filter
        remove_filter('wp_mail', [$this, 'check_email_spam'], 10);
        wp_mail($email, $subject, $message);
        add_filter('wp_mail', [$this, 'check_email_spam'], 10, 1);
    }
    
    /**
     * Extract sender email from mail headers
     *
     * @param array $mail_data The mail data
     * @return string The sender email
     */
    private function extract_sender_email($mail_data) {
        // Check headers for sender information
        if (isset($mail_data['headers'])) {
            $headers = is_array($mail_data['headers']) ? $mail_data['headers'] : explode("\n", $mail_data['headers']);
            
            foreach ($headers as $header) {
                if (stripos($header, 'From:') === 0) {
                    // Extract email from "From: Name <email@domain.com>" format
                    if (preg_match('/From:.*<(.+?)>/', $header, $matches)) {
                        return $matches[1];
                    }
                    // Extract email from "From: email@domain.com" format
                    if (preg_match('/From:\s*(.+)/', $header, $matches)) {
                        return trim($matches[1]);
                    }
                }
                if (stripos($header, 'Reply-To:') === 0) {
                    // Extract email from Reply-To header
                    if (preg_match('/Reply-To:.*<(.+?)>/', $header, $matches)) {
                        return $matches[1];
                    }
                    if (preg_match('/Reply-To:\s*(.+)/', $header, $matches)) {
                        return trim($matches[1]);
                    }
                }
            }
        }
        
        return '';
    }
    
    /**
     * Extract sender name from mail headers
     *
     * @param array $mail_data The mail data
     * @return string The sender name
     */
    private function extract_sender_name($mail_data) {
        // Check headers for sender name
        if (isset($mail_data['headers'])) {
            $headers = is_array($mail_data['headers']) ? $mail_data['headers'] : explode("\n", $mail_data['headers']);
            
            foreach ($headers as $header) {
                if (stripos($header, 'From:') === 0) {
                    // Extract name from "From: Name <email@domain.com>" format
                    if (preg_match('/From:\s*(.+?)\s*</', $header, $matches)) {
                        return trim($matches[1]);
                    }
                }
            }
        }
        
        return '';
    }
    
    /**
     * Check if IP is whitelisted
     *
     * @param string $ip_address The IP address to check
     * @return bool True if whitelisted, false otherwise
     */
    private function is_ip_whitelisted($ip_address) {
        $whitelist = get_option('ss_ip_whitelist', '');
        if (empty($whitelist)) {
            return false;
        }
        
        $whitelist_ips = array_map('trim', explode(',', $whitelist));
        return in_array($ip_address, $whitelist_ips);
    }
    
    /**
     * Get client IP address
     *
     * @return string The client IP address
     */
    private function get_client_ip() {
        // Check for shared internet/proxy
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        // Check for remote address
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        // Check for remote address from share via proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        }
        // Check for remote address from share via proxy
        elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_FORWARDED_FOR'];
        }
        // Check for remote address from share via proxy
        elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            return $_SERVER['HTTP_FORWARDED'];
        }
        // Check for remote address
        elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Log email attempt
     *
     * @param array $mail_data The email data
     */
    public function log_email_attempt($mail_data) {
        $ip_address = $this->get_client_ip();
        $subject = $mail_data['subject'] ?? 'No Subject';
        
        $this->logger->log_event('email', "Email sent from {$ip_address}: {$subject}", 'info');
    }
    
    /**
     * Check if email content is spam (direct method)
     *
     * @param string $subject The email subject
     * @param string $message The email message
     * @param array $context Additional context
     * @return bool True if spam, false otherwise
     */
    public function is_email_spam($subject, $message, $context = []) {
        return $this->spamHandler->is_spam($message, 'email', array_merge($context, ['subject' => $subject]));
    }
    
    /**
     * Get email spam detection statistics
     *
     * @return array Statistics about email spam detection
     */
    public function get_statistics() {
        global $wpdb;
        
        $stats = [];
        
        // Get email spam detection stats from logs
        $logs_table = $wpdb->prefix . 'smart_shield_logs';
        
        // Total email spam blocked
        $stats['total_email_spam_blocked'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$logs_table} WHERE event_type = 'email' AND status = 'blocked'"
        );
        
        // Email spam blocked today
        $stats['email_spam_blocked_today'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$logs_table} WHERE event_type = 'email' AND status = 'blocked' AND DATE(created_at) = CURDATE()"
        );
        
        // Email spam blocked this week
        $stats['email_spam_blocked_week'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$logs_table} WHERE event_type = 'email' AND status = 'blocked' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Email warnings sent
        $stats['email_warnings_sent'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$logs_table} WHERE event_type = 'email' AND status = 'warning'"
        );
        
        return $stats;
    }
    
    /**
     * Test email spam detection
     *
     * @param string $subject Test email subject
     * @param string $message Test email message
     * @return array Test results
     */
    public function test_email_spam_detection($subject, $message) {
        try {
            $context = [
                'subject' => $subject,
                'email' => 'test@example.com',
                'name' => 'Test User'
            ];
            
            $is_spam = $this->spamHandler->is_spam($message, 'email', $context);
            
            return [
                'success' => true,
                'is_spam' => $is_spam,
                'result' => $is_spam ? 'SPAM' : 'LEGITIMATE'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
} 