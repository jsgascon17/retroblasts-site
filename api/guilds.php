<?php
header("Content-Type: application/json");
session_start();

$guildsFile = __DIR__ . "/../data/guilds.json";
$usersFile = __DIR__ . "/../data/users.json";

function loadGuilds() {
    global $guildsFile;
    if (!file_exists($guildsFile)) return ["guilds" => [], "nextId" => 1];
    return json_decode(file_get_contents($guildsFile), true) ?: ["guilds" => [], "nextId" => 1];
}

function saveGuilds($data) {
    global $guildsFile;
    file_put_contents($guildsFile, json_encode($data, JSON_PRETTY_PRINT));
}

function loadUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ["users" => []];
    return json_decode(file_get_contents($usersFile), true) ?: ["users" => []];
}

function saveUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

// Guild icons/badges
$GUILD_ICONS = ["⚔️", "🛡️", "🏰", "🐉", "🦁", "🐺", "🦅", "🔥", "⚡", "💎", "👑", "🌟", "🎮", "🚀", "💀", "🎯"];

// Guild perks by level
$GUILD_PERKS = [
    1 => ["name" => "Starter", "coinBonus" => 0, "xpBonus" => 0, "maxMembers" => 10],
    2 => ["name" => "Bronze", "coinBonus" => 2, "xpBonus" => 2, "maxMembers" => 15],
    3 => ["name" => "Silver", "coinBonus" => 5, "xpBonus" => 5, "maxMembers" => 20],
    4 => ["name" => "Gold", "coinBonus" => 8, "xpBonus" => 8, "maxMembers" => 30],
    5 => ["name" => "Platinum", "coinBonus" => 12, "xpBonus" => 12, "maxMembers" => 40],
    6 => ["name" => "Diamond", "coinBonus" => 15, "xpBonus" => 15, "maxMembers" => 50],
    7 => ["name" => "Master", "coinBonus" => 20, "xpBonus" => 20, "maxMembers" => 75],
    8 => ["name" => "Legendary", "coinBonus" => 25, "xpBonus" => 25, "maxMembers" => 100],
];

function getGuildLevel($xp) {
    if ($xp >= 100000) return 8;
    if ($xp >= 50000) return 7;
    if ($xp >= 25000) return 6;
    if ($xp >= 10000) return 5;
    if ($xp >= 5000) return 4;
    if ($xp >= 2000) return 3;
    if ($xp >= 500) return 2;
    return 1;
}

function getXpForNextLevel($level) {
    $thresholds = [0, 500, 2000, 5000, 10000, 25000, 50000, 100000, 999999999];
    return $thresholds[$level] ?? 999999999;
}

