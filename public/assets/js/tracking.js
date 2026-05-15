/**
 * Bokonzi — Tracking / Logging
 *
 * Extracted from index.php (lines 9256-9413).
 * Logs user actions (page_view, clicks, form_submit, input_change, copy,
 * navigation, scroll depth, js_error, page_leave) in batches via POST
 * to /api/log.php every 2 s, with sendBeacon fallback on page unload.
 */
(function() {
    try {
    var LOG_URL = (typeof BASE_API !== 'undefined' ? BASE_API : '/api') + '/log.php';
    var SID;
    try { SID = sessionStorage.getItem('bk_sid'); } catch(e) { SID = null; }
    if (!SID) {
        SID = Math.random().toString(36).substr(2) + Date.now().toString(36);
        try { sessionStorage.setItem('bk_sid', SID); } catch(e) {}
    }
    var queue = [];
    var flushTimer = null;
    var pageStart = Date.now();
    var flushing = false;
    var leaveLogged = false;

    function bkLog(action, detail, value, target) {
        try {
            queue.push({
                sid: SID,
                page: (location.pathname + location.search).substring(0, 500),
                action: (action || 'unknown').substring(0, 50),
                detail: ((detail || '') + '').substring(0, 500),
                value: ((value || '') + '').substring(0, 1000),
                target: ((target || '') + '').substring(0, 200),
                screen: (typeof screen !== 'undefined' ? screen.width + 'x' + screen.height : ''),
                lang: (navigator.language || ''),
                referrer: (document.referrer || '').substring(0, 500),
                duration_ms: Date.now() - pageStart,
            });
            if (queue.length > 200) queue.splice(0, queue.length - 200);
            if (!flushTimer) flushTimer = setTimeout(flushLogs, 2000);
        } catch(e) {}
    }

    function flushLogs() {
        flushTimer = null;
        if (queue.length === 0 || flushing) return;
        flushing = true;
        var batch = queue.splice(0, 50);
        var body;
        try { body = JSON.stringify({ events: batch }); } catch(e) { flushing = false; return; }
        try {
            if (navigator.sendBeacon) {
                navigator.sendBeacon(LOG_URL, new Blob([body], { type: 'application/json' }));
            } else if (typeof fetch !== 'undefined') {
                fetch(LOG_URL, { method: 'POST', body: body, headers: { 'Content-Type': 'application/json' }, keepalive: true }).catch(function(){});
            }
        } catch(e) {}
        flushing = false;
        if (queue.length > 0 && !flushTimer) flushTimer = setTimeout(flushLogs, 2000);
    }

    // 1. Page view
    bkLog('page_view', document.title);

    // 2. Clicks (links, buttons)
    document.addEventListener('click', function(e) {
        try {
            var el = e.target.closest ? e.target.closest('a, button, [onclick]') : null;
            if (!el) return;
            var tag = el.tagName.toLowerCase();
            var text = (el.textContent || '').trim().substring(0, 80);
            var href = el.getAttribute('href') || '';
            if (tag === 'a' && href) {
                bkLog('click_link', text, href, el.className || '');
            } else {
                bkLog('click_button', text, '', el.className || '');
            }
        } catch(e) {}
    }, true);

    // 3. Form submits
    document.addEventListener('submit', function(e) {
        try {
            var form = e.target;
            var data = {};
            try {
                var fd = new FormData(form);
                fd.forEach(function(v, k) {
                    if (k !== 'password' && k !== 'mot_de_passe') data[k] = (v + '').substring(0, 100);
                });
            } catch(ex) {}
            bkLog('form_submit', form.action || '', JSON.stringify(data));
        } catch(e) {}
    }, true);

    // 4. Search inputs (debounced)
    var searchTimers = {};
    document.addEventListener('input', function(e) {
        try {
            var el = e.target;
            if (el.tagName !== 'INPUT' && el.tagName !== 'SELECT' && el.tagName !== 'TEXTAREA') return;
            var name = el.name || el.id || el.placeholder || 'input';
            if (el.type === 'password') return;
            clearTimeout(searchTimers[name]);
            searchTimers[name] = setTimeout(function() {
                bkLog('input_change', name, (el.value || '').substring(0, 200));
            }, 1500);
        } catch(e) {}
    }, true);

    // 5. Tab switches / filter changes
    try {
        var origPushState = history.pushState;
        if (origPushState) {
            history.pushState = function() {
                origPushState.apply(history, arguments);
                bkLog('navigation', 'pushState', location.pathname + location.search);
            };
        }
    } catch(e) {}
    window.addEventListener('popstate', function() {
        bkLog('navigation', 'popstate', location.pathname + location.search);
    });

    // 6. Copy actions
    document.addEventListener('copy', function() {
        try {
            var sel = (window.getSelection() || '').toString().substring(0, 200);
            bkLog('copy', 'text_copied', sel);
        } catch(e) {}
    });

    // 7. Scroll depth (on page leave)
    var maxScroll = 0;
    window.addEventListener('scroll', function() {
        try {
            var pct = Math.round((window.scrollY + window.innerHeight) / document.documentElement.scrollHeight * 100);
            if (pct > maxScroll) maxScroll = pct;
        } catch(e) {}
    }, { passive: true });

    // 8. Flush on page leave (une seule fois)
    function onLeave() {
        if (leaveLogged) return;
        leaveLogged = true;
        bkLog('page_leave', 'scroll_depth=' + maxScroll + '%', '', '');
        flushLogs();
    }
    window.addEventListener('beforeunload', onLeave);
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') onLeave();
    });

    // 9. Errors
    window.addEventListener('error', function(e) {
        try {
            bkLog('js_error', (e.message || '').substring(0, 200), (e.filename || '') + ':' + (e.lineno || 0));
        } catch(ex) {}
    });

    // Expose for manual logging
    window.bkLog = bkLog;

    } catch(e) { /* tracker init failed silently */ }
})();
