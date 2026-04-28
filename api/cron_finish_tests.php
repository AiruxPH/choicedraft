<?php
/**
 * ChoiceDraft Cron Job - Auto-Finish Expired Tests
 *
 * This script should be run on a schedule (e.g. every 15 minutes via Hostinger cron).
 * It sets all "Published" tests whose end_date has already passed to "Finished".
 *
 * Hostinger Cron Command:
 *   php /home/<username>/public_html/api/cron_finish_tests.php
 * Schedule: Every 15 minutes  
 */

require_once 'config.php';

$db = getDB();

// Find all Published tests where end_date is set and has already passed
$stmt = $db->prepare("
    SELECT id, title, end_date
    FROM tests
    WHERE status = 'Published'
      AND end_date IS NOT NULL
      AND end_date != ''
      AND end_date < NOW()
");
$stmt->execute();
$expiredTests = $stmt->fetchAll();

if (empty($expiredTests)) {
    echo "[" . date('Y-m-d H:i:s') . "] No expired tests found.\n";
    exit(0);
}

$updated = 0;
foreach ($expiredTests as $test) {
    $upd = $db->prepare("UPDATE tests SET status = 'Finished' WHERE id = ?");
    $upd->execute([$test['id']]);
    echo "[" . date('Y-m-d H:i:s') . "] Finished test: [{$test['id']}] {$test['title']} (ended: {$test['end_date']})\n";
    $updated++;
}

echo "[" . date('Y-m-d H:i:s') . "] Done. {$updated} test(s) marked as Finished.\n";
