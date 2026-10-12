<?php
/**
 * Bug / issue reports.
 *
 * POST (anyone, no login)   { "message": "...", "context": {...} }  - file a report
 * GET  ?action=list         (admin session only)                    - read reports
 * POST { "action": "status", "id": "...", "status": "open|fixed|wontfix" }
 *                           (admin session only)                    - update one
 *
 * Reports are written to data/bug-reports.json, which .htaccess already denies
 * over HTTP along with the rest of data/ - only the three allowlisted files are
 * reachable, and this is not one of them.
 *
 * Open to anonymous reporters on purpose: almost every player is anonymous, so
 * requiring an account would mean almost no reports. That makes abuse the thing
 * to design against, hence the caps below.
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/_store.php';

$reportsFile = __DIR__ . '/../data/bug-reports.json';

const MAX_MESSAGE   = 2000;   // characters kept from the reporter's text
const MAX_PER_HOUR  = 5;      // per browser session
const MAX_STORED    = 2000;   // oldest are dropped past this, so the file cannot grow without bound

function out($data) { echo json_encode($data); exit; }

function clean($value, $limit) {
    $value = is_string($value) ? $value : '';
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);   // control chars
    return mb_substr(trim($value), 0, $limit);
}

$isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;

/* ---------- admin: read ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$isAdmin) out(['success' => false, 'error' => 'Not authorised']);
    $all = store_hold_read($reportsFile, ['reports' => []]);
    $reports = $all['reports'] ?? [];
    usort($reports, fn($a, $b) => strcmp($b['at'] ?? '', $a['at'] ?? ''));
    out(['success' => true, 'reports' => $reports]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['success' => false, 'error' => 'POST required']);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

/* ---------- admin: change a report's status ---------- */
if (($input['action'] ?? '') === 'status') {
    if (!$isAdmin) out(['success' => false, 'error' => 'Not authorised']);
    $id     = clean($input['id'] ?? '', 40);
    $status = $input['status'] ?? '';
    if (!in_array($status, ['open', 'fixed', 'wontfix'], true)) {
        out(['success' => false, 'error' => 'Unknown status']);
    }
    $all = store_hold_read($reportsFile, ['reports' => []]);
    $found = false;
    foreach ($all['reports'] as &$r) {
        if (($r['id'] ?? '') === $id) { $r['status'] = $status; $found = true; break; }
    }
    unset($r);
    if (!$found) out(['success' => false, 'error' => 'No such report']);
    store_hold_write($reportsFile, $all);
    out(['success' => true]);
}

/* ---------- anyone: file a report ---------- */
$message = clean($input['message'] ?? '', MAX_MESSAGE);
if (mb_strlen($message) < 5) {
    out(['success' => false, 'error' => 'Please describe the problem in a few more words.']);
}

// Rate limit per browser session. Deliberately not by IP: that would mean
// storing visitors' addresses to solve a problem the session already solves.
$now = time();
$recent = array_values(array_filter($_SESSION['bugReportTimes'] ?? [], fn($t) => $t > $now - 3600));
if (count($recent) >= MAX_PER_HOUR) {
    out(['success' => false, 'error' => "That's a lot of reports in one hour - try again a bit later."]);
}
$recent[] = $now;
$_SESSION['bugReportTimes'] = $recent;

// Context the page collected, so a report is reproducible without the reporter
// having to describe their setup. Everything here is capped and sanitised.
$ctx = is_array($input['context'] ?? null) ? $input['context'] : [];
$report = [
    'id'        => 'r' . $now . substr(bin2hex(random_bytes(4)), 0, 6),
    'at'        => gmdate('c'),
    'status'    => 'open',
    'message'   => $message,
    'page'      => clean($ctx['page'] ?? '', 200),
    'game'      => clean($ctx['game'] ?? '', 60),
    'viewport'  => clean($ctx['viewport'] ?? '', 20),
    'screen'    => clean($ctx['screen'] ?? '', 20),
    'device'    => clean($ctx['device'] ?? '', 200),
    'touch'     => !empty($ctx['touch']),
    'username'  => isset($_SESSION['user']) ? clean($_SESSION['user'], 40) : null,
];

$all = store_hold_read($reportsFile, ['reports' => []]);
$all['reports'][] = $report;
if (count($all['reports']) > MAX_STORED) {
    $all['reports'] = array_slice($all['reports'], -MAX_STORED);
}
if (!store_hold_write($reportsFile, $all)) {
    out(['success' => false, 'error' => 'Could not save that - please try again.']);
}

out(['success' => true, 'id' => $report['id']]);
