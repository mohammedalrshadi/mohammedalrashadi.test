/**
 * Clock of Life - Space Colonization Tree Generator
 * Generates an organic tree growing out of the clock face based on the current day.
 */

const CONFIG = {
    stepLength: 3,
    killDistance: 6,
    influenceDistance: 45,
    maxSegments: 3500,
    attractorCountInside: 800,
    attractorCountOutside: 1200, // 40% outside
    scaleFactor: 1.8,
    branchBaseWidth: 4.0, // thick trunks
    branchMinWidth: 1.0,  // visible tips
    colorPulse: '#34D399', // Mint colored
    pulseDurationMs: 1500,
    growDurationMs: 300
};

function mulberry32(a) {
    return function() {
      var t = a += 0x6D2B79F5;
      t = Math.imul(t ^ t >>> 15, t | 1);
      t ^= t + Math.imul(t ^ t >>> 7, t | 61);
      return ((t ^ t >>> 14) >>> 0) / 4294967296;
    }
}

class Node {
    constructor(x, y, parent, gen) {
        this.x = x;
        this.y = y;
        this.parent = parent;
        this.gen = gen;
        this.children = [];
        this.descendantCount = 0;
        this.birthTimeMs = 0;
        this.revealed = false;
        this.animProgress = 0;
        this.activePulse = null;
        if (parent) parent.children.push(this);
    }
}

let instances = [];
let prefersReduced = false;

function generateTree(rng, clockRadius) {
    const attractors = [];
    
    // Inside clock
    for (let i = 0; i < CONFIG.attractorCountInside; i++) {
        let r = clockRadius * 0.95 * Math.sqrt(rng());
        let theta = rng() * 2 * Math.PI;
        attractors.push({x: r * Math.cos(theta), y: r * Math.sin(theta)});
    }
    // Outside clock (biased left and bottom)
    let maxR = clockRadius * (CONFIG.scaleFactor / 2);
    for (let i = 0; i < CONFIG.attractorCountOutside; i++) {
        let r = clockRadius + (maxR - clockRadius) * rng();
        let theta = rng() * 2 * Math.PI;
        if (rng() > 0.4) {
            theta = (rng() * Math.PI) + (Math.PI / 2); // Bias left/bottom (between PI/2 and 3PI/2)
        }
        attractors.push({x: r * Math.cos(theta), y: r * Math.sin(theta)});
    }

    const nodes = [new Node(0, 0, null, 0)];
    let activeNodes = [...nodes];
    let gen = 1;

    while (attractors.length > 0 && nodes.length < CONFIG.maxSegments) {
        let attractorsForNode = new Map();
        for (let i = 0; i < attractors.length; i++) {
            let a = attractors[i];
            let minDist = CONFIG.influenceDistance;
            let nearest = null;
            for (let n of activeNodes) {
                let d = Math.hypot(n.x - a.x, n.y - a.y);
                if (d < minDist) {
                    minDist = d;
                    nearest = n;
                }
            }
            if (nearest) {
                if (!attractorsForNode.has(nearest)) attractorsForNode.set(nearest, []);
                attractorsForNode.get(nearest).push(a);
            }
        }

        if (attractorsForNode.size === 0) break;

        let newNodes = [];
        for (let [node, attrs] of attractorsForNode.entries()) {
            let dirX = 0, dirY = 0;
            for (let a of attrs) {
                let d = Math.hypot(a.x - node.x, a.y - node.y);
                dirX += (a.x - node.x) / d;
                dirY += (a.y - node.y) / d;
            }
            let len = Math.hypot(dirX, dirY);
            dirX = (dirX / len) * CONFIG.stepLength;
            dirY = (dirY / len) * CONFIG.stepLength;

            let newNode = new Node(node.x + dirX, node.y + dirY, node, gen);
            nodes.push(newNode);
            newNodes.push(newNode);
            activeNodes.push(newNode);
        }

        for (let i = attractors.length - 1; i >= 0; i--) {
            let a = attractors[i];
            for (let n of newNodes) {
                if (Math.hypot(n.x - a.x, n.y - a.y) < CONFIG.killDistance) {
                    attractors.splice(i, 1);
                    break;
                }
            }
        }
        gen++;
    }

    function calcDescendants(n) {
        n.descendantCount = 1;
        for (let c of n.children) n.descendantCount += calcDescendants(c);
        return n.descendantCount;
    }
    calcDescendants(nodes[0]);

    let maxGen = gen;
    for (let n of nodes) {
        n.birthTimeMs = (n.gen / maxGen) * 86400000;
    }

    return { nodes, tips: nodes.filter(n => n.children.length === 0) };
}

function getColors() {
    const style = getComputedStyle(document.documentElement);
    return {
        branch: style.getPropertyValue('--color-primary').trim() || '#34D399',
        glow: style.getPropertyValue('--color-on-primary-container').trim() || '#D1FAE5',
        pulse: CONFIG.colorPulse
    };
}

