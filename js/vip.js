// ============================================
// RETROBLASTS VIP SYSTEM
// Include this file in any game to add VIP support
// ============================================

const VIPSystem = {
    STORAGE_KEY: 'retroblasts_vip',
    VIP_CODES: ['RETROVIP2024', 'BLASTVIP', 'PREMIUM123', 'OWNERVIP'],

    data: {
        isVIP: false,
        activatedAt: null,
        expiresAt: null, // null = lifetime
        tier: 'none', // none, bronze, silver, gold, lifetime
        totalSpent: 0,
        vipBadge: 'vip_default'
    },

    // VIP Perks by tier
    perks: {
        bronze: { coinMultiplier: 1.5, dailyBonus: 50, badge: '🥉', color: '#cd7f32' },
        silver: { coinMultiplier: 1.75, dailyBonus: 100, badge: '🥈', color: '#c0c0c0' },
        gold: { coinMultiplier: 2.0, dailyBonus: 200, badge: '🥇', color: '#ffd700' },
        lifetime: { coinMultiplier: 2.5, dailyBonus: 500, badge: '👑', color: '#ff00ff' }
    },

    // Initialize
    init() {
        this.load();
        this.checkExpiration();
        this.injectStyles();
        return this;
    },

    // Load from storage
    load() {
        try {
            const saved = localStorage.getItem(this.STORAGE_KEY);
            if (saved) {
                this.data = { ...this.data, ...JSON.parse(saved) };
            }
        } catch (e) {
            console.error('VIP load error:', e);
        }
    },

    // Save to storage
    save() {
        try {
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(this.data));
        } catch (e) {
            console.error('VIP save error:', e);
        }
    },

    // Check if VIP expired
    checkExpiration() {
        if (this.data.expiresAt && Date.now() > this.data.expiresAt) {
            this.data.isVIP = false;
            this.data.tier = 'none';
            this.save();
        }
    },

    // Check if user is VIP
    isVIP() {
        this.checkExpiration();
        return this.data.isVIP;
    },

    // Get current tier
    getTier() {
        return this.data.tier;
    },

    // Get perks for current tier
    getPerks() {
        if (!this.isVIP()) return { coinMultiplier: 1, dailyBonus: 0, badge: '', color: '#888' };
        return this.perks[this.data.tier] || this.perks.bronze;
    },

    // Get coin multiplier
    getCoinMultiplier() {
        return this.getPerks().coinMultiplier;
    },

    // Apply coin multiplier
    applyMultiplier(coins) {
        return Math.floor(coins * this.getCoinMultiplier());
    },

    // Activate VIP with code
    activateWithCode(code) {
        code = code.toUpperCase().trim();

        if (this.VIP_CODES.includes(code)) {
            this.data.isVIP = true;
            this.data.activatedAt = Date.now();
            this.data.tier = code === 'OWNERVIP' ? 'lifetime' : 'gold';
            this.data.expiresAt = code === 'OWNERVIP' ? null : Date.now() + (30 * 24 * 60 * 60 * 1000); // 30 days
            this.save();
            return { success: true, message: `VIP Activated! Tier: ${this.data.tier.toUpperCase()}` };
        }

        return { success: false, message: 'Invalid code' };
    },

    // Activate VIP directly (for admin/owner panels)
    activateVIP(tier = 'gold', days = 30) {
        this.data.isVIP = true;
        this.data.activatedAt = Date.now();
        this.data.tier = tier;
        this.data.expiresAt = days === 0 ? null : Date.now() + (days * 24 * 60 * 60 * 1000);
        this.save();
    },

    // Deactivate VIP
    deactivateVIP() {
        this.data.isVIP = false;
        this.data.tier = 'none';
        this.data.expiresAt = null;
        this.save();
    },

    // Get VIP badge HTML
    getBadgeHTML(size = 'small') {
        if (!this.isVIP()) return '';
        const perks = this.getPerks();
        const sizeClass = size === 'large' ? 'vip-badge-large' : 'vip-badge-small';
        return `<span class="vip-badge ${sizeClass}" style="background: ${perks.color};">${perks.badge} VIP</span>`;
    },

    // Get days remaining
    getDaysRemaining() {
        if (!this.data.expiresAt) return 'Lifetime';
        const days = Math.ceil((this.data.expiresAt - Date.now()) / (24 * 60 * 60 * 1000));
        return days > 0 ? `${days} days` : 'Expired';
    },

    // Get VIP status object
    getStatus() {
        return {
            isVIP: this.isVIP(),
            tier: this.data.tier,
            perks: this.getPerks(),
            daysRemaining: this.getDaysRemaining(),
            activatedAt: this.data.activatedAt
        };
    },

    // Inject CSS styles
    injectStyles() {
        if (document.getElementById('vip-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'vip-styles';
        styles.textContent = `
            .vip-badge {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 2px 8px;
                border-radius: 12px;
                font-weight: bold;
                color: #000;
                text-shadow: none;
                animation: vip-glow 2s ease-in-out infinite;
            }
            .vip-badge-small { font-size: 0.75rem; }
            .vip-badge-large { font-size: 1rem; padding: 4px 12px; }

            @keyframes vip-glow {
                0%, 100% { box-shadow: 0 0 5px currentColor; }
                50% { box-shadow: 0 0 15px currentColor, 0 0 25px currentColor; }
            }

            .vip-indicator {
                position: fixed;
                top: 10px;
                right: 10px;
                background: linear-gradient(135deg, #ffd700, #ff8c00);
                color: #000;
                padding: 8px 15px;
                border-radius: 20px;
                font-weight: bold;
                font-size: 0.9rem;
                z-index: 9999;
                display: flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
                transition: transform 0.2s;
            }
            .vip-indicator:hover { transform: scale(1.05); }

            .vip-multiplier {
                color: #ffd700;
                font-weight: bold;
                text-shadow: 0 0 10px #ffd700;
            }

            .vip-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.9);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
            }
            .vip-modal-content {
                background: linear-gradient(135deg, #1a1a2e, #16213e);
                border: 3px solid #ffd700;
                border-radius: 20px;
                padding: 30px;
                max-width: 400px;
                text-align: center;
                color: #fff;
            }
            .vip-modal h2 { color: #ffd700; margin-bottom: 20px; }
            .vip-modal input {
                width: 100%;
                padding: 12px;
                border-radius: 10px;
                border: 2px solid #ffd700;
                background: rgba(0,0,0,0.5);
                color: #fff;
                font-size: 1.1rem;
                text-align: center;
                margin: 15px 0;
            }
            .vip-modal button {
                padding: 12px 30px;
                border-radius: 10px;
                border: none;
                font-weight: bold;
                cursor: pointer;
                margin: 5px;
                font-size: 1rem;
            }
            .vip-modal .btn-activate { background: #ffd700; color: #000; }
            .vip-modal .btn-close { background: #666; color: #fff; }
        `;
        document.head.appendChild(styles);
    },

    // Show VIP indicator on page
    showIndicator() {
        if (!this.isVIP()) return;
        if (document.getElementById('vip-indicator')) return;

        const perks = this.getPerks();
        const indicator = document.createElement('div');
        indicator.id = 'vip-indicator';
        indicator.className = 'vip-indicator';
        indicator.innerHTML = `${perks.badge} VIP ${this.data.tier.toUpperCase()} <span style="font-size:0.75rem;">(${this.getCoinMultiplier()}x coins)</span>`;
        indicator.onclick = () => this.showStatusModal();
        document.body.appendChild(indicator);
    },

    // Show activation modal
    showActivationModal() {
        const modal = document.createElement('div');
        modal.className = 'vip-modal';
        modal.id = 'vip-activation-modal';
        modal.innerHTML = `
            <div class="vip-modal-content">
                <h2>👑 Activate VIP</h2>
                <p>Enter your VIP code to unlock premium features!</p>
                <input type="text" id="vip-code-input" placeholder="Enter VIP Code" maxlength="20">
                <div id="vip-activation-message" style="color: #ff6b6b; margin: 10px 0;"></div>
                <div>
                    <button class="btn-activate" onclick="VIPSystem.tryActivate()">Activate</button>
                    <button class="btn-close" onclick="VIPSystem.closeModal()">Cancel</button>
                </div>
                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #444;">
                    <h3 style="color: #ffd700; font-size: 1rem;">VIP Perks:</h3>
                    <ul style="text-align: left; color: #88c8bc; font-size: 0.9rem; margin-top: 10px;">
                        <li>🪙 Up to 2.5x coin earnings</li>
                        <li>🎁 Daily VIP bonus coins</li>
                        <li>🎨 Exclusive VIP skins</li>
                        <li>👑 VIP badge on profile</li>
                        <li>⚡ Early access to new games</li>
                    </ul>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        document.getElementById('vip-code-input').focus();
    },

    // Show status modal
    showStatusModal() {
        const status = this.getStatus();
        const modal = document.createElement('div');
        modal.className = 'vip-modal';
        modal.id = 'vip-status-modal';
        modal.innerHTML = `
            <div class="vip-modal-content">
                <h2>${status.perks.badge} VIP Status</h2>
                <div style="background: rgba(0,0,0,0.3); padding: 20px; border-radius: 15px; margin: 15px 0;">
                    <div style="font-size: 1.5rem; color: ${status.perks.color}; margin-bottom: 10px;">
                        ${status.tier.toUpperCase()} MEMBER
                    </div>
                    <div style="color: #88c8bc;">
                        <p>🪙 Coin Multiplier: <strong>${status.perks.coinMultiplier}x</strong></p>
                        <p>🎁 Daily Bonus: <strong>${status.perks.dailyBonus} coins</strong></p>
                        <p>⏰ Time Remaining: <strong>${status.daysRemaining}</strong></p>
                    </div>
                </div>
                <button class="btn-close" onclick="VIPSystem.closeModal()">Close</button>
            </div>
        `;
        document.body.appendChild(modal);
    },

    // Try to activate with entered code
    tryActivate() {
        const input = document.getElementById('vip-code-input');
        const message = document.getElementById('vip-activation-message');
        const result = this.activateWithCode(input.value);

        if (result.success) {
            message.style.color = '#2ecc71';
            message.textContent = result.message;
            setTimeout(() => {
                this.closeModal();
                this.showIndicator();
                location.reload();
            }, 1500);
        } else {
            message.style.color = '#ff6b6b';
            message.textContent = result.message;
            input.value = '';
        }
    },

    // Close any open modal
    closeModal() {
        document.getElementById('vip-activation-modal')?.remove();
        document.getElementById('vip-status-modal')?.remove();
    }
};

// Auto-initialize
VIPSystem.init();
