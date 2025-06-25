<?php

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

// --- Profile ---
if ($action === 'get_profile') {
    if (!get_user_id()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
    $stmt->execute([get_user_id()]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT rs.id as session_id, rs.created_at, c.name as course_name
         FROM round_sessions rs
         JOIN courses c ON rs.course_id = c.id
         WHERE rs.user_id = ?
         ORDER BY rs.created_at DESC"
    );
    $stmt->execute([get_user_id()]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sessions as &$session) {
        $stmt2 = $pdo->prepare(
            "SELECT hole_number, score FROM rounds WHERE round_session_id = ? ORDER BY hole_number"
        );
        $stmt2->execute([$session['session_id']]);
        $session['holes'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        $session['total'] = array_sum(array_column($session['holes'], 'score'));
    }

    echo json_encode([
        'email' => $user['email'],
        'name' => $user['name'],
        'sessions' => $sessions
    ]);
    exit;
}

// --- Start Round ---
if ($action === 'start_round') {
    if (!get_user_id()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    if (!isset($_SESSION['round_session_id']) || !$_SESSION['round_session_id']) {
        $course_id = 1;
        $user_id = get_user_id();
        $stmt = $pdo->prepare("INSERT INTO round_sessions (user_id, course_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $course_id]);
        $_SESSION['round_session_id'] = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'round_session_id' => $_SESSION['round_session_id']]);
    } else {
        echo json_encode(['success' => true, 'round_session_id' => $_SESSION['round_session_id']]);
    }
    exit;
}

// --- Check Round Session ---
if ($action === 'check_round_session') {
    $has_round = isset($_SESSION['round_session_id']) && $_SESSION['round_session_id'];
    echo json_encode(['has_round' => $has_round]);
    exit;
}

// --- Submit Round ---
if ($action === 'submit_round') {
    if (!get_user_id()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    if (!isset($_SESSION['round_session_id'])) {
        echo json_encode(['error' => 'No active round session. Start a round first.']);
        exit;
    }
    $user_id = get_user_id();
    $round_session_id = $_SESSION['round_session_id'];
    $scores = $data['scores'];
    if (!is_array($scores) || count($scores) === 0) {
        echo json_encode(['error' => 'No scores provided']);
        exit;
    }
    // Delete any existing scores for this session (in case of resubmission)
    $stmt = $pdo->prepare("DELETE FROM rounds WHERE user_id = ? AND course_id = 1 AND round_session_id = ?");
    $stmt->execute([$user_id, $round_session_id]);
    // Insert all scores
    $insert = $pdo->prepare("INSERT INTO rounds (user_id, course_id, hole_number, score, round_session_id) VALUES (?, 1, ?, ?, ?)");
    foreach ($scores as $row) {
        $hole_number = (int)$row['hole_number'];
        $score = (int)$row['score'];
        $insert->execute([$user_id, $hole_number, $score, $round_session_id]);
    }
    unset($_SESSION['round_session_id']);
    echo json_encode(['success' => true]);
    exit;
}

// --- End Round ---
if ($action === 'end_round') {
    if (!get_user_id()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    if (isset($_SESSION['round_session_id'])) {
        unset($_SESSION['round_session_id']);
    }
    echo json_encode(['success' => true]);
    exit;
}

// --- Get Round Details ---
if ($action === 'get_round_details') {
    if (!get_user_id()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    $session_id = intval($_GET['session_id'] ?? 0);
    if (!$session_id) {
        echo json_encode(['error' => 'No session id']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT hole_number, score FROM rounds WHERE round_session_id = ? ORDER BY hole_number");
    $stmt->execute([$session_id]);
    $holes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['holes' => $holes]);
    exit;
}

// --- Edit Round (upsert logic) ---
if ($action === 'edit_round') {
    if (!get_user_id()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (($data['csrf_token'] ?? null) !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    $session_id = intval($data['session_id'] ?? 0);
    $scores = $data['scores'] ?? [];
    if (!$session_id || !is_array($scores)) {
        echo json_encode(['error' => 'Missing session id or scores']);
        exit;
    }
    foreach ($scores as $row) {
        $hole_number = intval($row['hole_number']);
        $score = is_null($row['score']) || $row['score']==='' ? null : intval($row['score']);
        if ($score === null) continue;
        $stmt = $pdo->prepare("UPDATE rounds SET score = ? WHERE round_session_id = ? AND hole_number = ?");
        $stmt->execute([$score, $session_id, $hole_number]);
        if ($stmt->rowCount() === 0) {
            $user_id = get_user_id();
            $course_id = 1;
            $insert = $pdo->prepare("INSERT INTO rounds (user_id, course_id, hole_number, score, round_session_id) VALUES (?, ?, ?, ?, ?)");
            $insert->execute([$user_id, $course_id, $hole_number, $score, $session_id]);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

// --- Delete Round ---
if ($action === 'delete_round') {
    if (!get_user_id()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (($data['csrf_token'] ?? null) !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    $session_id = intval($data['session_id'] ?? 0);
    if (!$session_id) {
        echo json_encode(['error' => 'No session id']);
        exit;
    }
    $pdo->prepare("DELETE FROM rounds WHERE round_session_id = ?")->execute([$session_id]);
    $pdo->prepare("DELETE FROM round_sessions WHERE id = ?")->execute([$session_id]);
    echo json_encode(['success' => true]);
    exit;
}