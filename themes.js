/**
 * Seasonal Themes System
 * Automatically applies holiday themes based on date or manual selection
 */

const THEMES = {
    'stpatricks': {
        name: "St. Patrick's Day",
        icon: '☘️',
        dates: { start: '03-15', end: '03-18' },
        colors: {
            primary: '#0d4f21',
            secondary: '#116b35',
            accent: '#1a8f4a',
            gold: '#ffd700',
            text: '#fff'
        },
        gradient: 'linear-gradient(135deg, #0d4f21 0%, #116b35 50%, #1a8f4a 100%)',
        particles: ['☘️', '🍀', '🌈', '💰'],
        css: `
            body { background: linear-gradient(135deg, #0d4f21 0%, #116b35 50%, #1a8f4a 100%); }
            .header, header { background: rgba(0, 50, 0, 0.4); border-bottom: 3px solid #ffd700; }
            .game-card, .card { background: rgba(255, 215, 0, 0.15); border: 2px solid rgba(255, 215, 0, 0.4); }
            .game-card:hover, .card:hover { border-color: #ffd700; box-shadow: 0 0 30px rgba(255, 215, 0, 0.4); }
            h1, h2, .title { text-shadow: 2px 2px 4px rgba(0,0,0,0.5), 0 0 20px rgba(255, 215, 0, 0.5); }
            .btn, button { background: linear-gradient(180deg, #ffd700, #e6c200); color: #0d4f21; }
        `
    },
    'halloween': {
        name: 'Halloween',
        icon: '🎃',
        dates: { start: '10-01', end: '10-31' },
        colors: {
            primary: '#1a0a2e',
            secondary: '#2d1b4e',
            accent: '#ff6b00',
            gold: '#ff6b00',
            text: '#fff'
        },
        gradient: 'linear-gradient(135deg, #1a0a2e 0%, #2d1b4e 50%, #3d2a5e 100%)',
        particles: ['🎃', '👻', '🦇', '💀', '🕷️', '🕸️'],
        css: `
            body { background: linear-gradient(135deg, #1a0a2e 0%, #2d1b4e 50%, #3d2a5e 100%); }
            .header, header { background: rgba(26, 10, 46, 0.7); border-bottom: 3px solid #ff6b00; }
            .game-card, .card { background: rgba(255, 107, 0, 0.1); border: 2px solid rgba(255, 107, 0, 0.4); }
            .game-card:hover, .card:hover { border-color: #ff6b00; box-shadow: 0 0 30px rgba(255, 107, 0, 0.4); }
            h1, h2, .title { text-shadow: 2px 2px 4px rgba(0,0,0,0.5), 0 0 20px rgba(255, 107, 0, 0.5); color: #ff6b00; }
            .btn, button { background: linear-gradient(180deg, #ff6b00, #cc5500); color: #fff; }
        `
    },
    'christmas': {
        name: 'Christmas',
        icon: '🎄',
        dates: { start: '12-01', end: '12-31' },
        colors: {
            primary: '#1a472a',
            secondary: '#2d5a3d',
            accent: '#c41e3a',
            gold: '#ffd700',
            text: '#fff'
        },
        gradient: 'linear-gradient(135deg, #1a472a 0%, #8b0000 50%, #1a472a 100%)',
        particles: ['🎄', '⭐', '🎅', '🎁', '❄️', '☃️'],
        css: `
            body { background: linear-gradient(135deg, #1a472a 0%, #2d5a3d 50%, #1a472a 100%); }
            .header, header { background: rgba(139, 0, 0, 0.5); border-bottom: 3px solid #ffd700; }
            .game-card, .card { background: rgba(196, 30, 58, 0.15); border: 2px solid rgba(196, 30, 58, 0.4); }
            .game-card:hover, .card:hover { border-color: #c41e3a; box-shadow: 0 0 30px rgba(196, 30, 58, 0.4); }
            h1, h2, .title { text-shadow: 2px 2px 4px rgba(0,0,0,0.5), 0 0 20px rgba(255, 215, 0, 0.5); }
            .btn, button { background: linear-gradient(180deg, #c41e3a, #8b0000); color: #fff; }
        `
    },
    'valentines': {
        name: "Valentine's Day",
        icon: '💕',
        dates: { start: '02-10', end: '02-16' },
        colors: {
            primary: '#4a0d2e',
            secondary: '#6b1b4e',
            accent: '#ff69b4',
            gold: '#ff1493',
            text: '#fff'
        },
        gradient: 'linear-gradient(135deg, #4a0d2e 0%, #6b1b4e 50%, #8b2a6e 100%)',
        particles: ['💕', '❤️', '💖', '💘', '💝', '🌹'],
        css: `
            body { background: linear-gradient(135deg, #4a0d2e 0%, #6b1b4e 50%, #8b2a6e 100%); }
            .header, header { background: rgba(74, 13, 46, 0.7); border-bottom: 3px solid #ff69b4; }
            .game-card, .card { background: rgba(255, 105, 180, 0.15); border: 2px solid rgba(255, 105, 180, 0.4); }
            .game-card:hover, .card:hover { border-color: #ff69b4; box-shadow: 0 0 30px rgba(255, 105, 180, 0.4); }
            h1, h2, .title { text-shadow: 2px 2px 4px rgba(0,0,0,0.5), 0 0 20px rgba(255, 105, 180, 0.5); }
            .btn, button { background: linear-gradient(180deg, #ff69b4, #ff1493); color: #fff; }
        `
    },
    'easter': {
        name: 'Easter',
        icon: '🐰',
        dates: { start: '04-01', end: '04-21' },
        colors: {
            primary: '#e8f5e9',
            secondary: '#c8e6c9',
            accent: '#ab47bc',
            gold: '#ffeb3b',
            text: '#333'
        },
        gradient: 'linear-gradient(135deg, #e8f5e9 0%, #f3e5f5 50%, #fff9c4 100%)',
        particles: ['🐰', '🥚', '🐣', '🌷', '🦋', '🌸'],
        css: `
            body { background: linear-gradient(135deg, #e8f5e9 0%, #f3e5f5 50%, #fff9c4 100%); color: #333; }
            .header, header { background: rgba(171, 71, 188, 0.3); border-bottom: 3px solid #ab47bc; }
            .game-card, .card { background: rgba(255, 255, 255, 0.8); border: 2px solid rgba(171, 71, 188, 0.4); color: #333; }
            .game-card:hover, .card:hover { border-color: #ab47bc; box-shadow: 0 0 30px rgba(171, 71, 188, 0.4); }
            h1, h2, .title { color: #7b1fa2; text-shadow: 2px 2px 4px rgba(255,255,255,0.8); }
            .btn, button { background: linear-gradient(180deg, #ab47bc, #8e24aa); color: #fff; }
        `
    },
    'july4th': {
        name: '4th of July',
        icon: '🇺🇸',
        dates: { start: '07-01', end: '07-07' },
        colors: {
            primary: '#002868',
            secondary: '#0a3a7a',
            accent: '#bf0a30',
            gold: '#fff',
            text: '#fff'
        },
        gradient: 'linear-gradient(135deg, #002868 0%, #bf0a30 50%, #002868 100%)',
        particles: ['🇺🇸', '🎆', '🎇', '⭐', '🦅'],
        css: `
            body { background: linear-gradient(135deg, #002868 0%, #0a3a7a 50%, #002868 100%); }
            .header, header { background: rgba(191, 10, 48, 0.5); border-bottom: 3px solid #fff; }
            .game-card, .card { background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(255, 255, 255, 0.4); }
            .game-card:hover, .card:hover { border-color: #bf0a30; box-shadow: 0 0 30px rgba(191, 10, 48, 0.4); }
            h1, h2, .title { text-shadow: 2px 2px 4px rgba(0,0,0,0.5), 0 0 20px rgba(255, 255, 255, 0.5); }
            .btn, button { background: linear-gradient(180deg, #bf0a30, #8b0000); color: #fff; }
        `
    },
    'summer': {
        name: 'Summer',
        icon: '☀️',
        dates: { start: '06-01', end: '08-31' },
        colors: {
            primary: '#0277bd',
            secondary: '#039be5',
            accent: '#ffc107',
            gold: '#ff9800',
            text: '#fff'
        },
        gradient: 'linear-gradient(135deg, #0277bd 0%, #039be5 50%, #4fc3f7 100%)',
        particles: ['☀️', '🏖️', '🌴', '🍦', '🏄', '🌊'],
        css: `
            body { background: linear-gradient(135deg, #0277bd 0%, #039be5 50%, #4fc3f7 100%); }
            .header, header { background: rgba(2, 119, 189, 0.5); border-bottom: 3px solid #ffc107; }
            .game-card, .card { background: rgba(255, 193, 7, 0.15); border: 2px solid rgba(255, 193, 7, 0.4); }
            .game-card:hover, .card:hover { border-color: #ffc107; box-shadow: 0 0 30px rgba(255, 193, 7, 0.4); }
            h1, h2, .title { text-shadow: 2px 2px 4px rgba(0,0,0,0.3), 0 0 20px rgba(255, 193, 7, 0.5); }
            .btn, button { background: linear-gradient(180deg, #ffc107, #ff9800); color: #333; }
        `
    },
    'default': {
        name: 'RetroBlasts',
        icon: '🕹️',
        dates: null,
        colors: {
            primary: '#1a1a3e',
            secondary: '#2a1f5e',
            accent: '#00d4ff',
            gold: '#a855f7',
            text: '#fff'
        },
        gradient: 'linear-gradient(135deg, #1a1a3e 0%, #2a1f5e 50%, #1a0a4e 100%)',
        particles: ['⭐', '🎮', '🕹️', '👾', '🚀'],
        css: `
            body { background: linear-gradient(135deg, #1a1a3e 0%, #2a1f5e 50%, #1a0a4e 100%); }
            .header, header { background: rgba(26, 26, 62, 0.7); border-bottom: 3px solid #00d4ff; }
            .game-card, .card { background: rgba(0, 212, 255, 0.1); border: 2px solid rgba(168, 85, 247, 0.4); }
            .game-card:hover, .card:hover { border-color: #00d4ff; box-shadow: 0 0 30px rgba(0, 212, 255, 0.4); }
            h1, h2, .title { text-shadow: 2px 2px 4px rgba(0,0,0,0.5), 0 0 20px rgba(168, 85, 247, 0.5); }
            .btn, button { background: linear-gradient(180deg, #a855f7, #7c3aed); color: #fff; }
        `
    }
};

