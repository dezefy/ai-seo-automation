jQuery(document).ready(function($) {
    
    // Process individual post
    window.processPost = function(postId, keyword) {
        if (!keyword) {
            alert('Please select a keyword');
            return;
        }
        
        const button = $(`button[onclick="processPost(${postId}, document.getElementById('keyword-${postId}').value)"]`);
        const originalText = button.text();
        
        button.text('Processing...').prop('disabled', true);
        
        $.ajax({
            url: ai_seo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_seo_process',
                post_id: postId,
                keyword: keyword,
                nonce: ai_seo_ajax.nonce
            },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $(`#ai-title-${postId}`).html(result.ai_title);
                        $(`#ai-desc-${postId}`).html(result.ai_description);
                        button.text('Updated!').removeClass('button').addClass('button-primary');
                        setTimeout(() => {
                            button.text(originalText).removeClass('button-primary').addClass('button').prop('disabled', false);
                        }, 2000);
                    } else {
                        alert('Error: ' + result.message);
                        button.text(originalText).prop('disabled', false);
                    }
                } catch (e) {
                    alert('Error processing response');
                    button.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                alert('AJAX error occurred');
                button.text(originalText).prop('disabled', false);
            }
        });
    };
    
    // Load posts for selected post type
    window.loadPosts = function() {
        const postType = $('#post_type').val();
        const tableContainer = $('#posts-table');
        
        tableContainer.html('<p>Loading posts...</p>');
        
        $.ajax({
            url: ai_seo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_seo_get_posts',
                post_type: postType,
                nonce: ai_seo_ajax.nonce
            },
            success: function(response) {
                tableContainer.html(response);
            },
            error: function() {
                tableContainer.html('<p>Error loading posts</p>');
            }
        });
    };
    
    // Bulk process all posts
    window.bulkProcess = function() {
        const postType = $('#post_type').val();
        const button = $('button[onclick="bulkProcess()"]');
        const originalText = button.text();
        
        if (!confirm('Are you sure you want to process all posts? This may take a while.')) {
            return;
        }
        
        button.text('Processing...').prop('disabled', true);
        
        $.ajax({
            url: ai_seo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_seo_bulk_process',
                post_type: postType,
                nonce: ai_seo_ajax.nonce
            },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    alert(result.message);
                    if (result.success) {
                        loadPosts();
                    }
                } catch (e) {
                    alert('Error processing response');
                }
                button.text(originalText).prop('disabled', false);
            },
            error: function() {
                alert('AJAX error occurred');
                button.text(originalText).prop('disabled', false);
            }
        });
    };
    
    // Load media items
    window.loadMediaItems = function() {
        const tableContainer = $('#media-table');
        
        tableContainer.html('<p>Loading media items...</p>');
        
        $.ajax({
            url: ai_seo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_seo_get_media',
                nonce: ai_seo_ajax.nonce
            },
            success: function(response) {
                tableContainer.html(response);
            },
            error: function() {
                tableContainer.html('<p>Error loading media</p>');
            }
        });
    };
    
    // Process individual media
    window.processMedia = function(attachmentId) {
        const button = $(`button[onclick="processMedia(${attachmentId})"]`);
        const originalText = button.text();
        
        button.text('Processing...').prop('disabled', true);
        
        $.ajax({
            url: ai_seo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_seo_process_media',
                attachment_id: attachmentId,
                nonce: ai_seo_ajax.nonce
            },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $(`#ai-alt-${attachmentId}`).html(result.alt_text);
                        button.text('Updated!').removeClass('button').addClass('button-primary');
                        setTimeout(() => {
                            button.text(originalText).removeClass('button-primary').addClass('button').prop('disabled', false);
                        }, 2000);
                    } else {
                        alert('Error: ' + result.message);
                        button.text(originalText).prop('disabled', false);
                    }
                } catch (e) {
                    alert('Error processing response');
                    button.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                alert('AJAX error occurred');
                button.text(originalText).prop('disabled', false);
            }
        });
    };
    
    // Bulk process media
    window.bulkProcessMedia = function() {
        const button = $('button[onclick="bulkProcessMedia()"]');
        const originalText = button.text();
        
        if (!confirm('Process all media items? This may take a while.')) {
            return;
        }
        
        button.text('Processing...').prop('disabled', true);
        
        $.ajax({
            url: ai_seo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_seo_bulk_process_media',
                nonce: ai_seo_ajax.nonce
            },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    alert(result.message);
                    if (result.success) {
                        loadMediaItems();
                    }
                } catch (e) {
                    alert('Error processing response');
                }
                button.text(originalText).prop('disabled', false);
            },
            error: function() {
                alert('AJAX error occurred');
                button.text(originalText).prop('disabled', false);
            }
        });
    };
    
    // Initialize on page load
    if (window.location.href.indexOf('ai-seo-automation') !== -1) {
        loadPosts();
    }
    
    if (window.location.href.indexOf('ai-seo-media') !== -1) {
        loadMediaItems();
    }
});