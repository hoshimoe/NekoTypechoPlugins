/**
 * NekoTypechoLinks - 友情鏈接前端腳本
 *
 * 在訪客瀏覽器端擷取友鏈的 RSS 訂閱並顯示最新文章。
 * 請求由訪客瀏覽器直接發出，不經過本站伺服器，因此不會暴露伺服器 IP。
 *
 * 注意：部分 RSS 來源未開放跨域（CORS），瀏覽器會擷取失敗，此時將安靜降級。
 * 可於插件設置中填寫「RSS 代理地址」以繞過跨域限制。
 */
(function () {
    'use strict';

    var config = window.nekoTypechoLinksConfig || {};
    var itemCount = parseInt(config.rssItemCount, 10) || 3;
    var proxy = config.rssProxy || '';

    /**
     * 建立請求 URL（如設定了代理則套用）
     */
    function buildUrl(rssUrl) {
        if (proxy) {
            return proxy + encodeURIComponent(rssUrl);
        }
        return rssUrl;
    }

    /**
     * 解析 RSS / Atom，回傳 [{title, link}, ...]
     */
    function parseFeed(text) {
        var items = [];
        var parser = new DOMParser();
        var xml = parser.parseFromString(text, 'application/xml');

        if (xml.getElementsByTagName('parsererror').length > 0) {
            return items;
        }

        var nodes = xml.getElementsByTagName('item'); // RSS 2.0
        if (nodes.length > 0) {
            for (var i = 0; i < nodes.length; i++) {
                var titleEl = nodes[i].getElementsByTagName('title')[0];
                var linkEl = nodes[i].getElementsByTagName('link')[0];
                items.push({
                    title: titleEl ? titleEl.textContent.trim() : '(無標題)',
                    link: linkEl ? linkEl.textContent.trim() : '#'
                });
            }
            return items;
        }

        var entries = xml.getElementsByTagName('entry'); // Atom
        for (var j = 0; j < entries.length; j++) {
            var aTitle = entries[j].getElementsByTagName('title')[0];
            var aLinks = entries[j].getElementsByTagName('link');
            var href = '#';
            for (var k = 0; k < aLinks.length; k++) {
                var rel = aLinks[k].getAttribute('rel');
                if (!rel || rel === 'alternate') {
                    href = aLinks[k].getAttribute('href') || '#';
                    break;
                }
            }
            items.push({
                title: aTitle ? aTitle.textContent.trim() : '(無標題)',
                link: href
            });
        }
        return items;
    }

    /**
     * 渲染擷取結果
     */
    function renderItems(container, items) {
        container.classList.remove('is-loading');
        if (!items.length) {
            container.classList.add('is-empty');
            container.textContent = 'RSS 暫無內容或無法擷取';
            return;
        }

        var list = document.createElement('ul');
        items.slice(0, itemCount).forEach(function (item) {
            var li = document.createElement('li');
            var a = document.createElement('a');
            a.href = item.link;
            a.target = '_blank';
            a.rel = 'nofollow noopener';
            a.textContent = item.title;
            li.appendChild(a);
            list.appendChild(li);
        });
        container.textContent = '';
        container.appendChild(list);
    }

    /**
     * 擷取單個友鏈的 RSS
     */
    function fetchOne(container) {
        var rssUrl = container.getAttribute('data-rss-url');
        if (!rssUrl) {
            return;
        }

        container.classList.add('is-loading');
        container.textContent = '正在擷取最新文章…';

        fetch(buildUrl(rssUrl), { headers: { 'Accept': 'application/rss+xml, application/atom+xml, application/xml, text/xml' } })
            .then(function (resp) {
                if (!resp.ok) {
                    throw new Error('HTTP ' + resp.status);
                }
                return resp.text();
            })
            .then(function (text) {
                renderItems(container, parseFeed(text));
            })
            .catch(function () {
                // 跨域或網路錯誤時安靜降級
                container.classList.remove('is-loading');
                container.classList.add('is-empty');
                container.textContent = '無法擷取 RSS';
            });
    }

    function init() {
        var containers = document.querySelectorAll('.neko-typecho-link-rss[data-rss-url]');
        for (var i = 0; i < containers.length; i++) {
            fetchOne(containers[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
