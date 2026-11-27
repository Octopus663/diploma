import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

// Графіки з даних, вбудованих у сторінку: <canvas data-chart='{"type": ..., "data": ...}'>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        const config = JSON.parse(canvas.dataset.chart);
        config.options = Object.assign({ responsive: true, maintainAspectRatio: false }, config.options || {});
        new Chart(canvas, config);
    });
});

Alpine.start();