// Handle GET requests
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $action = $_GET["action"] ?? "";
    
    // Get all guilds (for browsing)
    if ($action === "list") {
        $data = loadGuilds();
        $guilds = [];
        
        foreach ($data["guilds"] as $id => $guild) {
            $level = getGuildLevel($guild["xp"]);
            $guilds[] = [
                "id" => $id,
                "name" => $guild["name"],
                "tag" => $guild["tag"],
                "icon" => $guild["icon"],
                "description" => $guild["description"],
                "memberCount" => count($guild["members"]),
                "maxMembers" => $GUILD_PERKS[$level]["maxMembers"],
                "level" => $level,
                "xp" => $guild["xp"],
                "leader" => $guild["leader"],
                "isOpen" => $guild["isOpen"] ?? true,
                "created" => $guild["created"]
            ];
        }
        
        // Sort by XP descending
        usort($guilds, fn($a, $b) => $b["xp"] - $a["xp"]);
        
        echo json_encode(["success" => true, "guilds" => $guilds]);
        exit;
    }
    
    // Get single guild details
    if ($action === "get") {
        $guildId = $_GET["id"] ?? "";
        $data = loadGuilds();
        
        if (!isset($data["guilds"][$guildId])) {
            echo json_encode(["success" => false, "error" => "Guild not found"]);
            exit;
        }
        
        $guild = $data["guilds"][$guildId];
        $level = getGuildLevel($guild["xp"]);
        global $GUILD_PERKS;
        
        // Get member details
        $users = loadUsers();
        $members = [];
        foreach ($guild["members"] as $member) {
            $username = $member["username"];
            $user = $users["users"][$username] ?? null;
            $members[] = [
                "username" => $username,
                "role" => $member["role"],
                "joined" => $member["joined"],
                "xpContributed" => $member["xpContributed"] ?? 0,
                "coins" => $user["coins"] ?? 0
            ];
        }
        
        // Sort by XP contributed
        usort($members, fn($a, $b) => $b["xpContributed"] - $a["xpContributed"]);
        
        echo json_encode([
            "success" => true,
            "guild" => [
                "id" => $guildId,
                "name" => $guild["name"],
                "tag" => $guild["tag"],
                "icon" => $guild["icon"],
                "description" => $guild["description"],
                "level" => $level,
                "xp" => $guild["xp"],
                "xpForNext" => getXpForNextLevel($level),
                "perks" => $GUILD_PERKS[$level],
                "leader" => $guild["leader"],
                "officers" => $guild["officers"] ?? [],
                "members" => $members,
                "memberCount" => count($members),
                "maxMembers" => $GUILD_PERKS[$level]["maxMembers"],
                "isOpen" => $guild["isOpen"] ?? true,
                "created" => $guild["created"],
                "chat" => array_slice($guild["chat"] ?? [], -50)
            ]
        ]);
        exit;
    }
    
    // Get user guild info
    if ($action === "myGuild") {
        if (!isset($_SESSION["user"])) {
            echo json_encode(["success" => false, "error" => "Not logged in"]);
            exit;
        }
        
        $username = $_SESSION["user"];
        $users = loadUsers();
        $guildId = $users["users"][$username]["guildId"] ?? null;
        
        if (!$guildId) {
            echo json_encode(["success" => true, "guild" => null]);
            exit;
        }
        
        $data = loadGuilds();
        if (!isset($data["guilds"][$guildId])) {
            // Guild was deleted, clear user guild
            $users["users"][$username]["guildId"] = null;
            saveUsers($users);
            echo json_encode(["success" => true, "guild" => null]);
            exit;
        }
        
        $guild = $data["guilds"][$guildId];
        $level = getGuildLevel($guild["xp"]);
        global $GUILD_PERKS;
        
        // Find user role
        $myRole = "member";
        foreach ($guild["members"] as $m) {
            if ($m["username"] === $username) {
                $myRole = $m["role"];
                break;
            }
        }
        
        echo json_encode([
            "success" => true,
            "guild" => [
                "id" => $guildId,
                "name" => $guild["name"],
                "tag" => $guild["tag"],
                "icon" => $guild["icon"],
                "level" => $level,
                "xp" => $guild["xp"],
                "memberCount" => count($guild["members"]),
                "myRole" => $myRole,
                "perks" => $GUILD_PERKS[$level]
            ]
        ]);
        exit;
    }
}

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);
    $action = $input["action"] ?? "";
    
    if (!isset($_SESSION["user"])) {
        echo json_encode(["success" => false, "error" => "Not logged in"]);
        exit;
    }
    
    $username = $_SESSION["user"];
    $users = loadUsers();
    
    // Create guild
    if ($action === "create") {
        $name = trim($input["name"] ?? "");
        $tag = strtoupper(trim($input["tag"] ?? ""));
        $icon = $input["icon"] ?? "⚔️";
        $description = trim($input["description"] ?? "");
        
        // Validation
        if (strlen($name) < 3 || strlen($name) > 24) {
            echo json_encode(["success" => false, "error" => "Name must be 3-24 characters"]);
            exit;
        }
        if (strlen($tag) < 2 || strlen($tag) > 5) {
            echo json_encode(["success" => false, "error" => "Tag must be 2-5 characters"]);
            exit;
        }
        if (!preg_match("/^[A-Z0-9]+$/", $tag)) {
            echo json_encode(["success" => false, "error" => "Tag must be letters/numbers only"]);
            exit;
        }
        
        // Check if user already in guild
        if (!empty($users["users"][$username]["guildId"])) {
            echo json_encode(["success" => false, "error" => "You are already in a guild"]);
            exit;
        }
        
        // Check cost (500 coins to create)
        $userCoins = $users["users"][$username]["coins"] ?? 0;
        if ($userCoins < 500) {
            echo json_encode(["success" => false, "error" => "Need 500 coins to create a guild"]);
            exit;
        }
        
        $data = loadGuilds();
        
        // Check for duplicate name/tag
        foreach ($data["guilds"] as $g) {
            if (strtolower($g["name"]) === strtolower($name)) {
                echo json_encode(["success" => false, "error" => "Guild name already taken"]);
                exit;
            }
            if ($g["tag"] === $tag) {
                echo json_encode(["success" => false, "error" => "Tag already taken"]);
                exit;
            }
        }
        
        // Create guild
        $guildId = "g" . $data["nextId"];
        $data["nextId"]++;
        
        $data["guilds"][$guildId] = [
            "name" => $name,
            "tag" => $tag,
            "icon" => $icon,
            "description" => $description,
            "leader" => $username,
            "officers" => [],
            "members" => [
                ["username" => $username, "role" => "leader", "joined" => date("Y-m-d H:i:s"), "xpContributed" => 0]
            ],
            "xp" => 0,
            "isOpen" => true,
            "chat" => [],
            "created" => date("Y-m-d H:i:s")
        ];
        
        saveGuilds($data);
        
        // Deduct coins and set guild
        $users["users"][$username]["coins"] -= 500;
        $users["users"][$username]["guildId"] = $guildId;
        saveUsers($users);
        
        echo json_encode(["success" => true, "guildId" => $guildId]);
        exit;
    }
    
    // Join guild
    if ($action === "join") {
        $guildId = $input["guildId"] ?? "";
        
        if (!empty($users["users"][$username]["guildId"])) {
            echo json_encode(["success" => false, "error" => "You are already in a guild"]);
            exit;
        }
        
        $data = loadGuilds();
        
        if (!isset($data["guilds"][$guildId])) {
            echo json_encode(["success" => false, "error" => "Guild not found"]);
            exit;
        }
        
        $guild = &$data["guilds"][$guildId];
        $level = getGuildLevel($guild["xp"]);
        global $GUILD_PERKS;
        
        if (!($guild["isOpen"] ?? true)) {
            echo json_encode(["success" => false, "error" => "Guild is invite-only"]);
            exit;
        }
        
        if (count($guild["members"]) >= $GUILD_PERKS[$level]["maxMembers"]) {
            echo json_encode(["success" => false, "error" => "Guild is full"]);
            exit;
        }
        
        $guild["members"][] = [
            "username" => $username,
            "role" => "member",
            "joined" => date("Y-m-d H:i:s"),
            "xpContributed" => 0
        ];
        
        saveGuilds($data);
        
        $users["users"][$username]["guildId"] = $guildId;
        saveUsers($users);
        
        echo json_encode(["success" => true]);
        exit;
    }
    
    // Leave guild
    if ($action === "leave") {
        $guildId = $users["users"][$username]["guildId"] ?? null;
        
        if (!$guildId) {
            echo json_encode(["success" => false, "error" => "Not in a guild"]);
            exit;
        }
        
        $data = loadGuilds();
        
        if (!isset($data["guilds"][$guildId])) {
            $users["users"][$username]["guildId"] = null;
            saveUsers($users);
            echo json_encode(["success" => true]);
            exit;
        }
        
        $guild = &$data["guilds"][$guildId];
        
        // If leader, must transfer or disband
        if ($guild["leader"] === $username) {
            if (count($guild["members"]) > 1) {
                echo json_encode(["success" => false, "error" => "Transfer leadership before leaving"]);
                exit;
            }
            // Last member, delete guild
            unset($data["guilds"][$guildId]);
        } else {
            // Remove from members
            $guild["members"] = array_values(array_filter($guild["members"], fn($m) => $m["username"] !== $username));
            $guild["officers"] = array_values(array_filter($guild["officers"] ?? [], fn($o) => $o !== $username));
        }
        
        saveGuilds($data);
        
        $users["users"][$username]["guildId"] = null;
        saveUsers($users);
        
        echo json_encode(["success" => true]);
        exit;
    }
    
    // Send chat message
    if ($action === "chat") {
        $message = trim($input["message"] ?? "");
        $guildId = $users["users"][$username]["guildId"] ?? null;
        
        if (!$guildId) {
            echo json_encode(["success" => false, "error" => "Not in a guild"]);
            exit;
        }
        
        if (strlen($message) < 1 || strlen($message) > 200) {
            echo json_encode(["success" => false, "error" => "Message must be 1-200 characters"]);
            exit;
        }
        
        $data = loadGuilds();
        
        if (!isset($data["guilds"][$guildId])) {
            echo json_encode(["success" => false, "error" => "Guild not found"]);
            exit;
        }
        
        $data["guilds"][$guildId]["chat"][] = [
            "username" => $username,
            "message" => $message,
            "time" => date("Y-m-d H:i:s")
        ];
        
        // Keep only last 100 messages
        if (count($data["guilds"][$guildId]["chat"]) > 100) {
            $data["guilds"][$guildId]["chat"] = array_slice($data["guilds"][$guildId]["chat"], -100);
        }
        
        saveGuilds($data);
        
        echo json_encode(["success" => true]);
        exit;
    }
    
    // Add XP to guild (called by games)
    if ($action === "addXP") {
        $xp = intval($input["xp"] ?? 0);
        $guildId = $users["users"][$username]["guildId"] ?? null;
        
        if (!$guildId || $xp <= 0) {
            echo json_encode(["success" => true]); // Silent fail
            exit;
        }
        
        $data = loadGuilds();
        
        if (!isset($data["guilds"][$guildId])) {
            echo json_encode(["success" => true]);
            exit;
        }
        
        $oldLevel = getGuildLevel($data["guilds"][$guildId]["xp"]);
        $data["guilds"][$guildId]["xp"] += $xp;
        $newLevel = getGuildLevel($data["guilds"][$guildId]["xp"]);
        
        // Update member XP contribution
        foreach ($data["guilds"][$guildId]["members"] as &$m) {
            if ($m["username"] === $username) {
                $m["xpContributed"] = ($m["xpContributed"] ?? 0) + $xp;
                break;
            }
        }
        
        saveGuilds($data);
        
        echo json_encode([
            "success" => true,
            "leveledUp" => $newLevel > $oldLevel,
            "newLevel" => $newLevel
        ]);
        exit;
    }
    
    // Promote/demote member (leader/officer only)
    if ($action === "promote" || $action === "demote") {
        $targetUser = $input["username"] ?? "";
        $guildId = $users["users"][$username]["guildId"] ?? null;
        
        if (!$guildId) {
            echo json_encode(["success" => false, "error" => "Not in a guild"]);
            exit;
        }
        
        $data = loadGuilds();
        $guild = &$data["guilds"][$guildId];
        
        // Check permissions
        $isLeader = $guild["leader"] === $username;
        $isOfficer = in_array($username, $guild["officers"] ?? []);
        
        if (!$isLeader && !$isOfficer) {
            echo json_encode(["success" => false, "error" => "No permission"]);
            exit;
        }
        
        // Find target
        $targetFound = false;
        foreach ($guild["members"] as &$m) {
            if ($m["username"] === $targetUser) {
                $targetFound = true;
                
                if ($action === "promote") {
                    if ($m["role"] === "member") {
                        $m["role"] = "officer";
                        $guild["officers"][] = $targetUser;
                    } elseif ($m["role"] === "officer" && $isLeader) {
                        // Transfer leadership
                        $m["role"] = "leader";
                        $guild["leader"] = $targetUser;
                        // Demote old leader to officer
                        foreach ($guild["members"] as &$m2) {
                            if ($m2["username"] === $username) {
                                $m2["role"] = "officer";
                                break;
                            }
                        }
                        $guild["officers"][] = $username;
                        $guild["officers"] = array_values(array_filter($guild["officers"], fn($o) => $o !== $targetUser));
                    }
                } else { // demote
                    if ($m["role"] === "officer" && $isLeader) {
                        $m["role"] = "member";
                        $guild["officers"] = array_values(array_filter($guild["officers"], fn($o) => $o !== $targetUser));
                    }
                }
                break;
            }
        }
        
        if (!$targetFound) {
            echo json_encode(["success" => false, "error" => "Member not found"]);
            exit;
        }
        
        saveGuilds($data);
        echo json_encode(["success" => true]);
        exit;
    }
    
    // Kick member
    if ($action === "kick") {
        $targetUser = $input["username"] ?? "";
        $guildId = $users["users"][$username]["guildId"] ?? null;
        
        if (!$guildId) {
            echo json_encode(["success" => false, "error" => "Not in a guild"]);
            exit;
        }
        
        $data = loadGuilds();
        $guild = &$data["guilds"][$guildId];
        
        $isLeader = $guild["leader"] === $username;
        $isOfficer = in_array($username, $guild["officers"] ?? []);
        
        if (!$isLeader && !$isOfficer) {
            echo json_encode(["success" => false, "error" => "No permission"]);
            exit;
        }
        
        // Cannot kick leader
        if ($targetUser === $guild["leader"]) {
            echo json_encode(["success" => false, "error" => "Cannot kick the leader"]);
            exit;
        }
        
        // Officers can only kick members
        if ($isOfficer && !$isLeader) {
            foreach ($guild["members"] as $m) {
                if ($m["username"] === $targetUser && $m["role"] !== "member") {
                    echo json_encode(["success" => false, "error" => "Cannot kick officers"]);
                    exit;
                }
            }
        }
        
        $guild["members"] = array_values(array_filter($guild["members"], fn($m) => $m["username"] !== $targetUser));
        $guild["officers"] = array_values(array_filter($guild["officers"] ?? [], fn($o) => $o !== $targetUser));
        
        saveGuilds($data);
        
        // Clear kicked user guild
        $users["users"][$targetUser]["guildId"] = null;
        saveUsers($users);
        
        echo json_encode(["success" => true]);
        exit;
    }
    
    // Update guild settings (leader only)
    if ($action === "updateSettings") {
        $guildId = $users["users"][$username]["guildId"] ?? null;
        
        if (!$guildId) {
            echo json_encode(["success" => false, "error" => "Not in a guild"]);
            exit;
        }
        
        $data = loadGuilds();
        $guild = &$data["guilds"][$guildId];
        
        if ($guild["leader"] !== $username) {
            echo json_encode(["success" => false, "error" => "Only leader can change settings"]);
            exit;
        }
        
        if (isset($input["description"])) {
            $guild["description"] = substr(trim($input["description"]), 0, 200);
        }
        if (isset($input["icon"])) {
            $guild["icon"] = $input["icon"];
        }
        if (isset($input["isOpen"])) {
            $guild["isOpen"] = (bool)$input["isOpen"];
        }
        
        saveGuilds($data);
        echo json_encode(["success" => true]);
        exit;
    }
}

echo json_encode(["success" => false, "error" => "Invalid request"]);
