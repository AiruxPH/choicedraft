<?php
/**
 * ChoiceDraft Tests API
 * Handles test CRUD operations and test availability
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getTest($_GET['id']);
        } else {
            listTests();
        }
        break;
        
    case 'POST':
        createTest($input);
        break;
        
    case 'PUT':
        if (isset($_GET['id'])) {
            updateTest($_GET['id'], $input);
        } else {
            sendResponse(['error' => 'Test ID required'], 400);
        }
        break;
        
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteTest($_GET['id']);
        } else {
            sendResponse(['error' => 'Test ID required'], 400);
        }
        break;
        
    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}

function getTest($id) {
    $db = getDB();
    
    // Get test with questions and choices
    $stmt = $db->prepare("
        SELECT t.*, 
               COUNT(DISTINCT q.id) as question_count
        FROM tests t
        LEFT JOIN questions q ON t.id = q.test_id
        WHERE t.id = ?
        GROUP BY t.id
    ");
    $stmt->execute([$id]);
    $test = $stmt->fetch();
    
    if (!$test) {
        sendResponse(['error' => 'Test not found'], 404);
    }
    
    // Get questions
    $stmt = $db->prepare("
        SELECT id, text, points, is_bold, is_italic, is_underline, pool_tag, correct_choice_id
        FROM questions WHERE test_id = ?
    ");
    $stmt->execute([$id]);
    $questions = $stmt->fetchAll();
    
    // Get choices for each question
    foreach ($questions as &$q) {
        $stmt = $db->prepare("
            SELECT id, text, is_correct FROM choices 
            WHERE test_id = ? AND question_id = ?
        ");
        $stmt->execute([$id, $q['id']]);
        $q['choices'] = $stmt->fetchAll();
    }
    
    $test['questions'] = $questions;
    
    // Get collaborators (denormalized - no JOIN needed)
    $stmt = $db->prepare("
        SELECT test_id, user_id, name, email, role 
        FROM test_collaborators
        WHERE test_id = ?
    ");
    $stmt->execute([$id]);
    $test['collaborators'] = $stmt->fetchAll();
    
    // Check if test is currently available (between start_date and end_date)
    $test['is_available'] = isTestAvailable($test);
    
    sendResponse(['test' => $test]);
}

function listTests() {
    $db = getDB();
    $userId = $_GET['user_id'] ?? null;
    
    if ($userId) {
        // Get tests for specific user (owned or collaborated)
        $stmt = $db->prepare("
            SELECT DISTINCT t.*, 
                   COUNT(DISTINCT q.id) as question_count,
                   u.name as owner_name
            FROM tests t
            JOIN users u ON t.owner_id = u.id
            LEFT JOIN questions q ON t.id = q.test_id
            LEFT JOIN test_collaborators tc ON t.id = tc.test_id
            LEFT JOIN subject_enrollments se ON t.subject_id = se.subject_id
            WHERE t.owner_id = ? 
               OR tc.user_id = ? 
               OR (se.user_id = ? AND t.status = 'Published')
            GROUP BY t.id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$userId, $userId, $userId]);
    } else {
        // Get all published tests
        $stmt = $db->prepare("
            SELECT t.*, 
                   COUNT(DISTINCT q.id) as question_count,
                   u.name as owner_name
            FROM tests t
            JOIN users u ON t.owner_id = u.id
            LEFT JOIN questions q ON t.id = q.test_id
            WHERE t.status = 'Published'
            GROUP BY t.id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute();
    }
    
    $tests = $stmt->fetchAll();
    
    // Add availability status to each test
    foreach ($tests as &$test) {
        $test['is_available'] = isTestAvailable($test);
    }
    
    sendResponse(['tests' => $tests]);
}

function createTest($data) {
    $ownerId = $data['owner_id'] ?? '';
    $title = $data['title'] ?? 'Untitled Test';
    $description = $data['description'] ?? '';
    $status = $data['status'] ?? 'Draft';
    $timeLimit = $data['time_limit'] ?? null;
    $shuffleQuestions = $data['shuffle_questions'] ?? 1;
    $shuffleChoices = $data['shuffle_choices'] ?? 1;
    $startDate = $data['start_date'] ?? null;
    $endDate = $data['end_date'] ?? null;
    $subjectId = $data['subject_id'] ?? null;
    
    if (empty($ownerId)) {
        sendResponse(['error' => 'Owner ID required'], 400);
    }
    
    $db = getDB();
    $id = generateId('test');
    
    $stmt = $db->prepare("
        INSERT INTO tests (id, owner_id, title, description, status, time_limit, 
                          shuffle_questions, shuffle_choices, start_date, end_date, subject_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    try {
        $stmt->execute([$id, $ownerId, $title, $description, $status, $timeLimit, 
                       $shuffleQuestions, $shuffleChoices, $startDate, $endDate, $subjectId]);
        
        sendResponse([
            'success' => true,
            'test' => [
                'id' => $id,
                'owner_id' => $ownerId,
                'title' => $title,
                'description' => $description,
                'status' => $status,
                'time_limit' => $timeLimit,
                'shuffle_questions' => $shuffleQuestions,
                'shuffle_choices' => $shuffleChoices,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ], 201);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to create test: ' . $e->getMessage()], 500);
    }
}

function updateTest($id, $data) {
    $db = getDB();
    
    // Check if test exists
    $stmt = $db->prepare("SELECT id FROM tests WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse(['error' => 'Test not found'], 404);
    }
    
    // Build update query dynamically
    $fields = [];
    $values = [];
    
    $allowedFields = ['title', 'description', 'status', 'time_limit', 
                      'shuffle_questions', 'shuffle_choices', 'start_date', 'end_date', 'subject_id'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }
    
    if (empty($fields)) {
        sendResponse(['error' => 'No fields to update'], 400);
    }
    
    $values[] = $id;
    $sql = "UPDATE tests SET " . implode(', ', $fields) . " WHERE id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        sendResponse(['success' => true]);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to update test: ' . $e->getMessage()], 500);
    }
}

function deleteTest($id) {
    $db = getDB();
    
    try {
        // Delete related records first
        $db->prepare("DELETE FROM choices WHERE test_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM questions WHERE test_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM test_collaborators WHERE test_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM test_attempts WHERE test_id = ?")->execute([$id]);
        
        // Delete test
        $stmt = $db->prepare("DELETE FROM tests WHERE id = ?");
        $stmt->execute([$id]);
        
        sendResponse(['success' => true]);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to delete test: ' . $e->getMessage()], 500);
    }
}

/**
 * Check if test is currently available (between start_date and end_date)
 * @param array $test
 * @return bool
 */
function isTestAvailable($test) {
    $now = new DateTime();
    
    // If no dates set, test is always available
    if (empty($test['start_date']) && empty($test['end_date'])) {
        return true;
    }
    
    // Check start date
    if (!empty($test['start_date'])) {
        $startDate = new DateTime($test['start_date']);
        if ($now < $startDate) {
            return false; // Test hasn't started yet
        }
    }
    
    // Check end date
    if (!empty($test['end_date'])) {
        $endDate = new DateTime($test['end_date']);
        if ($now > $endDate) {
            return false; // Test has ended
        }
    }
    
    return true;
}

/**
 * Check if test has ended (for showing answer key)
 * @param array $test
 * @return bool
 */
function hasTestEnded($test) {
    if (empty($test['end_date'])) {
        return true; // If no end date, consider it ended (or always show answers)
    }
    
    $now = new DateTime();
    $endDate = new DateTime($test['end_date']);
    
    return $now > $endDate;
}
