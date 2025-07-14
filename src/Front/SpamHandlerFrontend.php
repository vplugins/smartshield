<?php
namespace SmartShield\Front;

use SmartShield\Modules\SpamHandler\SpamHandler;
use SmartShield\Modules\IPBlocker\IPBlocker;

class SpamHandlerFrontend {
    private $spamHandler;
    private $ipBlocker;
    
    public function __construct() {
        $this->spamHandler = new SpamHandler();
        $this->ipBlocker = new IPBlocker();
        
        // Initialize hooks
        add_action('init', [$this, 'init']);
    }
    
    /**
     * Initialize the frontend module
     */
    public function init() {
        // Only initialize if comment spam protection is enabled
        if (get_option('ss_comment_enabled')) {
            // Add frontend hooks
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
            add_action('comment_form', [$this, 'add_spam_protection_notice']);
            add_action('wp_footer', [$this, 'add_spam_protection_styles']);
            
            // Handle blocked IP access
            add_action('template_redirect', [$this, 'check_blocked_ip']);
            
            // Add comment form validation
            add_action('wp_ajax_nopriv_check_comment_spam', [$this, 'ajax_check_comment_spam']);
            add_action('wp_ajax_check_comment_spam', [$this, 'ajax_check_comment_spam']);
        }
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        // Only enqueue on pages with comment forms
        if (is_single() || is_page()) {
            wp_enqueue_script('smart-shield-comment-protection', $this->get_script_url(), ['jquery'], '1.0.0', true);
            wp_localize_script('smart-shield-comment-protection', 'smart_shield_comment', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('smart_shield_comment_nonce'),
                'messages' => [
                    'checking' => __('Checking comment for spam...', 'smart-shield'),
                    'blocked' => __('Your comment has been blocked as spam.', 'smart-shield'),
                    'error' => __('Error checking comment. Please try again.', 'smart-shield'),
                    'rate_limit' => __('Please wait before submitting another comment.', 'smart-shield')
                ]
            ]);
        }
    }
    
    /**
     * Add spam protection notice to comment form
     */
    public function add_spam_protection_notice() {
        if (get_option('ss_comment_enabled')) {
            echo '<div class="smart-shield-notice">';
            echo '<p class="smart-shield-protection-info">';
            echo '<span class="smart-shield-icon">🛡️</span> ';
            echo __('Comments are protected by Smart Shield AI spam detection.', 'smart-shield');
            echo '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Add spam protection styles to wp_footer
     */
    public function add_spam_protection_styles() {
        if (get_option('ss_comment_enabled')) {
            ?>
            <style>
                .smart-shield-notice {
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: 4px;
                    padding: 10px;
                    margin-bottom: 15px;
                    font-size: 14px;
                }
                
                .smart-shield-protection-info {
                    margin: 0;
                    color: #495057;
                    display: flex;
                    align-items: center;
                }
                
                .smart-shield-icon {
                    margin-right: 8px;
                    font-size: 16px;
                }
                
                .smart-shield-blocked-message {
                    background: #f8d7da;
                    border: 1px solid #f5c6cb;
                    color: #721c24;
                    padding: 15px;
                    border-radius: 4px;
                    margin: 20px 0;
                }
                
                .smart-shield-error-message {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    color: #856404;
                    padding: 15px;
                    border-radius: 4px;
                    margin: 20px 0;
                }
                
                .smart-shield-checking {
                    background: #d4edda;
                    border: 1px solid #c3e6cb;
                    color: #155724;
                    padding: 10px;
                    border-radius: 4px;
                    margin: 10px 0;
                    display: none;
                }
                
                .smart-shield-spinner {
                    display: inline-block;
                    width: 16px;
                    height: 16px;
                    border: 2px solid #f3f3f3;
                    border-top: 2px solid #007cba;
                    border-radius: 50%;
                    animation: smart-shield-spin 1s linear infinite;
                    margin-right: 8px;
                }
                
                @keyframes smart-shield-spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                .smart-shield-rate-limit {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    color: #856404;
                    padding: 15px;
                    border-radius: 4px;
                    margin: 20px 0;
                }
            </style>
            <?php
        }
    }
    
    /**
     * Check if current IP is blocked and show appropriate message
     */
    public function check_blocked_ip() {
        if (!get_option('ss_comment_enabled')) {
            return;
        }
        
        $ip_address = $this->get_client_ip();
        $block_info = $this->ipBlocker->get_active_block($ip_address);
        
        if ($block_info) {
            // If on a single post/page with comments, show blocked message
            if (is_single() || is_page()) {
                add_filter('comments_template', [$this, 'show_blocked_comments_message']);
            }
        }
    }
    
    /**
     * Show blocked comments message
     */
    public function show_blocked_comments_message($template) {
        $ip_address = $this->get_client_ip();
        $block_info = $this->ipBlocker->get_active_block($ip_address);
        
        if ($block_info) {
            $blocked_until = date('F j, Y g:i A', strtotime($block_info->blocked_until));
            
            echo '<div class="smart-shield-blocked-message">';
            echo '<h3>' . __('Comments Blocked', 'smart-shield') . '</h3>';
            echo '<p>' . __('Your IP address is currently blocked from commenting due to spam activity.', 'smart-shield') . '</p>';
            echo '<p><strong>' . __('Blocked until:', 'smart-shield') . '</strong> ' . $blocked_until . '</p>';
            echo '<p><small>' . __('If you believe this is an error, please contact the site administrator.', 'smart-shield') . '</small></p>';
            echo '</div>';
            
            // Return empty template to hide comment form
            return '';
        }
        
        return $template;
    }
    
    /**
     * AJAX handler for checking comment spam
     */
    public function ajax_check_comment_spam() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'smart_shield_comment_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        
        // Get comment data
        $comment_content = sanitize_textarea_field($_POST['comment']);
        $comment_author = sanitize_text_field($_POST['author'] ?? '');
        $comment_email = sanitize_email($_POST['email'] ?? '');
        $comment_url = esc_url_raw($_POST['url'] ?? '');
        $post_id = intval($_POST['post_id'] ?? 0);
        
        // Get post title
        $post_title = '';
        if ($post_id) {
            $post = get_post($post_id);
            $post_title = $post ? $post->post_title : '';
        }
        
        // Check if content is spam
        $context = [
            'author' => $comment_author,
            'email' => $comment_email,
            'url' => $comment_url,
            'post_title' => $post_title
        ];
        
        $is_spam = $this->spamHandler->is_spam($comment_content, 'comment', $context);
        
        if ($is_spam) {
            wp_send_json_error(['message' => 'Comment identified as spam']);
        } else {
            wp_send_json_success(['message' => 'Comment appears to be legitimate']);
        }
    }
    
    /**
     * Get client IP address
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
     * Get script URL for frontend JavaScript
     */
    private function get_script_url() {
        // This would normally point to an actual JS file
        // For now, we'll inline the script
        return 'data:text/javascript;base64,' . base64_encode($this->get_frontend_script());
    }
    
    /**
     * Get frontend JavaScript for comment protection
     */
    private function get_frontend_script() {
        return '
            jQuery(document).ready(function($) {
                var commentForm = $("#commentform");
                var submitButton = commentForm.find("input[type=submit]");
                var originalButtonText = submitButton.val();
                var isChecking = false;
                
                // Add checking message container
                commentForm.prepend("<div class=\"smart-shield-checking\"><span class=\"smart-shield-spinner\"></span><span class=\"message\"></span></div>");
                var checkingMessage = commentForm.find(".smart-shield-checking");
                
                // Handle form submission
                commentForm.on("submit", function(e) {
                    if (isChecking) {
                        e.preventDefault();
                        return false;
                    }
                    
                    var commentText = $("#comment").val();
                    if (commentText.trim().length === 0) {
                        return true; // Allow empty comments to be handled by WordPress
                    }
                    
                    e.preventDefault();
                    
                    // Show checking message
                    isChecking = true;
                    checkingMessage.find(".message").text(smart_shield_comment.messages.checking);
                    checkingMessage.show();
                    submitButton.val("Checking...").prop("disabled", true);
                    
                    // Prepare data
                    var formData = {
                        action: "check_comment_spam",
                        nonce: smart_shield_comment.nonce,
                        comment: commentText,
                        author: $("#author").val() || "",
                        email: $("#email").val() || "",
                        url: $("#url").val() || "",
                        post_id: $("input[name=comment_post_ID]").val() || 0
                    };
                    
                    // Send AJAX request
                    $.ajax({
                        url: smart_shield_comment.ajax_url,
                        type: "POST",
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                // Comment is legitimate, submit form
                                isChecking = false;
                                checkingMessage.hide();
                                submitButton.val(originalButtonText).prop("disabled", false);
                                commentForm.off("submit").submit();
                            } else {
                                // Comment is spam
                                isChecking = false;
                                checkingMessage.hide();
                                submitButton.val(originalButtonText).prop("disabled", false);
                                
                                // Show error message
                                var errorMessage = response.data.message || smart_shield_comment.messages.blocked;
                                showErrorMessage(errorMessage);
                            }
                        },
                        error: function() {
                            // Error occurred, allow form submission as fallback
                            isChecking = false;
                            checkingMessage.hide();
                            submitButton.val(originalButtonText).prop("disabled", false);
                            commentForm.off("submit").submit();
                        }
                    });
                });
                
                function showErrorMessage(message) {
                    var errorDiv = $("<div class=\"smart-shield-error-message\"></div>");
                    errorDiv.html("<strong>Error:</strong> " + message);
                    commentForm.prepend(errorDiv);
                    
                    // Auto-hide after 5 seconds
                    setTimeout(function() {
                        errorDiv.fadeOut(function() {
                            errorDiv.remove();
                        });
                    }, 5000);
                }
            });
        ';
    }
    
    /**
     * Display spam statistics for logged-in users
     */
    public function display_spam_stats() {
        if (!is_user_logged_in() || !current_user_can('moderate_comments')) {
            return;
        }
        
        $stats = $this->spamHandler->get_statistics();
        
        echo '<div class="smart-shield-stats">';
        echo '<h4>' . __('Spam Protection Stats', 'smart-shield') . '</h4>';
        echo '<ul>';
        echo '<li>' . __('Total spam blocked:', 'smart-shield') . ' ' . $stats['total_spam_blocked'] . '</li>';
        echo '<li>' . __('Spam blocked today:', 'smart-shield') . ' ' . $stats['spam_blocked_today'] . '</li>';
        echo '<li>' . __('Spam blocked this week:', 'smart-shield') . ' ' . $stats['spam_blocked_week'] . '</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    /**
     * Test AI connection (for admin users)
     */
    public function test_ai_connection() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $result = $this->spamHandler->test_ai_connection();
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => 'AI connection successful',
                'provider' => $result['provider']
            ]);
        } else {
            wp_send_json_error([
                'message' => 'AI connection failed: ' . $result['error']
            ]);
        }
    }
} 