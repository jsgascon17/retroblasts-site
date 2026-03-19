// Duel Challenge System
// Include this in any game that supports challenges

const DuelSystem = {
    challengeId: null,
    challenge: null,
    opponentScore: null,
    myScore: null,
    hasSubmitted: false,
    pollInterval: null,

    async init() {
        // Check URL for challenge parameter
        const params = new URLSearchParams(window.location.search);
        this.challengeId = params.get('challenge');
        
        if (!this.challengeId) {
            // Check for any active challenge for this game
            await this.checkActiveChallenge();
        }
        
        if (this.challengeId) {
            await this.loadChallenge();
            if (this.challenge && this.challenge.status === 'accepted') {
                this.showDuelBanner();
                this.startPolling();
            }
        }
    },

    async checkActiveChallenge() {
        try {
            const resp = await fetch('api/challenges.php?action=active', { credentials: 'include' });
            const data = await resp.json();
            if (data.success && data.challenge) {
                // Check if this challenge is for the current game
                const currentGame = window.location.pathname.split('/').pop().replace('.html', '');
                if (data.challenge.game === currentGame) {
                    this.challengeId = data.challenge.id;
                }
            }
        } catch (e) {
            console.log('No active challenge');
        }
    },

    async loadChallenge() {
        try {
            const resp = await fetch(`api/challenges.php?action=get&id=${this.challengeId}`, { credentials: 'include' });
            const data = await resp.json();
            if (data.success) {
                this.challenge = data.challenge;
            }
        } catch (e) {
            console.error('Failed to load challenge:', e);
        }
    },

    showDuelBanner() {
        if (!this.challenge) return;
        
        // Remove existing banner
        const existing = document.getElementById('duelBanner');
        if (existing) existing.remove();
        
        const banner = document.createElement('div');
        banner.id = 'duelBanner';
        banner.innerHTML = `
            <style>
                #duelBanner {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    background: linear-gradient(90deg, #ffd700, #ff8c00);
                    color: #000;
                    padding: 12px 20px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 30px;
                    font-weight: bold;
                    z-index: 10000;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
                }
                #duelBanner .player {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                #duelBanner .vs {
                    font-size: 1.5rem;
                    color: #c00;
                }
                #duelBanner .score {
                    background: rgba(0,0,0,0.2);
                    padding: 5px 15px;
                    border-radius: 20px;
                    min-width: 60px;
                    text-align: center;
                }
                #duelBanner .wager {
                    background: rgba(0,0,0,0.3);
                    padding: 5px 12px;
                    border-radius: 15px;
                    font-size: 0.9rem;
                }
                .duel-active body { padding-top: 50px !important; }
            </style>
            <div class="player">
                <span>${this.challenge.fromDisplay}</span>
                <span class="score" id="duelScore1">-</span>
            </div>
            <span class="vs">⚔️ VS ⚔️</span>
            <div class="player">
                <span class="score" id="duelScore2">-</span>
                <span>${this.challenge.toDisplay}</span>
            </div>
            ${this.challenge.wager > 0 ? `<span class="wager">💰 ${this.challenge.wager} coins</span>` : ''}
        `;
        
        document.body.prepend(banner);
        document.body.classList.add('duel-active');
        document.body.style.paddingTop = '50px';
    },

    async submitScore(score) {
        if (!this.challengeId || this.hasSubmitted) return;
        
        this.myScore = score;
        this.hasSubmitted = true;
        
        // Update UI immediately
        this.updateScoreDisplay();
        
        try {
            const resp = await fetch('api/challenges.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'submit-score',
                    id: this.challengeId,
                    score: score
                })
            });
            
            const data = await resp.json();
            if (data.success) {
                this.challenge = data.challenge;
                this.updateScoreDisplay();
                
                if (this.challenge.status === 'completed') {
                    this.showResults();
                }
            }
        } catch (e) {
            console.error('Failed to submit score:', e);
        }
    },

    updateScoreDisplay() {
        if (!this.challenge) return;
        
        const score1El = document.getElementById('duelScore1');
        const score2El = document.getElementById('duelScore2');
        
        if (score1El && this.challenge.scores) {
            score1El.textContent = this.challenge.scores[this.challenge.from] ?? '-';
        }
        if (score2El && this.challenge.scores) {
            score2El.textContent = this.challenge.scores[this.challenge.to] ?? '-';
        }
    },

    startPolling() {
        // Poll for opponent's score
        this.pollInterval = setInterval(async () => {
            await this.loadChallenge();
            this.updateScoreDisplay();
            
            if (this.challenge && this.challenge.status === 'completed') {
                clearInterval(this.pollInterval);
                this.showResults();
            }
        }, 3000);
    },

    showResults() {
        clearInterval(this.pollInterval);
        
        if (!this.challenge) return;
        
        // Get current username
        fetch('api/auth.php?action=check', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                if (!data.loggedIn) return;
                
                const myUsername = data.user.username;
                const isWinner = this.challenge.winner === myUsername;
                const isTie = this.challenge.winner === 'tie';
                
                // Trigger victory effects
                if (typeof VictoryEffects !== 'undefined') {
                    if (isWinner) VictoryEffects.duelWin();
                    else if (isTie) VictoryEffects.confettiBurst({ count: 50 });
                }
                
                const overlay = document.createElement('div');
                overlay.id = 'duelResults';
                overlay.innerHTML = `
                    <style>
                        #duelResults {
                            position: fixed;
                            top: 0;
                            left: 0;
                            right: 0;
                            bottom: 0;
                            background: rgba(0,0,0,0.9);
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            z-index: 100000;
                        }
                        #duelResults .content {
                            background: linear-gradient(135deg, #1a1a2e, #16213e);
                            border: 4px solid ${isTie ? '#ffd700' : (isWinner ? '#22c55e' : '#ef4444')};
                            border-radius: 25px;
                            padding: 40px 60px;
                            text-align: center;
                            max-width: 500px;
                        }
                        #duelResults h1 {
                            font-size: 3rem;
                            margin-bottom: 20px;
                            color: ${isTie ? '#ffd700' : (isWinner ? '#22c55e' : '#ef4444')};
                        }
                        #duelResults .scores {
                            display: flex;
                            justify-content: center;
                            gap: 40px;
                            margin: 30px 0;
                        }
                        #duelResults .player-score {
                            text-align: center;
                        }
                        #duelResults .player-score .name {
                            font-size: 1.2rem;
                            color: #888;
                            margin-bottom: 10px;
                        }
                        #duelResults .player-score .score {
                            font-size: 2.5rem;
                            font-weight: bold;
                            color: #fff;
                        }
                        #duelResults .player-score.winner .score {
                            color: #22c55e;
                        }
                        #duelResults .wager-result {
                            background: rgba(255,215,0,0.2);
                            padding: 15px 25px;
                            border-radius: 15px;
                            margin: 20px 0;
                            font-size: 1.2rem;
                            color: #ffd700;
                        }
                        #duelResults .btn {
                            background: linear-gradient(135deg, #ffd700, #ff8c00);
                            color: #000;
                            border: none;
                            padding: 15px 40px;
                            border-radius: 25px;
                            font-size: 1.2rem;
                            font-weight: bold;
                            cursor: pointer;
                            margin-top: 20px;
                        }
                        #duelResults .btn:hover {
                            transform: scale(1.05);
                        }
                    </style>
                    <div class="content">
                        <h1>${isTie ? '🤝 TIE!' : (isWinner ? '🏆 YOU WIN!' : '😢 YOU LOSE')}</h1>
                        <div class="scores">
                            <div class="player-score ${this.challenge.winner === this.challenge.from ? 'winner' : ''}">
                                <div class="name">${this.challenge.fromDisplay}</div>
                                <div class="score">${this.challenge.scores[this.challenge.from]}</div>
                            </div>
                            <div style="font-size: 2rem; color: #ffd700; align-self: center;">VS</div>
                            <div class="player-score ${this.challenge.winner === this.challenge.to ? 'winner' : ''}">
                                <div class="name">${this.challenge.toDisplay}</div>
                                <div class="score">${this.challenge.scores[this.challenge.to]}</div>
                            </div>
                        </div>
                        ${this.challenge.wager > 0 ? `
                            <div class="wager-result">
                                ${isTie ? '💰 Wager returned' : (isWinner ? `💰 You won ${this.challenge.wager} coins!` : `💰 You lost ${this.challenge.wager} coins`)}
                            </div>
                        ` : ''}
                        <button class="btn" onclick="window.location.href='challenge.html'">Back to Challenges</button>
                    </div>
                `;
                
                document.body.appendChild(overlay);
            });
    },

    // Call this from game's game over function
    isInDuel() {
        return this.challengeId !== null && this.challenge !== null;
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => DuelSystem.init());
} else {
    DuelSystem.init();
}
