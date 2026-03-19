// High Score Notifications System

// Create notification container if not exists
function ensureNotificationContainer() {
    if (!document.getElementById('scoreNotificationContainer')) {
        const container = document.createElement('div');
        container.id = 'scoreNotificationContainer';
        container.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:99999;pointer-events:none;';
        document.body.appendChild(container);
    }
    
    // Add styles if not exists
    if (!document.getElementById('notificationStyles')) {
        const style = document.createElement('style');
        style.id = 'notificationStyles';
        style.textContent = `
            .score-notification {
                background: linear-gradient(135deg, #1a1a2e, #16213e);
                border: 4px solid #ffd700;
                border-radius: 20px;
                padding: 30px 50px;
                text-align: center;
                animation: popIn 0.5s ease, popOut 0.5s ease 2.5s forwards;
                box-shadow: 0 0 50px rgba(255,215,0,0.5);
                pointer-events: auto;
            }
            .score-notification h2 {
                color: #ffd700;
                font-size: 2rem;
                margin: 0 0 10px 0;
                text-shadow: 0 0 10px rgba(255,215,0,0.5);
            }
            .score-notification p {
                color: #fff;
                font-size: 1.2rem;
                margin: 5px 0;
            }
            .score-notification .score-value {
                color: #98FB98;
                font-size: 2.5rem;
                font-weight: bold;
                margin: 15px 0;
            }
            .beaten-notification {
                background: linear-gradient(135deg, #7f1d1d, #991b1b);
                border-color: #ef4444;
                box-shadow: 0 0 50px rgba(239,68,68,0.5);
            }
            .beaten-notification h2 {
                color: #ef4444;
            }
            .beaten-notification .reclaim-btn {
                display: inline-block;
                background: #ef4444;
                color: #fff;
                padding: 10px 25px;
                border-radius: 20px;
                text-decoration: none;
                font-weight: bold;
                margin-top: 15px;
                pointer-events: auto;
                cursor: pointer;
                border: none;
                font-size: 1rem;
                transition: transform 0.2s;
            }
            .beaten-notification .reclaim-btn:hover {
                transform: scale(1.1);
            }
            .confetti {
                position: fixed;
                width: 10px;
                height: 10px;
                pointer-events: none;
                z-index: 99998;
            }
            @keyframes popIn {
                0% { transform: scale(0); opacity: 0; }
                50% { transform: scale(1.1); }
                100% { transform: scale(1); opacity: 1; }
            }
            @keyframes popOut {
                0% { transform: scale(1); opacity: 1; }
                100% { transform: scale(0); opacity: 0; }
            }
            @keyframes confettiFall {
                0% { transform: translateY(-100vh) rotate(0deg); opacity: 1; }
                100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
            }
            
            /* Toast notifications for beaten scores */
            .beaten-toast {
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #7f1d1d, #991b1b);
                border: 2px solid #ef4444;
                border-radius: 15px;
                padding: 20px;
                max-width: 350px;
                z-index: 10000;
                animation: slideInRight 0.3s ease;
                box-shadow: 0 5px 25px rgba(0,0,0,0.5);
            }
            .beaten-toast h4 {
                color: #ef4444;
                margin: 0 0 10px 0;
            }
            .beaten-toast p {
                color: #fff;
                margin: 5px 0;
                font-size: 0.9rem;
            }
            .beaten-toast .scores {
                display: flex;
                gap: 20px;
                margin: 15px 0;
            }
            .beaten-toast .old-score {
                color: #888;
                text-decoration: line-through;
            }
            .beaten-toast .new-score {
                color: #ef4444;
                font-weight: bold;
            }
            .beaten-toast .actions {
                display: flex;
                gap: 10px;
                margin-top: 15px;
            }
            .beaten-toast .btn {
                padding: 8px 15px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-weight: bold;
                font-size: 0.85rem;
            }
            .beaten-toast .btn-play {
                background: #ef4444;
                color: #fff;
            }
            .beaten-toast .btn-dismiss {
                background: rgba(255,255,255,0.1);
                color: #fff;
            }
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }
}

function showHighScoreNotification(type, score, rank) {
    ensureNotificationContainer();
    const container = document.getElementById('scoreNotificationContainer');
    
    let title, message;
    
    if (type === 'personal_best') {
        title = '🎉 NEW PERSONAL BEST! 🎉';
        message = 'You beat your previous record!';
    } else if (type === 'leaderboard') {
        title = '🏆 LEADERBOARD! 🏆';
        message = 'You made it to #' + rank + '!';
    } else if (type === 'top_score') {
        title = '👑 #1 HIGH SCORE! 👑';
        message = 'You have the top score!';
    }
    
    const notification = document.createElement('div');
    notification.className = 'score-notification';
    notification.innerHTML = `
        <h2>${title}</h2>
        <div class="score-value">${score.toLocaleString()}</div>
        <p>${message}</p>
    `;
    
    container.innerHTML = '';
    container.appendChild(notification);
    
    // Confetti!
    if (type === 'top_score' || type === 'leaderboard') {
        createConfetti();
    }
    
    // Remove after animation
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// NEW: Show notification when someone beats your score
function showBeatenNotification(beater, game, gameName, theirScore, yourOldScore) {
    ensureNotificationContainer();
    
    const toast = document.createElement('div');
    toast.className = 'beaten-toast';
    toast.innerHTML = `
        <h4>😱 Your Score Was Beaten!</h4>
        <p><strong>${beater}</strong> beat your high score in <strong>${gameName}</strong>!</p>
        <div class="scores">
            <div>
                <div style="font-size: 0.75rem; color: #888;">Your old score</div>
                <div class="old-score">${yourOldScore.toLocaleString()}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: #888;">Their new score</div>
                <div class="new-score">${theirScore.toLocaleString()}</div>
            </div>
        </div>
        <div class="actions">
            <a href="${game}.html" class="btn btn-play">⚔️ Reclaim Your Spot!</a>
            <button class="btn btn-dismiss" onclick="this.parentElement.parentElement.remove()">Dismiss</button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Auto dismiss after 10 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.animation = 'slideInRight 0.3s ease reverse';
            setTimeout(() => toast.remove(), 300);
        }
    }, 10000);
}

