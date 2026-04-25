<?php
/**
 * ChoiceDraft Collaborators API
 * Handles test collaboration management
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();

switch ($method) {
    case 'GET':
        if (isset($_GET['test_id'])) {
            getCollaborators($_GET['test_id']);
        } else {
            sendResponse(['error' => 'Test ID required'], 400);
        }
        break;
        
    case 'POST':
        addCollaborator($input);
        break;
        
    case 'DELETE':
        if (isset($_GET['test_id']) && isset($_GET['user_id'])) {
            removeCollaborator($_GET['test_id'], $_GET['user_id']);
        } else {
            sendResponse(['error' => 'Test ID and User ID required'], 400);
        }
        break;
        
    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}

function getCollaborators($testId) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT test_id, user_id, name, email, role
        FROM test_collaborators
        WHERE test_id = ?
    ");
    $stmt->execute([$testId]);
    $collaborators = $stmt->fetchAll();
    
    sendResponse(['collaborators' => $collaborators]);
}

function addCollaborator($data) {
    $testId = $data['test_id'] ?? '';
    $email = $data['email'] ?? '';
    $collaboratorRole = $data['role'] ?? 'Viewer';
    $currentUserId = $data['current_user_id'] ?? '';
    
    if (empty($testId) || empty($email)) {
        sendResponse(['success' => false, 'error' => 'Test ID and email required'], 400);
    }
    
    $db = getDB();
    
    // Get test owner
    $stmt = $db->prepare("SELECT owner_id FROM tests WHERE id = ?");
    $stmt->execute([$testId]);
    $test = $stmt->fetch();
    
    if (!$test) {
        sendResponse(['success' => false, 'error' => 'Test not found'], 404);
    }
    
    // Find user by email
    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse(['success' => false, 'error' => 'User not found with this email'], 404);
    }
    
    // Check if trying to add self
    if ($currentUserId && $user['id'] === $currentUserId) {
        sendResponse(['success' => false, 'error' => 'You cannot add yourself'], 400);
    }
    
    // Check if trying to add owner
    if ($user['id'] === $test['owner_id']) {
        sendResponse(['success' => false, 'error' => 'Owner cannot be a collaborator'], 400);
    }
    
    // Check if already a collaborator
    $stmt = $db->prepare("SELECT user_id FROM test_collaborators WHERE test_id = ? AND user_id = ?");
    $stmt->execute([$testId, $user['id']]);
    if ($stmt->fetch()) {
        sendResponse(['success' => false, 'error' => 'User is already a collaborator'], 409);
    }
    
    // Add collaborator with denormalized data
    $stmt = $db->prepare("INSERT INTO test_collaborators (test_id, user_id, name, email, role) VALUES (?, ?, ?, ?, ?)");
    
    try {
        $stmt->execute([$testId, $user['id'], $user['name'], $user['email'], $collaboratorRole]);
        sendResponse([
            'success' => true,
            'collaborator' => [
                'test_id' => $testId,
                'user_id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $collaboratorRole
            ]
        ], 201);
    } catch (PDOException $e) {
        sendResponse(['success' => false, 'error' => 'Failed to add collaborator: ' . $e->getMessage()], 500);
    }
}

function removeCollaborator($testId, $userId) {
    $db = getDB();
    
    $stmt = $db->prepare("DELETE FROM test_collaborators WHERE test_id = ? AND user_id = ?");
    
    try {
        $stmt->execute([$testId, $userId]);
        sendResponse(['success' => true]);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to remove collaborator: ' . $e->getMessage()], 500);
    }
}
