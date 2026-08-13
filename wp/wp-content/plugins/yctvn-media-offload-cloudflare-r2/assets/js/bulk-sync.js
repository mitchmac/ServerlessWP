jQuery(document).ready(function($) {
    console.log('Bulk Sync JS Loaded!');
    
    var syncing = false;
    var stopped = false;
    var currentMode = 'full';
    var totalToProcess = 0;
    var processed = 0;
    var successCount = 0;
    var errorCount = 0;
    
    function log(message, type) {
        type = type || 'info';
        var timestamp = new Date().toLocaleTimeString();
        
        var colors = {
            'info': '#61afef',
            'success': '#98c379',
            'error': '#e06c75',
            'warning': '#e5c07b'
        };
        
        var icons = {
            'info': 'ℹ️',
            'success': '✅',
            'error': '❌',
            'warning': '⚠️'
        };
        
        var color = colors[type] || '#d4d4d4';
        var icon = icons[type] || '';
        
        var logEntry = $('<div>')
            .css({
                'margin': '3px 0',
                'padding': '5px',
                'border-left': '3px solid ' + color,
                'background': 'rgba(255,255,255,0.05)'
            })
            .html('<span style="color: #888;">[' + timestamp + ']</span> ' + 
                  '<span style="color: ' + color + ';">' + icon + ' ' + message + '</span>');
        
        $('#sync-log').append(logEntry);
        $('#sync-log').scrollTop($('#sync-log')[0].scrollHeight);
    }
    
    function updateProgress(current, total) {
        processed = current;
        totalToProcess = total;
        
        var percentage = total > 0 ? Math.round((current / total) * 100) : 0;
        $('#progress-bar').css('width', percentage + '%');
        $('#progress-text').text(percentage + '% (' + current + '/' + total + ')');
        $('#processed-count').text(current);
        $('#total-count').text(total);
    }
    
    function updateStatus(status) {
        $('#sync-status').text(status);
    }
    
    function resetUI() {
        syncing = false;
        stopped = false;
        $('#start-sync').prop('disabled', false).show();
        $('#stop-sync').hide();
        updateStatus('Ready');
    }
    
    $('#start-sync').click(function() {
        currentMode = $('input[name="sync-mode"]:checked').val() || 'full';
        startSync();
    });
    
    $('#stop-sync').click(function() {
        stopped = true;
        $(this).prop('disabled', true).text('⏹️ Stopping...');
        updateStatus('Stopping...');
        log('Sync stopped by user', 'warning');
    });
    
    $('#clear-log').click(function() {
        $('#sync-log').empty();
        log('Log cleared', 'info');
    });
    
    function startSync() {
        if (syncing) {
            log('Sync already running!', 'warning');
            return;
        }
        
        syncing = true;
        stopped = false;
        processed = 0;
        successCount = 0;
        errorCount = 0;
        
        $('#start-sync').prop('disabled', true).hide();
        $('#stop-sync').prop('disabled', false).show();
        $('#sync-progress').show();
        
        var batchSize = parseInt($('#batch-size').val()) || 10;
        var regenerateMetadata = $('#regenerate-metadata').is(':checked');
        
        updateStatus('Initializing...');
        log('='.repeat(50), 'info');
        log('Starting ' + currentMode + ' sync', 'info');
        log('Batch size: ' + batchSize, 'info');
        
        if (regenerateMetadata) {
            log('Metadata regeneration: ENABLED', 'warning');
        }
        
        // Start processing immediately
        log('Fetching items to sync...', 'info');
        processBatch(0, batchSize, regenerateMetadata);
    }
    
    function processBatch(offset, batchSize, regenerateMetadata) {
        if (stopped) {
            log('Sync stopped by user', 'warning');
            log('='.repeat(50), 'info');
            log('Summary: ' + successCount + ' success, ' + errorCount + ' errors', 'info');
            resetUI();
            return;
        }
        
        updateStatus('Processing batch ' + Math.floor(offset / batchSize + 1) + '...');
        
        $.post(ajaxurl, {
            action: 'yctvn_media_offload_bulk_sync',
            mode: currentMode,
            offset: offset,
            batch_size: batchSize,
            regenerate_metadata: regenerateMetadata,
            nonce: yctvn_media_offload_bulk.nonce
        }, function(response) {
            if (response.success) {
                var data = response.data;
                processed += data.processed;
                
                // Update total on first batch
                if (offset === 0 && totalToProcess === 0) {
                    // Estimate total based on first batch
                    log('Processing started...', 'info');
                }
                
                // Log individual messages
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(function(msg) {
                        if (msg.type === 'success') {
                            successCount++;
                            log(msg.message, 'success');
                        } else if (msg.type === 'error') {
                            errorCount++;
                            log(msg.message, 'error');
                        } else {
                            log(msg.message, msg.type || 'info');
                        }
                    });
                } else if (data.processed > 0) {
                    log('Batch processed: ' + data.processed + ' items', 'success');
                    successCount += data.processed;
                }
                
                // Update progress (use processed count as total estimate)
                var estimatedTotal = processed + batchSize;
                updateProgress(processed, estimatedTotal);
                
                // Check if there are more items
                if (data.processed > 0 && data.processed === batchSize && !stopped) {
                    // Continue with next batch
                    setTimeout(function() {
                        processBatch(offset + batchSize, batchSize, regenerateMetadata);
                    }, 100); // Small delay
                } else {
                    // Finished
                    updateProgress(processed, processed); // Final update
                    updateStatus('Completed!');
                    log('='.repeat(50), 'info');
                    log('✨ Sync completed successfully!', 'success');
                    log('Total processed: ' + processed + ' items', 'info');
                    log('Success: ' + successCount + ', Errors: ' + errorCount, 'info');
                    resetUI();
                }
            } else {
                log('Error: ' + (response.data || 'Unknown error'), 'error');
                errorCount++;
                resetUI();
            }
        }).fail(function(xhr, status, error) {
            log('Network error: ' + error, 'error');
            log('Please check your connection and try again', 'warning');
            resetUI();
        });
    }
});