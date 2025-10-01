importScripts(
    "https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"
);
importScripts(
    "https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js"
);

self.addEventListener("install", (event) => {
    self.skipWaiting();
});

firebase.initializeApp({
    apiKey: "AIzaSyCEMlcfeAigR71a0mqqqZe5sEw-cfJs-08",
    authDomain: "tutor-dev-99ac3.firebaseapp.com",
    projectId: "tutor-dev-99ac3",
    storageBucket: "tutor-dev-99ac3.firebasestorage.app",
    messagingSenderId: "203795571526",
    appId: "1:203795571526:web:4722b34c413a31b4b23b47",
});

const messaging = firebase.messaging();

// messaging.onBackgroundMessage((payload) => {
//     console.log(
//         "[firebase-messaging-sw.js] Received background message ",
//         payload
//     );

//     self.clients
//         .matchAll({ type: "window", includeUncontrolled: true })
//         .then((clients) => {
//             for (const client of clients) {
//                 client.postMessage({
//                     type: "PUSH_RECEIVED",
//                     payload: payload,
//                 });
//             }
//         });

//     const notificationTitle = payload.notification?.title || "New Notification";
//     const notificationOptions = {
//         body: payload.notification?.body || payload.notification?.title || "",
//         icon: "/images/settings/dark_logo.png",
//         data: payload.data || {},
//     };

//     return self.registration.showNotification(
//         notificationTitle,
//         notificationOptions
//     );
// });

self.addEventListener("push", (event) => {
    try {
        const raw = event.data && event.data.json();
        const data = raw.notification || raw.data || {};
        console.log("NOTIFICATION ==========>",raw);

        self.clients
            .matchAll({ type: "window", includeUncontrolled: true })
            .then((clients) => {
                for (const client of clients) {
                    client.postMessage({
                        type: "PUSH_RECEIVED",
                        payload: raw,
                    });
                }
            });

        event.waitUntil(
            self.registration.showNotification(
                data.title || "New Notification",
                {
                    body: data.body || data.title || "",
                    icon: "/images/settings/dark_logo.png",
                    data: data || {},
                }
            )
        );
    } catch (err) {
        console.warn("Push event but not FCM? skipping.", err);
    }
});

self.addEventListener("notificationclick", (event) => {
    event.notification.close();

    const data = event.notification.data || {};
    const clickAction =
        data.click_action || data.FCM_MSG?.notification?.click_action || "/";
    const urlToOpen = new URL(clickAction, self.location.origin).href;
    const targetOrigin = new URL(urlToOpen).origin;

    event.waitUntil(
        self.clients
            .matchAll({
                type: "window",
                includeUncontrolled: true,
            })
            .then((windowClients) => {
                for (const client of windowClients) {
                    const clientOrigin = new URL(client.url).origin;
                    if (
                        clientOrigin === targetOrigin &&
                        "focus" in client &&
                        "navigate" in client
                    ) {
                        client.focus();
                        return client.navigate(urlToOpen);
                    }
                }

                if (self.clients.openWindow) {
                    return self.clients.openWindow(urlToOpen);
                }
            })
    );
});
