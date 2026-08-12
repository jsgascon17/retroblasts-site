<?php
/**
 * Coin Shop API
 * 
 * GET:
 *   ?action=items     - Get all shop items
 *   ?action=inventory - Get user inventory
 * 
 * POST:
 *   { "action": "purchase", "category": "name_colors", "itemId": "gold" }
 *   { "action": "equip", "category": "name_colors", "itemId": "gold" }
 *   { "action": "unequip", "category": "name_colors" }
 *   { "action": "activate-boost", "boostType": "xp_2x" }
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/_store.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$usersFile = __DIR__ . '/../data/users.json';
$shopDataFile = __DIR__ . '/../data/shop-data.json';

// Shop items definition
$SHOP_ITEMS = [
    'name_colors' => [
        'name' => 'Name Colors',
        'icon' => '🎨',
        'items' => [
            ['id' => 'gold', 'name' => 'Gold', 'cost' => 500, 'css' => 'color: #ffd700; text-shadow: 0 0 5px rgba(255,215,0,0.5)'],
            ['id' => 'rainbow', 'name' => 'Rainbow', 'cost' => 2000, 'css' => 'background: linear-gradient(90deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #4b0082, #8f00ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text'],
            ['id' => 'neon_green', 'name' => 'Neon Green', 'cost' => 750, 'css' => 'color: #39ff14; text-shadow: 0 0 10px #39ff14'],
            ['id' => 'fire', 'name' => 'Fire', 'cost' => 1500, 'css' => 'background: linear-gradient(180deg, #ff0, #f00); -webkit-background-clip: text; -webkit-text-fill-color: transparent'],
            ['id' => 'ice', 'name' => 'Ice', 'cost' => 1500, 'css' => 'color: #00ffff; text-shadow: 0 0 10px #00ffff, 0 0 20px #0080ff'],
            ['id' => 'purple', 'name' => 'Royal Purple', 'cost' => 750, 'css' => 'color: #9b59b6; text-shadow: 0 0 8px rgba(155,89,182,0.6)'],
            ['id' => 'neon_pink', 'name' => 'Neon Pink', 'cost' => 750, 'css' => 'color: #ff1493; text-shadow: 0 0 10px #ff1493'],
            ['id' => 'cyan', 'name' => 'Cyan', 'cost' => 500, 'css' => 'color: #00bcd4; text-shadow: 0 0 8px rgba(0,188,212,0.6)'],
            ['id' => 'crimson', 'name' => 'Crimson', 'cost' => 750, 'css' => 'color: #dc143c; text-shadow: 0 0 8px rgba(220,20,60,0.6)'],
            ['id' => 'silver', 'name' => 'Silver', 'cost' => 500, 'css' => 'color: #c0c0c0; text-shadow: 0 0 5px rgba(192,192,192,0.5)'],
            ['id' => 'lime', 'name' => 'Lime', 'cost' => 500, 'css' => 'color: #00ff00; text-shadow: 0 0 8px rgba(0,255,0,0.5)'],
            ['id' => 'animated_rainbow', 'name' => 'Animated Rainbow', 'cost' => 5000, 'css' => 'background: linear-gradient(90deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #4b0082, #8f00ff, #ff0000); background-size: 200% 100%; -webkit-background-clip: text; -webkit-text-fill-color: transparent; animation: rainbow-scroll 2s linear infinite']
        ]
    ],
    'borders' => [
        'name' => 'Profile Borders',
        'icon' => '🖼️',
        'items' => [
            ['id' => 'bronze', 'name' => 'Bronze', 'cost' => 1000, 'css' => 'border: 3px solid #cd7f32; box-shadow: 0 0 10px rgba(205,127,50,0.5)'],
            ['id' => 'silver', 'name' => 'Silver', 'cost' => 2000, 'css' => 'border: 3px solid #c0c0c0; box-shadow: 0 0 10px rgba(192,192,192,0.5)'],
            ['id' => 'gold', 'name' => 'Gold', 'cost' => 3500, 'css' => 'border: 3px solid #ffd700; box-shadow: 0 0 15px rgba(255,215,0,0.6)'],
            ['id' => 'diamond', 'name' => 'Diamond', 'cost' => 5000, 'css' => 'border: 3px solid #b9f2ff; box-shadow: 0 0 20px #b9f2ff, inset 0 0 10px rgba(185,242,255,0.3)'],
            ['id' => 'rainbow', 'name' => 'Rainbow', 'cost' => 7500, 'css' => 'border: 3px solid transparent; background: linear-gradient(#1a1a2e, #1a1a2e) padding-box, linear-gradient(90deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #8f00ff) border-box; animation: rainbow-border 3s linear infinite'],
            ['id' => 'animated_fire', 'name' => 'Animated Fire', 'cost' => 8000, 'css' => 'border: 3px solid #ff4500; box-shadow: 0 0 15px #ff4500, 0 0 30px #ff6600; animation: fire-pulse 1s ease-in-out infinite'],
            ['id' => 'animated_ice', 'name' => 'Animated Ice', 'cost' => 8000, 'css' => 'border: 3px solid #00bfff; box-shadow: 0 0 15px #00bfff, 0 0 30px #87ceeb; animation: ice-shimmer 2s ease-in-out infinite'],
            ['id' => 'pixel', 'name' => 'Pixel Art', 'cost' => 4000, 'css' => 'border: 4px dashed #00ff00; box-shadow: 0 0 0 2px #000, 0 0 10px #00ff00'],
            ['id' => 'neon_glow', 'name' => 'Neon Glow', 'cost' => 6000, 'css' => 'border: 2px solid #fff; box-shadow: 0 0 10px #ff00ff, 0 0 20px #ff00ff, 0 0 30px #ff00ff, 0 0 40px #ff00ff']
        ]
    ],
    'avatar_effects' => [
        'name' => 'Avatar Effects',
        'icon' => '✨',
        'items' => [
            ['id' => 'sparkle', 'name' => 'Sparkle', 'cost' => 2000, 'animation' => 'sparkle'],
            ['id' => 'bounce', 'name' => 'Bounce', 'cost' => 2000, 'animation' => 'bounce'],
            ['id' => 'glow', 'name' => 'Glow', 'cost' => 2000, 'animation' => 'glow'],
            ['id' => 'shake', 'name' => 'Shake', 'cost' => 2000, 'animation' => 'shake'],
            ['id' => 'pulse', 'name' => 'Pulse', 'cost' => 2000, 'animation' => 'pulse'],
            ['id' => 'ghost', 'name' => 'Ghost', 'cost' => 3000, 'animation' => 'ghost'],
            ['id' => 'fire_aura', 'name' => 'Fire Aura', 'cost' => 4000, 'animation' => 'fire-aura'],
            ['id' => 'rainbow_trail', 'name' => 'Rainbow Trail', 'cost' => 4500, 'animation' => 'rainbow-trail'],
            ['id' => 'glitch', 'name' => 'Glitch', 'cost' => 3500, 'animation' => 'glitch']
        ]
    ],
    'avatar_rings' => [
        'name' => 'Avatar Rings',
        'icon' => '💍',
        'items' => [
            ['id' => 'gold', 'name' => 'Gold', 'cost' => 500],
            ['id' => 'rainbow', 'name' => 'Rainbow', 'cost' => 2000],
            ['id' => 'neon-pink', 'name' => 'Neon Pink', 'cost' => 750],
            ['id' => 'neon-blue', 'name' => 'Neon Blue', 'cost' => 750],
            ['id' => 'neon-green', 'name' => 'Neon Green', 'cost' => 750],
            ['id' => 'fire', 'name' => 'Fire', 'cost' => 1500],
            ['id' => 'ice', 'name' => 'Ice', 'cost' => 1500]
        ]
    ],
    'titles' => [
        'name' => 'Titles',
        'icon' => '🏷️',
        'items' => [
            // Standard Titles
            ['id' => 'champion', 'name' => 'Champion 👑', 'cost' => 5000, 'display' => 'Champion 👑'],
            ['id' => 'legend', 'name' => 'Legend ⭐', 'cost' => 7500, 'display' => 'Legend ⭐'],
            ['id' => 'whale', 'name' => 'Whale 🐋', 'cost' => 10000, 'display' => 'Whale 🐋'],
            ['id' => 'og', 'name' => 'OG 🔥', 'cost' => 15000, 'display' => 'OG 🔥', 'limited' => 10],
            // Premium Titles
            ['id' => 'goat', 'name' => 'The GOAT 🐐', 'cost' => 25000, 'display' => 'The GOAT 🐐', 'rarity' => 'legendary'],
            ['id' => 'arcade_king', 'name' => 'Arcade King 🕹️', 'cost' => 20000, 'display' => 'Arcade King 🕹️', 'rarity' => 'epic'],
            ['id' => 'arcade_queen', 'name' => 'Arcade Queen 👸', 'cost' => 20000, 'display' => 'Arcade Queen 👸', 'rarity' => 'epic'],
            ['id' => 'pixel_master', 'name' => 'Pixel Master 🎮', 'cost' => 15000, 'display' => 'Pixel Master 🎮', 'rarity' => 'rare'],
            ['id' => 'high_roller', 'name' => 'High Roller 🎰', 'cost' => 30000, 'display' => 'High Roller 🎰', 'rarity' => 'legendary'],
            ['id' => 'speedrunner', 'name' => 'Speedrunner ⚡', 'cost' => 12000, 'display' => 'Speedrunner ⚡', 'rarity' => 'rare'],
            ['id' => 'tryhard', 'name' => 'Tryhard 💪', 'cost' => 8000, 'display' => 'Tryhard 💪', 'rarity' => 'uncommon'],
            ['id' => 'noob', 'name' => 'Certified Noob 🤓', 'cost' => 3000, 'display' => 'Certified Noob 🤓', 'rarity' => 'common'],
            ['id' => 'pro_gamer', 'name' => 'Pro Gamer 🏆', 'cost' => 18000, 'display' => 'Pro Gamer 🏆', 'rarity' => 'epic'],
            ['id' => 'night_owl', 'name' => 'Night Owl 🦉', 'cost' => 10000, 'display' => 'Night Owl 🦉', 'rarity' => 'rare'],
            ['id' => 'collector', 'name' => 'Collector 💎', 'cost' => 35000, 'display' => 'Collector 💎', 'rarity' => 'legendary'],
            ['id' => 'boss_slayer', 'name' => 'Boss Slayer ⚔️', 'cost' => 22000, 'display' => 'Boss Slayer ⚔️', 'rarity' => 'epic'],
            ['id' => 'lucky', 'name' => 'Lucky 🍀', 'cost' => 7777, 'display' => 'Lucky 🍀', 'rarity' => 'rare'],
            ['id' => 'veteran', 'name' => 'Veteran 🎖️', 'cost' => 50000, 'display' => 'Veteran 🎖️', 'rarity' => 'legendary', 'limited' => 25],
            ['id' => 'founder', 'name' => 'Founder ✨', 'cost' => 100000, 'display' => 'Founder ✨', 'rarity' => 'mythic', 'limited' => 5]
        ]
    ],
    'banners' => [
        'name' => 'Profile Banners',
        'icon' => '🖼️',
        'items' => [
            ['id' => 'sunset', 'name' => 'Sunset', 'cost' => 2000, 'gradient' => 'linear-gradient(135deg, #ff6b6b, #feca57, #ff9f43)'],
            ['id' => 'ocean', 'name' => 'Ocean Wave', 'cost' => 2000, 'gradient' => 'linear-gradient(135deg, #667eea, #764ba2, #f953c6)'],
            ['id' => 'forest', 'name' => 'Forest', 'cost' => 2000, 'gradient' => 'linear-gradient(135deg, #11998e, #38ef7d)'],
            ['id' => 'galaxy', 'name' => 'Galaxy', 'cost' => 3500, 'gradient' => 'linear-gradient(135deg, #0f0c29, #302b63, #24243e)'],
            ['id' => 'fire', 'name' => 'Inferno', 'cost' => 3500, 'gradient' => 'linear-gradient(135deg, #f12711, #f5af19)'],
            ['id' => 'neon', 'name' => 'Neon City', 'cost' => 4000, 'gradient' => 'linear-gradient(135deg, #00d2ff, #3a7bd5, #f953c6, #f5576c)'],
            ['id' => 'rainbow', 'name' => 'Rainbow', 'cost' => 5000, 'gradient' => 'linear-gradient(135deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #8b00ff)'],
            ['id' => 'gold', 'name' => 'Golden', 'cost' => 7500, 'gradient' => 'linear-gradient(135deg, #f7971e, #ffd200, #b38728)'],
            ['id' => 'diamond', 'name' => 'Diamond', 'cost' => 10000, 'gradient' => 'linear-gradient(135deg, #00CED1, #7FFFD4, #E0FFFF, #00CED1)'],
            ['id' => 'animated_fire', 'name' => 'Animated Fire', 'cost' => 8000, 'gradient' => 'linear-gradient(135deg, #f12711, #f5af19)', 'animated' => true],
            ['id' => 'animated_water', 'name' => 'Animated Water', 'cost' => 8000, 'gradient' => 'linear-gradient(135deg, #667eea, #764ba2)', 'animated' => true]
        ]
    ],
    'emotes' => [
        'name' => 'Chat Emotes',
        'icon' => '😀',
        'items' => [
            ['id' => 'gg', 'name' => ':gg:', 'cost' => 300, 'emoji' => '🎮', 'display' => 'GG'],
            ['id' => 'nice', 'name' => ':nice:', 'cost' => 300, 'emoji' => '👍', 'display' => 'Nice!'],
            ['id' => 'rip', 'name' => ':rip:', 'cost' => 300, 'emoji' => '💀', 'display' => 'RIP'],
            ['id' => 'pog', 'name' => ':pog:', 'cost' => 300, 'emoji' => '😲', 'display' => 'POG'],
            ['id' => 'ez', 'name' => ':ez:', 'cost' => 300, 'emoji' => '😎', 'display' => 'EZ'],
            ['id' => 'sad', 'name' => ':sad:', 'cost' => 300, 'emoji' => '😢', 'display' => 'Sad'],
            ['id' => 'hype', 'name' => ':hype:', 'cost' => 300, 'emoji' => '🔥', 'display' => 'HYPE'],
            ['id' => 'love', 'name' => ':love:', 'cost' => 300, 'emoji' => '❤️', 'display' => 'Love'],
            ['id' => 'laugh', 'name' => ':laugh:', 'cost' => 300, 'emoji' => '😂', 'display' => 'LOL'],
            ['id' => 'think', 'name' => ':think:', 'cost' => 300, 'emoji' => '🤔', 'display' => 'Hmm'],
            ['id' => 'wow', 'name' => ':wow:', 'cost' => 300, 'emoji' => '😮', 'display' => 'WOW'],
            ['id' => 'angry', 'name' => ':angry:', 'cost' => 300, 'emoji' => '😡', 'display' => 'Angry'],
            ['id' => 'clap', 'name' => ':clap:', 'cost' => 300, 'emoji' => '👏', 'display' => 'GG WP'],
            ['id' => 'skull', 'name' => ':skull:', 'cost' => 300, 'emoji' => '💀', 'display' => 'Dead']
        ]
    ],
    'boosters' => [
        'name' => 'Boosters',
        'icon' => '⚡',
        'items' => [
            ['id' => 'xp_2x', 'name' => '2x XP (1 hour)', 'cost' => 1000, 'duration' => 3600, 'multiplier' => 2, 'type' => 'xp'],
            ['id' => 'coins_2x', 'name' => '2x Coins (1 hour)', 'cost' => 1500, 'duration' => 3600, 'multiplier' => 2, 'type' => 'coins']
        ]
    ],
    'social' => [
        'name' => 'Social Items',
        'icon' => '🏆',
        'items' => [
            ['id' => 'tournament_banner', 'name' => 'Tournament Banner', 'cost' => 3000, 'desc' => 'Custom gold banner on tournaments you create'],
            ['id' => 'spotlight', 'name' => 'Profile Spotlight (24h)', 'cost' => 5000, 'duration' => 86400, 'desc' => 'Featured on homepage for 24 hours']
        ]
    ],
    'cursors' => [
        'name' => 'Cursor Effects',
        'icon' => '🖱️',
        'items' => [
            ['id' => 'default', 'name' => 'Default', 'cost' => 0, 'cursor' => 'default'],
            ['id' => 'crosshair', 'name' => 'Crosshair', 'cost' => 500, 'cursor' => 'crosshair'],
            ['id' => 'pointer_gold', 'name' => 'Golden Pointer', 'cost' => 2000, 'cursor' => 'pointer', 'color' => '#ffd700'],
            ['id' => 'pointer_neon', 'name' => 'Neon Pointer', 'cost' => 2500, 'cursor' => 'pointer', 'color' => '#39ff14', 'glow' => true],
            ['id' => 'sword', 'name' => 'Pixel Sword', 'cost' => 3500, 'cursor' => 'custom', 'image' => '⚔️'],
            ['id' => 'wand', 'name' => 'Magic Wand', 'cost' => 4000, 'cursor' => 'custom', 'image' => '🪄', 'trail' => 'sparkle'],
            ['id' => 'fire_trail', 'name' => 'Fire Trail', 'cost' => 5000, 'cursor' => 'pointer', 'trail' => 'fire'],
            ['id' => 'rainbow_trail', 'name' => 'Rainbow Trail', 'cost' => 6000, 'cursor' => 'pointer', 'trail' => 'rainbow'],
            ['id' => 'star_trail', 'name' => 'Star Trail', 'cost' => 4500, 'cursor' => 'pointer', 'trail' => 'stars'],
            ['id' => 'snow_trail', 'name' => 'Snow Trail', 'cost' => 4000, 'cursor' => 'pointer', 'trail' => 'snow'],
            ['id' => 'laser', 'name' => 'Laser Pointer', 'cost' => 5500, 'cursor' => 'custom', 'image' => '🔴', 'trail' => 'laser'],
            ['id' => 'ghost_trail', 'name' => 'Ghost Trail', 'cost' => 5000, 'cursor' => 'pointer', 'trail' => 'ghost']
        ]
    ],
    'profile_music' => [
        'name' => 'Profile Music',
        'icon' => '🎵',
        'items' => [
            ['id' => 'none', 'name' => 'No Music', 'cost' => 0, 'track' => null],
            ['id' => 'retro_arcade', 'name' => 'Retro Arcade', 'cost' => 3000, 'track' => 'retro_arcade.mp3', 'desc' => 'Classic 8-bit arcade vibes'],
            ['id' => 'chiptune_hero', 'name' => 'Chiptune Hero', 'cost' => 3500, 'track' => 'chiptune_hero.mp3', 'desc' => 'Energetic chiptune beats'],
            ['id' => 'synthwave', 'name' => 'Synthwave Dreams', 'cost' => 4000, 'track' => 'synthwave.mp3', 'desc' => '80s synthwave nostalgia'],
            ['id' => 'lofi_chill', 'name' => 'Lo-Fi Chill', 'cost' => 3000, 'track' => 'lofi_chill.mp3', 'desc' => 'Relaxing lo-fi beats'],
            ['id' => 'epic_boss', 'name' => 'Epic Boss Battle', 'cost' => 5000, 'track' => 'epic_boss.mp3', 'desc' => 'Intense boss fight music'],
            ['id' => 'pixel_adventure', 'name' => 'Pixel Adventure', 'cost' => 3500, 'track' => 'pixel_adventure.mp3', 'desc' => 'Adventurous platformer tunes'],
            ['id' => 'space_journey', 'name' => 'Space Journey', 'cost' => 4500, 'track' => 'space_journey.mp3', 'desc' => 'Cosmic ambient vibes'],
            ['id' => 'victory_fanfare', 'name' => 'Victory Fanfare', 'cost' => 4000, 'track' => 'victory_fanfare.mp3', 'desc' => 'Triumphant celebration music'],
            ['id' => 'mystery_dungeon', 'name' => 'Mystery Dungeon', 'cost' => 3500, 'track' => 'mystery_dungeon.mp3', 'desc' => 'Mysterious exploration theme'],
            ['id' => 'racing_pulse', 'name' => 'Racing Pulse', 'cost' => 4000, 'track' => 'racing_pulse.mp3', 'desc' => 'High-speed racing beats'],
            ['id' => 'peaceful_meadow', 'name' => 'Peaceful Meadow', 'cost' => 2500, 'track' => 'peaceful_meadow.mp3', 'desc' => 'Calm nature sounds']
        ]
    ],
    'pets' => [
        'name' => 'Pets',
        'icon' => '🐾',
        'items' => [
            // Common Pets (1000-3000)
            ['id' => 'pixel_cat', 'name' => 'Pixel Cat', 'cost' => 2000, 'emoji' => '🐱', 'rarity' => 'common', 'animation' => 'bounce', 'desc' => 'A cute pixelated kitty'],
            ['id' => 'pixel_dog', 'name' => 'Pixel Dog', 'cost' => 2000, 'emoji' => '🐕', 'rarity' => 'common', 'animation' => 'wag', 'desc' => 'A loyal pixel pup'],
            ['id' => 'pixel_bunny', 'name' => 'Pixel Bunny', 'cost' => 2500, 'emoji' => '🐰', 'rarity' => 'common', 'animation' => 'hop', 'desc' => 'A bouncy little bunny'],
            ['id' => 'pixel_bird', 'name' => 'Pixel Bird', 'cost' => 1500, 'emoji' => '🐦', 'rarity' => 'common', 'animation' => 'fly', 'desc' => 'A cheerful songbird'],
            ['id' => 'pixel_fish', 'name' => 'Pixel Fish', 'cost' => 1000, 'emoji' => '🐠', 'rarity' => 'common', 'animation' => 'swim', 'desc' => 'A colorful fish friend'],
            // Uncommon Pets (3000-6000)
            ['id' => 'ghost_pet', 'name' => 'Friendly Ghost', 'cost' => 4000, 'emoji' => '👻', 'rarity' => 'uncommon', 'animation' => 'float', 'desc' => 'A spooky but friendly ghost'],
            ['id' => 'slime_pet', 'name' => 'Slime Buddy', 'cost' => 3500, 'emoji' => '🟢', 'rarity' => 'uncommon', 'animation' => 'jiggle', 'desc' => 'A wobbly slime companion'],
            ['id' => 'robot_pet', 'name' => 'Mini Robot', 'cost' => 5000, 'emoji' => '🤖', 'rarity' => 'uncommon', 'animation' => 'beep', 'desc' => 'A helpful little robot'],
            ['id' => 'bat_pet', 'name' => 'Pixel Bat', 'cost' => 4500, 'emoji' => '🦇', 'rarity' => 'uncommon', 'animation' => 'fly', 'desc' => 'A nocturnal friend'],
            ['id' => 'fox_pet', 'name' => 'Pixel Fox', 'cost' => 5500, 'emoji' => '🦊', 'rarity' => 'uncommon', 'animation' => 'sneak', 'desc' => 'A clever fox companion'],
            // Rare Pets (6000-12000)
            ['id' => 'dragon_baby', 'name' => 'Baby Dragon', 'cost' => 10000, 'emoji' => '🐲', 'rarity' => 'rare', 'animation' => 'breathe', 'desc' => 'A tiny fire-breathing dragon'],
            ['id' => 'unicorn_pet', 'name' => 'Mini Unicorn', 'cost' => 12000, 'emoji' => '🦄', 'rarity' => 'rare', 'animation' => 'sparkle', 'desc' => 'A magical unicorn'],
            ['id' => 'phoenix_pet', 'name' => 'Phoenix Chick', 'cost' => 11000, 'emoji' => '🔥', 'rarity' => 'rare', 'animation' => 'flame', 'desc' => 'A baby phoenix rising'],
            ['id' => 'alien_pet', 'name' => 'Alien Buddy', 'cost' => 8000, 'emoji' => '👽', 'rarity' => 'rare', 'animation' => 'hover', 'desc' => 'An extraterrestrial friend'],
            ['id' => 'wizard_cat', 'name' => 'Wizard Cat', 'cost' => 9000, 'emoji' => '🧙‍♂️', 'rarity' => 'rare', 'animation' => 'cast', 'desc' => 'A magical feline wizard'],
            // Epic Pets (15000-25000)
            ['id' => 'shadow_wolf', 'name' => 'Shadow Wolf', 'cost' => 18000, 'emoji' => '🐺', 'rarity' => 'epic', 'animation' => 'prowl', 'desc' => 'A mysterious dark wolf'],
            ['id' => 'crystal_golem', 'name' => 'Crystal Golem', 'cost' => 20000, 'emoji' => '💎', 'rarity' => 'epic', 'animation' => 'shine', 'desc' => 'A living crystal being'],
            ['id' => 'thunder_tiger', 'name' => 'Thunder Tiger', 'cost' => 22000, 'emoji' => '🐯', 'rarity' => 'epic', 'animation' => 'spark', 'desc' => 'An electric tiger'],
            ['id' => 'ice_dragon', 'name' => 'Ice Dragon', 'cost' => 25000, 'emoji' => '❄️', 'rarity' => 'epic', 'animation' => 'freeze', 'desc' => 'A frost-breathing dragon'],
            // Legendary Pets (30000-50000)
            ['id' => 'golden_dragon', 'name' => 'Golden Dragon', 'cost' => 50000, 'emoji' => '🌟', 'rarity' => 'legendary', 'animation' => 'majestic', 'desc' => 'The ultimate dragon companion'],
            ['id' => 'void_cat', 'name' => 'Void Cat', 'cost' => 40000, 'emoji' => '🌌', 'rarity' => 'legendary', 'animation' => 'warp', 'desc' => 'A cat from another dimension'],
            ['id' => 'celestial_owl', 'name' => 'Celestial Owl', 'cost' => 45000, 'emoji' => '🦉', 'rarity' => 'legendary', 'animation' => 'cosmos', 'desc' => 'A wise cosmic owl'],
            // Mythic Pets (75000+)
            ['id' => 'rainbow_serpent', 'name' => 'Rainbow Serpent', 'cost' => 75000, 'emoji' => '🌈', 'rarity' => 'mythic', 'animation' => 'rainbow', 'desc' => 'A legendary rainbow serpent', 'limited' => 10],
            ['id' => 'omega_pet', 'name' => 'OMEGA Entity', 'cost' => 100000, 'emoji' => '💀', 'rarity' => 'mythic', 'animation' => 'omega', 'desc' => 'The ultimate companion', 'limited' => 3]
        ]
    ]
];

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function writeUsers($data) {
    global $usersFile;
    store_write($usersFile, $data);
}

function readShopData() {
    global $shopDataFile;
    if (!file_exists($shopDataFile)) return ['limitedItems' => []];
    return json_decode(file_get_contents($shopDataFile), true) ?: ['limitedItems' => []];
}

function writeShopData($data) {
    global $shopDataFile;
    store_write($shopDataFile, $data);
}

function ensureUserHasShopFields(&$user) {
    if (!isset($user['coins'])) $user['coins'] = 0;
    if (!isset($user['inventory'])) {
        $user['inventory'] = [
            'name_colors' => [],
            'borders' => [],
            'avatar_effects' => [],
            'avatar_rings' => [],
            'titles' => [],
            'banners' => [],
            'emotes' => [],
            'boosters' => [],
            'social' => [],
            'cursors' => [],
            'profile_music' => [],
            'pets' => []
        ];
    }
    // Ensure all categories exist for existing users
    $categories = ['banners', 'titles', 'avatar_rings', 'cursors', 'profile_music', 'pets'];
    foreach ($categories as $cat) {
        if (!isset($user['inventory'][$cat])) {
            $user['inventory'][$cat] = [];
        }
    }
    if (!isset($user['equipped'])) {
        $user['equipped'] = [
            'name_color' => null,
            'border' => null,
            'avatar_effect' => null,
            'avatar_ring' => null,
            'title' => null,
            'banner' => null,
            'cursor' => null,
            'profile_music' => null,
            'pet' => null
        ];
    }
    // Ensure all equip slots exist
    $equipSlots = ['title', 'avatar_ring', 'cursor', 'profile_music', 'pet'];
    foreach ($equipSlots as $slot) {
        if (!isset($user['equipped'][$slot])) {
            $user['equipped'][$slot] = null;
        }
    }
    if (!isset($user['activeBoosts'])) $user['activeBoosts'] = [];
}

function getItemFromShop($category, $itemId) {
    global $SHOP_ITEMS;
    if (!isset($SHOP_ITEMS[$category])) return null;
    foreach ($SHOP_ITEMS[$category]['items'] as $item) {
        if ($item['id'] === $itemId) return $item;
    }
    return null;
}

function cleanExpiredBoosts(&$user) {
    $now = time();
    $user['activeBoosts'] = array_filter($user['activeBoosts'], function($boost) use ($now) {
        return strtotime($boost['expiresAt']) > $now;
    });
    $user['activeBoosts'] = array_values($user['activeBoosts']);
}

function getLimitedItemPurchaseCount($itemId) {
    $shopData = readShopData();
    return $shopData['limitedItems'][$itemId]['count'] ?? 0;
}

function incrementLimitedItemCount($itemId) {
    $shopData = readShopData();
    if (!isset($shopData['limitedItems'][$itemId])) {
        $shopData['limitedItems'][$itemId] = ['count' => 0, 'buyers' => []];
    }
    $shopData['limitedItems'][$itemId]['count']++;
    writeShopData($shopData);
}

function addLimitedItemBuyer($itemId, $username) {
    $shopData = readShopData();
    if (!isset($shopData['limitedItems'][$itemId])) {
        $shopData['limitedItems'][$itemId] = ['count' => 0, 'buyers' => []];
    }
    $shopData['limitedItems'][$itemId]['buyers'][] = $username;
    $shopData['limitedItems'][$itemId]['count'] = count($shopData['limitedItems'][$itemId]['buyers']);
    writeShopData($shopData);
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'items';
    
    if ($action === 'items') {
        // Add limited item info to shop data
        $shopData = readShopData();
        $shopWithLimits = $SHOP_ITEMS;
        
        // Add purchase counts for limited items
        foreach ($shopWithLimits as $catKey => &$category) {
            foreach ($category['items'] as &$item) {
                if (isset($item['limited'])) {
                    $item['purchaseCount'] = $shopData['limitedItems'][$item['id']]['count'] ?? 0;
                    $item['soldOut'] = $item['purchaseCount'] >= $item['limited'];
                }
            }
        }
        
        echo json_encode(['success' => true, 'shop' => $shopWithLimits]);
        exit();
    }
    
    if ($action === 'status') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit();
        }
        
        $data = readUsers();
        $username = $_SESSION['user'];
        
        if (!isset($data['users'][$username])) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit();
        }
        
        $user = $data['users'][$username];
        
        echo json_encode([
            'success' => true,
            'equipped' => $user['equipped'] ?? []
        ]);
        exit();
    }

    if ($action === 'inventory') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit();
        }
        
        $data = readUsers();
        $username = $_SESSION['user'];
        
        if (!isset($data['users'][$username])) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit();
        }
        
        $user = &$data['users'][$username];
        ensureUserHasShopFields($user);
        cleanExpiredBoosts($user);
        writeUsers($data);
        
        echo json_encode([
            'success' => true,
            'coins' => $user['coins'],
            'inventory' => $user['inventory'],
            'equipped' => $user['equipped'],
            'activeBoosts' => $user['activeBoosts']
        ]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit();
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $data = readUsers();
    $username = $_SESSION['user'];
    
    if (!isset($data['users'][$username])) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    $user = &$data['users'][$username];
    ensureUserHasShopFields($user);
    cleanExpiredBoosts($user);
    
    if ($action === 'purchase') {
        $category = $input['category'] ?? '';
        $itemId = $input['itemId'] ?? '';
        
        $item = getItemFromShop($category, $itemId);
        if (!$item) {
            echo json_encode(['success' => false, 'error' => 'Item not found']);
            exit();
        }
        
        // Check if already owned (except boosters which can be bought multiple times)
        if ($category !== 'boosters' && in_array($itemId, $user['inventory'][$category] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'You already own this item']);
            exit();
        }
        
        // Check if limited item is sold out
        if (isset($item['limited'])) {
            $purchaseCount = getLimitedItemPurchaseCount($itemId);
            if ($purchaseCount >= $item['limited']) {
                echo json_encode(['success' => false, 'error' => 'This limited item is sold out!']);
                exit();
            }
        }
        
        // Check coins
        if ($user['coins'] < $item['cost']) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit();
        }
        
        // Purchase
        $user['coins'] -= $item['cost'];
        
        if ($category === 'boosters') {
            // Add to boosters inventory (consumable)
            $user['inventory']['boosters'][] = $itemId;
        } else {
            // Add to inventory
            if (!isset($user['inventory'][$category])) {
                $user['inventory'][$category] = [];
            }
            $user['inventory'][$category][] = $itemId;
            
            // Track limited item purchase
            if (isset($item['limited'])) {
                addLimitedItemBuyer($itemId, $username);
            }
        }
        
        writeUsers($data);
        
        echo json_encode([
            'success' => true,
            'message' => 'Purchased ' . $item['name'],
            'coins' => $user['coins'],
            'inventory' => $user['inventory']
        ]);
        exit();
    }
    
    if ($action === 'equip') {
        $category = $input['category'] ?? '';
        $itemId = $input['itemId'] ?? '';
        
        // Map category to equipped key
        $equipKey = str_replace('s', '', $category); // name_colors -> name_color
        if ($category === 'borders') $equipKey = 'border';
        if ($category === 'avatar_effects') $equipKey = 'avatar_effect';
        if ($category === 'titles') $equipKey = 'title';
        if ($category === 'banners') $equipKey = 'banner';
        if ($category === 'avatar_rings') $equipKey = 'avatar_ring';
        if ($category === 'cursors') $equipKey = 'cursor';
        if ($category === 'profile_music') $equipKey = 'profile_music';
        if ($category === 'pets') $equipKey = 'pet';

        // Check ownership
        if (!in_array($itemId, $user['inventory'][$category] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'You do not own this item']);
            exit();
        }
        
        $user['equipped'][$equipKey] = $itemId;
        writeUsers($data);
        
        echo json_encode([
            'success' => true,
            'equipped' => $user['equipped']
        ]);
        exit();
    }
    
    if ($action === 'unequip') {
        $category = $input['category'] ?? '';
        
        $equipKey = str_replace('s', '', $category);
        if ($category === 'borders') $equipKey = 'border';
        if ($category === 'avatar_effects') $equipKey = 'avatar_effect';
        if ($category === 'titles') $equipKey = 'title';
        if ($category === 'banners') $equipKey = 'banner';
        if ($category === 'avatar_rings') $equipKey = 'avatar_ring';
        if ($category === 'cursors') $equipKey = 'cursor';
        if ($category === 'profile_music') $equipKey = 'profile_music';
        if ($category === 'pets') $equipKey = 'pet';

        $user['equipped'][$equipKey] = null;
        writeUsers($data);
        
        echo json_encode([
            'success' => true,
            'equipped' => $user['equipped']
        ]);
        exit();
    }
    
    if ($action === 'activate-boost') {
        $boostType = $input['boostType'] ?? '';
        
        // Check if user has this boost in inventory
        $boostIndex = array_search($boostType, $user['inventory']['boosters'] ?? []);
        if ($boostIndex === false) {
            echo json_encode(['success' => false, 'error' => 'You do not have this boost']);
            exit();
        }
        
        // Get boost info
        $boostItem = getItemFromShop('boosters', $boostType);
        if (!$boostItem) {
            echo json_encode(['success' => false, 'error' => 'Invalid boost']);
            exit();
        }
        
        // Check if already have active boost of same type
        foreach ($user['activeBoosts'] as $active) {
            if ($active['type'] === $boostType) {
                echo json_encode(['success' => false, 'error' => 'You already have this boost active']);
                exit();
            }
        }
        
        // Remove from inventory
        array_splice($user['inventory']['boosters'], $boostIndex, 1);
        
        // Add to active boosts
        $user['activeBoosts'][] = [
            'type' => $boostType,
            'multiplier' => $boostItem['multiplier'],
            'boostFor' => $boostItem['type'],
            'expiresAt' => date('c', time() + $boostItem['duration'])
        ];
        
        writeUsers($data);
        
        echo json_encode([
            'success' => true,
            'message' => 'Activated ' . $boostItem['name'],
            'activeBoosts' => $user['activeBoosts'],
            'inventory' => $user['inventory']
        ]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
