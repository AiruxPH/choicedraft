<?php
/**
 * ChoiceDraft Subjects API
 * Handles subject CRUD and enrollment operations
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getSubject($_GET['id']);
        } else {
            listSubjects();
        }
        break;
        
    case 'POST':
        if (isset($input['action']) && $input['action'] === 'join') {
            joinSubject($input);
        } else {
            createSubject($input);
        }
        break;
        
    case 'PUT':
        if (isset($_GET['id'])) {
            updateSubject($_GET['id'], $input);
        } else {
            sendResponse(['error' => 'Subject ID required'], 400);
        }
        break;
        
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteSubject($_GET['id']);
        } else {
            sendResponse(['error' => 'Subject ID required'], 400);
        }
        break;
        
    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}

function getSubject($id) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([$id]);
    $subject = $stmt->fetch();
    
    if (!$subject) {
        sendResponse(['error' => 'Subject not found'], 404);
    }
    
    // Get enrolled students
    $stmt = $db->prepare("SELECT user_id FROM subject_enrollments WHERE subject_id = ?");
    $stmt->execute([$id]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $subject['enrolledStudents'] = $enrollments ?: [];
    
    sendResponse(['subject' => $subject]);
}

function listSubjects() {
    $db = getDB();
    $userId = $_GET['user_id'] ?? null;
    $role = $_GET['role'] ?? null;
    
    if (!$userId) {
        sendResponse(['error' => 'user_id parameter is required'], 400);
    }
    
    if ($role === 'Teacher' || $role === 'Admin') {
        // Teacher sees classes they own
        $stmt = $db->prepare("SELECT * FROM subjects WHERE teacher_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
    } else {
        // Student sees classes they are enrolled in
        $stmt = $db->prepare("
            SELECT s.* FROM subjects s
            JOIN subject_enrollments se ON s.id = se.subject_id
            WHERE se.user_id = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$userId]);
    }
    
    $subjects = $stmt->fetchAll();
    
    // Add enrolled students count/list for each
    foreach ($subjects as &$subject) {
        $stmt = $db->prepare("SELECT user_id FROM subject_enrollments WHERE subject_id = ?");
        $stmt->execute([$subject['id']]);
        $enrollments = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $subject['enrolledStudents'] = $enrollments ?: [];
    }
    
    sendResponse(['subjects' => $subjects]);
}

function createSubject($data) {
    $teacherId = $data['teacher_id'] ?? '';
    $name = $data['name'] ?? '';
    $joinCode = $data['join_code'] ?? null;
    
    if (empty($teacherId) || empty($name)) {
        sendResponse(['error' => 'Teacher ID and Name are required'], 400);
    }
    
    $db = getDB();
    $id = generateId('subj');
    
    $stmt = $db->prepare("INSERT INTO subjects (id, name, teacher_id, join_code) VALUES (?, ?, ?, ?)");
    
    try {
        $stmt->execute([$id, $name, $teacherId, $joinCode]);
        sendResponse([
            'success' => true,
            'subject' => [
                'id' => $id,
                'name' => $name,
                'teacher_id' => $teacherId,
                'join_code' => $joinCode,
                'enrolledStudents' => [],
                'created_at' => date('Y-m-d H:i:s')
            ]
        ], 201);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to create subject: ' . $e->getMessage()], 500);
    }
}

function joinSubject($data) {
    $userId = $data['user_id'] ?? '';
    $joinCode = $data['join_code'] ?? '';
    
    if (empty($userId) || empty($joinCode)) {
        sendResponse(['error' => 'User ID and Join Code are required'], 400);
    }
    
    $db = getDB();
    
    // Find subject by join code
    $stmt = $db->prepare("SELECT * FROM subjects WHERE join_code = ?");
    $stmt->execute([$joinCode]);
    $subject = $stmt->fetch();
    
    if (!$subject) {
        sendResponse(['success' => false, 'error' => 'Invalid Join Code'], 404);
    }
    
    // Check if already enrolled
    $stmt = $db->prepare("SELECT * FROM subject_enrollments WHERE subject_id = ? AND user_id = ?");
    $stmt->execute([$subject['id'], $userId]);
    if ($stmt->fetch()) {
        sendResponse(['success' => false, 'error' => 'Already enrolled in this subject'], 400);
    }
    
    // Enroll
    $stmt = $db->prepare("INSERT INTO subject_enrollments (subject_id, user_id) VALUES (?, ?)");
    try {
        $stmt->execute([$subject['id'], $userId]);
        
        // Refresh subject to include new enrollment
        $stmt = $db->prepare("SELECT user_id FROM subject_enrollments WHERE subject_id = ?");
        $stmt->execute([$subject['id']]);
        $enrollments = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $subject['enrolledStudents'] = $enrollments ?: [];
        
        sendResponse([
            'success' => true,
            'subject' => $subject
        ]);
    } catch (PDOException $e) {
        sendResponse(['success' => false, 'error' => 'Failed to join subject: ' . $e->getMessage()], 500);
    }
}

function updateSubject($id, $data) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT id FROM subjects WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse(['error' => 'Subject not found'], 404);
    }
    
    $fields = [];
    $values = [];
    
    $allowedFields = ['name', 'join_code'];
    
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
    $sql = "UPDATE subjects SET " . implode(', ', $fields) . " WHERE id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        sendResponse(['success' => true]);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to update subject: ' . $e->getMessage()], 500);
    }
}

function deleteSubject($id) {
    $db = getDB();
    
    try {
        // Constraints ON DELETE CASCADE should handle enrollments and tests
        // Alternatively, SET NULL for tests. We use ON DELETE SET NULL for tests in the schema.
        $stmt = $db->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$id]);
        
        sendResponse(['success' => true]);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to delete subject: ' . $e->getMessage()], 500);
    }
}
