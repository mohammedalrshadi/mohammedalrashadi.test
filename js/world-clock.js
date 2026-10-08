document.addEventListener('DOMContentLoaded', () => {
    initWorldClock();
});

function initWorldClock() {
    const hands = [
        {
            s: document.getElementById('sec-hand-desktop')
        },
        {
            s: document.getElementById('sec-hand-mobile')
        },
        {
            s: document.getElementById('sec-hand-hero')
        }
    ];

    const digital = {
        desktop: {
            time: document.getElementById('digital-time-desktop'),
            tz: document.getElementById('detail-tz-desktop')
        },
        mobile: {
            time: document.getElementById('digital-time-mobile'),
            tz: document.getElementById('detail-tz-mobile')
        },
        hero: {
            time: document.getElementById('digital-time-hero'),
            tz: document.getElementById('detail-tz-hero')
        }
    };

    const greeting = document.getElementById('greeting-prefix');
    if (greeting) {
        greeting.dataset.fallback = greeting.textContent;
    }

    // Generate tick marks in the SVG arc
    document.querySelectorAll('.chrono-arc').forEach(svg => {
        let g = svg.querySelector('.chrono-ticks');
        if (!g) {
            g = document.createElementNS("http://www.w3.org/2000/svg", "g");
            g.setAttribute("class", "chrono-ticks");
            for (let i = 0; i < 60; i++) {
                const isHour = i % 5 === 0;
                const angle = (i * 6) * Math.PI / 180;
                const r1 = 38; // inner ring radius
                const r2 = isHour ? 34 : 36; // inward pointing
                const x1 = 50 + r1 * Math.cos(angle);
                const y1 = 50 + r1 * Math.sin(angle);
                const x2 = 50 + r2 * Math.cos(angle);
                const y2 = 50 + r2 * Math.sin(angle);
                
                const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
                line.setAttribute("x1", x1);
                line.setAttribute("y1", y1);
                line.setAttribute("x2", x2);
                line.setAttribute("y2", y2);
                line.setAttribute("class", isHour ? "chrono-tick-major" : "chrono-tick-minor");
                g.appendChild(line);
            }
            const progressCircle = svg.querySelector('.chrono-ring-progress');
            if (progressCircle) {
                svg.insertBefore(g, progressCircle);
            } else {
                svg.appendChild(g);
            }
        }
    });

    // Formatting rules
    const getTzOffset = () => {
        const offset = -new Date().getTimezoneOffset();
        const sign = offset >= 0 ? '+' : '-';
        const hours = String(Math.floor(Math.abs(offset) / 60)).padStart(2, '0');
        const mins = String(Math.abs(offset) % 60).padStart(2, '0');
        return `UTC ${sign}${hours}:${mins}`;
    };
    const tzString = getTzOffset();

    let is24Hour = localStorage.getItem('clockFormat') === '12' ? false : true;

    function getFormatter() {
        return new Intl.DateTimeFormat(undefined, { 
            hour: 'numeric', 
            minute: '2-digit', 
            second: '2-digit', 
            hour12: !is24Hour 
        });
    }

    let tF = getFormatter();

    document.querySelectorAll('.chrono-clock').forEach(clock => {
        clock.style.cursor = 'pointer';
        clock.addEventListener('click', () => {
            is24Hour = !is24Hour;
            localStorage.setItem('clockFormat', is24Hour ? '24' : '12');
            tF = getFormatter();
            updateClock(); // force immediate update
        });
    });
    
    // Set Timezone names once
    if (digital.desktop.tz) digital.desktop.tz.textContent = tzString;
    if (digital.mobile.tz) digital.mobile.tz.textContent = tzString;
    if (digital.hero.tz) digital.hero.tz.textContent = tzString;

    function updateClock() {
        const now = new Date();
        const h = now.getHours();
        const m = now.getMinutes();
        const s = now.getSeconds();
        
        // Greeting Update 
        if (greeting) {
            let word = greeting.dataset.fallback;
            if (h >= 5 && h < 12) word = "Good morning";
            else if (h >= 12 && h < 18) word = "Good afternoon";
            else word = "Good evening";
            if (greeting.textContent !== word) greeting.textContent = word;
        }

        // Digital Time strings
        const timeStr = tF.format(now).toUpperCase();
        
        Object.values(digital).forEach(view => {
            if (view.time && view.time.textContent !== timeStr) view.time.textContent = timeStr;
        });
        
        const secAng = s * 6;
        
        hands.forEach(set => {
            if (set.s) set.s.style.transform = `rotate(${secAng}deg)`;
        });

        // Update progress rings
        const circ = 2 * Math.PI * 46;
        const strokeDash = (s / 60) * circ;
        
        const progDesktop = document.getElementById('chrono-progress-desktop');
        if (progDesktop) {
            progDesktop.style.strokeDasharray = `${strokeDash} ${circ}`;
        }
        
        const progMobile = document.getElementById('chrono-progress-mobile');
        if (progMobile) {
            progMobile.style.strokeDasharray = `${strokeDash} ${circ}`;
        }

        const progHero = document.getElementById('chrono-progress-hero');
        if (progHero) {
            progHero.style.strokeDasharray = `${strokeDash} ${circ}`;
        }
    }
    
    updateClock();
    setInterval(updateClock, 1000);
}
