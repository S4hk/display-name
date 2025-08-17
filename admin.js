(function($) {
    'use strict';
    
    let isProcessing = false;
    
    $(document).ready(function() {
        $('#start-batch-process').on('click', function() {
            if (isProcessing) {
                return;
            }
            
            isProcessing = true;
            $(this).prop('disabled', true);
            $('#batch-progress').show();
            $('#batch-complete').hide();
            
            processBatch(0, 0);
        });
    });
    
    function processBatch(offset, totalProcessed) {
        $.ajax({
            url: ddnm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ddnm_process_batch',
                nonce: ddnm_ajax.nonce,
                offset: offset
            },
            timeout: 30000, // 30 second timeout
            success: function(response) {
                if (response && response.success && response.data) {
                    totalProcessed += parseInt(response.data.processed) || 0;
                    updateProgress(totalProcessed);
                    
                    if (response.data.has_more) {
                        // Continue processing next batch
                        processBatch(offset + parseInt(ddnm_ajax.batch_size), totalProcessed);
                    } else {
                        // Processing complete
                        completeBatch();
                    }
                } else {
                    const errorMessage = response && response.data 
                        ? response.data 
                        : ddnm_ajax.strings.unknown_error;
                    handleError(errorMessage);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                
                let errorMessage = ddnm_ajax.strings.server_error;
                if (status === 'timeout') {
                    errorMessage = 'Request timeout - please try again';
                } else if (error) {
                    errorMessage += ': ' + error;
                }
                
                handleError(errorMessage);
            }
        });
    }
    
    function updateProgress(processed) {
        const progressText = $('#progress-text').text();
        const totalUsers = parseInt(progressText.split(' / ')[1]) || 0;
        
        if (totalUsers > 0) {
            const percentage = Math.min((processed / totalUsers) * 100, 100);
            $('#progress-text').text(processed + ' / ' + totalUsers);
            $('.progress-fill').css('width', percentage + '%');
        }
    }
    
    function completeBatch() {
        isProcessing = false;
        $('#start-batch-process').prop('disabled', false);
        $('#batch-progress').hide();
        $('#batch-complete').show();
    }
    
    function handleError(message) {
        isProcessing = false;
        $('#start-batch-process').prop('disabled', false);
        $('#batch-progress').hide();
        
        const errorPrefix = ddnm_ajax.strings.error_prefix || 'Error';
        alert(errorPrefix + ': ' + message);
    }
    
})(jQuery);
