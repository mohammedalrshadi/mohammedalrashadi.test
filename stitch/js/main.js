document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initGreeting();
    initClock();
});

function initThemeToggle() {
    const toggleBtn = document.getElementById('theme-toggle');
    const htmlEl = document.documentElement;

    // Load saved theme or default to dark
    const savedTheme = localStorage.getItem('theme') || 'dark';
    if (savedTheme === 'light') {
        htmlEl.classList.remove('dark');
        htmlEl.classList.add('light');
    }

    toggleBtn.addEventListener('click', () => {
        if (htmlEl.classList.contains('dark')) {
            htmlEl.classList.remove('dark');
            htmlEl.classList.add('light');
            localStorage.setItem('theme', 'light');
        } else {
            htmlEl.classList.remove('light');
            htmlEl.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        }
    });
}

function initGreeting() {
    const greetingEl = document.getElementById('greeting-text');
    if (!greetingEl) return;

    const hour = new Date().getHours();
    let text = 'Good evening';
    
    if (hour >= 5 && hour < 12) {
        text = 'Good morning';
    } else if (hour >= 12 && hour < 18) {
        text = 'Good afternoon';
    }
    
    greetingEl.textContent = text;
}

function initClock() {
    const hourHand = document.getElementById('hour-hand');
    const minHand = document.getElementById('min-hand');
    const secHand = document.getElementById('sec-hand');
    
    function updateClock() {
        const now = new Date();
        const h = now.getHours() % 12;
        const m = now.getMinutes();
        const s = now.getSeconds();
        const ms = now.getMilliseconds();
        
        // Smooth sweeping second hand
        const secAng = (s + ms / 1000) * 6;
        const minAng = (m + s / 60) * 6;
        const hourAng = (h + m / 60) * 30;
        
        if (hourHand) hourHand.style.transform = `rotate(${hourAng}deg)`;
        if (minHand) minHand.style.transform = `rotate(${minAng}deg)`;
        if (secHand) secHand.style.transform = `rotate(${secAng}deg)`;
        
        requestAnimationFrame(updateClock);
    }
    
    updateClock();
}