class ClockInstance {
    constructor(container) {
        this.container = container;
        this.canvas = container.querySelector('.clock-of-life-canvas');
        this.ctx = this.canvas.getContext('2d');
        
        // Hands
        this.hourHand = container.querySelector('.watch-hour');
        this.minHand = container.querySelector('.watch-min');
        this.secHand = container.querySelector('.watch-sec');
        
        this.clockRadius = 0;
        this.canvasSize = 0;
        this.treeNodes = [];
        this.pulses = [];
        
        this.resizeObserver = new ResizeObserver(entries => {
            for (let entry of entries) {
                const rect = entry.contentRect;
                if (rect.width > 0 && rect.height > 0) {
                    this.updateSize(rect.width);
                }
            }
        });
        this.resizeObserver.observe(this.container);
        
        const rect = this.container.getBoundingClientRect();
        if (rect.width > 0) {
            this.updateSize(rect.width);
        } else {
            this.updateSize(parseInt(container.dataset.size, 10) || 128);
        }
    }
    
    updateSize(width) {
        const newRadius = width / 2;
        if (Math.abs(this.clockRadius - newRadius) < 1) return;
        
        this.size = width;
        this.clockRadius = newRadius;
        this.canvasSize = width * CONFIG.scaleFactor;
        
        this.canvas.style.width = this.canvasSize + 'px';
        this.canvas.style.height = this.canvasSize + 'px';
        this.canvas.style.position = 'absolute';
        this.canvas.style.left = '50%';
        this.canvas.style.top = '50%';
        this.canvas.style.transform = 'translate(-50%, -50%)';
        
        this.resize();
        this.initTree();
        
        console.log(`[Clock of Life] Re-generated tree for ${width}px container.`);
        console.log(`[Clock of Life] Nodes: ${this.nodes ? this.nodes.length : 0}`);
    }

    resize() {
        const dpr = window.devicePixelRatio || 1;
        this.canvas.width = this.canvasSize * dpr;
        this.canvas.height = this.canvasSize * dpr;
        this.ctx.scale(dpr, dpr);
        this.ctx.translate(this.canvasSize / 2, this.canvasSize / 2);
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
    }

    initTree() {
        const now = new Date();
        const dateStr = `${now.getFullYear()}-${now.getMonth()+1}-${now.getDate()}`;
        const startOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
        const rng = mulberry32(startOfDay);
        const treeData = generateTree(rng, this.clockRadius);
        this.nodes = treeData.nodes;
        this.tips = treeData.tips;
        this.startOfDay = startOfDay;
        this.colors = getColors();
        
        // Precompute path from root for tips (for pulses)
        this.tips.forEach(tip => {
            let path = [];
            let curr = tip;
            while (curr) {
                path.push(curr);
                curr = curr.parent;
            }
            tip.rootPath = path.reverse();
        });
        
        this.lastPulseTime = 0;
    }

