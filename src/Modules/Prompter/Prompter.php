<?php
namespace SmartShield\Modules\Prompter;

class Prompter {
    
    /**
     * Generate a prompt for comment spam detection
     *
     * @param string $comment_content The comment content to analyze
     * @param string $comment_author The comment author name
     * @param string $comment_email The comment author email
     * @param string $comment_url The comment author URL (if any)
     * @param string $post_title The post title where comment was made
     * @param array $context Additional context information
     * @return string The generated prompt
     */
    public function generate_comment_spam_prompt($comment_content, $comment_author = '', $comment_email = '', $comment_url = '', $post_title = '', $context = []) {
        $prompt = "You are a spam detection AI assistant. Analyze the following comment and determine if it's spam or legitimate.\n\n";
        
        $prompt .= "Comment Details:\n";
        $prompt .= "- Content: \"" . $this->clean_text($comment_content) . "\"\n";
        
        if (!empty($comment_author)) {
            $prompt .= "- Author: \"" . $this->clean_text($comment_author) . "\"\n";
        }
        
        if (!empty($comment_email)) {
            $prompt .= "- Email: \"" . $this->clean_text($comment_email) . "\"\n";
        }
        
        if (!empty($comment_url)) {
            $prompt .= "- Website: \"" . $this->clean_text($comment_url) . "\"\n";
        }
        
        if (!empty($post_title)) {
            $prompt .= "- Post Title: \"" . $this->clean_text($post_title) . "\"\n";
        }
        
        $prompt .= "\nSpam Detection Criteria:\n";
        $prompt .= "- Generic or irrelevant comments\n";
        $prompt .= "- Excessive links or promotional content\n";
        $prompt .= "- Poor grammar or strange language patterns\n";
        $prompt .= "- Irrelevant to the post content\n";
        $prompt .= "- Contains suspicious URLs or email addresses\n";
        $prompt .= "- Repetitive or template-like content\n";
        $prompt .= "- Attempts to sell products or services\n";
        $prompt .= "- Contains malicious links or phishing attempts\n";
        
        $prompt .= "\nInstructions:\n";
        $prompt .= "1. Analyze the comment thoroughly\n";
        $prompt .= "2. Consider the context and relevance\n";
        $prompt .= "3. Respond with only 'SPAM' or 'LEGITIMATE'\n";
        $prompt .= "4. Be strict - when in doubt, classify as SPAM\n";
        $prompt .= "5. Consider cultural and language variations\n";
        
        $prompt .= "\nResponse format: Just answer with 'SPAM' or 'LEGITIMATE' (no explanation needed)";
        
        return $prompt;
    }
    
    /**
     * Generate a prompt for email spam detection
     *
     * @param string $email_subject The email subject
     * @param string $email_content The email content
     * @param string $sender_email The sender's email
     * @param string $sender_name The sender's name
     * @param array $context Additional context information
     * @return string The generated prompt
     */
    public function generate_email_spam_prompt($email_subject, $email_content, $sender_email = '', $sender_name = '', $context = []) {
        $prompt = "You are a spam detection AI assistant. Analyze the following email and determine if it's spam or legitimate.\n\n";
        
        $prompt .= "Email Details:\n";
        $prompt .= "- Subject: \"" . $this->clean_text($email_subject) . "\"\n";
        $prompt .= "- Content: \"" . $this->clean_text($email_content) . "\"\n";
        
        if (!empty($sender_email)) {
            $prompt .= "- Sender Email: \"" . $this->clean_text($sender_email) . "\"\n";
        }
        
        if (!empty($sender_name)) {
            $prompt .= "- Sender Name: \"" . $this->clean_text($sender_name) . "\"\n";
        }
        
        $prompt .= "\nSpam Detection Criteria:\n";
        $prompt .= "- Unsolicited promotional emails\n";
        $prompt .= "- Phishing attempts\n";
        $prompt .= "- Suspicious attachments or links\n";
        $prompt .= "- Requests for personal information\n";
        $prompt .= "- Get-rich-quick schemes\n";
        $prompt .= "- Fake lottery or prize notifications\n";
        $prompt .= "- Suspicious sender addresses\n";
        $prompt .= "- Urgent or threatening language\n";
        
        $prompt .= "\nInstructions:\n";
        $prompt .= "1. Analyze the email thoroughly\n";
        $prompt .= "2. Check for common spam patterns\n";
        $prompt .= "3. Respond with only 'SPAM' or 'LEGITIMATE'\n";
        $prompt .= "4. Be cautious with unsolicited emails\n";
        $prompt .= "5. Consider sender reputation and content quality\n";
        
        $prompt .= "\nResponse format: Just answer with 'SPAM' or 'LEGITIMATE' (no explanation needed)";
        
        return $prompt;
    }
    