// Get current theme from storage or auto-detect
function getCurrentTheme() {
    const stored = localStorage.getItem('arcadeTheme');
    if (stored && stored !== 'auto') {
        return stored;
    }
    return getSeasonalTheme();
}

// Auto-detect seasonal theme based on date
function getSeasonalTheme() {
    const today = new Date();
    const monthDay = String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

    // Check each theme's date range (except default which has no dates)
    // Priority order matters - more specific holidays first
    const priority = ['july4th', 'halloween', 'christmas', 'valentines', 'stpatricks', 'easter', 'summer'];

    for (const themeId of priority) {
        const theme = THEMES[themeId];
        if (theme.dates) {
            const start = theme.dates.start;
            const end = theme.dates.end;
            if (monthDay >= start && monthDay <= end) {
                return themeId;
            }
        }
    }

    return 'default';
}

// Apply theme to page
function applyTheme(themeId) {
    const theme = THEMES[themeId] || THEMES['default'];

    // Remove existing theme style
    const existing = document.getElementById('seasonal-theme-style');
    if (existing) existing.remove();

    // Add new theme style
    const style = document.createElement('style');
    style.id = 'seasonal-theme-style';
    style.textContent = theme.css;
    document.head.appendChild(style);

    // Update floating particles if they exist
    updateParticles(theme.particles);

    // Store theme
    if (themeId !== 'auto') {
        localStorage.setItem('arcadeTheme', themeId);
    }

    // Dispatch event for other scripts
    window.dispatchEvent(new CustomEvent('themeChanged', { detail: { themeId, theme } }));

    return theme;
}