    updateAndDraw(nowMs, isVisible) {
        const msSinceMidnight = nowMs - this.startOfDay;
        
        // Update theme colors if changed
        if (Math.random() < 0.02) this.colors = getColors();

        this.ctx.clearRect(-this.canvasSize/2, -this.canvasSize/2, this.canvasSize, this.canvasSize);
        
        // Draw Tree
        this.ctx.strokeStyle = this.colors.branch;
        this.ctx.shadowBlur = 0;
        
        let pathBegun = false;
        
        for (let i = 1; i < this.nodes.length; i++) {
            let n = this.nodes[i];
            let p = n.parent;
            
            if (prefersReduced) {
                n.revealed = true;
                n.animProgress = 1;
            } else {
                if (msSinceMidnight >= n.birthTimeMs) {
                    n.revealed = true;
                    if (n.animProgress < 1) {
                        n.animProgress += 16 / CONFIG.growDurationMs;
                        if (n.animProgress > 1) n.animProgress = 1;
                    }
                }
            }

            if (!n.revealed) continue;

            let width = CONFIG.branchMinWidth + (n.descendantCount / 100) * CONFIG.branchBaseWidth;
            width = Math.min(width, CONFIG.branchBaseWidth);
            this.ctx.lineWidth = width;

            let x2 = p.x + (n.x - p.x) * n.animProgress;
            let y2 = p.y + (n.y - p.y) * n.animProgress;
            
            let fadeDist = this.clockRadius * 0.8;
            let fadeOpacity = 1.0;
            if (x2 < -fadeDist) {
                let maxFade = this.clockRadius * (CONFIG.scaleFactor/2);
                let prog = (-x2 - fadeDist) / (maxFade - fadeDist);
                fadeOpacity = Math.max(0, 1 - prog);
            }

            this.ctx.beginPath();
            this.ctx.moveTo(p.x, p.y);
            this.ctx.lineTo(x2, y2);
            
            this.ctx.globalAlpha = 0.15 * n.animProgress * fadeOpacity;
            this.ctx.strokeStyle = this.colors.glow;
            this.ctx.stroke();
            
            this.ctx.globalAlpha = (0.2 + 0.6 * n.animProgress) * fadeOpacity;
            this.ctx.strokeStyle = this.colors.branch;
            this.ctx.stroke();
            
            // Draw glowing node at junction occasionally
            if (n.animProgress === 1 && n.children.length > 1 && n.gen % 5 === 0) {
                this.ctx.globalAlpha = fadeOpacity;
                this.ctx.fillStyle = this.colors.glow;
                this.ctx.beginPath();
                this.ctx.arc(n.x, n.y, width * 0.8, 0, Math.PI * 2);
                this.ctx.fill();
            }
            this.ctx.globalAlpha = 1.0;
        }
        
        // Handle Pulses
        if (!prefersReduced && nowMs - this.lastPulseTime > 1000) {
            this.lastPulseTime = nowMs;
            if (this.tips.length > 0) {
                let tip = this.tips[Math.floor(Math.random() * this.tips.length)];
                this.pulses.push({ path: tip.rootPath, startTime: nowMs });
            }
        }
        
        if (!prefersReduced && this.pulses.length > 0) {
            this.ctx.strokeStyle = this.colors.pulse;
            this.ctx.shadowColor = this.colors.pulse;
            this.ctx.shadowBlur = 5;
            
            for (let i = this.pulses.length - 1; i >= 0; i--) {
                let pulse = this.pulses[i];
                let progress = (nowMs - pulse.startTime) / CONFIG.pulseDurationMs;
                
                if (progress > 1) {
                    this.pulses.splice(i, 1);
                    continue;
                }
                
                let targetIndex = Math.floor(progress * (pulse.path.length - 1));
                let pNode = pulse.path[targetIndex];
                if (pNode && pNode.revealed) {
                    this.ctx.lineWidth = 1.5;
                    this.ctx.beginPath();
                    this.ctx.arc(pNode.x, pNode.y, 1.5, 0, Math.PI*2);
                    this.ctx.stroke();
                }
            }
            this.ctx.shadowBlur = 0;
        }
    }
    
    updateHands(now) {
        const s = prefersReduced ? now.getSeconds() : now.getSeconds() + now.getMilliseconds()/1000;
        const m = now.getMinutes();
        const h = now.getHours() % 12;
        
        const secAng = s * 6;
        const minAng = (m + s/60) * 6;
        const hourAng = (h + m/60) * 30;
        
        if (this.hourHand) this.hourHand.setAttribute('transform', `rotate(${hourAng} 100 100)`);
        if (this.minHand) this.minHand.setAttribute('transform', `rotate(${minAng} 100 100)`);
        if (this.secHand && !prefersReduced) this.secHand.setAttribute('transform', `rotate(${secAng} 100 100)`);
    }
}

// Global loop
let rAF;
function loop() {
    if (document.visibilityState === 'hidden') return;
    
    const now = new Date();
    const nowMs = now.getTime();
    
    const dF = new Intl.DateTimeFormat(undefined, { weekday: 'short', day: 'numeric', month: 'short' });
    const tF = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' });
    const fullDateStr = dF.format(now).replace(/,/g, '');
    
    document.querySelectorAll('.watch-date-full').forEach(d => {
        if (d && d.textContent !== fullDateStr) d.textContent = fullDateStr;
    });
    
    const str = `${fullDateStr} · ${tF.format(now)}`.toUpperCase();
    const captions = [document.getElementById('workspace-caption-desktop'), document.getElementById('workspace-caption-mobile')].filter(Boolean);
    captions.forEach(c => { if (c.textContent !== str) c.textContent = str; });
    
    const greeting = document.getElementById('greeting-prefix');
    if (greeting && greeting.dataset.fallback) {
        const h = now.getHours();
        let word = greeting.dataset.fallback;
        if (h >= 5 && h < 12) word = "Good morning";
        else if (h >= 12 && h < 18) word = "Good afternoon";
        else word = "Good evening";
        if (greeting.textContent !== word) greeting.textContent = word;
    }
    
    instances.forEach(inst => {
        inst.updateAndDraw(nowMs, true);
        inst.updateHands(now);
    });
    
    if (!prefersReduced) {
        rAF = requestAnimationFrame(loop);
    } else {
        setTimeout(loop, 1000);
    }
}

export function mount() {
    prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('.watch-container').forEach(el => {
        instances.push(new ClockInstance(el));
    });
    
    const greeting = document.getElementById('greeting-prefix');
    if (greeting && !greeting.dataset.fallback) {
        greeting.dataset.fallback = greeting.textContent;
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            if (!prefersReduced) rAF = requestAnimationFrame(loop);
            else loop();
        } else {
            cancelAnimationFrame(rAF);
        }
    });
    
    if (!prefersReduced) rAF = requestAnimationFrame(loop);
    else loop();
}

// Auto-mount
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
} else {
    mount();
}
