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
        $reason = $this->get_human_readable_reason($block_details['reason']);
        $expires_timestamp = !$block_details['is_permanent'] ? strtotime($block_details['expires_at']) : 0;
        $current_timestamp = time();
        $remaining_seconds = $expires_timestamp - $current_timestamp;
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <title>Access Temporarily Restricted</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .container {
                    max-width: 500px;
                    width: 100%;
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                    overflow: hidden;
                    animation: slideIn 0.5s ease-out;
                }
                @keyframes slideIn {
                    from { opacity: 0; transform: translateY(30px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .header {
                    background: linear-gradient(135deg, #ff6b6b, #ee5a24);
                    color: white;
                    text-align: center;
                    padding: 40px 30px;
                }
                .header .icon {
                    font-size: 64px;
                    margin-bottom: 20px;
                    display: block;
                }
                .header h1 {
                    font-size: 28px;
                    font-weight: 600;
                    margin-bottom: 10px;
                }
                .header p {
                    font-size: 16px;
                    opacity: 0.9;
                    line-height: 1.4;
                }
                .content {
                    padding: 40px 30px;
                }
                .info-grid {
                    display: grid;
                    gap: 20px;
                    margin-bottom: 30px;
                }
                .info-item {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 10px;
                    border-left: 4px solid #667eea;
                }
                .info-item .label {
                    font-size: 14px;
                    color: #6c757d;
                    font-weight: 500;
                    margin-bottom: 8px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .info-item .value {
                    font-size: 16px;
                    color: #2d3748;
                    font-weight: 600;
                }
                .countdown-section {
                    text-align: center;
                    margin: 30px 0;
                }
                .countdown-timer {
                    background: linear-gradient(135deg, #4CAF50, #45a049);
                    color: white;
                    padding: 20px;
                    border-radius: 12px;
                    font-size: 24px;
                    font-weight: 700;
                    margin: 20px 0;
                    box-shadow: 0 8px 16px rgba(76, 175, 80, 0.3);
                }
                .permanent-block {
                    background: linear-gradient(135deg, #f44336, #d32f2f);
                    color: white;
                    padding: 20px;
                    border-radius: 12px;
                    font-size: 18px;
                    font-weight: 600;
                    text-align: center;
                    margin: 20px 0;
                    box-shadow: 0 8px 16px rgba(244, 67, 54, 0.3);
                }
                .time-units {
                    display: flex;
                    justify-content: center;
                    gap: 20px;
                    margin-top: 15px;
                }
                .time-unit {
                    text-align: center;
                    background: rgba(255, 255, 255, 0.2);
                    padding: 15px;
                    border-radius: 8px;
                    min-width: 80px;
                }
                .time-unit .number {
                    font-size: 28px;
                    font-weight: 700;
                    display: block;
                }
                .time-unit .text {
                    font-size: 12px;
                    opacity: 0.9;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .contact-info {
                    background: #e3f2fd;
                    border: 1px solid #bbdefb;
                    border-radius: 10px;
                    padding: 20px;
                    text-align: center;
                    margin-top: 30px;
                }
                .contact-info p {
                    color: #1565c0;
                    font-size: 14px;
                    line-height: 1.6;
                }
                .footer {
                    text-align: center;
                    color: #6c757d;
                    font-size: 13px;
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #e9ecef;
                }
                .pulse {
                    animation: pulse 2s infinite;
                }
                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                    100% { transform: scale(1); }
                }
                @media (max-width: 600px) {
                    .container { margin: 10px; }
                    .header { padding: 30px 20px; }
                    .content { padding: 30px 20px; }
                    .time-units { flex-wrap: wrap; gap: 10px; }
                    .time-unit { min-width: 60px; padding: 10px; }
                    .time-unit .number { font-size: 20px; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <span class="icon">🛡️</span>
                    <h1>Access Temporarily Restricted</h1>
                    <p>Your connection has been temporarily blocked by our security system</p>
                </div>
                
                <div class="content">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="label">IP Address</div>
                            <div class="value"><?php echo esc_html($block_details['ip_address']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Reason</div>
                            <div class="value"><?php echo esc_html($reason); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Blocked At</div>
                            <div class="value"><?php echo esc_html(date('F j, Y \a\t g:i A', strtotime($block_details['blocked_at']))); ?></div>
                        </div>
                    </div>
                    
                                         <?php if (!$block_details['is_permanent'] && $remaining_seconds > 0): ?>
                         <div class="countdown-section">
                             <div class="countdown-timer pulse">
                                 <div style="font-size: 18px; margin-bottom: 20px; font-weight: 600;">Access will be restored in:</div>
                                 <div class="time-units">
                                     <div class="time-unit">
                                         <span class="number" id="hours">00</span>
                                         <span class="text">Hours</span>
                                     </div>
                                     <div class="time-unit">
                                         <span class="number" id="minutes">00</span>
                                         <span class="text">Minutes</span>
                                     </div>
                                     <div class="time-unit">
                                         <span class="number" id="seconds">00</span>
                                         <span class="text">Seconds</span>
                                     </div>
                                 </div>
                             </div>
                         </div>
                    <?php elseif ($block_details['is_permanent']): ?>
                        <div class="permanent-block">
                            <strong>⚠️ Permanent Block</strong><br>
                            This restriction is permanent and will not be automatically lifted
                        </div>
                    <?php endif; ?>
                    
                    <div class="contact-info">
                        <p><strong>Need assistance?</strong><br>
                        If you believe this restriction was applied in error, please contact our support team with your IP address and the time this occurred.</p>
                    </div>
                    
                    <div class="footer">
                        <p>Protected by Smart Shield Security System</p>
                    </div>
                </div>
            </div>
            
            <?php if (!$block_details['is_permanent'] && $remaining_seconds > 0): ?>
                         <script>
                 // Countdown timer
                 let remainingTime = <?php echo $remaining_seconds; ?>;
                 
                 function updateCountdown() {
                     if (remainingTime <= 0) {
                         window.location.reload();
                         return;
                     }
                     
                     const hours = Math.floor(remainingTime / 3600);
                     const minutes = Math.floor((remainingTime % 3600) / 60);
                     const seconds = remainingTime % 60;
                     
                     document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
                     document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
                     document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
                     
                     remainingTime--;
                 }
                 
                 // Update immediately and then every second
                 updateCountdown();
                 setInterval(updateCountdown, 1000);
             </script>
            <?php endif; ?>
        </body>
        </html>
        <?php
    }
    
    /**
     * Convert technical reasons to human-readable messages
     */
    private function get_human_readable_reason($technical_reason) {
        $reasons = [
            'login_attempts' => 'Multiple failed login attempts detected',
            'comment_spam' => 'Spam comment activity detected',
            'email_spam' => 'Spam email activity detected',
            'brute_force' => 'Brute force attack detected',
            'suspicious_activity' => 'Suspicious activity detected',
            'rate_limit' => 'Rate limit exceeded',
            'manual_block' => 'Manually blocked by administrator',
            'admin_manual' => 'Manually blocked by administrator',
            'spam_handler' => 'Automated spam detection',
            'security_violation' => 'Security policy violation',
            'malicious_request' => 'Malicious request detected',
            'bot_activity' => 'Automated bot activity detected',
            'ddos_protection' => 'DDoS protection activated',
            'vulnerability_scan' => 'Vulnerability scanning detected',
            'sql_injection' => 'SQL injection attempt detected',
            'xss_attempt' => 'Cross-site scripting attempt detected',
            'file_upload_abuse' => 'File upload abuse detected',
            'api_abuse' => 'API abuse detected',
            'content_scraping' => 'Content scraping detected',
            'form_spam' => 'Form spam detected'
        ];
        
        return $reasons[$technical_reason] ?? 'Security policy violation';
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