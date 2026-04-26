<?php
/**
 * ChoiceDraft Authentication API
 * Handles user login and registration
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();

switch ($method) {
    case 'POST':
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'login':
                handleLogin($input);
                break;
            case 'register':
                handleRegister($input);
                break;
            case 'logout':
                handleLogout();
                break;
            default:
                sendResponse(['error' => 'Invalid action'], 400);
        }
        break;
        
    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}

function handleLogin($data) {
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        sendResponse(['success' => false, 'error' => 'Email and password required'], 400);
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email, password, role, institution, school_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || $user['password'] !== $password) {
        sendResponse(['success' => false, 'error' => 'Invalid email or password'], 401);
    }
    
    // Remove password from response
    unset($user['password']);
    
    sendResponse(['success' => true, 'user' => $user]);
}

function handleRegister($data) {
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? 'Student';
    $institution = $data['institution'] ?? '';
    $schoolId = ($role === 'Student') ? ($data['school_id'] ?? null) : null;
    
    if (empty($name) || empty($email) || empty($password)) {
        sendResponse(['success' => false, 'error' => 'Name, email, and password required'], 400);
    }
    
    $db = getDB();
    
    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendResponse(['success' => false, 'error' => 'Email already registered'], 409);
    }
    
    // Create new user
    $id = generateId('user');
    $stmt = $db->prepare("INSERT INTO users (id, name, email, password, role, institution, school_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    try {
        $stmt->execute([$id, $name, $email, $password, $role, $institution, $schoolId]);
        
        $user = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'institution' => $institution,
            'school_id' => $schoolId
        ];
        
        sendResponse(['success' => true, 'user' => $user], 201);
    } catch (PDOException $e) {
        sendResponse(['success' => false, 'error' => 'Registration failed: ' . $e->getMessage()], 500);
    }
}

function handleLogout() {
    // In a stateless API, logout is handled client-side
    sendResponse(['success' => true]);
}
