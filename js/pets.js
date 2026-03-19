// Pet System Integration
// Include this in games to apply pet bonuses and level up pets

const PetSystem = {
    bonus: { coinBonus: 0, xpBonus: 0, petId: null, petLevel: 0 },
    loaded: false,

    // Load pet bonus at game start
    async init() {
        try {
            const resp = await fetch('api/pets.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'getBonus' })
            });
            const data = await resp.json();
            if (data.success) {
                this.bonus = {
                    coinBonus: data.coinBonus || 0,
                    xpBonus: data.xpBonus || 0,
                    petId: data.petId || null,
                    petLevel: data.petLevel || 0
                };
                this.loaded = true;

                // Show pet indicator if pet is active
                if (this.bonus.petId) {
                    this.showPetIndicator();
                }
            }
        } catch (e) {
            console.log('Pet system not available');
        }
    },

    // Apply coin bonus - call this when awarding coins
    applyBonus(coins) {
        if (!this.loaded || this.bonus.coinBonus === 0) return coins;
        const bonus = Math.floor(coins * (this.bonus.coinBonus / 100));
        return coins + bonus;
    },

    // Apply XP bonus
    applyXPBonus(xp) {
        if (!this.loaded || this.bonus.xpBonus === 0) return xp;
        const bonus = Math.floor(xp * (this.bonus.xpBonus / 100));
        return xp + bonus;
    },

    // Get bonus amounts for display
    getBonusCoins(baseCoins) {
        if (!this.loaded || this.bonus.coinBonus === 0) return 0;
        return Math.floor(baseCoins * (this.bonus.coinBonus / 100));
    },

    // Add XP to pet - call at end of game
    async addPetXP(xp) {
        if (!this.loaded || !this.bonus.petId) return null;

        try {
            const resp = await fetch('api/pets.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'addXP', xp: Math.max(1, Math.floor(xp)) })
            });
            const data = await resp.json();

            if (data.success && data.leveledUp) {
                this.showLevelUp(data.newLevel);
            }

            return data;
        } catch (e) {
            console.log('Failed to add pet XP');
            return null;
        }
    },

    // Show small pet indicator during game
    showPetIndicator() {
        const petIcons = {
            'puppy': '🐕‍🦺', 'kitten': '🐱', 'bunny': '🐰', 'hamster': '🐹',
            'fox': '🦊', 'owl': '🦉', 'penguin': '🐧', 'panda': '🐼',
            'tiger': '🐯', 'unicorn': '🦄', 'dragon': '🐉', 'phoenix': '🔥', 'alien': '👽'
        };

        const icon = petIcons[this.bonus.petId] || '🐾';
        const bonusText = [];
        if (this.bonus.coinBonus > 0) bonusText.push(`+${this.bonus.coinBonus}% coins`);
        if (this.bonus.xpBonus > 0) bonusText.push(`+${this.bonus.xpBonus}% XP`);

        const indicator = document.createElement('div');
        indicator.id = 'petIndicator';
        indicator.innerHTML = `${icon} Lv.${this.bonus.petLevel}`;
        indicator.title = bonusText.join(', ');
        indicator.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.7);
            color: #fff;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            z-index: 1000;
            cursor: help;
            border: 2px solid #ffd700;
        `;
        document.body.appendChild(indicator);
    },

    // Show level up notification
    showLevelUp(newLevel) {
        const popup = document.createElement('div');
        popup.innerHTML = `🎉 Pet leveled up to Lv.${newLevel}!`;
        popup.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            color: #000;
            padding: 20px 40px;
            border-radius: 15px;
            font-size: 1.3rem;
            font-weight: bold;
            z-index: 10000;
            animation: petLevelUp 0.5s ease;
            box-shadow: 0 10px 40px rgba(255,215,0,0.5);
        `;

        const style = document.createElement('style');
        style.textContent = `
            @keyframes petLevelUp {
                0% { transform: translate(-50%, -50%) scale(0); }
                50% { transform: translate(-50%, -50%) scale(1.2); }
                100% { transform: translate(-50%, -50%) scale(1); }
            }
        `;
        document.head.appendChild(style);
        document.body.appendChild(popup);

        setTimeout(() => popup.remove(), 3000);
    }
};

// Auto-init when script loads
PetSystem.init();
