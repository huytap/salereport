(function () {
    'use strict';
    function init() {
        var root = document.getElementById('mvp-reminder-menu');
        if (!root) return;
        var badge = document.getElementById('mvp-reminder-count');
        var list = document.getElementById('mvp-reminder-preview');
        var overdue = document.getElementById('mvp-overdue-count');
        var banner = document.getElementById('mvp-dispatch-banner');
        var timer = null, request = null;
        function clearItems() { while (list.firstChild) list.removeChild(list.firstChild); }
        function textItem(text, href) {
            var li = document.createElement('li');
            var el = document.createElement(href ? 'a' : 'span');
            if (href) el.setAttribute('href', href);
            el.appendChild(document.createTextNode(text)); li.appendChild(el); list.appendChild(li);
        }
        function schedule() {
            if (timer !== null) window.clearTimeout(timer);
            timer = document.hidden ? null : window.setTimeout(refresh, 60000);
        }
        function unavailable() {
            badge.textContent = '—'; overdue.textContent = '—'; clearItems();
            textItem('Chưa tải được thông báo. Mở danh sách để thử lại.', root.getAttribute('data-inbox-url'));
            if (banner) { banner.textContent = 'Chưa tải được trạng thái dispatch.'; banner.style.display = ''; }
        }
        function display(data) {
            badge.textContent = String(data.unread_count);
            overdue.textContent = String(data.overdue_task_count);
            clearItems();
            if (!data.items.length) textItem('Không có thông báo chưa đọc hợp lệ.');
            data.items.forEach(function (item) { textItem((item.kind === 'overdue' ? 'Quá hạn: ' : 'Nhắc lịch: ') + item.title, item.task_url); });
            textItem('Xem tất cả thông báo', data.inbox_url);
            if (banner && data.monitor) {
                banner.textContent = data.monitor.messages.join(' ');
                banner.style.display = data.monitor.healthy ? 'none' : '';
            }
        }
        function refresh() {
            if (document.hidden || request) { schedule(); return; }
            request = new XMLHttpRequest();
            var current = request;
            current.open('GET', root.getAttribute('data-summary-url'), true);
            current.timeout = 8000; current.setRequestHeader('Accept', 'application/json');
            function complete(ok) {
                if (request !== current) return;
                request = null;
                if (ok) { try { display(JSON.parse(current.responseText)); } catch (ignore) { unavailable(); } }
                else unavailable();
                schedule();
            }
            current.onload = function () { complete(current.status === 200); };
            current.onerror = current.ontimeout = function () { complete(false); };
            current.send(null);
        }
        document.addEventListener('visibilitychange', function () {
            if (timer !== null) { window.clearTimeout(timer); timer = null; }
            if (!document.hidden) refresh();
        });
        refresh();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
