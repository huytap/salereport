'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
let checks = 0;
function check(value, message) { assert.ok(value, message); checks++; }
class Element {
    constructor(tag) { this.tag = tag; this.children = []; this.attributes = {}; this.style = {}; this.textContent = ''; }
    get firstChild() { return this.children[0] || null; }
    appendChild(item) { this.children.push(item); }
    removeChild(item) { this.children.splice(this.children.indexOf(item), 1); }
    setAttribute(key, value) { this.attributes[key] = value; }
    getAttribute(key) { return this.attributes[key]; }
    set innerHTML(value) { throw new Error('Notification content must not use innerHTML: ' + value); }
}
const elements = {};
['mvp-reminder-menu', 'mvp-reminder-count', 'mvp-reminder-preview', 'mvp-overdue-count', 'mvp-dispatch-banner'].forEach(id => { elements[id] = new Element('div'); });
elements['mvp-reminder-menu'].attributes = { 'data-summary-url': '/index.php?r=reminder/summary&format=json', 'data-inbox-url': '/index.php?r=reminder/admin' };
const listeners = {}, timers = new Map(); let timerId = 0;
const document = { readyState: 'complete', hidden: false, getElementById: id => elements[id], createElement: tag => new Element(tag), createTextNode: value => ({ tag: '#text', value }), addEventListener: (name, callback) => { listeners[name] = callback; } };
class Request {
    constructor() { Request.all.push(this); }
    open(method, url, async) { this.method = method; this.url = url; this.async = async; }
    setRequestHeader() {}
    send(body) { this.body = body; }
    respond(status, body) { this.status = status; this.responseText = typeof body === 'string' ? body : JSON.stringify(body); this.onload(); }
}
Request.all = [];
const context = { document, XMLHttpRequest: Request, window: { setTimeout: (fn, delay) => { const id = ++timerId; timers.set(id, { fn, delay }); return id; }, clearTimeout: id => timers.delete(id) } };
vm.runInNewContext(fs.readFileSync(__dirname + '/../../js/mvp-reminders.js', 'utf8'), context);
check(Request.all.length === 1 && Request.all[0].method === 'GET' && Request.all[0].async === true, 'Summary must be an asynchronous GET');
check(Request.all[0].url.includes('reminder/summary') && !Request.all[0].url.includes('dispatch'), 'Polling must never dispatch');
listeners.visibilitychange(); check(Request.all.length === 1, 'No overlapping requests');
const malicious = '<img src=x onerror=alert(1)>';
Request.all[0].respond(200, { unread_count: 2, overdue_task_count: 3, items: [{ kind: 'before', title: malicious, task_url: '/index.php?r=task/view&id=1' }], inbox_url: '/index.php?r=reminder/admin', monitor: { healthy: false, messages: ['Worker chưa hoàn tất.'] } });
check(elements['mvp-reminder-count'].textContent === '2' && elements['mvp-overdue-count'].textContent === '3', 'Separate unread and overdue counters');
check(elements['mvp-reminder-preview'].children[0].children[0].children[0].value.includes(malicious), 'Untrusted title remains a text node');
check(elements['mvp-dispatch-banner'].textContent === 'Worker chưa hoàn tất.', 'Admin health banner');
check(timers.size === 1 && [...timers.values()][0].delay === 60000, 'Only one lightweight polling timer');
const staleTimer = [...timers.values()][0].fn;
document.hidden = true; listeners.visibilitychange(); check(timers.size === 0, 'Hidden page suspends timer');
staleTimer(); check(Request.all.length === 1, 'Hidden page does not start a request');
document.hidden = false; listeners.visibilitychange(); check(Request.all.length === 2, 'Visible page refreshes');
Request.all[1].respond(403, { error_code: 'FORBIDDEN' });
check(elements['mvp-reminder-count'].textContent === '—' && elements['mvp-overdue-count'].textContent === '—', 'Authorization failure clears stale counters');
check(!JSON.stringify(elements['mvp-reminder-preview'].children).includes(malicious), 'Authorization failure clears prior task titles');
[...timers.values()][0].fn(); Request.all[2].respond(200, 'invalid-json');
check(elements['mvp-reminder-count'].textContent === '—', 'Invalid JSON is handled without breaking the page');
console.log('PASS: ' + checks + ' reminder client assertions; async read-only polling, no overlap, visibility pause/resume, safe text rendering and stale-data clearing.');
