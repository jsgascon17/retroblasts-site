// Zookeeper Admin/Owner Panel System
const ADMIN_PASSWORD = "zoo123";
const OWNER_PASSWORD = "owner2024";
let adminKeyBuffer = "";
let ownerKeyBuffer = "";
let adminPanelOpen = false;
let ownerPanelOpen = false;

// Inject styles
const adminStyles = document.createElement('style');
adminStyles.textContent = `
    .admin-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); display: flex; justify-content: center; align-items: center; z-index: 10000; }
    .admin-content { background: linear-gradient(135deg, #1a1a2e, #16213e); border: 3px solid #ff6b6b; border-radius: 20px; padding: 30px; max-width: 450px; text-align: center; color: #fff; font-family: 'Press Start 2P', monospace; }
    .admin-content.owner { border-color: #ffd700; }
    .admin-content h2 { color: #ff6b6b; margin-bottom: 20px; font-size: 1rem; }
    .admin-content.owner h2 { color: #ffd700; }
    .admin-content input { width: 100%; padding: 12px; border-radius: 10px; border: 2px solid #ff6b6b; background: rgba(0,0,0,0.5); color: #fff; font-family: inherit; font-size: 0.7rem; text-align: center; margin: 10px 0; }
    .admin-content.owner input { border-color: #ffd700; }
    .admin-btn { padding: 10px 20px; border-radius: 10px; border: none; font-family: inherit; font-size: 0.6rem; cursor: pointer; margin: 5px; }
    .admin-btn-primary { background: #ff6b6b; color: #fff; }
    .admin-btn-owner { background: #ffd700; color: #000; }
    .admin-btn-close { background: #666; color: #fff; }
    .admin-actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 20px; }
    .admin-action-btn { padding: 12px 20px; background: rgba(255,255,255,0.1); border: 2px solid #fff; border-radius: 10px; color: #fff; cursor: pointer; font-family: inherit; font-size: 0.55rem; }
    .admin-action-btn:hover { background: rgba(255,255,255,0.2); }
    .admin-action-btn.danger { border-color: #ff6b6b; color: #ff6b6b; }
`;
document.head.appendChild(adminStyles);

document.addEventListener("keydown", (e) => {
    const tag = document.activeElement?.tagName?.toLowerCase();
    if (tag === "input" || tag === "textarea") return;

    adminKeyBuffer += e.key.toLowerCase();
    ownerKeyBuffer += e.key.toLowerCase();
    if (adminKeyBuffer.length > 5) adminKeyBuffer = adminKeyBuffer.slice(-5);
    if (ownerKeyBuffer.length > 5) ownerKeyBuffer = ownerKeyBuffer.slice(-5);

    if (adminKeyBuffer === "admin") { showAdminLogin(); adminKeyBuffer = ""; }
    if (ownerKeyBuffer === "owner") { showOwnerLogin(); ownerKeyBuffer = ""; }
});

function showAdminLogin() {
    const modal = document.createElement("div");
    modal.className = "admin-modal";
    modal.id = "adminLoginModal";
    modal.innerHTML = `
        <div class="admin-content">
            <h2>ADMIN LOGIN</h2>
            <input type="password" id="adminPwInput" placeholder="Enter Admin Password" maxlength="20">
            <div>
                <button class="admin-btn admin-btn-primary" onclick="verifyAdminPassword()">LOGIN</button>
                <button class="admin-btn admin-btn-close" onclick="closeAdminModal()">CANCEL</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    document.getElementById("adminPwInput").focus();
}

function showOwnerLogin() {
    const modal = document.createElement("div");
    modal.className = "admin-modal";
    modal.id = "ownerLoginModal";
    modal.innerHTML = `
        <div class="admin-content owner">
            <h2>OWNER LOGIN</h2>
            <input type="password" id="ownerPwInput" placeholder="Enter Owner Password" maxlength="20">
            <div>
                <button class="admin-btn admin-btn-owner" onclick="verifyOwnerPassword()">LOGIN</button>
                <button class="admin-btn admin-btn-close" onclick="closeAdminModal()">CANCEL</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    document.getElementById("ownerPwInput").focus();
}

function verifyAdminPassword() {
    const pw = document.getElementById("adminPwInput").value;
    if (pw === ADMIN_PASSWORD) { closeAdminModal(); showAdminPanel(); }
    else { document.getElementById("adminPwInput").value = ""; alert("Wrong password!"); }
}

