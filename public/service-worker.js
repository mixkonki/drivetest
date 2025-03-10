self.addEventListener('install', event => {
    event.waitUntil(
      caches.open('drivetest-cache').then(cache => {
        return cache.addAll([
          '/public/index.php',
          '/assets/css/styles.css',
          '/assets/js/register.js'
        ]);
      })
    );
  });
  
  self.addEventListener('fetch', event => {
    event.respondWith(
      caches.match(event.request).then(response => {
        return response || fetch(event.request);
      })
    );
  });