<?php
/**
 * ChoiceDraft Test Attempts API
 * Handles test taking and result submission
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();

switch ($method) {
    case 'GET':
        if (isset($_GET['test_id']) && isset($_GET['user_id'])) {
            getUserAttempts($_GET['test_id'], $_GET['user_id']);
        } elseif (isset($_GET['test_id'])) {
            getAttemptsByTest($_GET['test_id']);
        } elseif (isset($_GET['user_id'])) {
            getUserAllAttempts($_GET['user_id']);
        } else {
            sendResponse(['error' => 'Parameters required'], 400);
        }
        break;

    case 'POST':
        submitAttempt($input);
        break;

    case 'PUT':
        if (isset($_GET['id'])) {
            updateFeedback($_GET['id'], $input);
        } else {
            sendResponse(['error' => 'Attempt ID required'], 400);
        }
        break;

    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}

function getUserAllAttempts($userId)
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT 
            ta.id,
            ta.test_id,
            ta.percentage,
            ta.score,
            ta.total_points,
            ta.feedback,
            ta.feedback_at,
            ta.completed_at,
            t.title AS test_title,
            t.subject_id
        FROM test_attempts ta
        JOIN tests t ON ta.test_id = t.id
        WHERE ta.user_id = ?
        ORDER BY ta.completed_at DESC
    ");
    $stmt->execute([$userId]);
    $attempts = $stmt->fetchAll();

    sendResponse(['attempts' => $attempts]);
}

function getUserAttempts($testId, $userId)
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT * FROM test_attempts 
        WHERE test_id = ? AND user_id = ?
        ORDER BY completed_at DESC
    ");
    $stmt->execute([$testId, $userId]);
    $attempts = $stmt->fetchAll();

    // Parse JSON answers
    foreach ($attempts as &$attempt) {
        $attempt['answers'] = json_decode($attempt['answers'], true) ?: [];
    }

    sendResponse(['attempts' => $attempts]);
}

function getAttemptsByTest($testId)
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT ta.*,
               CASE WHEN ta.is_anonymous = 1 THEN 'Anonymous' ELSE COALESCE(ta.display_name, u.name) END as user_name,
               CASE WHEN ta.is_anonymous = 1 THEN NULL ELSE u.email END as user_email
        FROM test_attempts ta
        LEFT JOIN users u ON ta.user_id = u.id
        WHERE ta.test_id = ?
        ORDER BY ta.completed_at DESC
    ");
    $stmt->execute([$testId]);
    $attempts = $stmt->fetchAll();

    // Parse JSON answers
    foreach ($attempts as &$attempt) {
        $attempt['answers'] = json_decode($attempt['answers'], true) ?: [];
    }

    sendResponse(['attempts' => $attempts]);
}

function submitAttempt($data)
{
    $testId = $data['test_id'] ?? '';
    $userId = $data['user_id'] ?? '';
    $answers = $data['answers'] ?? [];
    $isAnonymous = isset($data['is_anonymous']) ? (int) $data['is_anonymous'] : 0;

    if (empty($testId) || empty($userId)) {
        sendResponse(['error' => 'Test ID and User ID required'], 400);
    }

    $db = getDB();

    // Get test questions with correct answers
    $stmt = $db->prepare("
        SELECT q.id, q.points, q.correct_choice_id, c.id as choice_id, c.is_correct
        FROM questions q
        LEFT JOIN choices c ON q.id = c.question_id AND c.test_id = q.test_id
        WHERE q.test_id = ?
    ");
    $stmt->execute([$testId]);
    $questions = $stmt->fetchAll();

    // Calculate score
    $score = 0;
    $totalPoints = 0;
    $questionMap = [];

    foreach ($questions as $q) {
        if (!isset($questionMap[$q['id']])) {
            $questionMap[$q['id']] = [
                'points' => $q['points'],
                'correct_choice' => $q['correct_choice_id']
            ];
            $totalPoints += $q['points'];
        }
    }

    foreach ($answers as $questionId => $choiceId) {
        if (isset($questionMap[$questionId])) {
            if ($questionMap[$questionId]['correct_choice'] === $choiceId) {
                $score += $questionMap[$questionId]['points'];
            }
        }
    }

    $percentage = $totalPoints > 0 ? round(($score / $totalPoints) * 100, 2) : 0;

    // Get display name (anonymous or real)
    $displayName = null;
    if ($isAnonymous) {
        $displayName = 'Anonymous';
    } else {
        $nameStmt = $db->prepare("SELECT name FROM users WHERE id = ?");
        $nameStmt->execute([$userId]);
        $nameRow = $nameStmt->fetch();
        $displayName = $nameRow ? $nameRow['name'] : 'Unknown';
    }

    // Save attempt
    $stmt = $db->prepare("
        INSERT INTO test_attempts (test_id, user_id, percentage, score, total_points, answers, is_anonymous, display_name, completed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    try {
        $stmt->execute([$testId, $userId, $percentage, $score, $totalPoints, json_encode($answers), $isAnonymous, $displayName]);
        $attemptId = $db->lastInsertId();

        sendResponse([
            'success' => true,
            'attempt' => [
                'id' => $attemptId,
                'test_id' => $testId,
                'user_id' => $userId,
                'percentage' => $percentage,
                'score' => $score,
                'total_points' => $totalPoints,
                'answers' => $answers,
                'is_anonymous' => $isAnonymous,
                'display_name' => $displayName,
                'completed_at' => date('Y-m-d H:i:s')
            ]
        ], 201);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to save attempt: ' . $e->getMessage()], 500);
    }
}

function updateFeedback($attemptId, $data)
{
    $feedback = $data['feedback'] ?? '';

    $db = getDB();

    $stmt = $db->prepare("
        UPDATE test_attempts 
        SET feedback = ?, feedback_at = NOW()
        WHERE id = ?
    ");

    try {
        $stmt->execute([$feedback, $attemptId]);
        sendResponse(['success' => true]);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to update feedback: ' . $e->getMessage()], 500);
    }
}