// Update floating particles
function updateParticles(particles) {
    // Find existing particle containers
    const shamrocks = document.querySelectorAll('.shamrock, .floating-particle');
    shamrocks.forEach((el, i) => {
        el.textContent = particles[i % particles.length];
    });
}

// Create floating particles
function createParticles(count = 15) {
    const theme = THEMES[getCurrentTheme()];
    const container = document.body;

    for (let i = 0; i < count; i++) {
        const particle = document.createElement('div');
        particle.className = 'floating-particle shamrock';
        particle.textContent = theme.particles[i % theme.particles.length];
        particle.style.cssText = `
            position: fixed;
            font-size: ${1.5 + Math.random() * 1.5}rem;
            left: ${Math.random() * 100}%;
            top: ${Math.random() * 100}%;
            opacity: 0.3;
            pointer-events: none;
            z-index: 0;
            animation: floatShamrock ${10 + Math.random() * 10}s ease-in-out infinite;
            animation-delay: ${Math.random() * 5}s;
        `;
        container.appendChild(particle);
    }
}

// Set theme manually
function setTheme(themeId) {
    if (themeId === 'auto') {
        localStorage.setItem('arcadeTheme', 'auto');
        themeId = getSeasonalTheme();
    }
    return applyTheme(themeId);
}

// Get all available themes
function getThemes() {
    return Object.entries(THEMES).map(([id, theme]) => ({
        id,
        name: theme.name,
        icon: theme.icon
    }));
}

// Initialize theme on page load
function initTheme() {
    // Force reset outdated theme cache (v2 update)
    const storedTheme = localStorage.getItem('arcadeTheme');
    if (storedTheme === 'stpatricks' && getSeasonalTheme() !== 'stpatricks') {
        localStorage.removeItem('arcadeTheme');
    }
    const themeId = getCurrentTheme();
    applyTheme(themeId);
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTheme);
} else {
    initTheme();
}

// Export for use in other scripts
window.ThemeSystem = {
    THEMES,
    getCurrentTheme,
    getSeasonalTheme,
    applyTheme,
    setTheme,
    getThemes,
    createParticles
};
