# RetroBlasts

## What is this?
A gaming platform with retro browser games, user accounts, leaderboards, chat, and more.

## URLs
- **Dev (test here):** https://retroblasts.dev.freshbuild.co
- **Production (live site):** https://retroblasts.com

## Tech Stack
- **Frontend:** HTML, CSS, JavaScript (each game is its own .html file)
- **Backend:** PHP APIs in the api/ directory
- **Data:** JSON files in data/ and leaderboards/ (NOT in git — each site has its own copy)

## How to deploy
1. Make changes and test on the dev site
2. Push to GitHub: git add, git commit, git push
3. SSH to server: ssh devserver
4. Update dev site: cd /var/www/clients/freshbuild/retroblasts && git pull
5. When ready to go live: cd /var/www/retroblasts.com && git pull

## Important rules
- Do NOT add data/ or leaderboards/ to git — they are in .gitignore on purpose. Each site keeps its own user accounts and scores.
- When adding a new game, create a single .html file with everything inline (HTML + CSS + JS)
- PHP API files go in api/ — they read and write JSON files in data/
