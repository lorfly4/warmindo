import Alpine from 'alpinejs';
import { initEcho } from './echo';
import { startBoardPolling } from './board-polling';
import './cart';

window.Alpine = Alpine;
window.startBoardPolling = startBoardPolling;

document.addEventListener('alpine:init', () => {
    Alpine.store('cartCount', 0);
    Alpine.store('cart', {
        items: [],
    });
});

Alpine.start();

if (window.EchoEnabled) {
    try {
        initEcho();
    } catch (error) {
        console.warn('Echo not available:', error);
    }
}


if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', {
                scope: '/'
            })
            .then((registration) => {
                console.log(
                    'Warmindo PWA berhasil didaftarkan:',
                    registration.scope
                );
            })
            .catch((error) => {
                console.error(
                    'Warmindo PWA gagal:',
                    error
                );
            });
    });
}