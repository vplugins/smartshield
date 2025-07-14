<?php
namespace SmartShield\Front;

use SmartShield\Modules\EmailHandler\EmailHandler;
use SmartShield\Admin\Logger;

class EmailHandlerFrontend {
    private $emailHandler;
    private $logger;
    
    public function __construct() {
        $this->emailHandler = new EmailHandler();
        $this->logger = new Logger();
        
        // Initialize frontend hooks
        add_action('init', [$this, 'init']);
    }
    
    /**
     * Initialize frontend functionality
     */
    public function init() {
        // Only initialize if email protection is enabled
        if (get_option('ss_email_enabled')) {
            // Hook into common contact form plugins
            $this->init_contact_form_hooks();
            
            // Add AJAX handlers for admin testing
            add_action('wp_ajax_ss_test_email_spam', [$this, 'ajax_test_email_spam']);
            add_action('wp_ajax_ss_get_email_stats', [$this, 'ajax_get_email_stats']);
        }
    }
    
    /**
     * Initialize contact form plugin hooks
     */
    private function init_contact_form_hooks() {
        // Contact Form 7
        add_action('wpcf7_before_send_mail', [$this, 'check_cf7_spam'], 10, 2);
        
        // Gravity Forms
        add_filter('gform_pre_send_email', [$this, 'check_gravity_forms_spam'], 10, 4);
        
        // WPForms
        add_action('wpforms_process_before_send_email', [$this, 'check_wpforms_spam'], 10, 4);
        
        // Ninja Forms
        add_action('ninja_forms_after_submission', [$this, 'check_ninja_forms_spam'], 10, 1);
        
        // Elementor Forms
        add_action('elementor_pro/forms/new_record', [$this, 'check_elementor_forms_spam'], 10, 2);
        
        // Generic form processing
        add_action('wp_mail', [$this, 'intercept_form_emails'], 1, 1);
    }
    
    /**
     * Check Contact Form 7 submissions for spam
     *
     * @param object $contact_form The contact form object
     * @param bool $abort Whether to abort sending
     */
    public function check_cf7_spam($contact_form, &$abort) {
        if (!get_option('ss_email_enabled')) {
            return;
        }
        
        // Get submission instance
        $submission = \WPCF7_Submission::get_instance();
        if (!$submission) {
            return;
        }
        
        // Get form data
        $posted_data = $submission->get_posted_data();
        $form_data = $this->extract_form_data($posted_data);
        
        // Check if form submission is spam
        if ($this->is_form_spam($form_data)) {
            // Log spam detection
            $this->logger->log_event('email', "Contact Form 7 spam detected: " . $form_data['subject'], 'blocked');
            
            // Check if spam warning is enabled
            if (get_option('ss_email_enable_spam_warning')) {
                // Don't abort email sending, just let it go through (EmailHandler will add SPAM to subject)
                // No error message shown to user
            } else {
                // Abort email sending and show error message
                $abort = true;
                $submission->set_response('spam_detected', 'Your message has been identified as spam and blocked.');
            }
        }
    }
    
    /**
     * Check Gravity Forms submissions for spam
     *
     * @param array $email The email data
     * @param string $message_format The message format
     * @param object $notification The notification object
     * @param object $entry The entry object
     * @return array|false The email data or false to block
     */
    public function check_gravity_forms_spam($email, $message_format, $notification, $entry) {
        if (!get_option('ss_email_enabled')) {
            return $email;
        }
        
        // Extract form data
        $form_data = [
            'subject' => $email['subject'] ?? '',
            'message' => $email['message'] ?? '',
            'name' => $entry['1'] ?? '', // Common field ID for name
            'email' => $entry['2'] ?? '', // Common field ID for email
        ];
        
        // Check if form submission is spam
        if ($this->is_form_spam($form_data)) {
            // Log spam detection
            $this->logger->log_event('email', "Gravity Forms spam detected: " . $form_data['subject'], 'blocked');
            
            // Check if spam warning is enabled
            if (get_option('ss_email_enable_spam_warning')) {
                // Don't block email, let it go through (EmailHandler will add SPAM to subject)
                return $email;
            } else {
                // Block email
                return false;
            }
        }
        
        return $email;
    }
    
