// Activity Feed System
// Logs player activities and shows friend feed

const ActivityFeed = {
    // Log an activity
    async log(type, data) {
        try {
            await fetch('api/activity.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'log', type, data })
            });
        } catch (e) {
            console.log('Failed to log activity');
        }
    },

    // Get friend feed
    async getFeed() {
        try {
            const resp = await fetch('api/activity.php?action=feed', { credentials: 'include' });
            const data = await resp.json();
            return data.success ? data.feed : [];
        } catch (e) {
            return [];
        }
    },

    // Helper methods for common activities
    logHighScore(game, score) {
        this.log('high_score', { game, score });
    },

    logAchievement(game, name) {
        this.log('achievement', { game, name });
    },

    logLevelUp(game, level) {
        this.log('level_up', { game, level });
    },

    logPurchase(game, item) {
        this.log('purchase', { game, item });
    },

    logDuelWin(game, opponent) {
        this.log('duel_win', { game, opponent });
    },

    logDuelLoss(game, opponent) {
        this.log('duel_loss', { game, opponent });
    },

    logWarContribution(score) {
        this.log('war_contribution', { score });
    },

    logPlaying(game) {
        this.log('playing', { game });
    },

    getIcon(type) {
        const icons = {
            'high_score': '🏆',
            'achievement': '⭐',
            'level_up': '📈',
            'purchase': '🛍️',
            'duel_win': '⚔️',
            'duel_loss': '😢',
            'war_contribution': '🔥',
            'joined_team': '👥',
            'playing': '🎮'
        };
        return icons[type] || '📌';
    },

    // Render feed widget
    async renderFeedWidget(containerId, maxItems = 10) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const feed = await this.getFeed();
        
        if (feed.length === 0) {
            container.innerHTML = '<div style="color: #666; text-align: center; padding: 20px;">No activity yet. Add some friends!</div>';
            return;
        }

        let html = '';
        const items = feed.slice(0, maxItems);
        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            const icon = this.getIcon(item.type);
            html += '<div class="activity-item" style="padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; gap: 10px; align-items: flex-start;">';
            html += '<div class="activity-icon" style="font-size: 1.5rem;">' + icon + '</div>';
            html += '<div class="activity-content" style="flex: 1;">';
            html += '<div class="activity-message" style="color: #fff; font-size: 0.9rem;">' + item.message + '</div>';
            html += '<div class="activity-time" style="color: #666; font-size: 0.75rem; margin-top: 3px;">' + item.timeAgo + '</div>';
            html += '</div></div>';
        }

        container.innerHTML = html;
    }
};

// Log that user is playing this game
const currentGame = window.location.pathname.split('/').pop().replace('.html', '');
if (currentGame && !['index', 'login', 'profile', 'friends', 'teams', 'wars', 'shop', 'challenge'].includes(currentGame)) {
    ActivityFeed.logPlaying(currentGame);
}
