// CONFIG
const CONFIG = {
    seedDuration: 3000,
    particleCount: 12,
    debug: new URLSearchParams(window.location.search).has('debug')
};

class WorldTreeSystem {
    constructor() {
        this.seed = document.getElementById('wt-seed');
        this.ripple = document.getElementById('wt-ripple');
        this.replayBtns = document.querySelectorAll('.js-replay-seed-btn');
        this.clockContainerDesktop = document.getElementById('workspace-watch-desktop');
        this.clockContainerMobile = document.getElementById('workspace-watch-mobile');
        
        this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        
        this.init();
    }

    init() {
        if (!this.seed || !this.ripple) return;
        
        if (this.replayBtns.length > 0) {
            this.replayBtns.forEach(btn => {
                btn.style.display = 'block';
                btn.addEventListener('click', () => this.playSeedAnimation());
            });
        }

        // Only play once per session unless replayed manually
        const hasPlayed = sessionStorage.getItem('wt_seed_played');
        if (!hasPlayed && !this.prefersReducedMotion) {
            // Delay slightly after load
            setTimeout(() => {
                this.playSeedAnimation();
                sessionStorage.setItem('wt_seed_played', 'true');
            }, 500);
        }
    }

    playSeedAnimation() {
        if (this.prefersReducedMotion) return;

        // Determine active clock container (desktop or mobile)
        const activeClock = (this.clockContainerMobile && getComputedStyle(this.clockContainerMobile).display !== 'none' && this.clockContainerMobile.offsetParent !== null) 
            ? this.clockContainerMobile 
            : this.clockContainerDesktop;

        if (!activeClock) return;

        const clockRect = activeClock.getBoundingClientRect();
        // Target the center of the clock
        const targetX = clockRect.left + clockRect.width / 2;
        const targetY = clockRect.top + clockRect.height / 2;

        // Reset elements
        this.seed.classList.remove('hidden');
        this.ripple.classList.add('hidden');
        
        // Remove old animations by cloning and replacing
        const newSeed = this.seed.cloneNode(true);
        this.seed.parentNode.replaceChild(newSeed, this.seed);
        this.seed = newSeed;

        const newRipple = this.ripple.cloneNode(true);
        this.ripple.parentNode.replaceChild(newRipple, this.ripple);
        this.ripple = newRipple;

        // Set target CSS var for the seed
        this.seed.style.setProperty('--target-y', `${targetY}px`);
        this.seed.style.left = `${targetX}px`;
        this.seed.style.animation = `wt-seedSwayAndDrop ${CONFIG.seedDuration}ms cubic-bezier(0.25, 1, 0.5, 1) forwards`;

        // Wait for drop to finish, then trigger ripple and particles
        setTimeout(() => {
            this.seed.classList.add('hidden');
            
            this.ripple.style.left = `${targetX}px`;
            this.ripple.style.top = `${targetY}px`;
            this.ripple.classList.remove('hidden');
            this.ripple.style.animation = `wt-rippleExpand 1.5s ease-out forwards`;

            this.createParticles(targetX, targetY);
            
            // Cleanup ripple
            setTimeout(() => {
                this.ripple.classList.add('hidden');
            }, 1500);
            
            // In Phase 3, this will trigger the root growth.
            if (this.onSeedImpact) this.onSeedImpact();

        }, CONFIG.seedDuration * 0.95); // Trigger slightly before it completely fades
    }

    createParticles(x, y) {
        for (let i = 0; i < CONFIG.particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'wt-particle';
            
            // Random direction and distance
            const angle = Math.random() * Math.PI * 2;
            const velocity = 20 + Math.random() * 40;
            const tx = Math.cos(angle) * velocity;
            const ty = Math.sin(angle) * velocity;
            
            particle.style.left = `${x}px`;
            particle.style.top = `${y}px`;
            particle.style.transition = `transform 0.6s cubic-bezier(0.1, 0.8, 0.3, 1), opacity 0.6s ease`;
            particle.style.transform = `translate(-50%, -50%)`;
            
            document.body.appendChild(particle);
            
            // Trigger animation
            requestAnimationFrame(() => {
                particle.style.transform = `translate(calc(-50% + ${tx}px), calc(-50% + ${ty}px)) scale(0.1)`;
                particle.style.opacity = '0';
            });
            
            setTimeout(() => {
                particle.remove();
            }, 600);
        }
    }

    getFruitSlots() {
        return []; // To be implemented in Phase 4
    }
}

window.WorldTree = new WorldTreeSystem();
