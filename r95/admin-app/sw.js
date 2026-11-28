/* H View Admin app service worker (served at /admin/sw.js, scope /admin/). It does not cache pages:
   the admin always shows live data. It only makes the admin installable as its own app and is ready for push alerts. */
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function () { /* network as normal */ });
self.addEventListener('push', function (e) {
  var d = {}; try { d = e.data ? e.data.json() : {}; } catch (x) { d = { title: 'H View', body: e.data ? e.data.text() : '' }; }
  e.waitUntil(self.registration.showNotification(d.title || 'H View Admin', { body: d.body || '', icon: '/assets/admin-app/icon-192.png', badge: '/assets/admin-app/icon-192.png', data: { url: d.url || '/admin/' }, tag: d.tag || undefined }));
});
self.addEventListener('notificationclick', function (e) {
  e.notification.close(); var url = (e.notification.data && e.notification.data.url) || '/admin/';
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (cs) {
    for (var i = 0; i < cs.length; i++) { if (cs[i].url.indexOf('/admin/') !== -1 && 'focus' in cs[i]) { cs[i].navigate(url); return cs[i].focus(); } }
    return self.clients.openWindow(url);
  }));
});
