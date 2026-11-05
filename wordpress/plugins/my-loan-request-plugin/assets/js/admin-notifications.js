jQuery(document).ready(function($) {
    // Ensure ajax object exists
    if (typeof mlrNotificationAjax === 'undefined') {
        return;
    }

    var $bell = $('<div class="mlr-notification-bell"><span class="dashicons dashicons-bell"></span><span class="mlr-notification-badge hidden">0</span></div>');
    var $panel = $('<div class="mlr-notification-panel"><div class="mlr-notification-header"><h4>اعلان‌ها</h4><span class="mlr-mark-all-read">خواندن همه</span></div><ul class="mlr-notification-list"></ul></div>');
    
    $('body').append($bell).append($panel);

    var lastUnreadCount = -1; // Initialize to -1 to handle first load correctly

    function playNotificationSound() {
        function fallbackBeep() {
            try {
                var ctx = new (window.AudioContext || window.webkitAudioContext)();
                var osc = ctx.createOscillator();
                osc.type = 'sine';
                osc.frequency.value = 800;
                osc.connect(ctx.destination);
                osc.start();
                setTimeout(function() { osc.stop(); }, 150);
            } catch (err) { /* ignore */ }
        }
        if (mlrNotificationAjax.sound_url) {
            var audio = new Audio(mlrNotificationAjax.sound_url);
            audio.play().catch(function() {
                fallbackBeep();
            });
        } else {
            fallbackBeep();
        }
    }

    function fetchNotifications() {
        $.ajax({
            url: mlrNotificationAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'mlr_get_admin_notifications',
                nonce: mlrNotificationAjax.nonce
            },
            success: function(res) {
                if (res.success) {
                    var count = parseInt(res.data.unread_count, 10) || 0;
                    var $badge = $('.mlr-notification-badge');
                    
                    if (count > 0) {
                        $badge.text(count).removeClass('hidden');
                        // Play sound when there are new unread (count increased) — for every admin
                        if (lastUnreadCount !== -1 && count > lastUnreadCount) {
                            playNotificationSound();
                        }
                    } else {
                        $badge.addClass('hidden');
                    }
                    lastUnreadCount = count;

                    var listHtml = '';
                    if (res.data.notifications && res.data.notifications.length > 0) {
                        $.each(res.data.notifications, function(i, n) {
                            var unreadClass = (n.is_read == 0) ? 'unread' : '';
                            var link = n.link ? n.link : '#';
                            listHtml += '<a href="' + link + '" class="mlr-notification-item ' + unreadClass + '" data-id="' + n.id + '">';
                            listHtml += '<div class="mlr-notif-icon"><span class="dashicons ' + n.icon + '"></span></div>';
                            listHtml += '<div class="mlr-notif-content">';
                            listHtml += '<span class="mlr-notif-message">' + n.message + '</span>';
                            listHtml += '<span class="mlr-notif-time">' + n.time_ago + '</span>';
                            listHtml += '</div></a>';
                        });
                    } else {
                        listHtml = '<div class="mlr-notification-empty">هیچ اعلان جدیدی وجود ندارد.</div>';
                    }
                    $('.mlr-notification-list').html(listHtml);
                }
            }
        });
    }

    // Initial fetch
    fetchNotifications();

    // Poll every 15 seconds so admins get notifications without refresh
    setInterval(fetchNotifications, 15000);

    // Toggle panel
    $bell.on('click', function(e) {
        e.stopPropagation();
        $panel.toggleClass('open');
    });

    // Mark all as read
    $(document).on('click', '.mlr-mark-all-read', function() {
        $.post(mlrNotificationAjax.ajax_url, {
            action: 'mlr_mark_notifications_read',
            nonce: mlrNotificationAjax.nonce
        }, function() {
            fetchNotifications();
        });
    });

    // Mark single as read on click
    $(document).on('click', '.mlr-notification-item.unread', function(e) {
        var id = $(this).data('id');
        $.post(mlrNotificationAjax.ajax_url, {
            action: 'mlr_mark_notifications_read',
            nonce: mlrNotificationAjax.nonce,
            id: id
        });
    });

    // Close panel when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.mlr-notification-bell, .mlr-notification-panel').length) {
            $panel.removeClass('open');
        }
    });
});