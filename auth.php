<?php
if ($action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $data['password'] ?? '';
    $name = trim($data['name'] ?? '');

    if (!$email || strlen($password) < 6 || empty($name)) {
        echo json_encode(['error' => 'Invalid email, password (min 6 chars), or name']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['error' => 'Email already registered']);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (email, password, name) VALUES (?, ?, ?)");
    try {
        $stmt->execute([$email, $hashedPassword, $name]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $data['password'] ?? '';

    if (!$email || !$password) {
        echo json_encode(['error' => 'Invalid email or password']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, password, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        session_regenerate_id(true);
        echo json_encode(['success' => true, 'name' => $user['name']]);
    } else {
        error_log("Login failed for email $email");
        if ($user) {
            error_log("User found, password hash is: " . $user['password']);
            error_log("password_verify result: " . (password_verify($password, $user['password']) ? "yes" : "no"));
        } else {
            error_log("No user found for that email");
        }
        echo json_encode(['error' => 'Incorrect email or password']);
    }
    exit;
}

if ($action === 'logout') {
    session_destroy();
    session_start();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    echo json_encode(['success' => true]);
    exit;
}