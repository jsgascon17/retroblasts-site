// Victory Effects System
// Include this in any game for celebration effects

const VictoryEffects = {
    canvas: null,
    ctx: null,
    particles: [],
    confetti: [],
    fireworks: [],
    animating: false,

    init() {
        // Create overlay canvas
        if (!this.canvas) {
            this.canvas = document.createElement('canvas');
            this.canvas.id = 'victoryCanvas';
            this.canvas.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                pointer-events: none;
                z-index: 99999;
            `;
            document.body.appendChild(this.canvas);
            this.ctx = this.canvas.getContext('2d');
            this.resize();
            window.addEventListener('resize', () => this.resize());
        }
    },

    resize() {
        this.canvas.width = window.innerWidth;
        this.canvas.height = window.innerHeight;
    },

    // Confetti burst - great for wins/achievements
    confettiBurst(options = {}) {
        this.init();
        const count = options.count || 150;
        const colors = options.colors || ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff', '#ffd700', '#ff6b35'];
        
        for (let i = 0; i < count; i++) {
            this.confetti.push({
                x: this.canvas.width / 2 + (Math.random() - 0.5) * 200,
                y: this.canvas.height / 2,
                vx: (Math.random() - 0.5) * 20,
                vy: Math.random() * -20 - 10,
                color: colors[Math.floor(Math.random() * colors.length)],
                size: Math.random() * 10 + 5,
                rotation: Math.random() * 360,
                rotationSpeed: (Math.random() - 0.5) * 10,
                gravity: 0.3 + Math.random() * 0.2,
                life: 1
            });
        }
        this.startAnimation();
    },

    // Fireworks - great for high scores
    fireworks(options = {}) {
        this.init();
        const count = options.count || 5;
        
        for (let i = 0; i < count; i++) {
            setTimeout(() => {
                this.launchFirework(
                    Math.random() * this.canvas.width,
                    this.canvas.height,
                    Math.random() * this.canvas.width,
                    this.canvas.height * 0.2 + Math.random() * this.canvas.height * 0.3
                );
            }, i * 300);
        }
        this.startAnimation();
    },

    launchFirework(x, y, targetX, targetY) {
        const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff', '#ffd700'];
        const color = colors[Math.floor(Math.random() * colors.length)];
        
        this.fireworks.push({
            x, y, targetX, targetY,
            vx: (targetX - x) / 40,
            vy: (targetY - y) / 40,
            color,
            trail: [],
            exploded: false
        });
    },

    explodeFirework(fw) {
        const particleCount = 80;
        for (let i = 0; i < particleCount; i++) {
            const angle = (i / particleCount) * Math.PI * 2;
            const speed = 3 + Math.random() * 5;
            this.particles.push({
                x: fw.x,
                y: fw.y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                color: fw.color,
                size: 3,
                life: 1,
                decay: 0.015 + Math.random() * 0.01
            });
        }
    },

    // Star burst - quick reward feedback
    starBurst(x, y, options = {}) {
        this.init();
        const count = options.count || 20;
        const color = options.color || '#ffd700';
        
        for (let i = 0; i < count; i++) {
            const angle = (i / count) * Math.PI * 2;
            const speed = 5 + Math.random() * 5;
            this.particles.push({
                x: x || this.canvas.width / 2,
                y: y || this.canvas.height / 2,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                color,
                size: 4 + Math.random() * 4,
                life: 1,
                decay: 0.02,
                star: true
            });
        }
        this.startAnimation();
    },

    // Coin shower - for coin rewards
    coinShower(options = {}) {
        this.init();
        const count = options.count || 30;
        
        for (let i = 0; i < count; i++) {
            setTimeout(() => {
                this.particles.push({
                    x: Math.random() * this.canvas.width,
                    y: -20,
                    vx: (Math.random() - 0.5) * 2,
                    vy: 3 + Math.random() * 3,
                    color: '#ffd700',
                    size: 15,
                    life: 1,
                    decay: 0,
                    coin: true,
                    rotation: Math.random() * 360,
                    rotationSpeed: 5 + Math.random() * 5
                });
            }, i * 50);
        }
        this.startAnimation();
    },

    // Rainbow wave - for special achievements
    rainbowWave() {
        this.init();
        const colors = ['#ff0000', '#ff7f00', '#ffff00', '#00ff00', '#0000ff', '#4b0082', '#9400d3'];
        
        for (let c = 0; c < colors.length; c++) {
            setTimeout(() => {
                for (let i = 0; i < 30; i++) {
                    this.particles.push({
                        x: this.canvas.width / 2,
                        y: this.canvas.height,
                        vx: (Math.random() - 0.5) * 15,
                        vy: -15 - Math.random() * 10,
                        color: colors[c],
                        size: 8,
                        life: 1,
                        decay: 0.01,
                        gravity: 0.2
                    });
                }
            }, c * 100);
        }
        this.startAnimation();
    },

    // Screen flash - for impacts
    screenFlash(color = '#ffffff', duration = 100) {
        this.init();
        const flash = document.createElement('div');
        flash.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: ${color};
            opacity: 0.5;
            z-index: 99998;
            pointer-events: none;
        `;
        document.body.appendChild(flash);
        setTimeout(() => flash.remove(), duration);
    },

    // Trophy popup - for achievements
    trophyPopup(title, subtitle, icon = '🏆') {
        const popup = document.createElement('div');
        popup.innerHTML = `
            <div style="
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) scale(0);
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                border: 4px solid #ffd700;
                border-radius: 20px;
                padding: 30px 50px;
                text-align: center;
                z-index: 100000;
                animation: trophyPop 0.5s ease forwards, trophyFade 0.5s ease 2s forwards;
                box-shadow: 0 0 50px rgba(255, 215, 0, 0.5);
            ">
                <div style="font-size: 4rem; margin-bottom: 10px;">${icon}</div>
                <div style="font-size: 1.8rem; color: #ffd700; font-weight: bold; margin-bottom: 5px;">${title}</div>
                <div style="font-size: 1rem; color: #aaa;">${subtitle}</div>
            </div>
            <style>
                @keyframes trophyPop {
                    0% { transform: translate(-50%, -50%) scale(0) rotate(-10deg); }
                    50% { transform: translate(-50%, -50%) scale(1.1) rotate(5deg); }
                    100% { transform: translate(-50%, -50%) scale(1) rotate(0deg); }
                }
                @keyframes trophyFade {
                    to { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
                }
            </style>
        `;
        document.body.appendChild(popup);
        setTimeout(() => popup.remove(), 2500);
    },

    // Victory screen shake
    screenShake(intensity = 10, duration = 300) {
        const body = document.body;
        const originalTransform = body.style.transform;
        const startTime = Date.now();
        
        const shake = () => {
            const elapsed = Date.now() - startTime;
            if (elapsed < duration) {
                const x = (Math.random() - 0.5) * intensity;
                const y = (Math.random() - 0.5) * intensity;
                body.style.transform = `translate(${x}px, ${y}px)`;
                requestAnimationFrame(shake);
            } else {
                body.style.transform = originalTransform;
            }
        };
        shake();
    },

    startAnimation() {
        if (this.animating) return;
        this.animating = true;
        this.animate();
    },

    animate() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        // Update and draw confetti
        for (let i = this.confetti.length - 1; i >= 0; i--) {
            const c = this.confetti[i];
            c.x += c.vx;
            c.y += c.vy;
            c.vy += c.gravity;
            c.rotation += c.rotationSpeed;
            c.life -= 0.005;

            if (c.life <= 0 || c.y > this.canvas.height) {
                this.confetti.splice(i, 1);
                continue;
            }

            this.ctx.save();
            this.ctx.translate(c.x, c.y);
            this.ctx.rotate(c.rotation * Math.PI / 180);
            this.ctx.fillStyle = c.color;
            this.ctx.globalAlpha = c.life;
            this.ctx.fillRect(-c.size / 2, -c.size / 4, c.size, c.size / 2);
            this.ctx.restore();
        }

        // Update and draw fireworks
        for (let i = this.fireworks.length - 1; i >= 0; i--) {
            const fw = this.fireworks[i];
            
            if (!fw.exploded) {
                fw.x += fw.vx;
                fw.y += fw.vy;
                fw.trail.push({ x: fw.x, y: fw.y });
                if (fw.trail.length > 10) fw.trail.shift();

                // Draw trail
                for (let j = 0; j < fw.trail.length; j++) {
                    const t = fw.trail[j];
                    this.ctx.beginPath();
                    this.ctx.arc(t.x, t.y, 2, 0, Math.PI * 2);
                    this.ctx.fillStyle = fw.color;
                    this.ctx.globalAlpha = j / fw.trail.length;
                    this.ctx.fill();
                }
                this.ctx.globalAlpha = 1;

                // Check if reached target
                if (Math.abs(fw.y - fw.targetY) < 20) {
                    fw.exploded = true;
                    this.explodeFirework(fw);
                    this.fireworks.splice(i, 1);
                }
            }
        }

        // Update and draw particles
        for (let i = this.particles.length - 1; i >= 0; i--) {
            const p = this.particles[i];
            p.x += p.vx;
            p.y += p.vy;
            if (p.gravity) p.vy += p.gravity;
            p.life -= p.decay;

            if (p.life <= 0 || (p.coin && p.y > this.canvas.height)) {
                this.particles.splice(i, 1);
                continue;
            }

            this.ctx.save();
            this.ctx.globalAlpha = p.life;

            if (p.coin) {
                // Draw coin
                p.rotation += p.rotationSpeed;
                this.ctx.translate(p.x, p.y);
                this.ctx.scale(Math.cos(p.rotation * Math.PI / 180), 1);
                this.ctx.beginPath();
                this.ctx.arc(0, 0, p.size, 0, Math.PI * 2);
                this.ctx.fillStyle = '#ffd700';
                this.ctx.fill();
                this.ctx.strokeStyle = '#b8860b';
                this.ctx.lineWidth = 2;
                this.ctx.stroke();
                this.ctx.fillStyle = '#b8860b';
                this.ctx.font = 'bold 12px Arial';
                this.ctx.textAlign = 'center';
                this.ctx.textBaseline = 'middle';
                this.ctx.fillText('$', 0, 0);
            } else if (p.star) {
                // Draw star
                this.ctx.translate(p.x, p.y);
                this.ctx.fillStyle = p.color;
                this.drawStar(0, 0, 5, p.size, p.size / 2);
            } else {
                // Draw regular particle
                this.ctx.beginPath();
                this.ctx.arc(p.x, p.y, p.size * p.life, 0, Math.PI * 2);
                this.ctx.fillStyle = p.color;
                this.ctx.fill();
            }
            this.ctx.restore();
        }

        // Continue animation if there are still effects
        if (this.confetti.length > 0 || this.fireworks.length > 0 || this.particles.length > 0) {
            requestAnimationFrame(() => this.animate());
        } else {
            this.animating = false;
        }
    },

    drawStar(cx, cy, spikes, outerRadius, innerRadius) {
        let rot = Math.PI / 2 * 3;
        let x = cx;
        let y = cy;
        const step = Math.PI / spikes;

        this.ctx.beginPath();
        this.ctx.moveTo(cx, cy - outerRadius);
        for (let i = 0; i < spikes; i++) {
            x = cx + Math.cos(rot) * outerRadius;
            y = cy + Math.sin(rot) * outerRadius;
            this.ctx.lineTo(x, y);
            rot += step;

            x = cx + Math.cos(rot) * innerRadius;
            y = cy + Math.sin(rot) * innerRadius;
            this.ctx.lineTo(x, y);
            rot += step;
        }
        this.ctx.lineTo(cx, cy - outerRadius);
        this.ctx.closePath();
        this.ctx.fill();
    },

    // Preset combinations
    newHighScore() {
        this.screenFlash('#ffd700', 150);
        this.screenShake(8, 200);
        this.fireworks({ count: 7 });
        this.trophyPopup('NEW HIGH SCORE!', 'You beat your record!', '🏆');
    },

    achievementUnlocked(name) {
        this.screenFlash('#00ff00', 100);
        this.starBurst(null, null, { color: '#00ff00' });
        this.trophyPopup('ACHIEVEMENT UNLOCKED', name, '⭐');
    },

    levelComplete() {
        this.confettiBurst({ count: 100 });
        this.trophyPopup('LEVEL COMPLETE!', 'Great job!', '🎉');
    },

    gameWin() {
        this.screenFlash('#ffd700', 100);
        this.rainbowWave();
        setTimeout(() => this.confettiBurst({ count: 200 }), 500);
        this.trophyPopup('VICTORY!', 'You win!', '🏅');
    },

    duelWin() {
        this.screenFlash('#ffd700', 100);
        this.fireworks({ count: 10 });
        setTimeout(() => this.confettiBurst({ count: 150 }), 300);
        this.trophyPopup('DUEL WON!', 'You defeated your opponent!', '⚔️');
    },

    coinsEarned(amount) {
        this.coinShower({ count: Math.min(amount, 50) });
    }
};

// Auto-init when DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => VictoryEffects.init());
} else {
    VictoryEffects.init();
}
