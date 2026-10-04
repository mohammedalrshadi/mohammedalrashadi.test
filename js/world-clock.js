document.addEventListener('DOMContentLoaded', () => {
    initWorldClock();
});

function initWorldClock() {
    const hands = [
        {
            h: document.getElementById('hour-hand-desktop'),
            m: document.getElementById('min-hand-desktop'),
            s: document.getElementById('sec-hand-desktop')
        },
        {
            h: document.getElementById('hour-hand-mobile'),
            m: document.getElementById('min-hand-mobile'),
            s: document.getElementById('sec-hand-mobile')
        }
    ];

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
        
        hands.forEach(set => {
            if (set.h) set.h.style.transform = `rotate(${hourAng}deg)`;
            if (set.m) set.m.style.transform = `rotate(${minAng}deg)`;
            if (set.s) set.s.style.transform = `rotate(${secAng}deg)`;
        });
        
        requestAnimationFrame(updateClock);
    }
    
    updateClock();
}
