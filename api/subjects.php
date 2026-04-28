<?php
/**
 * ChoiceDraft Subjects API
 * Handles subject CRUD, enrollment, and class management
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();

switch ($method) {
    case 'GET':
        if (isset($_GET['id']) && isset($_GET['analytics'])) {
            getClassAnalytics($_GET['id']);
        } elseif (isset($_GET['id'])) {
            getSubject($_GET['id']);
        } else {
            listSubjects();
        }
        break;

    case 'POST':
        if (isset($input['action']) && $input['action'] === 'join') {
            joinSubject($input);
        } elseif (isset($input['action']) && $input['action'] === 'add_by_school_id') {
            addBySchoolId($input);
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
        if (isset($_GET['id']) && isset($_GET['user_id'])) {
            // Remove a specific student from enrollment
            removeEnrollment($_GET['id'], $_GET['user_id']);
        } elseif (isset($_GET['id'])) {
            deleteSubject($_GET['id']);
        } else {
            sendResponse(['error' => 'Subject ID required'], 400);
        }
        break;

    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}

function getEnrichedMembers($db, $subjectId)
{
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.school_id, se.enrolled_at
        FROM subject_enrollments se
        JOIN users u ON se.user_id = u.id
        WHERE se.subject_id = ?
        ORDER BY se.enrolled_at ASC
    ");
    $stmt->execute([$subjectId]);
    return $stmt->fetchAll();
}

function getSubject($id)
{
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([$id]);
    $subject = $stmt->fetch();

    if (!$subject) {
        sendResponse(['error' => 'Subject not found'], 404);
    }

    $subject['members'] = getEnrichedMembers($db, $id);
    $subject['enrolled_count'] = count($subject['members']);
    // Keep legacy field for compatibility
    $subject['enrolledStudents'] = array_column($subject['members'], 'id');

    sendResponse(['subject' => $subject]);
}

function listSubjects()
{
    $db = getDB();
    $userId = $_GET['user_id'] ?? null;
    $role = $_GET['role'] ?? null;

    if (!$userId) {
        sendResponse(['error' => 'user_id parameter is required'], 400);
    }

    if ($role === 'Teacher' || $role === 'Admin') {
        $stmt = $db->prepare("SELECT * FROM subjects WHERE teacher_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
    } else {
        $stmt = $db->prepare("
            SELECT s.* FROM subjects s
            JOIN subject_enrollments se ON s.id = se.subject_id
            WHERE se.user_id = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$userId]);
    }

    $subjects = $stmt->fetchAll();

    foreach ($subjects as &$subject) {
        $subject['members'] = getEnrichedMembers($db, $subject['id']);
        $subject['enrolled_count'] = count($subject['members']);
        $subject['enrolledStudents'] = array_column($subject['members'], 'id');
    }

    sendResponse(['subjects' => $subjects]);
}

function createSubject($data)
{
    $teacherId = $data['teacher_id'] ?? '';
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? null;
    $joinCode = $data['join_code'] ?? null;

    if (empty($teacherId) || empty($name)) {
        sendResponse(['error' => 'Teacher ID and Name are required'], 400);
    }

    $db = getDB();
    $id = generateId('subj');

    $stmt = $db->prepare("INSERT INTO subjects (id, name, description, teacher_id, join_code) VALUES (?, ?, ?, ?, ?)");

    try {
        $stmt->execute([$id, $name, $description, $teacherId, $joinCode]);
        sendResponse([
            'success' => true,
            'subject' => [
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'teacher_id' => $teacherId,
                'join_code' => $joinCode,
                'members' => [],
                'enrolled_count' => 0,
                'enrolledStudents' => [],
                'created_at' => date('Y-m-d H:i:s')
            ]
        ], 201);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to create subject: ' . $e->getMessage()], 500);
    }
}

function joinSubject($data)
{
    $userId = $data['user_id'] ?? '';
    $joinCode = $data['join_code'] ?? '';

    if (empty($userId) || empty($joinCode)) {
        sendResponse(['error' => 'User ID and Join Code are required'], 400);
    }

    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM subjects WHERE join_code = ?");
    $stmt->execute([$joinCode]);
    $subject = $stmt->fetch();

    if (!$subject) {
        sendResponse(['success' => false, 'error' => 'Invalid Join Code'], 404);
    }

    $stmt = $db->prepare("SELECT * FROM subject_enrollments WHERE subject_id = ? AND user_id = ?");
    $stmt->execute([$subject['id'], $userId]);
    if ($stmt->fetch()) {
        sendResponse(['success' => false, 'error' => 'Already enrolled in this subject'], 400);
    }

    $stmt = $db->prepare("INSERT INTO subject_enrollments (subject_id, user_id) VALUES (?, ?)");
    try {
        $stmt->execute([$subject['id'], $userId]);
        $subject['members'] = getEnrichedMembers($db, $subject['id']);
        $subject['enrolled_count'] = count($subject['members']);
        $subject['enrolledStudents'] = array_column($subject['members'], 'id');
        sendResponse(['success' => true, 'subject' => $subject]);
    } catch (PDOException $e) {
        sendResponse(['success' => false, 'error' => 'Failed to join subject: ' . $e->getMessage()], 500);
    }
}

function addBySchoolId($data)
{
    $subjectId = $data['subject_id'] ?? '';
    $schoolId = $data['school_id'] ?? '';

    if (empty($subjectId) || empty($schoolId)) {
        sendResponse(['error' => 'Subject ID and School ID are required'], 400);
    }

    $db = getDB();

    // Look up student by school_id
    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE school_id = ? AND role = 'Student'");
    $stmt->execute([$schoolId]);
    $student = $stmt->fetch();

    if (!$student) {
        sendResponse(['success' => false, 'error' => 'No student found with School ID: ' . $schoolId], 404);
    }

    // Check already enrolled
    $stmt = $db->prepare("SELECT * FROM subject_enrollments WHERE subject_id = ? AND user_id = ?");
    $stmt->execute([$subjectId, $student['id']]);
    if ($stmt->fetch()) {
        sendResponse(['success' => false, 'error' => $student['name'] . ' is already enrolled in this class'], 400);
    }

    $stmt = $db->prepare("INSERT INTO subject_enrollments (subject_id, user_id) VALUES (?, ?)");
    try {
        $stmt->execute([$subjectId, $student['id']]);
        sendResponse(['success' => true, 'student' => $student]);
    } catch (PDOException $e) {
        sendResponse(['success' => false, 'error' => 'Failed to add student: ' . $e->getMessage()], 500);
    }
}

function removeEnrollment($subjectId, $userId)
{
    $db = getDB();

    $stmt = $db->prepare("DELETE FROM subject_enrollments WHERE subject_id = ? AND user_id = ?");
    try {
        $stmt->execute([$subjectId, $userId]);
        sendResponse(['success' => true]);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to remove student: ' . $e->getMessage()], 500);
    }
}

function updateSubject($id, $data)
{
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM subjects WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse(['error' => 'Subject not found'], 404);
    }

    $fields = [];
    $values = [];

    $allowedFields = ['name', 'description', 'join_code'];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
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

function deleteSubject($id)
{
    $db = getDB();

    // Guard 1: No enrolled students
    $stmt = $db->prepare("SELECT COUNT(*) FROM subject_enrollments WHERE subject_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        sendResponse(['error' => 'Cannot delete: class still has enrolled students. Remove all students first.'], 409);
    }

    // Guard 2: No active (Published) tests
    $stmt = $db->prepare("SELECT COUNT(*) FROM tests WHERE subject_id = ? AND status = 'Published'");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        sendResponse(['error' => 'Cannot delete: class has ongoing (Published) tests. Finish or archive them first.'], 409);
    }

    try {
        $stmt = $db->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(['success' => true]);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to delete subject: ' . $e->getMessage()], 500);
    }
}

function getClassAnalytics($id)
{
    $db = getDB();

    // 1. Get Subject basic info
    $stmt = $db->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([$id]);
    $subject = $stmt->fetch();

    if (!$subject) {
        sendResponse(['error' => 'Subject not found'], 404);
    }

    // 2. Get students in this subject
    $members = getEnrichedMembers($db, $id);

    // 3. Get all tests belonging to this subject
    $stmt = $db->prepare("SELECT id, title, status, created_at FROM tests WHERE subject_id = ?");
    $stmt->execute([$id]);
    $tests = $stmt->fetchAll();

    $testIds = array_column($tests, 'id');
    $allAttempts = [];

    if (!empty($testIds)) {
        // 4. Get all attempts for all tests in this subject
        $placeholders = implode(',', array_fill(0, count($testIds), '?'));
        $stmt = $db->prepare("
            SELECT ta.*, u.name as user_name 
            FROM test_attempts ta
            LEFT JOIN users u ON ta.user_id = u.id
            WHERE ta.test_id IN ($placeholders)
            ORDER BY ta.completed_at DESC
        ");
        $stmt->execute($testIds);
        $allAttempts = $stmt->fetchAll();
    }

    // 5. Aggregate overall stats
    $totalAttempts = count($allAttempts);
    $totalPercentage = 0;
    foreach ($allAttempts as $att) {
        $totalPercentage += $att['percentage'];
    }
    $avgScore = $totalAttempts > 0 ? round($totalPercentage / $totalAttempts, 1) : 0;

    // 6. Test-specific analytics
    $testStats = [];
    foreach ($tests as $t) {
        $attempts = array_filter($allAttempts, function ($a) use ($t) {
            return $a['test_id'] === $t['id']; });
        $count = count($attempts);
        $sum = 0;
        $high = 0;
        foreach ($attempts as $a) {
            $sum += $a['percentage'];
            if ($a['percentage'] > $high)
                $high = $a['percentage'];
        }
        $avg = $count > 0 ? round($sum / $count, 1) : 0;

        $testStats[] = [
            'id' => $t['id'],
            'title' => $t['title'],
            'status' => $t['status'],
            'attempt_count' => $count,
            'avg_pct' => $avg,
            'high_pct' => $high
        ];
    }

    // 7. Student-specific analytics
    $studentStats = [];
    foreach ($members as $m) {
        $attempts = array_filter($allAttempts, function ($a) use ($m) {
            return $a['user_id'] === $m['id']; });
        $count = count($attempts);
        $sum = 0;
        foreach ($attempts as $a)
            $sum += $a['percentage'];
        $avg = $count > 0 ? round($sum / $count, 1) : 0;

        $studentStats[] = [
            'id' => $m['id'],
            'name' => $m['name'],
            'school_id' => $m['school_id'],
            'attempt_count' => $count,
            'avg_pct' => $avg
        ];
    }

    sendResponse([
        'subject' => $subject,
        'class_average' => $avgScore,
        'total_attempts' => $totalAttempts,
        'total_students' => count($members),
        'total_tests' => count($tests),
        'tests' => $testStats,
        'students' => $studentStats,
        'recent_attempts' => array_slice($allAttempts, 0, 15)
    ]);
}

