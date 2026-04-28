<?php
/**
 * ChoiceDraft Users API
 * Handles user management
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getUser($_GET['id']);
        } elseif (isset($_GET['email'])) {
            getUserByEmail($_GET['email']);
        } elseif (isset($_GET['school_id'])) {
            getUserBySchoolId($_GET['school_id']);
        } else {
            listUsers();
        }
        break;

    case 'PUT':
        if (isset($_GET['id'])) {
            updateUser($_GET['id'], $input);
        } else {
            sendResponse(['error' => 'User ID required'], 400);
        }
        break;

    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteUser($_GET['id']);
        } else {
            sendResponse(['error' => 'User ID required'], 400);
        }
        break;

    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}

function getUser($id)
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT id, name, email, role, institution, school_id, created_at 
        FROM users WHERE id = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        sendResponse(['error' => 'User not found'], 404);
    }

    sendResponse(['user' => $user]);
}

function getUserByEmail($email)
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT id, name, email, role, institution, school_id, created_at 
        FROM users WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        sendResponse(['error' => 'User not found'], 404);
    }

    sendResponse(['user' => $user]);
}

function getUserBySchoolId($schoolId)
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT id, name, email, role, institution, school_id, created_at 
        FROM users WHERE school_id = ? AND role = 'Student'
    ");
    $stmt->execute([$schoolId]);
    $user = $stmt->fetch();

    if (!$user) {
        sendResponse(['error' => 'No student found with that School ID'], 404);
    }

    sendResponse(['user' => $user]);
}

function listUsers()
{
    $db = getDB();
    $role = $_GET['role'] ?? null;

    if ($role) {
        $stmt = $db->prepare("
            SELECT u.id, u.name, u.email, u.role, u.institution, u.school_id, u.created_at,
                   (SELECT COUNT(*) FROM tests t WHERE t.owner_id = u.id) as tests_count,
                   (SELECT COUNT(*) FROM test_attempts ta WHERE ta.user_id = u.id) as attempts_count
            FROM users u WHERE u.role = ?
            ORDER BY u.created_at DESC
        ");
        $stmt->execute([$role]);
    } else {
        $stmt = $db->query("
            SELECT u.id, u.name, u.email, u.role, u.institution, u.school_id, u.created_at,
                   (SELECT COUNT(*) FROM tests t WHERE t.owner_id = u.id) as tests_count,
                   (SELECT COUNT(*) FROM test_attempts ta WHERE ta.user_id = u.id) as attempts_count
            FROM users u 
            ORDER BY u.created_at DESC
        ");
    }

    $users = $stmt->fetchAll();
    sendResponse(['users' => $users]);
}

function updateUser($id, $data)
{
    $db = getDB();

    // Check if user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse(['error' => 'User not found'], 404);
    }

    // Build update query dynamically
    $fields = [];
    $values = [];

    $allowedFields = ['name', 'email', 'role', 'institution', 'school_id'];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }

    // Handle password separately
    if (isset($data['password']) && !empty($data['password'])) {
        $fields[] = "password = ?";
        $values[] = $data['password'];
    }

    if (empty($fields)) {
        sendResponse(['error' => 'No fields to update'], 400);
    }

    $values[] = $id;
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        sendResponse(['success' => true]);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to update user: ' . $e->getMessage()], 500);
    }
}

function deleteUser($id)
{
    $db = getDB();

    try {
        // Delete related records first
        $db->prepare("DELETE FROM test_attempts WHERE user_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM test_collaborators WHERE user_id = ?")->execute([$id]);

        // Delete user's tests and related data
        $stmt = $db->prepare("SELECT id FROM tests WHERE owner_id = ?");
        $stmt->execute([$id]);
        $tests = $stmt->fetchAll();

        foreach ($tests as $test) {
            $testId = $test['id'];
            $db->prepare("DELETE FROM choices WHERE test_id = ?")->execute([$testId]);
            $db->prepare("DELETE FROM questions WHERE test_id = ?")->execute([$testId]);
            $db->prepare("DELETE FROM test_collaborators WHERE test_id = ?")->execute([$testId]);
            $db->prepare("DELETE FROM test_attempts WHERE test_id = ?")->execute([$testId]);
            $db->prepare("DELETE FROM tests WHERE id = ?")->execute([$testId]);
        }

        // Delete user
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        sendResponse(['success' => true]);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to delete user: ' . $e->getMessage()], 500);
    }
}
