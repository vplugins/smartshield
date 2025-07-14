<?php
namespace SmartShield\Modules\SpamHandler;

use SmartShield\Modules\Prompter\Prompter;
use SmartShield\Modules\IPBlocker\IPBlocker;
use SmartShield\Admin\Logger;
use AIEngine\AIEngine;
use Exception;

class SpamHandler {
    private $prompter;
    private $ipBlocker;
    private $logger;
    
    public function __construct() {
        $this->prompter = new Prompter();
        $this->ipBlocker = new IPBlocker();
        $this->logger = new Logger();
        
        // Initialize hooks
        add_action('init', [$this, 'init']);
    }
    
    /**
     * Initialize the module
     */
    public function init() {
        // Only initialize if comment spam protection is enabled
        if (get_option('ss_comment_enabled')) {
            // Hook into WordPress comment processing
            add_filter('preprocess_comment', [$this, 'check_comment_spam'], 10, 1);
            add_filter('comment_post', [$this, 'log_comment_result'], 10, 2);
        }
    }
    
    /**
     * Check if a comment is spam using AI
     *
     * @param array $commentdata The comment data
     * @return array The comment data (potentially modified)
     */
    public function check_comment_spam($commentdata) {
        // Skip if spam protection is disabled
        if (!get_option('ss_comment_enabled')) {
            return $commentdata;
        }
        
        // Get AI API key
        $api_key = get_option('ss_ai_api_key');
        if (empty($api_key)) {
            $this->logger->log_event('comment', 'AI API key not configured', 'error');
            return $commentdata;
        }
        
        // Get current IP
        $ip_address = $this->get_client_ip();
        
        // Skip if IP is whitelisted
        if ($this->is_ip_whitelisted($ip_address)) {
            $this->logger->log_event('comment', "IP {$ip_address} is whitelisted", 'allowed');
            return $commentdata;
        }
        
        // Skip if IP is already blocked
        if ($this->ipBlocker->is_blocked($ip_address)) {
            $this->logger->log_event('comment', "IP {$ip_address} is already blocked", 'blocked');
            wp_die('Your IP address is currently blocked due to spam activity.');
        }
        
        try {
            // Get post title for context
            $post_title = '';
            if (isset($commentdata['comment_post_ID'])) {
                $post = get_post($commentdata['comment_post_ID']);
                $post_title = $post ? $post->post_title : '';
            }
            
            // Generate AI prompt
            $prompt = $this->prompter->generate_comment_spam_prompt(
                $commentdata['comment_content'],
                $commentdata['comment_author'] ?? '',
                $commentdata['comment_author_email'] ?? '',
                $commentdata['comment_author_url'] ?? '',
                $post_title
            );

            // Initialize AI Engine
            $ai_client = new AIEngine($api_key);
            
            // Get AI response
            $response_data = $ai_client->generateContent($prompt);
            
            // Handle AI response
            $is_spam = $this->parse_ai_response($response_data);
            
            if ($is_spam) {
                // Log spam detection
                $this->logger->log_event('comment', "Spam comment detected from {$ip_address}: " . substr($commentdata['comment_content'], 0, 100), 'blocked');
                
                // Block IP address
                $this->block_spam_ip($ip_address, 'comment_spam');
                
                // Handle spam comment based on settings
                $this->handle_spam_comment($commentdata);
            } else {
                // Log legitimate comment
                $this->logger->log_event('comment', "Legitimate comment from {$ip_address}", 'allowed');
            }
            
        } catch (Exception $e) {
            // Log error
            $this->logger->log_event('comment', "AI spam detection error: " . $e->getMessage(), 'error');
            
            // Allow comment on error (fail-safe)
            return $commentdata;
        }
        
        return $commentdata;
    }
    
    /**
     * Block IP address for spam
     *
     * @param string $ip_address The IP address to block
     * @param string $reason The reason for blocking
     */
    private function block_spam_ip($ip_address, $reason) {
        // Get block duration from settings (default 24 hours)
        $duration = get_option('ss_ip_blocked_duration', 86400);
        
        // Block the IP
        $this->ipBlocker->block_ip($ip_address, $duration, $reason, 'spam_handler');
        
        // Send notification if enabled
        if (get_option('ss_notification_enabled')) {
            $this->send_spam_notification($ip_address, $reason);
        }
    }
    
    /**
     * Handle spam comment based on settings
     *
     * @param array $commentdata The comment data
     */
    private function handle_spam_comment($commentdata) {
        // Check if we should save for review
        if (get_option('ss_comment_span_save_for_review')) {
            // Mark as spam but allow it to be saved for review
            $commentdata['comment_approved'] = 'spam';
        } else {
            // Block the comment completely
            wp_die('Your comment has been identified as spam and blocked.');
        }
    }
    