function createConfetti() {
    const colors = ['#ffd700', '#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#ff8c00', '#98FB98'];
    
    for (let i = 0; i < 50; i++) {
        setTimeout(() => {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.cssText = `
                left: ${Math.random() * 100}vw;
                top: -20px;
                background: ${colors[Math.floor(Math.random() * colors.length)]};
                border-radius: ${Math.random() > 0.5 ? '50%' : '0'};
                animation: confettiFall ${2 + Math.random() * 2}s linear forwards;
            `;
            document.body.appendChild(confetti);
            
            setTimeout(() => confetti.remove(), 4000);
        }, i * 30);
    }
}

// Enhanced score submission that shows notifications
async function submitScoreWithNotification(game, score, name) {
    try {
        const response = await fetch('leaderboard-global.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ game, score, name })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Check for achievements
            if (data.rank === 1) {
                showHighScoreNotification('top_score', score, 1);
            } else if (data.rank && data.rank <= 10) {
                showHighScoreNotification('leaderboard', score, data.rank);
            } else if (data.isPersonalBest) {
                showHighScoreNotification('personal_best', score);
            }
        }
        
        return data;
    } catch (e) {
        console.error('Score submission error:', e);
        return { success: false, error: e.message };
    }
}

// Check for beaten scores on page load
async function checkBeatenScores() {
    // Check if user is logged in
    try {
        const authResp = await fetch('api/auth.php?action=check', { credentials: 'include' });
        const authData = await authResp.json();
        
        if (!authData.loggedIn) return;
        
        const username = authData.user.username;
        
        // Get notifications
        const resp = await fetch('api/score-notifications.php?action=check', { credentials: 'include' });
        const data = await resp.json();
        
        if (data.success && data.notifications && data.notifications.length > 0) {
            // Show notifications with delay
            data.notifications.forEach((n, i) => {
                setTimeout(() => {
                    showBeatenNotification(n.beater, n.game, n.gameName, n.theirScore, n.yourOldScore);
                }, i * 1500);
            });
            
            // Mark as read
            fetch('api/score-notifications.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark-read', ids: data.notifications.map(n => n.id) })
            });
        }
    } catch (e) {
        console.error('Error checking beaten scores:', e);
    }
}

// Generic Arcade Notifications
const ArcadeNotifications = {
    show: function(type, title, message, duration = 4000) {
        ensureNotificationContainer();
        
        const colors = {
            success: { bg: '#22c55e', border: '#16a34a' },
            error: { bg: '#ef4444', border: '#dc2626' },
            warning: { bg: '#f59e0b', border: '#d97706' },
            info: { bg: '#3b82f6', border: '#2563eb' }
        };
        
        const color = colors[type] || colors.info;
        
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${color.bg};
            border: 2px solid ${color.border};
            border-radius: 10px;
            padding: 15px 20px;
            max-width: 300px;
            z-index: 10000;
            animation: slideInRight 0.3s ease;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        `;
        toast.innerHTML = `
            <div style="font-weight: bold; margin-bottom: 5px;">${title}</div>
            <div style="font-size: 0.9rem; opacity: 0.9;">${message}</div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideInRight 0.3s ease reverse';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
};

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkBeatenScores);
} else {
    checkBeatenScores();
}
