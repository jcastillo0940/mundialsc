importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: 'AIzaSyBEhsaKSnOZm3BXiiF1Zm7Jom0ZJsxjKhw',
  authDomain: 'mundial-supercarnes.firebaseapp.com',
  projectId: 'mundial-supercarnes',
  storageBucket: 'mundial-supercarnes.firebasestorage.app',
  messagingSenderId: '333655603238',
  appId: '1:333655603238:web:65780d5dae2fc6db1ccee7',
  measurementId: 'G-BD1XLQY33D',
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
  const title = payload?.notification?.title || payload?.data?.title || 'Nueva promocion';
  const options = {
    body: payload?.notification?.body || payload?.data?.body || 'Tienes una nueva notificacion.',
    icon: payload?.notification?.icon || '/favicon.svg',
    badge: '/favicon.svg',
    image: payload?.notification?.image || payload?.data?.image_url || undefined,
    data: {
      url: payload?.fcmOptions?.link || payload?.data?.button_url || '/',
    },
  };

  self.registration.showNotification(title, options);
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const targetUrl = event.notification.data?.url || '/';
  event.waitUntil(clients.openWindow(targetUrl));
});
