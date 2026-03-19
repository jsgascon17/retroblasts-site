<?php
header('Content-Type: application/json');
session_start();

$dataFile = __DIR__ . '/../data/news.json';

// Admin users who can post/delete news
$ADMIN_USERS = ['billybuffalo15', 'admin'];

function loadNews() {
    global $dataFile;
    if (!file_exists($dataFile)) return [];
    $data = json_decode(file_get_contents($dataFile), true);
    return $data ?: [];
}

function saveNews($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
}

function generateId() {
    return bin2hex(random_bytes(8));
}

function isAdmin() {
    global $ADMIN_USERS;
    if (!isset($_SESSION['user'])) return false;
    return in_array($_SESSION['user'], $ADMIN_USERS);
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // List all news
    if ($action === 'list') {
        $news = loadNews();
        echo json_encode(['success' => true, 'news' => array_values($news)]);
        exit;
    }

    // Get single news item
    if ($action === 'get') {
        $id = $_GET['id'] ?? '';
        $news = loadNews();

        if (!isset($news[$id])) {
            echo json_encode(['success' => false, 'error' => 'Not found']);
            exit;
        }

        echo json_encode(['success' => true, 'news' => $news[$id]]);
        exit;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    // Post news (admin only)
    if ($action === 'post') {
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Not authorized']);
            exit;
        }

        $title = trim($input['title'] ?? '');
        $tag = $input['tag'] ?? 'update';
        $content = $input['content'] ?? '';
        $pinned = $input['pinned'] ?? false;

        if (empty($title) || empty($content)) {
            echo json_encode(['success' => false, 'error' => 'Title and content required']);
            exit;
        }

        // Sanitize content - allow only safe HTML
        $allowedTags = '<strong><em><b><i><ul><ol><li><a><br><p>';
        $content = strip_tags($content, $allowedTags);

        // Validate tag
        $validTags = ['update', 'new', 'event', 'fix', 'important'];
        if (!in_array($tag, $validTags)) {
            $tag = 'update';
        }

        $news = loadNews();
        $id = generateId();

        $news[$id] = [
            'id' => $id,
            'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            'tag' => $tag,
            'content' => $content,
            'pinned' => (bool)$pinned,
            'date' => date('c'),
            'author' => $_SESSION['user']
        ];

        saveNews($news);

        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    // Delete news (admin only)
    if ($action === 'delete') {
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Not authorized']);
            exit;
        }

        $id = $input['id'] ?? '';
        $news = loadNews();

        if (!isset($news[$id])) {
            echo json_encode(['success' => false, 'error' => 'Not found']);
            exit;
        }

        unset($news[$id]);
        saveNews($news);

        echo json_encode(['success' => true]);
        exit;
    }

    // Update news (admin only)
    if ($action === 'update') {
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Not authorized']);
            exit;
        }

        $id = $input['id'] ?? '';
        $news = loadNews();

        if (!isset($news[$id])) {
            echo json_encode(['success' => false, 'error' => 'Not found']);
            exit;
        }

        if (isset($input['title'])) {
            $news[$id]['title'] = htmlspecialchars(trim($input['title']), ENT_QUOTES, 'UTF-8');
        }
        if (isset($input['content'])) {
            $allowedTags = '<strong><em><b><i><ul><ol><li><a><br><p>';
            $news[$id]['content'] = strip_tags($input['content'], $allowedTags);
        }
        if (isset($input['tag'])) {
            $validTags = ['update', 'new', 'event', 'fix', 'important'];
            if (in_array($input['tag'], $validTags)) {
                $news[$id]['tag'] = $input['tag'];
            }
        }
        if (isset($input['pinned'])) {
            $news[$id]['pinned'] = (bool)$input['pinned'];
        }

        $news[$id]['updatedAt'] = date('c');
        saveNews($news);

        echo json_encode(['success' => true]);
        exit;
    }

    // Toggle pin (admin only)
    if ($action === 'togglePin') {
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Not authorized']);
            exit;
        }

        $id = $input['id'] ?? '';
        $news = loadNews();

        if (!isset($news[$id])) {
            echo json_encode(['success' => false, 'error' => 'Not found']);
            exit;
        }

        $news[$id]['pinned'] = !$news[$id]['pinned'];
        saveNews($news);

        echo json_encode(['success' => true, 'pinned' => $news[$id]['pinned']]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
