jQuery(document).ready(function($) {
    // Handle refresh button clicks
    $('.smart-shield-refresh-btn').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var icon = button.find('.dashicons');
        var refreshType = button.data('refresh');
        
        // Add loading state
        icon.addClass('refreshing');
        button.prop('disabled', true);
        
        // Make AJAX request
        $.ajax({
            url: smart_shield_ajax.ajaxurl,
            method: 'POST',
            data: {
                action: 'smart_shield_get_stats',
                type: refreshType,
                nonce: smart_shield_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateContent(refreshType, response.data);
                    showNotification('Data refreshed successfully!', 'success');
                } else {
                    showNotification('Failed to refresh data', 'error');
                }
            },
            error: function() {
                showNotification('Network error while refreshing data', 'error');
            },
            complete: function() {
                // Remove loading state
                icon.removeClass('refreshing');
                button.prop('disabled', false);
            }
        });
    });
    
    // Function to update content based on refresh type
    function updateContent(type, data) {
        switch(type) {
            case 'stats':
                updateStatsCards(data);
                break;
            case 'status':
                updateProtectionStatus(data);
                break;
            case 'logs':
                updateRecentLogs(data);
                break;
        }
    }
    
    // Update statistics cards
    function updateStatsCards(data) {
        $('#total-events').text(data.total_events);
        $('#recent-24h').text(data.recent_24h);
        $('#unique-ips').text(data.unique_ips);
        $('#blocked-today').text(data.blocked_today);
        
        // Add brief animation
        $('.smart-shield-stat-card').each(function() {
            $(this).addClass('smart-shield-pulse');
            setTimeout(() => {
                $(this).removeClass('smart-shield-pulse');
            }, 1000);
        });
    }
    
    // Update protection status
    function updateProtectionStatus(data) {
        var statusContainer = $('#protection-status');
        var statusItems = statusContainer.find('.smart-shield-status-item');
        
        // Update each status item
        statusItems.each(function() {
            var item = $(this);
            var badge = item.find('.smart-shield-status-badge');
            var indicator = item.find('.smart-shield-status-indicator');
            
            if (item.text().includes('Login Protection')) {
                badge.removeClass('active inactive').addClass(data.login_enabled ? 'active' : 'inactive');
                indicator.removeClass('active inactive').addClass(data.login_enabled ? 'active' : 'inactive');
                badge.html('<span class="smart-shield-status-indicator ' + (data.login_enabled ? 'active' : 'inactive') + '"></span>' + (data.login_enabled ? 'Active' : 'Inactive'));
            } else if (item.text().includes('Comment Protection')) {
                badge.removeClass('active inactive').addClass(data.comment_enabled ? 'active' : 'inactive');
                indicator.removeClass('active inactive').addClass(data.comment_enabled ? 'active' : 'inactive');
                badge.html('<span class="smart-shield-status-indicator ' + (data.comment_enabled ? 'active' : 'inactive') + '"></span>' + (data.comment_enabled ? 'Active' : 'Inactive'));
            } else if (item.text().includes('Email Protection')) {
                badge.removeClass('active inactive').addClass(data.email_enabled ? 'active' : 'inactive');
                indicator.removeClass('active inactive').addClass(data.email_enabled ? 'active' : 'inactive');
                badge.html('<span class="smart-shield-status-indicator ' + (data.email_enabled ? 'active' : 'inactive') + '"></span>' + (data.email_enabled ? 'Active' : 'Inactive'));
            } else if (item.text().includes('AI Protection')) {
                badge.removeClass('configured not-configured').addClass(data.ai_configured ? 'configured' : 'not-configured');
                indicator.removeClass('configured not-configured').addClass(data.ai_configured ? 'configured' : 'not-configured');
                badge.html('<span class="smart-shield-status-indicator ' + (data.ai_configured ? 'configured' : 'not-configured') + '"></span>' + (data.ai_configured ? 'Configured' : 'Not Configured'));
            }
        });
        
        // Add brief animation
        statusContainer.addClass('smart-shield-pulse');
        setTimeout(() => {
            statusContainer.removeClass('smart-shield-pulse');
        }, 1000);
    }
    
    // Update recent logs
    function updateRecentLogs(data) {
        $('#recent-logs .inside').html(data.html);
        
        // Add brief animation
        $('#recent-logs').addClass('smart-shield-pulse');
        setTimeout(() => {
            $('#recent-logs').removeClass('smart-shield-pulse');
        }, 1000);
    }
    
    // Show notification
    function showNotification(message, type) {
        var notificationClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notification = $('<div class="notice ' + notificationClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notification);
        
        // Auto-dismiss after 3 seconds
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // Auto-refresh functionality (every 30 seconds)
    var autoRefreshInterval = 30000; // 30 seconds
    
    setInterval(function() {
        // Only auto-refresh if we're on the dashboard page
        if (window.location.href.includes('page=smart-shield') && !window.location.href.includes('page=smart-shield-')) {
            refreshStats();
            refreshLogs();
        }
    }, autoRefreshInterval);
    
    // Function to refresh stats without user interaction
    function refreshStats() {
        $.ajax({
            url: smart_shield_ajax.ajaxurl,
            method: 'POST',
            data: {
                action: 'smart_shield_get_stats',
                type: 'stats',
                nonce: smart_shield_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatsCards(response.data);
                }
            }
        });
    }
    
    // Function to refresh logs without user interaction
    function refreshLogs() {
        $.ajax({
            url: smart_shield_ajax.ajaxurl,
            method: 'POST',
            data: {
                action: 'smart_shield_get_stats',
                type: 'logs',
                nonce: smart_shield_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateRecentLogs(response.data);
                }
            }
        });
    }
    
    // Animation classes are now in admin.css
    
    // Add hover effects to setting cards
    $('.smart-shield-setting-card').hover(
        function() {
            $(this).css('transform', 'translateY(-2px)');
        },
        function() {
            $(this).css('transform', 'translateY(0)');
        }
    );
    
    // Add click animation to buttons
    $('.smart-shield-refresh-btn').on('mousedown', function() {
        $(this).css('transform', 'scale(0.95)');
    }).on('mouseup mouseleave', function() {
        $(this).css('transform', 'scale(1)');
    });
    
    // Initialize tooltips if available
    if (typeof $.fn.tooltip !== 'undefined') {
        $('[data-tooltip]').tooltip();
    }
    
    // All styles are now in admin.css
}); 