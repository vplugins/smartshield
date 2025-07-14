# Smart Shield - AI Spam Shield Plugin for WordPress

**Version:** 1.0.0  
**Authors:** Rajan, Manish, Mohan  
**License:** GPL-2.0-or-later  
**Requires:** WordPress 5.0+, PHP 7.4+

## 📋 Overview

Smart Shield is a comprehensive WordPress security plugin that provides AI-powered spam protection across multiple vectors including login attempts, comments, and emails. It features intelligent IP blocking, detailed logging, and an intuitive admin dashboard for monitoring and managing security threats.

## ✨ Key Features

### 🔐 Login Protection
- **Brute Force Protection**: Automatically blocks IP addresses after failed login attempts
- **Configurable Thresholds**: Set custom maximum login attempts before blocking
- **Temporary Blocking**: Uses transient-based storage for automatic cleanup

### 💬 Comment Spam Protection
- **AI-Powered Detection**: Leverages Gemini AI API for intelligent spam detection
- **Flexible Handling**: Choose to block spam comments or save them for review
- **Real-time Processing**: Analyzes comments before they're published

### 📧 Email Spam Protection
- **AI Analysis**: Uses advanced AI to detect spam in email submissions
- **Configurable Actions**: Block spam emails or add warning labels
- **Form Protection**: Protects contact forms and email submissions

### 🛡️ IP Blocking System
- **Manual IP Management**: Add/remove IP addresses from block list
- **Automatic Blocking**: AI and login protection automatically block suspicious IPs
- **Flexible Duration**: Configure block duration (1 hour to permanent)
- **Whitelist Support**: Protect trusted IP addresses from blocking

### 📊 Comprehensive Logging
- **Detailed Event Tracking**: Log all security events with timestamps
- **Visual Dashboard**: Real-time statistics and recent activity monitoring
- **Filterable Logs**: Search and filter logs by event type, IP, status, and date
- **Configurable Retention**: Set maximum log entries to control database size

### 🤖 AI Engine Integration
- **Gemini AI API**: Uses Google's Gemini AI for accurate spam detection
- **Intelligent Prompting**: Specialized prompts for different spam types
- **Modular Architecture**: Easy to extend with additional AI providers

## 🚀 Installation

### Prerequisites
- WordPress 5.0 or higher
- PHP 7.4 or higher
- Composer (for development)

### Installation Steps

1. **Download and Extract**
   ```bash
   cd /path/to/wordpress/wp-content/plugins/
   # Extract the plugin files to smartshield/ directory
   ```

2. **Install Dependencies**
   ```bash
   cd smartshield/
   composer install --no-dev
   ```

3. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Smart Shield" and click "Activate"

4. **Configure Settings**
   - Navigate to Smart Shield → Dashboard
   - Configure your desired protection settings
   - Add your Gemini AI API key for AI features

## ⚙️ Configuration

