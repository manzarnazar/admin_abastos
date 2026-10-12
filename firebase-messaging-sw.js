importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');

firebase.initializeApp({
    apiKey: "AIzaSyAxAdlAJ5DB0CG-257qBUdaOzgJj2M3T1M",
    authDomain: "abastos-app.firebaseapp.com",
    projectId: "abastos-app",
    storageBucket: "abastos-app.firebasestorage.app",
    messagingSenderId: "329776474866",
    appId: "1:329776474866:web:2d0a09f52ed1eda4c83e6b",
    measurementId: ""
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function (payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body ? payload.data.body : '',
        icon: payload.data.icon ? payload.data.icon : ''
    });
});