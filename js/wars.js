// Team Wars Integration
// Include this in games to auto-submit scores to active wars

const WarSystem = {
    warId: null,
    war: null,
    myTeamId: null,

    async init() {
        // Check URL for war parameter
        const params = new URLSearchParams(window.location.search);
        this.warId = params.get('war');
        
        // If no war ID in URL, check for active war
        if (!this.warId) {
            await this.checkActiveWar();
        }
        
        if (this.warId) {
            this.showWarBanner();
        }
    },

    async checkActiveWar() {
        try {
            const resp = await fetch('api/wars.php?action=my', { credentials: 'include' });
            const data = await resp.json();
            
            if (data.success && data.war && data.war.status === 'active') {
                // Check if current game is in the war's games list
                const currentGame = window.location.pathname.split('/').pop().replace('.html', '');
                if (data.war.games.includes(currentGame)) {
                    this.warId = data.war.id;
                    this.war = data.war;
                    this.myTeamId = data.myTeamId;
                }
            }
        } catch (e) {
            console.log('No active war');
        }
    },

    showWarBanner() {
        if (!this.war) return;
        
        const existing = document.getElementById('warBanner');
        if (existing) existing.remove();
        
        const isTeam1 = this.myTeamId === this.war.team1;
        const myScore = this.war.scores[this.myTeamId];
        const oppScore = this.war.scores[isTeam1 ? this.war.team2 : this.war.team1];
        
        const banner = document.createElement('div');
        banner.id = 'warBanner';
        banner.innerHTML = `
            <style>
                #warBanner {
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    background: linear-gradient(90deg, #ef4444, #dc2626);
                    color: #fff;
                    padding: 10px 20px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 20px;
                    font-weight: bold;
                    z-index: 10000;
                    box-shadow: 0 -4px 15px rgba(0,0,0,0.3);
                }
                #warBanner .scores {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                #warBanner .team {
                    text-align: center;
                }
                #warBanner .team.my-team { color: #22ff22; }
                #warBanner .team-score {
                    font-size: 1.3rem;
                    text-shadow: 0 0 10px rgba(0,0,0,0.3);
                }
                #warBanner .vs { color: #ffd700; }
                #warBanner .war-link {
                    background: rgba(255,255,255,0.2);
                    padding: 5px 15px;
                    border-radius: 15px;
                    text-decoration: none;
                    color: #fff;
                }
            </style>
            <span>⚔️ TEAM WAR</span>
            <div class="scores">
                <div class="team ${isTeam1 ? 'my-team' : ''}">
                    <div>${this.war.team1Name}</div>
                    <div class="team-score">${myScore.toLocaleString()}</div>
                </div>
                <span class="vs">VS</span>
                <div class="team ${!isTeam1 ? 'my-team' : ''}">
                    <div>${this.war.team2Name}</div>
                    <div class="team-score">${oppScore.toLocaleString()}</div>
                </div>
            </div>
            <a href="wars.html" class="war-link">View War</a>
        `;
        
        document.body.appendChild(banner);
        document.body.style.paddingBottom = '50px';
    },

    async submitScore(score) {
        if (!this.warId) return;
        
        const currentGame = window.location.pathname.split('/').pop().replace('.html', '');
        
        try {
            const resp = await fetch('api/wars.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'submit-score',
                    warId: this.warId,
                    game: currentGame,
                    score: score
                })
            });
            
            const data = await resp.json();
            if (data.success) {
                this.war = data.war;
                this.showWarBanner(); // Update banner with new scores
                this.showScoreSubmitted(score);
            }
        } catch (e) {
            console.error('Failed to submit war score:', e);
        }
    },

    showScoreSubmitted(score) {
        const popup = document.createElement('div');
        popup.innerHTML = `
            <div style="
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: linear-gradient(135deg, #ef4444, #dc2626);
                border: 3px solid #ffd700;
                border-radius: 15px;
                padding: 20px 40px;
                text-align: center;
                z-index: 100001;
                animation: popIn 0.3s ease;
                color: #fff;
            ">
                <div style="font-size: 2rem;">⚔️</div>
                <div style="font-size: 1.2rem; font-weight: bold; margin: 10px 0;">WAR POINTS!</div>
                <div style="font-size: 1.5rem; color: #ffd700;">+${score.toLocaleString()}</div>
            </div>
            <style>
                @keyframes popIn {
                    from { transform: translate(-50%, -50%) scale(0); }
                    to { transform: translate(-50%, -50%) scale(1); }
                }
            </style>
        `;
        document.body.appendChild(popup);
        setTimeout(() => popup.remove(), 2000);
    },

    isInWar() {
        return this.warId !== null && this.war !== null;
    }
};

// Auto-init when DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => WarSystem.init());
} else {
    WarSystem.init();
}
