/**
 * Maps voting and likes functionality
 */

$(document).ready(function() {
    // Cache for loaded likes data
    var likesCache = {};
    
    // Active AJAX requests tracker
    var activeRequests = {};
    
    // Show likes tooltip on hover
    $(document).on('mouseenter', '.maps_content_list_item_footer_like', function() {
        var btn = $(this);
        var mapId = btn.data('map-id');
        var tooltip = btn.find('.likes-tooltip');
        
        // If tooltip already exists, don't do anything
        if (tooltip.length > 0) {
            return;
        }
        
        // Get likes count
        var likesCount = parseInt(btn.find('.maps_content_list_item_footer_like_count').text());
        if (likesCount === 0 || isNaN(likesCount)) {
            return;
        }
        
        // Check if data is already cached
        if (likesCache[mapId]) {
            // Show cached data immediately
            var cachedTooltip = $('<div class="likes-tooltip">' + likesCache[mapId] + '</div>');
            btn.append(cachedTooltip);
            return;
        }
        
        // Check if request is already in progress
        if (activeRequests[mapId]) {
            return;
        }
        
        // Show loading state
        tooltip = $('<div class="likes-tooltip"><div class="tooltip-loading">Загрузка...</div></div>');
        btn.append(tooltip);
        
        // Mark request as active
        activeRequests[mapId] = true;
        
        // Load likes list via AJAX
        $.ajax({
            url: '/maps/get-likes',
            method: 'GET',
            data: { id: mapId },
            success: function(response) {
                // Remove from active requests
                delete activeRequests[mapId];
                
                if (response.users && response.users.length > 0) {
                    var html = '<div class="tooltip-title">Лайки:</div>';
                    
                    response.users.forEach(function(user) {
                        html += `
                            <div class="tooltip-user">
                                <img src="${user.avatar}" width="24" height="24" alt="${user.username}">
                                <span>${user.username}</span>
                            </div>
                        `;
                    });
                    
                    // Show "and N more" if total > displayed count
                    var remaining = response.total - response.users.length;
                    if (remaining > 0) {
                        var word = remaining === 1 ? 'человек' : (remaining < 5 ? 'человека' : 'человек');
                        html += `<div class="tooltip-more">и ещё ${remaining} ${word}</div>`;
                    }
                    
                    // Cache the result
                    likesCache[mapId] = html;
                    
                    // Update tooltip if it still exists (user hasn't moved mouse away)
                    var currentTooltip = btn.find('.likes-tooltip');
                    if (currentTooltip.length > 0) {
                        currentTooltip.html(html);
                    }
                } else {
                    btn.find('.likes-tooltip').remove();
                }
            },
            error: function() {
                // Remove from active requests
                delete activeRequests[mapId];
                btn.find('.likes-tooltip').remove();
            }
        });
    });
    
    // Remove tooltip on mouse leave
    $(document).on('mouseleave', '.maps_content_list_item_footer_like', function() {
        $(this).find('.likes-tooltip').remove();
    });
});

