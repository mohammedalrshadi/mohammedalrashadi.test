// assets/js/motion.js
// Handles intersection observer reveals and page transitions

document.addEventListener('DOMContentLoaded', () => {
    // 1. Intersection Observer for Scroll Reveals
    const observerOptions = {
        root: null,
        rootMargin: '0px 0px -50px 0px', // Trigger slightly before it comes into view
        threshold: 0.01
    };

    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('reveal-visible');
                // Once revealed, we don't need to observe it again
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    const revealElements = document.querySelectorAll('.reveal-section');
    revealElements.forEach(el => revealObserver.observe(el));

    // 2. Page Transitions (Entrance / Exit)
    // When the DOM is ready, ensure we remove the is-animating class to fade in the page wrapper
    setTimeout(() => {
        document.documentElement.classList.remove('is-animating');
    }, 10); // Tiny delay to allow CSS to apply

    // Attach exit transition on internal link clicks
    const internalLinks = document.querySelectorAll('a[href^="/"], a[href^="' + window.location.origin + '"]');
    
    internalLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            // Ignore if it has target="_blank", or is a download, or is hash-only, or has specific modifiers
            if (
                link.target === '_blank' ||
                link.hasAttribute('download') ||
                link.getAttribute('href').startsWith('#') ||
                e.ctrlKey || e.metaKey || e.shiftKey || e.altKey
            ) {
                return;
            }

            // Exclude links that might trigger a modal or external auth behavior
            if (link.closest('.no-transition') || link.classList.contains('no-transition')) {
                return;
            }

            const isReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (isReduced) {
                return; // Default immediate navigation
            }

            e.preventDefault();
            const targetUrl = link.getAttribute('href');
            
            // Apply exit class
            document.documentElement.classList.add('is-animating');
            
            // Wait for transition duration (approx 250ms) then navigate
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 250);
        });
    });
});

// For back/forward cache (BFCache) restorations, make sure to remove the animating class
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        document.documentElement.classList.remove('is-animating');
    }
});