function verifyOwnerPassword() {
    const pw = document.getElementById("ownerPwInput").value;
    if (pw === OWNER_PASSWORD) { closeAdminModal(); showOwnerPanel(); }
    else { document.getElementById("ownerPwInput").value = ""; alert("Wrong password!"); }
}

function closeAdminModal() {
    document.getElementById("adminLoginModal")?.remove();
    document.getElementById("ownerLoginModal")?.remove();
    document.getElementById("adminPanelModal")?.remove();
    document.getElementById("ownerPanelModal")?.remove();
    adminPanelOpen = false;
    ownerPanelOpen = false;
}

function showAdminPanel() {
    adminPanelOpen = true;
    const modal = document.createElement("div");
    modal.className = "admin-modal";
    modal.id = "adminPanelModal";
    modal.innerHTML = `
        <div class="admin-content">
            <h2>ADMIN PANEL</h2>
            <p style="color:#888;font-size:0.5rem;margin-bottom:15px;">Coins: ${coins}</p>
            <div class="admin-actions">
                <button class="admin-action-btn" onclick="adminAddCoins(1000)">+1000 Coins</button>
                <button class="admin-action-btn" onclick="adminAddCoins(10000)">+10K Coins</button>
                <button class="admin-action-btn" onclick="adminUnlockZone()">Unlock All Zones</button>
                <button class="admin-action-btn" onclick="adminMaxUpgrades()">Max Upgrades</button>
            </div>
            <button class="admin-btn admin-btn-close" style="margin-top:20px;" onclick="closeAdminModal()">CLOSE</button>
        </div>
    `;
    document.body.appendChild(modal);
}

function showOwnerPanel() {
    ownerPanelOpen = true;
    const modal = document.createElement("div");
    modal.className = "admin-modal";
    modal.id = "ownerPanelModal";
    modal.innerHTML = `
        <div class="admin-content owner">
            <h2>OWNER PANEL</h2>
            <p style="color:#888;font-size:0.5rem;margin-bottom:15px;">Coins: ${coins}</p>
            <div class="admin-actions">
                <button class="admin-action-btn" onclick="ownerSetCoins()">Set Coins</button>
                <button class="admin-action-btn" onclick="ownerInfiniteCoins()">Infinite Coins</button>
                <button class="admin-action-btn" onclick="adminUnlockZone()">Unlock Everything</button>
                <button class="admin-action-btn" onclick="adminMaxUpgrades()">Max All Upgrades</button>
                <button class="admin-action-btn danger" onclick="ownerResetGame()">RESET GAME</button>
            </div>
            <button class="admin-btn admin-btn-close" style="margin-top:20px;" onclick="closeAdminModal()">CLOSE</button>
        </div>
    `;
    document.body.appendChild(modal);
}

function adminAddCoins(amount) {
    coins += amount;
    updateUI();
    saveGame();
    closeAdminModal();
    showMessage("+" + amount + " coins!");
}

function adminUnlockZone() {
    upgrades.forest.owned = true;
    upgrades.arctic.owned = true;
    upgrades.jungle.owned = true;
    updateShop();
    saveGame();
    closeAdminModal();
    showMessage("All zones unlocked!");
}

function adminMaxUpgrades() {
    upgrades.ticket.level = upgrades.ticket.maxLevel;
    upgrades.ad.level = upgrades.ad.maxLevel;
    upgrades.zoo.level = upgrades.zoo.maxLevel;
    upgrades.fence.level = upgrades.fence.maxLevel;
    zooSize = 20 + upgrades.zoo.level * 10;
    updateShop();
    updateIncome();
    saveGame();
    closeAdminModal();
    showMessage("All upgrades maxed!");
}

function ownerSetCoins() {
    const amount = prompt("Enter coin amount:");
    if (amount && !isNaN(amount)) {
        coins = parseInt(amount);
        updateUI();
        saveGame();
        closeAdminModal();
        showMessage("Coins set to " + coins);
    }
}

function ownerInfiniteCoins() {
    coins = 999999999;
    updateUI();
    saveGame();
    closeAdminModal();
    showMessage("Infinite coins!");
}

function ownerResetGame() {
    if (confirm("Are you sure you want to RESET ALL PROGRESS?")) {
        localStorage.removeItem(SAVE_KEY);
        location.reload();
    }
}