### 1. AI API Setup
1. Get your Gemini AI API key from [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Go to Smart Shield → Other Settings
3. Enter your API key in the "AI API Key" field

### 2. Login Protection
- **Enable Protection**: Toggle login spam protection on/off
- **Max Attempts**: Set maximum failed login attempts (default: 5)
- **Block Duration**: Configure how long IPs are blocked (set in Other Settings)

### 3. Comment Protection
- **Enable Protection**: Toggle comment spam protection on/off
- **Save for Review**: Choose to save spam comments for manual review or block them entirely

### 4. Email Protection
- **Enable Protection**: Toggle email spam protection on/off
- **Spam Warning**: Add "SPAM" to subject line or block entirely

### 5. IP Management
- **Default Block Duration**: Set default duration for automatic blocks
- **IP Whitelist**: Add trusted IP addresses that will never be blocked
- **IP Block List**: Manually add IP addresses to block
- **Notification Settings**: Configure email notifications for admin

### 6. Logging & Storage
- **Max Log Entries**: Set maximum number of log entries to store
- **Auto-cleanup**: Automatic cleanup of old logs when limit reached

## 📖 Usage Guide

### Admin Dashboard
Access the main dashboard at **WordPress Admin → Smart Shield → Dashboard**

**Statistics Overview:**
- Total Events: All security events tracked
- Last 24 Hours: Recent activity count
- Unique IPs: Number of unique IP addresses in logs
- Blocked Today: IPs blocked in the current day

**Protection Status:**
- Real-time status of all protection modules
- Quick enable/disable toggles
- Configuration status indicators

**Recent Activity:**
- Latest 5 security events
- Event types: login, comment, email, IP block
- Status indicators: blocked, allowed, pending

### Managing IP Blocks
1. Go to **Smart Shield → IP Blocker**
2. **Add New Block:**
   - Enter IP address
   - Set block duration
   - Add optional reason
   - Click "Block IP Address"

3. **Manage Existing Blocks:**
   - View all blocked IPs with status
   - Filter by active/expired/removed
   - Manually remove or extend blocks
   - Clean up expired blocks

### Viewing Logs
1. Go to **Smart Shield → Logs**
2. **Filter Options:**
   - Event Type: login, comment, email, ip_block
   - Status: blocked, allowed, pending
   - Date Range: Custom date filtering
   - IP Address: Search by specific IP

3. **Log Details:**
   - Timestamp and event type
   - IP address and user agent
   - Status and AI confidence score
   - Detailed event information

## 🔧 Developer Information

### Plugin Structure
```
smartshield/
├── src/
│   ├── Admin/          # Admin interface classes
│   ├── Front/          # Frontend integration
│   ├── Helper/         # Helper functions
│   └── Modules/        # Core functionality modules
├── vendor/             # Composer dependencies
├── composer.json       # Dependency management
└── smartshield.php     # Main plugin file
```

### Key Classes

**Core Modules:**
- `SmartShield\Modules\LoginHandler\LoginHandler` - Login protection
- `SmartShield\Modules\SpamHandler\SpamHandler` - Comment spam detection
- `SmartShield\Modules\EmailHandler\EmailHandler` - Email spam protection
- `SmartShield\Modules\IPBlocker\IPBlocker` - IP blocking system

**Admin Interface:**
- `SmartShield\Admin\SettingsPage` - Main admin dashboard
- `SmartShield\Admin\Logger` - Logging system
- `SmartShield\Admin\*Settings` - Individual settings pages

**Frontend Integration:**
- `SmartShield\Front\*Frontend` - Frontend handlers for each module

### Hooks and Filters

**Actions:**
- `smart_shield_ip_blocked` - Triggered when IP is blocked
- `smart_shield_spam_detected` - Triggered when spam is detected
- `smart_shield_login_blocked` - Triggered when login is blocked

**Filters:**
- `smart_shield_ip_whitelist` - Modify IP whitelist
- `smart_shield_spam_threshold` - Modify spam detection threshold
- `smart_shield_block_duration` - Modify block duration

### Database Tables

**wp_smart_shield_logs:**
- `id` - Primary key
- `ip_address` - IP address of the event
- `event_type` - Type of event (login, comment, email, ip_block)
- `status` - Event status (blocked, allowed, pending)
- `user_agent` - User agent string
- `details` - JSON encoded event details
- `created_at` - Timestamp

**wp_smart_shield_ip_blocks:**
- `id` - Primary key
- `ip_address` - Blocked IP address
- `reason` - Block reason
- `duration` - Block duration in seconds
- `status` - Block status (active, expired, manually_removed)
- `created_at` - Block creation timestamp
- `expires_at` - Block expiration timestamp

## 🛠️ Troubleshooting

### Common Issues

**1. AI Features Not Working**
- Ensure you have a valid Gemini AI API key
- Check API key permissions in Google Cloud Console
- Verify internet connectivity from your server

**2. IP Blocking Not Working**
- Check if IP is in whitelist
- Verify block duration settings
- Ensure database tables are created properly

**3. High False Positives**
- Adjust AI confidence threshold
- Review and update IP whitelist
- Check spam detection prompts

**4. Performance Issues**
- Reduce maximum log entries
- Enable log cleanup
- Check database table indexes

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 🔄 Changelog

### Version 1.0.0
- Initial release
- Login protection with IP blocking
- AI-powered comment spam detection
- Email spam protection
- Comprehensive admin dashboard
- IP blocking management
- Detailed logging system
- Gemini AI integration

## 📄 License

This plugin is licensed under the GPL-2.0-or-later license. See the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

We welcome contributions to Smart Shield! Please feel free to submit issues, feature requests, or pull requests.

### Development Setup
1. Clone the repository
2. Run `composer install`
3. Set up WordPress development environment
4. Activate the plugin in development mode

## 🆘 Support

For support, please:
1. Check the troubleshooting section above
2. Review the WordPress debug logs
3. Create an issue in the repository
4. Contact the development team

## 🔗 Links

- [GitHub Repository](https://github.com/vplugins/smartshield)

---

**Made with ❤️ by the WordPress Hosting Team** 