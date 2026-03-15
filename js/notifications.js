/**
 * Arcade Notification System
 * Level-ups, achievements, and general notifications
 */

window.ArcadeNotifications = {
    container: null,
    
    init() {
        if (this.container) return;
        this.container = document.createElement('div');
        this.container.id = 'arcade-notifications';
        this.container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; display: flex; flex-direction: column; gap: 10px; pointer-events: none;';
        document.body.appendChild(this.container);
        
        // Add styles
        const style = document.createElement('style');
        style.textContent = `
            .arcade-notification {
                background: linear-gradient(135deg, rgba(30,30,30,0.95), rgba(50,50,50,0.95));
                border-radius: 15px;
                padding: 15px 25px;
                color: #fff;
                font-family: 'Segoe UI', sans-serif;
                box-shadow: 0 5px 30px rgba(0,0,0,0.5);
                animation: notifSlideIn 0.5s ease-out;
                pointer-events: auto;
                max-width: 300px;
            }
            .arcade-notification.level-up {
                background: linear-gradient(135deg, #e94560, #ff6b6b);
                border: 2px solid #ffd700;
            }
            .arcade-notification.achievement {
                background: linear-gradient(135deg, #f39c12, #e67e22);
            }
            .arcade-notification.xp {
                background: linear-gradient(135deg, #3498db, #2980b9);
            }
            .arcade-notification.friend {
                background: linear-gradient(135deg, #9b59b6, #8e44ad);
            }
            .notif-title {
                font-size: 0.8rem;
                opacity: 0.8;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-bottom: 5px;
            }
            .notif-content {
                font-size: 1.1rem;
                font-weight: bold;
            }
            .notif-sub {
                font-size: 0.85rem;
                opacity: 0.8;
                margin-top: 5px;
            }
            .notif-icon {
                font-size: 2rem;
                margin-right: 15px;
            }
            .notif-row {
                display: flex;
                align-items: center;
            }
            @keyframes notifSlideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes notifSlideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            
            /* Level up modal */
            .level-up-modal {
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 20000;
                animation: fadeIn 0.3s ease-out;
            }
            .level-up-content {
                background: linear-gradient(135deg, #1a1a2e, #16213e);
                border: 3px solid #ffd700;
                border-radius: 25px;
                padding: 40px;
                text-align: center;
                animation: levelUpPop 0.5s ease-out;
                max-width: 350px;
            }
            .level-up-icon { font-size: 4rem; margin-bottom: 20px; }
            .level-up-title { color: #ffd700; font-size: 1.5rem; margin-bottom: 10px; }
            .level-up-level { color: #fff; font-size: 3rem; font-weight: bold; margin-bottom: 10px; }
            .level-up-subtitle { color: #e94560; font-size: 1.2rem; margin-bottom: 20px; }
            .level-up-btn {
                background: linear-gradient(135deg, #e94560, #ff6b6b);
                color: #fff; border: none; padding: 12px 40px;
                border-radius: 25px; font-size: 1rem; font-weight: bold;
                cursor: pointer; transition: transform 0.2s;
            }
            .level-up-btn:hover { transform: scale(1.05); }
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
            @keyframes levelUpPop {
                0% { transform: scale(0.5); opacity: 0; }
                70% { transform: scale(1.1); }
                100% { transform: scale(1); opacity: 1; }
            }
            
            /* Confetti */
            .confetti {
                position: fixed;
                width: 10px;
                height: 10px;
                pointer-events: none;
                z-index: 20001;
                animation: confettiFall 3s linear forwards;
            }
            @keyframes confettiFall {
                to { transform: translateY(100vh) rotate(720deg); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    },
    
    show(type, title, content, sub = '', duration = 4000) {
        this.init();
        
        const notif = document.createElement('div');
        notif.className = 'arcade-notification ' + type;
        
        const icons = {
            'level-up': '🎉',
            'achievement': '🏆',
            'xp': '⭐',
            'friend': '👋',
            'info': 'ℹ️',
            'success': '✓',
            'error': '✗'
        };
        
        notif.innerHTML = `
            <div class="notif-row">
                <span class="notif-icon">${icons[type] || '📢'}</span>
                <div>
                    <div class="notif-title">${title}</div>
                    <div class="notif-content">${content}</div>
                    ${sub ? '<div class="notif-sub">' + sub + '</div>' : ''}
                </div>
            </div>
        `;
        
        this.container.appendChild(notif);
        
        setTimeout(() => {
            notif.style.animation = 'notifSlideOut 0.5s ease-in forwards';
            setTimeout(() => notif.remove(), 500);
        }, duration);
    },
    
    showXP(amount, source = '') {
        this.show('xp', 'XP Earned', `+${amount} XP`, source);
    },
    
    showAchievement(name, description = '') {
        this.show('achievement', 'Achievement Unlocked!', name, description, 5000);
    },
    
    showLevelUp(newLevel, newTitle) {
        this.init();
        
        // Create confetti
        for (let i = 0; i < 50; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.top = '-10px';
            confetti.style.background = ['#e94560', '#ffd700', '#3498db', '#2ecc71', '#9b59b6'][Math.floor(Math.random() * 5)];
            confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
            confetti.style.animationDuration = (2 + Math.random() * 2) + 's';
            document.body.appendChild(confetti);
            setTimeout(() => confetti.remove(), 4000);
        }
        
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'level-up-modal';
        modal.innerHTML = `
            <div class="level-up-content">
                <div class="level-up-icon">🎉</div>
                <div class="level-up-title">LEVEL UP!</div>
                <div class="level-up-level">Level ${newLevel}</div>
                <div class="level-up-subtitle">${newTitle}</div>
                <button class="level-up-btn" onclick="this.parentElement.parentElement.remove()">Awesome!</button>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Play sound if audio context exists
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            const now = ctx.currentTime;
            osc.frequency.setValueAtTime(523, now);
            osc.frequency.setValueAtTime(659, now + 0.1);
            osc.frequency.setValueAtTime(784, now + 0.2);
            osc.frequency.setValueAtTime(1047, now + 0.3);
            gain.gain.setValueAtTime(0.2, now);
            gain.gain.exponentialRampToValueAtTime(0.01, now + 0.5);
            osc.start(now);
            osc.stop(now + 0.5);
        } catch(e) {}
        
        // Auto-close after 5 seconds
        setTimeout(() => {
            if (modal.parentElement) modal.remove();
        }, 5000);
    },
    
    showFriendRequest(fromName) {
        this.show('friend', 'Friend Request', fromName + ' wants to be your friend!', 'Check your friends page');
    }
};

// Auto-init on load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ArcadeNotifications.init());
} else {
    ArcadeNotifications.init();
}
