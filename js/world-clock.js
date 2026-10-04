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

    const greeting = document.getElementById('greeting-prefix');
    if (greeting) {
        greeting.dataset.fallback = greeting.textContent;
    }

    const captions = [
        document.getElementById('workspace-caption-desktop'),
        document.getElementById('workspace-caption-mobile')
    ].filter(Boolean);

    const dF = new Intl.DateTimeFormat(undefined, { weekday: 'short', day: 'numeric', month: 'short' });
    const tF = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' });

    function updateClock() {
        const now = new Date();
        const h = now.getHours();
        const h12 = h % 12;
        const m = now.getMinutes();
        const s = now.getSeconds();
        const ms = now.getMilliseconds();
        
        // Greeting Update (only once a minute or on first run)
        if (greeting) {
            let word = greeting.dataset.fallback;
            if (h >= 5 && h < 12) word = "Good morning";
            else if (h >= 12 && h < 18) word = "Good afternoon";
            else word = "Good evening";
            if (greeting.textContent !== word) greeting.textContent = word;
        }

        // Caption Update
        const str = `${dF.format(now)} · ${tF.format(now)}`.toUpperCase().replace(/,/g, '');
        captions.forEach(c => {
            if (c.textContent !== str) c.textContent = str;
        });
        
        // Smooth sweeping second hand
        const secAng = (s + ms / 1000) * 6;
        const minAng = (m + s / 60) * 6;
        const hourAng = (h12 + m / 60) * 30;
        
        hands.forEach(set => {
            if (set.h) set.h.style.transform = `rotate(${hourAng}deg)`;
            if (set.m) set.m.style.transform = `rotate(${minAng}deg)`;
            if (set.s) set.s.style.transform = `rotate(${secAng}deg)`;
        });
        
        requestAnimationFrame(updateClock);
    }
    
    updateClock();
}
