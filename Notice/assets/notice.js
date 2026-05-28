/**
 * TypechoNotice - 前端通知控制腳本
 * 使用Cookie記住用戶的「不再顯示」選擇
 */
(function() {
    'use strict';

    var cookieDays = window.typechoNoticeCookieDays || 7;

    /**
     * 設置Cookie
     */
    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        var secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax' + secure;
    }

    /**
     * 獲取Cookie
     */
    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i].trim();
            if (c.indexOf(nameEQ) === 0) {
                return decodeURIComponent(c.substring(nameEQ.length));
            }
        }
        return null;
    }

    /**
     * 獲取已隱藏的通知ID列表
     */
    function getDismissedNotices() {
        var dismissed = getCookie('typecho_notice_dismissed');
        if (!dismissed) return [];
        try {
            return JSON.parse(dismissed);
        } catch (e) {
            return [];
        }
    }

    /**
     * 記錄已隱藏的通知
     */
    function dismissNotice(noticeId) {
        var dismissed = getDismissedNotices();
        if (dismissed.indexOf(noticeId) === -1) {
            dismissed.push(noticeId);
        }
        setCookie('typecho_notice_dismissed', JSON.stringify(dismissed), cookieDays);
    }

    /**
     * 隱藏通知元素（帶動畫）
     */
    function hideNotice(element) {
        element.classList.add('notice-hiding');
        setTimeout(function() {
            element.style.display = 'none';
            checkContainerEmpty();
        }, 300);
    }

    /**
     * 檢查容器是否為空
     */
    function checkContainerEmpty() {
        var container = document.getElementById('typecho-notice-container');
        if (!container) return;
        
        var visibleItems = container.querySelectorAll('.typecho-notice-item:not([style*="display: none"])');
        if (visibleItems.length === 0) {
            container.style.display = 'none';
        }
    }

    /**
     * 初始化
     */
    function init() {
        var container = document.getElementById('typecho-notice-container');
        if (!container) return;

        var dismissed = getDismissedNotices();
        var items = container.querySelectorAll('.typecho-notice-item');

        // 隱藏已dismiss的通知
        items.forEach(function(item) {
            var noticeId = item.getAttribute('data-notice-id');
            if (dismissed.indexOf(noticeId) !== -1) {
                item.style.display = 'none';
            }
        });

        checkContainerEmpty();

        // 綁定關閉按鈕
        container.addEventListener('click', function(e) {
            var target = e.target;

            // 關閉按鈕 - 僅隱藏本次
            if (target.classList.contains('typecho-notice-close')) {
                var noticeItem = target.closest('.typecho-notice-item');
                if (noticeItem) {
                    hideNotice(noticeItem);
                }
            }

            // 不再顯示按鈕 - 記入Cookie
            if (target.classList.contains('typecho-notice-dismiss')) {
                var noticeId = target.getAttribute('data-notice-id');
                var noticeItem = target.closest('.typecho-notice-item');
                if (noticeId && noticeItem) {
                    dismissNotice(noticeId);
                    hideNotice(noticeItem);
                }
            }
        });
    }

    // DOM載入後初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
