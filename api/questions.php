<?php
/**
 * ChoiceDraft Questions API
 * Handles question and choice CRUD operations
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();

switch ($method) {
    case 'GET':
        if (isset($_GET['test_id'])) {
            getQuestionsByTest($_GET['test_id']);
        } else {
            sendResponse(['error' => 'Test ID required'], 400);
        }
        break;
        
    case 'POST':
        createQuestion($input);
        break;
        
    case 'PUT':
        if (isset($_GET['id']) && isset($_GET['test_id'])) {
            updateQuestion($_GET['test_id'], $_GET['id'], $input);
        } else {
            sendResponse(['error' => 'Question ID and Test ID required'], 400);
        }
        break;
        
    case 'DELETE':
        if (isset($_GET['id']) && isset($_GET['test_id'])) {
            deleteQuestion($_GET['test_id'], $_GET['id']);
        } else {
            sendResponse(['error' => 'Question ID and Test ID required'], 400);
        }
        break;
        
    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}

function getQuestionsByTest($testId) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT id, text, points, is_bold, is_italic, is_underline, pool_tag, correct_choice_id
        FROM questions WHERE test_id = ?
        ORDER BY id
    ");
    $stmt->execute([$testId]);
    $questions = $stmt->fetchAll();
    
    // Get choices for each question
    foreach ($questions as &$q) {
        $stmt = $db->prepare("
            SELECT id, text, is_correct FROM choices 
            WHERE test_id = ? AND question_id = ?
            ORDER BY id
        ");
        $stmt->execute([$testId, $q['id']]);
        $q['choices'] = $stmt->fetchAll();
    }
    
    sendResponse(['questions' => $questions]);
}

function createQuestion($data) {
    $testId = $data['test_id'] ?? '';
    $id = $data['id'] ?? generateId('q');
    $text = $data['text'] ?? '';
    $points = $data['points'] ?? 10;
    $isBold = $data['is_bold'] ?? 0;
    $isItalic = $data['is_italic'] ?? 0;
    $isUnderline = $data['is_underline'] ?? 0;
    $poolTag = $data['pool_tag'] ?? null;
    $correctChoiceId = $data['correct_choice_id'] ?? null;
    $choices = $data['choices'] ?? [];
    
    if (empty($testId) || empty($text)) {
        sendResponse(['error' => 'Test ID and question text required'], 400);
    }
    
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Insert question
        $stmt = $db->prepare("
            INSERT INTO questions (id, test_id, text, points, is_bold, is_italic, is_underline, pool_tag, correct_choice_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$id, $testId, $text, $points, $isBold, $isItalic, $isUnderline, $poolTag, $correctChoiceId]);
        
        // Insert choices
        if (!empty($choices)) {
            $stmt = $db->prepare("
                INSERT INTO choices (id, test_id, question_id, text, is_correct)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($choices as $choice) {
                $choiceId = $choice['id'] ?? generateId('c');
                $choiceText = $choice['text'] ?? '';
                $isCorrect = $choice['is_correct'] ?? 0;
                $stmt->execute([$choiceId, $testId, $id, $choiceText, $isCorrect]);
            }
        }
        
        $db->commit();
        
        sendResponse([
            'success' => true,
            'question' => [
                'id' => $id,
                'test_id' => $testId,
                'text' => $text,
                'points' => $points,
                'is_bold' => $isBold,
                'is_italic' => $isItalic,
                'is_underline' => $isUnderline,
                'pool_tag' => $poolTag,
                'correct_choice_id' => $correctChoiceId,
                'choices' => $choices
            ]
        ], 201);
        
    } catch (PDOException $e) {
        $db->rollBack();
        sendResponse(['error' => 'Failed to create question: ' . $e->getMessage()], 500);
    }
}

function updateQuestion($testId, $questionId, $data) {
    $db = getDB();
    
    // Build update query dynamically
    $fields = [];
    $values = [];
    
    $allowedFields = ['text', 'points', 'is_bold', 'is_italic', 'is_underline', 'pool_tag', 'correct_choice_id'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }
    
    if (empty($fields)) {
        sendResponse(['error' => 'No fields to update'], 400);
    }
    
    $values[] = $questionId;
    $values[] = $testId;
    $sql = "UPDATE questions SET " . implode(', ', $fields) . " WHERE id = ? AND test_id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        
        // Update choices if provided
        if (isset($data['choices'])) {
            // Delete existing choices
            $stmt = $db->prepare("DELETE FROM choices WHERE test_id = ? AND question_id = ?");
            $stmt->execute([$testId, $questionId]);
            
            // Insert new choices
            $stmt = $db->prepare("
                INSERT INTO choices (id, test_id, question_id, text, is_correct)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($data['choices'] as $choice) {
                $choiceId = $choice['id'] ?? generateId('c');
                $choiceText = $choice['text'] ?? '';
                $isCorrect = $choice['is_correct'] ?? 0;
                $stmt->execute([$choiceId, $testId, $questionId, $choiceText, $isCorrect]);
            }
        }
        
        sendResponse(['success' => true]);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to update question: ' . $e->getMessage()], 500);
    }
}

function deleteQuestion($testId, $questionId) {
    $db = getDB();
    
    try {
        // Delete choices first
        $stmt = $db->prepare("DELETE FROM choices WHERE test_id = ? AND question_id = ?");
        $stmt->execute([$testId, $questionId]);
        
        // Delete question
        $stmt = $db->prepare("DELETE FROM questions WHERE id = ? AND test_id = ?");
        $stmt->execute([$questionId, $testId]);
        
        sendResponse(['success' => true]);
    } catch (PDOException $e) {
        sendResponse(['error' => 'Failed to delete question: ' . $e->getMessage()], 500);
    }
}
