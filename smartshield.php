<?php
/**
 * Plugin Name: Smart Shield
 * Description: Smart Shield - AI Spam Shield Plugin for WordPress.
 * Version: 1.0.0
 * Author: Rajan, Manish, Mohan
 * Author URI: https://github.com/vplugins
 * Text Domain: smart-shield
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

// Autoload dependencies using Composer
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require __DIR__ . '/vendor/autoload.php';
}

use SmartShield\Admin\SettingsPage;
use SmartShield\Front\IPBlockerFrontend;
use SmartShield\Front\LoginHandlerFrontend;
use SmartShield\Front\SpamHandlerFrontend;
use SmartShield\Front\EmailHandlerFrontend;
use SmartShield\Modules\LoginHandler\LoginHandler;
use SmartShield\Modules\SpamHandler\SpamHandler;
use SmartShield\Modules\EmailHandler\EmailHandler;


// Initialize the plugin
function smart_shield_init() {
    // Load admin settings and logs page
    if ( is_admin() ) {
        new SettingsPage();
    }
    
    // Load frontend IP blocker
    new IPBlockerFrontend();
    
    // Load login handler (both backend and frontend)
    new LoginHandler();
    new LoginHandlerFrontend();
    
    // Load spam handler (both backend and frontend)
    new SpamHandler();
    new SpamHandlerFrontend();
    
    // Load email handler (both backend and frontend)
    error_log('Smart Shield: Creating EmailHandler instances');
    new EmailHandler();
    new EmailHandlerFrontend();
    error_log('Smart Shield: EmailHandler instances created');
}
add_action( 'plugins_loaded', 'smart_shield_init' );
