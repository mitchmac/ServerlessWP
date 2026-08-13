jQuery(document).ready(function($) {
    console.log('YCTVN Media Offload Admin JS Loaded!');
    
    // Save settings via AJAX
    $('#save-settings').click(function(e) {
        e.preventDefault();
        console.log('Save button clicked!');
        
        var button = $(this);
        var result = $('#save-result');
        var form = button.closest('form');
        
        console.log('Form:', form.length);
        console.log('Result div:', result.length);
        
        button.prop('disabled', true).text('Saving...');
        result.hide();
        
        $.post(ajaxurl, {
            action: 'yctvn_media_offload_save_settings',
            nonce: yctvn_media_offload_admin.save_settings_nonce,
            settings: form.serialize()
        }, function(response) {
            if (response.success) {
                result.html('<div class="notice notice-success inline" style="padding: 10px; margin: 0;"><p><strong>✅ ' + response.data.message + '</strong></p></div>').show();
                
                // Show warning if connection failed
                if (response.data.warning) {
                    result.append('<div class="notice notice-warning inline" style="padding: 10px; margin: 10px 0 0 0;"><p><strong>⚠️ ' + response.data.warning + '</strong></p></div>');
                }
                
                // Auto-hide success message after 3 seconds
                setTimeout(function() {
                    result.fadeOut();
                }, 3000);
            } else {
                result.html('<div class="notice notice-error inline" style="padding: 10px; margin: 0;"><p><strong>❌ ' + response.data + '</strong></p></div>').show();
            }
        }).fail(function() {
            result.html('<div class="notice notice-error inline" style="padding: 10px; margin: 0;"><p><strong>❌ Failed to save settings. Please try again.</strong></p></div>').show();
        }).always(function() {
            button.prop('disabled', false).text('Save Changes');
        });
    });
    
    $('#test-connection').click(function() {
        var button = $(this);
        var result = $('#test-result');
        
        button.prop('disabled', true).text('Testing...');
        result.hide();
        
        $.post(ajaxurl, {
            action: 'yctvn_media_offload_test_connection',
            nonce: yctvn_media_offload_admin.test_connection_nonce
        }, function(response) {
            if (response.success) {
                result.html('<div class="notice notice-success"><p>' + response.data + '</p></div>').show();
            } else {
                result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>').show();
            }
        }).always(function() {
            button.prop('disabled', false).text(yctvn_media_offload_admin.test_connection_text);
        });
    });
    
    // Fix all thumbnails button
    $('#fix-all-thumbnails').click(function() {
        var button = $(this);
        var result = $('#test-result');
        
        if (!confirm(yctvn_media_offload_admin.fix_thumbnails_confirm)) {
            return;
        }
        
        button.prop('disabled', true).text('Fixing thumbnails...');
        result.hide();
        
        $.post(ajaxurl, {
            action: 'yctvn_media_offload_fix_all_thumbnails',
            nonce: yctvn_media_offload_admin.fix_thumbnails_nonce
        }, function(response) {
            if (response.success) {
                result.html('<div class="notice notice-success"><p>' + response.data + '</p></div>').show();
            } else {
                result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>').show();
            }
        }).always(function() {
            button.prop('disabled', false).text(yctvn_media_offload_admin.fix_thumbnails_text);
        });
    });
    
    // Run sync now button
    $('#run-sync-now').click(function() {
        var button = $(this);
        var result = $('#test-result');
        
        button.prop('disabled', true).text('Running sync...');
        result.hide();
        
        $.post(ajaxurl, {
            action: 'yctvn_media_offload_run_auto_sync',
            nonce: yctvn_media_offload_admin.run_sync_nonce
        }, function(response) {
            if (response.success) {
                result.html('<div class="notice notice-success"><p>' + response.data + '</p></div>').show();
            } else {
                result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>').show();
            }
        }).always(function() {
            button.prop('disabled', false).text(yctvn_media_offload_admin.run_sync_text);
        });
    });
});