    /**
     * Check WPForms submissions for spam
     *
     * @param array $fields The form fields
     * @param array $form_data The form data
     * @param array $email The email data
     * @param string $email_id The email ID
     */
    public function check_wpforms_spam($fields, $form_data, $email, $email_id) {
        if (!get_option('ss_email_enabled')) {
            return;
        }
        
        // Extract form data
        $extracted_data = [
            'subject' => $email['subject'] ?? '',
            'message' => $email['message'] ?? '',
            'name' => $this->extract_wpforms_field($fields, 'name'),
            'email' => $this->extract_wpforms_field($fields, 'email'),
        ];
        
        // Check if form submission is spam
        if ($this->is_form_spam($extracted_data)) {
            // Log spam detection
            $this->logger->log_event('email', "WPForms spam detected: " . $extracted_data['subject'], 'blocked');
            
            // Check if spam warning is enabled
            if (get_option('ss_email_enable_spam_warning')) {
                // Don't stop email processing, let it go through (EmailHandler will add SPAM to subject)
                // No error message shown to user
                return;
            } else {
                // Stop email processing
                wp_die('Your message has been identified as spam and blocked.');
            }
        }
    }
    
    /**
     * Check Ninja Forms submissions for spam
     *
     * @param array $form_data The form data
     */
    public function check_ninja_forms_spam($form_data) {
        if (!get_option('ss_email_enabled')) {
            return;
        }
        
        // Extract form data
        $extracted_data = [
            'subject' => $form_data['settings']['email_subject'] ?? '',
            'message' => $form_data['fields']['message']['value'] ?? '',
            'name' => $form_data['fields']['name']['value'] ?? '',
            'email' => $form_data['fields']['email']['value'] ?? '',
        ];
        
        // Check if form submission is spam
        if ($this->is_form_spam($extracted_data)) {
            // Log spam detection
            $this->logger->log_event('email', "Ninja Forms spam detected: " . $extracted_data['subject'], 'blocked');
            
            // Check if spam warning is enabled
            if (get_option('ss_email_enable_spam_warning')) {
                // Don't add error to form, let it go through (EmailHandler will add SPAM to subject)
                // No error message shown to user
            } else {
                // Add error to form
                $form_data['errors'][] = 'Your message has been identified as spam and blocked.';
            }
        }
    }
    
    /**
     * Check Elementor Forms submissions for spam
     *
     * @param object $record The form record
     * @param object $handler The form handler
     */
    public function check_elementor_forms_spam($record, $handler) {
        if (!get_option('ss_email_enabled')) {
            return;
        }
        
        // Extract form data
        $fields = $record->get_field_values();
        $extracted_data = [
            'subject' => $fields['subject'] ?? 'Contact Form Submission',
            'message' => $fields['message'] ?? '',
            'name' => $fields['name'] ?? '',
            'email' => $fields['email'] ?? '',
        ];
        
        // Check if form submission is spam
        if ($this->is_form_spam($extracted_data)) {
            // Log spam detection
            $this->logger->log_event('email', "Elementor Forms spam detected: " . $extracted_data['subject'], 'blocked');
            
            // Check if spam warning is enabled
            if (get_option('ss_email_enable_spam_warning')) {
                // Don't stop processing, let it go through (EmailHandler will add SPAM to subject)
                // No error message shown to user
                return;
            } else {
                // Stop processing
                wp_die('Your message has been identified as spam and blocked.');
            }
        }
    }
    
    /**
     * Intercept form emails generically
     *
     * @param array $mail_data The email data
     * @return array The email data
     */
    public function intercept_form_emails($mail_data) {
        // Only process emails that look like form submissions
        if ($this->is_form_email($mail_data)) {
            // Extract form data from email
            $form_data = [
                'subject' => $mail_data['subject'] ?? '',
                'message' => $mail_data['message'] ?? '',
                'name' => $this->extract_name_from_email($mail_data),
                'email' => $this->extract_email_from_headers($mail_data),
            ];
            
            // Check if form submission is spam
            if ($this->is_form_spam($form_data)) {
                // Log spam detection
                $this->logger->log_event('email', "Generic form spam detected: " . $form_data['subject'], 'blocked');
                
                // Let the EmailHandler handle it
                return $mail_data;
            }
        }
        
        return $mail_data;
    }
    