    /**
     * Generate a prompt for contact form spam detection
     *
     * @param string $message The contact form message
     * @param string $name The sender's name
     * @param string $email The sender's email
     * @param string $subject The message subject
     * @param array $context Additional context information
     * @return string The generated prompt
     */
    public function generate_contact_form_spam_prompt($message, $name = '', $email = '', $subject = '', $context = []) {
        $prompt = "You are a spam detection AI assistant. Analyze the following contact form submission and determine if it's spam or legitimate.\n\n";
        
        $prompt .= "Contact Form Details:\n";
        $prompt .= "- Message: \"" . $this->clean_text($message) . "\"\n";
        
        if (!empty($name)) {
            $prompt .= "- Name: \"" . $this->clean_text($name) . "\"\n";
        }
        
        if (!empty($email)) {
            $prompt .= "- Email: \"" . $this->clean_text($email) . "\"\n";
        }
        
        if (!empty($subject)) {
            $prompt .= "- Subject: \"" . $this->clean_text($subject) . "\"\n";
        }
        
        $prompt .= "\nSpam Detection Criteria:\n";
        $prompt .= "- Generic or template messages\n";
        $prompt .= "- Promotional or sales content\n";
        $prompt .= "- Irrelevant or nonsensical content\n";
        $prompt .= "- Suspicious links or requests\n";
        $prompt .= "- Poor grammar or language patterns\n";
        $prompt .= "- Requests for personal information\n";
        $prompt .= "- Automated or bot-generated content\n";
        $prompt .= "- Attempts to redirect to external sites\n";
        
        $prompt .= "\nInstructions:\n";
        $prompt .= "1. Analyze the message thoroughly\n";
        $prompt .= "2. Check for genuine inquiry vs spam patterns\n";
        $prompt .= "3. Respond with only 'SPAM' or 'LEGITIMATE'\n";
        $prompt .= "4. Consider the context and purpose\n";
        $prompt .= "5. Be strict with promotional content\n";
        
        $prompt .= "\nResponse format: Just answer with 'SPAM' or 'LEGITIMATE' (no explanation needed)";
        
        return $prompt;
    }
    
    /**
     * Generate a custom spam detection prompt
     *
     * @param string $content The content to analyze
     * @param string $type The type of content (comment, email, form, etc.)
     * @param array $additional_criteria Additional spam criteria
     * @param array $context Additional context information
     * @return string The generated prompt
     */
    public function generate_custom_spam_prompt($content, $type = 'general', $additional_criteria = [], $context = []) {
        $prompt = "You are a spam detection AI assistant. Analyze the following {$type} content and determine if it's spam or legitimate.\n\n";
        
        $prompt .= "Content to Analyze:\n";
        $prompt .= "\"" . $this->clean_text($content) . "\"\n\n";
        
        $prompt .= "General Spam Detection Criteria:\n";
        $prompt .= "- Unwanted or unsolicited content\n";
        $prompt .= "- Promotional or commercial intent\n";
        $prompt .= "- Poor quality or irrelevant content\n";
        $prompt .= "- Suspicious links or requests\n";
        $prompt .= "- Automated or bot-generated patterns\n";
        $prompt .= "- Attempts to deceive or mislead\n";
        
        if (!empty($additional_criteria)) {
            $prompt .= "\nAdditional Criteria:\n";
            foreach ($additional_criteria as $criterion) {
                $prompt .= "- " . $this->clean_text($criterion) . "\n";
            }
        }
        
        $prompt .= "\nInstructions:\n";
        $prompt .= "1. Analyze the content thoroughly\n";
        $prompt .= "2. Apply the spam detection criteria\n";
        $prompt .= "3. Respond with only 'SPAM' or 'LEGITIMATE'\n";
        $prompt .= "4. When in doubt, err on the side of caution\n";
        $prompt .= "5. Consider the context and intent\n";
        
        $prompt .= "\nResponse format: Just answer with 'SPAM' or 'LEGITIMATE' (no explanation needed)";
        
        return $prompt;
    }
    
    /**
     * Clean and sanitize text for prompt generation
     *
     * @param string $text The text to clean
     * @return string The cleaned text
     */
    private function clean_text($text) {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove potentially harmful content
        $text = strip_tags($text);
        
        // Escape quotes to prevent prompt injection
        $text = str_replace('"', '\"', $text);
        
        // Trim and return
        return trim($text);
    }
    
    /**
     * Get available prompt types
     *
     * @return array Array of available prompt types
     */
    public function get_available_prompt_types() {
        return [
            'comment' => 'Comment Spam Detection',
            'email' => 'Email Spam Detection',
            'contact_form' => 'Contact Form Spam Detection',
            'custom' => 'Custom Spam Detection'
        ];
    }
    
    /**
     * Validate prompt content
     *
     * @param string $content The content to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_prompt_content($content) {
        // Check if content is not empty
        if (empty(trim($content))) {
            return false;
        }
        
        // Check for reasonable length (not too short, not too long)
        $length = strlen($content);
        if ($length < 5 || $length > 10000) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get prompt statistics
     *
     * @param string $prompt The prompt to analyze
     * @return array Statistics about the prompt
     */
    public function get_prompt_statistics($prompt) {
        return [
            'character_count' => strlen($prompt),
            'word_count' => str_word_count($prompt),
            'line_count' => substr_count($prompt, "\n") + 1,
            'estimated_tokens' => ceil(strlen($prompt) / 4) // Rough estimate
        ];
    }
} 