<?php
namespace SmartShield\Front;

use SmartShield\Modules\IPBlocker\IPBlocker;
use SmartShield\Admin\Logger;

class IPBlockerFrontend {
    private $ipBlocker;
    private $logger;
    
    public function __construct() {
        $this->ipBlocker = new IPBlocker();
        $this->logger = new Logger();
        
        // Initialize frontend hooks
        add_action('wp_loaded', [$this, 'check_blocked_ip']);
    }
    
    /**
     * Check if current user's IP is blocked
     */
    public function check_blocked_ip() {
        // Skip for admin users and AJAX requests
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        
        $current_ip = $this->get_client_ip();
        
        if ($this->ipBlocker->is_blocked($current_ip)) {
            $this->handle_blocked_ip($current_ip);
        }
    }
    
    /**
     * Handle blocked IP access
     */
    private function handle_blocked_ip($ip_address) {
        $block_details = $this->ipBlocker->get_block_details($ip_address);
        
        // Log the blocked access attempt
        $this->logger->log_event(
            'blocked_access',
            "Blocked IP {$ip_address} attempted to access site. Reason: {$block_details['reason']}",
            'blocked'
        );
        
        // Set proper HTTP status
        status_header(403);
        
        // Display block message
        $this->display_blocked_message($block_details);
        exit;
    }
    
    /**
     * Display blocked IP message
     */
    private function display_blocked_message($block_details) {
        $time_remaining = $block_details['time_remaining'];
        $reason = $block_details['reason'] ?: 'Security policy violation';
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Access Blocked</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f1f1f1; margin: 0; padding: 40px; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .icon { font-size: 48px; text-align: center; margin-bottom: 20px; }
                h1 { color: #d63638; text-align: center; margin-bottom: 20px; }
                .message { color: #444; line-height: 1.6; margin-bottom: 20px; }
                .details { background: #f8f9fa; padding: 20px; border-radius: 4px; margin: 20px 0; }
                .details strong { color: #1d2327; }
                .footer { text-align: center; color: #666; font-size: 14px; margin-top: 30px; }
                .countdown { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 4px; margin: 15px 0; text-align: center; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="icon">🛡️</div>
                <h1>Access Blocked</h1>
                <div class="message">
                    <p>Your IP address has been temporarily blocked from accessing this website.</p>
                    <div class="details">
                        <p><strong>IP Address:</strong> <?php echo esc_html($block_details['ip_address']); ?></p>
                        <p><strong>Reason:</strong> <?php echo esc_html($reason); ?></p>
                        <?php if (!$block_details['is_permanent'] && $time_remaining): ?>
                            <p><strong>Time Remaining:</strong> <?php echo esc_html($time_remaining); ?></p>
                            <div class="countdown">
                                This block will be automatically lifted in <?php echo esc_html($time_remaining); ?>
                            </div>
                        <?php elseif ($block_details['is_permanent']): ?>
                            <p><strong>Duration:</strong> Permanent</p>
                            <div class="countdown" style="background: #f8d7da; border-color: #f5c6cb; color: #721c24;">
                                This is a permanent block
                            </div>
                        <?php endif; ?>
                        <p><strong>Blocked At:</strong> <?php echo esc_html(date('M j, Y g:i A', strtotime($block_details['blocked_at']))); ?></p>
                    </div>
                    <p>If you believe this is an error, please contact the website administrator.</p>
                </div>
                <div class="footer">
                    <p>Protected by Smart Shield Security</p>
                </div>
            </div>
            
            <?php if (!$block_details['is_permanent'] && $time_remaining): ?>
            <script>
                // Auto-refresh the page when the block expires
                const refreshTime = <?php echo (strtotime($block_details['expires_at']) - time()) * 1000; ?>;
                if (refreshTime > 0) {
                    setTimeout(() => {
                        window.location.reload();
                    }, refreshTime + 1000); // Add 1 second buffer
                }
            </script>
            <?php endif; ?>
        </body>
        </html>
        <?php
    }
    
    /**
     * Get the client's IP address
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