    /**
     * Check if form submission is spam
     *
     * @param array $form_data The form data
     * @return bool True if spam, false otherwise
     */
    private function is_form_spam($form_data) {
        // Prepare message content
        $message = $form_data['message'] ?? '';
        
        // Prepare context
        $context = [
            'subject' => $form_data['subject'] ?? '',
            'name' => $form_data['name'] ?? '',
            'email' => $form_data['email'] ?? '',
        ];
        
        // Use the spam handler to check
        return $this->emailHandler->is_email_spam($context['subject'], $message, $context);
    }
    
    /**
     * Extract form data from posted data
     *
     * @param array $posted_data The posted data
     * @return array Extracted form data
     */
    private function extract_form_data($posted_data) {
        $form_data = [
            'subject' => '',
            'message' => '',
            'name' => '',
            'email' => '',
        ];
        
        // Common field mappings
        $field_mappings = [
            'subject' => ['subject', 'your-subject', 'email-subject'],
            'message' => ['message', 'your-message', 'textarea', 'comments'],
            'name' => ['name', 'your-name', 'full-name', 'first-name'],
            'email' => ['email', 'your-email', 'email-address'],
        ];
        
        foreach ($field_mappings as $key => $possible_fields) {
            foreach ($possible_fields as $field) {
                if (isset($posted_data[$field]) && !empty($posted_data[$field])) {
                    $form_data[$key] = $posted_data[$field];
                    break;
                }
            }
        }
        
        return $form_data;
    }
    
    /**
     * Extract field value from WPForms fields
     *
     * @param array $fields The form fields
     * @param string $field_type The field type to extract
     * @return string The field value
     */
    private function extract_wpforms_field($fields, $field_type) {
        foreach ($fields as $field) {
            if (isset($field['type']) && $field['type'] === $field_type) {
                return $field['value'] ?? '';
            }
        }
        return '';
    }
    
    /**
     * Check if email looks like a form submission
     *
     * @param array $mail_data The email data
     * @return bool True if looks like form email
     */
    private function is_form_email($mail_data) {
        $subject = strtolower($mail_data['subject'] ?? '');
        
        // Common form email indicators
        $form_indicators = [
            'contact',
            'form',
            'inquiry',
            'message',
            'submission',
            'feedback',
            'support',
            'quote',
            'request'
        ];
        
        foreach ($form_indicators as $indicator) {
            if (strpos($subject, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract name from email content
     *
     * @param array $mail_data The email data
     * @return string The extracted name
     */
    private function extract_name_from_email($mail_data) {
        $message = $mail_data['message'] ?? '';
        
        // Look for common name patterns
        $patterns = [
            '/Name:\s*(.+)/i',
            '/From:\s*(.+)/i',
            '/Sender:\s*(.+)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return '';
    }
    
    /**
     * Extract email from headers
     *
     * @param array $mail_data The email data
     * @return string The extracted email
     */
    private function extract_email_from_headers($mail_data) {
        if (isset($mail_data['headers'])) {
            $headers = is_array($mail_data['headers']) ? $mail_data['headers'] : explode("\n", $mail_data['headers']);
            
            foreach ($headers as $header) {
                if (stripos($header, 'Reply-To:') === 0) {
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
     * AJAX handler for testing email spam detection
     */
    public function ajax_test_email_spam() {
        // Check nonce and permissions
        if (!current_user_can('manage_options') || !check_ajax_referer('ss_admin_nonce', 'nonce', false)) {
            wp_die('Unauthorized access');
        }
        
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (empty($subject) || empty($message)) {
            wp_send_json_error(['message' => 'Subject and message are required']);
        }
        
        // Test email spam detection
        $result = $this->emailHandler->test_email_spam_detection($subject, $message);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX handler for getting email statistics
     */
    public function ajax_get_email_stats() {
        // Check nonce and permissions
        if (!current_user_can('manage_options') || !check_ajax_referer('ss_admin_nonce', 'nonce', false)) {
            wp_die('Unauthorized access');
        }
        
        $stats = $this->emailHandler->get_statistics();
        wp_send_json_success($stats);
    }
    
    /**
     * Add email spam detection to admin bar
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $stats = $this->emailHandler->get_statistics();
        
        $wp_admin_bar->add_menu([
            'id' => 'smart-shield-email',
            'title' => 'Email Spam: ' . $stats['total_email_spam_blocked'],
            'href' => admin_url('admin.php?page=smart-shield-logs'),
            'meta' => [
                'title' => 'View Smart Shield Email Logs'
            ]
        ]);
    }
} 