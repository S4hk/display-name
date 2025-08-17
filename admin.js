jQuery(document).ready(function($) {
    let isProcessing = false;
    
    $('#start-batch-process').on('click', function() {
        if (isProcessing) return;
        
        isProcessing = true;
        $(this).prop('disabled', true);
        $('#batch-progress').show();
        $('#batch-complete').hide();
        
        processBatch(0, 0);
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
            success: function(response) {
                if (response.success) {
                    totalProcessed += response.data.processed;
                    updateProgress(totalProcessed);
                    
                    if (response.data.has_more) {
                        // Continue processing next batch
                        processBatch(offset + ddnm_ajax.batch_size, totalProcessed);
                    } else {
                        // Processing complete
                        completeBatch();
                    }
                } else {
                    handleError(response.data);
                }
            },
            error: function() {
                handleError('An error occurred during processing.');
            }
        });
    }
    
    function updateProgress(processed) {
        const totalUsers = parseInt($('#progress-text').text().split(' / ')[1]);
        const percentage = (processed / totalUsers) * 100;
        
        $('#progress-text').text(processed + ' / ' + totalUsers);
        $('.progress-fill').css('width', percentage + '%');
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
        alert('Error: ' + message);
    }
});