    /**
     * Parse AI response to determine if content is spam
     *
     * @param mixed $response_data The AI response
     * @return bool True if spam, false otherwise
     */
    private function parse_ai_response($response_data) {
        // Handle different response formats
        if (is_array($response_data)) {
            if (isset($response_data['error'])) {
                throw new Exception('AI API Error: ' . $response_data['error']);
            }
            
            // Extract text from response
            $response_text = $response_data['content'] ?? $response_data['text'] ?? '';
        } else {
            $response_text = (string) $response_data;
        }
        
        // Clean and normalize response
        $response_text = trim(strtoupper($response_text));
        
        // Check for spam indicators
        return strpos($response_text, 'SPAM') !== false;
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
        
        $subject = 'Smart Shield: Spam Detected and IP Blocked';
        $message = "Smart Shield has detected spam activity and blocked an IP address.\n\n";
        $message .= "IP Address: {$ip_address}\n";
        $message .= "Reason: {$reason}\n";
        $message .= "Time: " . current_time('mysql') . "\n";
        $message .= "Site: " . get_site_url() . "\n\n";
        $message .= "This is an automated message from Smart Shield.";
        
        wp_mail($email, $subject, $message);
    }
    
    /**
     * Log comment result
     *
     * @param int $comment_id The comment ID
     * @param string $approved The approval status
     */
    public function log_comment_result($comment_id, $approved) {
        $comment = get_comment($comment_id);
        if ($comment) {
            $ip_address = $comment->comment_author_IP;
            $status = $approved === 'spam' ? 'spam' : 'approved';
            
            $this->logger->log_event('comment', "Comment {$comment_id} from {$ip_address} status: {$status}", $status);
        }
    }
    
    /**
     * Check if content is spam using AI (generic method)
     *
     * @param string $content The content to check
     * @param string $type The type of content
     * @param array $context Additional context
     * @return bool True if spam, false otherwise
     */
    public function is_spam($content, $type = 'comment', $context = []) {
        // Get AI API key
        $api_key = get_option('ss_ai_api_key');
        if (empty($api_key)) {
            return false;
        }
        
        try {
            // Generate appropriate prompt based on type
            switch ($type) {
                case 'email':
                    $prompt = $this->prompter->generate_email_spam_prompt(
                        $context['subject'] ?? '',
                        $content,
                        $context['email'] ?? '',
                        $context['name'] ?? ''
                    );
                    break;
                    
                case 'contact_form':
                    $prompt = $this->prompter->generate_contact_form_spam_prompt(
                        $content,
                        $context['name'] ?? '',
                        $context['email'] ?? '',
                        $context['subject'] ?? ''
                    );
                    break;
                    
                case 'comment':
                default:
                    $prompt = $this->prompter->generate_comment_spam_prompt(
                        $content,
                        $context['author'] ?? '',
                        $context['email'] ?? '',
                        $context['url'] ?? '',
                        $context['post_title'] ?? ''
                    );
                    break;
            }
            
            // Initialize AI Engine
            $ai_client = new AIEngine($api_key);
            
            // Get AI response
            $response_data = $ai_client->generateContent($prompt);
            
            // Parse response
            return $this->parse_ai_response($response_data);
            
        } catch (Exception $e) {
            // Log error and return false (fail-safe)
            $this->logger->log_event('spam_check', "AI spam detection error: " . $e->getMessage(), 'error');
            return false;
        }
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
     * Get spam detection statistics
     *
     * @return array Statistics about spam detection
     */
    public function get_statistics() {
        global $wpdb;
        
        $stats = [];
        
        // Get spam detection stats from logs
        $logs_table = $wpdb->prefix . 'smart_shield_logs';
        
        // Total spam blocked
        $stats['total_spam_blocked'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$logs_table} WHERE event_type = 'comment' AND status = 'blocked'"
        );
        
        // Spam blocked today
        $stats['spam_blocked_today'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$logs_table} WHERE event_type = 'comment' AND status = 'blocked' AND DATE(created_at) = CURDATE()"
        );
        
        // Spam blocked this week
        $stats['spam_blocked_week'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$logs_table} WHERE event_type = 'comment' AND status = 'blocked' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Top spam IPs
        $stats['top_spam_ips'] = $wpdb->get_results(
            "SELECT ip_address, COUNT(*) as count FROM {$logs_table} WHERE event_type = 'comment' AND status = 'blocked' GROUP BY ip_address ORDER BY count DESC LIMIT 10"
        );
        
        return $stats;
    }
    
    /**
     * Test AI connection
     *
     * @return array Test results
     */
    public function test_ai_connection() {
        $api_key = get_option('ss_ai_api_key');
        if (empty($api_key)) {
            return [
                'success' => false,
                'error' => 'AI API key not configured'
            ];
        }
        
        try {
            $ai_client = new AIEngine($api_key);
            
            // Test with a simple prompt
            $test_prompt = "Is this spam? 'This is a test message.'";
            $response = $ai_client->generateContent($test_prompt);
            
            return [
                'success' => true,
                'response' => $response,
                'provider' => $ai_client->getProviderName()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
} 