<?php
/**
 * Scheduling Reminders & Completion Cron
 * Run via: php assets/lib/scheduling-cron.php
 * Schedule: every 15-30 minutes for reminders; every hour for completion asks
 *
 * Handles:
 * - 1 day before donation reminder
 * - On the day of donation reminder (2 hours before)
 * - 5 hours after donation time: completion ask (yes/no/reschedule) to donor and recipient
 */
require_once __DIR__ . '/openconn.php';

$now = date('Y-m-d H:i:s');
$stats = ['reminders_sent' => 0, 'completion_asks' => 0, 'errors' => 0];

// Check if blood_donations and scheduling_reminders exist
$t1 = $conn->query("SHOW TABLES LIKE 'blood_donations'");
$t2 = $conn->query("SHOW TABLES LIKE 'scheduling_reminders'");
if (!$t1 || $t1->num_rows === 0 || !$t2 || $t2->num_rows === 0) {
    echo json_encode(['message' => 'blood_donations or scheduling_reminders table not found', 'stats' => $stats]);
    exit;
}

// 1. Process scheduling_reminders (1day, day_of) - send notifications when due
$dueStmt = $conn->prepare("
    SELECT sr.id, sr.blood_donation_id, sr.type, bd.donor_id, bd.recipient_id, bd.donation_date, bd.donation_time, bd.location
    FROM scheduling_reminders sr
    JOIN blood_donations bd ON bd.id = sr.blood_donation_id
    WHERE sr.sent_at IS NULL AND sr.scheduled_for <= ? AND sr.type IN ('1day','day_of')
");
$dueStmt->bind_param("s", $now);
$dueStmt->execute();
$dueRes = $dueStmt->get_result();

while ($row = $dueRes->fetch_assoc()) {
    $users = array_filter([$row['donor_id'], $row['recipient_id']]);
    $payload = json_encode([
        'type' => 'scheduling_reminder',
        'reminder_type' => $row['type'],
        'blood_donation_id' => $row['blood_donation_id'],
        'donation_date' => $row['donation_date'],
        'donation_time' => $row['donation_time'],
        'location' => $row['location']
    ]);
    $template = 'scheduling_' . $row['type'];

    foreach ($users as $uid) {
        $ins = $conn->prepare("INSERT INTO emergency_notifications (user_id, channel, template_key, payload, status) VALUES (?, 'in_app', ?, ?, 'queued')");
        $ins->bind_param("sss", $uid, $template, $payload);
        if ($ins->execute()) $stats['reminders_sent']++;
        $ins->close();
    }

    $mark = $conn->prepare("UPDATE scheduling_reminders SET sent_at = NOW() WHERE id = ?");
    $mark->bind_param("i", $row['id']);
    $mark->execute();
    $mark->close();
}
$dueStmt->close();

// 2. Process completion_ask - 5 hours after donation time: create notifications for donor & recipient
$completionStmt = $conn->prepare("
    SELECT sr.id, sr.blood_donation_id, bd.donor_id, bd.recipient_id, bd.donation_date, bd.donation_time, bd.location
    FROM scheduling_reminders sr
    JOIN blood_donations bd ON bd.id = sr.blood_donation_id
    WHERE sr.sent_at IS NULL AND sr.scheduled_for <= ? AND sr.type = 'completion_ask'
      AND bd.status = 'scheduled'
");
$completionStmt->bind_param("s", $now);
$completionStmt->execute();
$compRes = $completionStmt->get_result();

while ($row = $compRes->fetch_assoc()) {
    $payload = json_encode([
        'type' => 'completion_ask',
        'blood_donation_id' => $row['blood_donation_id'],
        'donation_date' => $row['donation_date'],
        'donation_time' => $row['donation_time'],
        'location' => $row['location']
    ]);

    foreach ([$row['donor_id'], $row['recipient_id']] as $uid) {
        $ins = $conn->prepare("INSERT INTO emergency_notifications (user_id, channel, template_key, payload, status) VALUES (?, 'in_app', 'scheduling_completion_ask', ?, 'queued')");
        $ins->bind_param("ss", $uid, $payload);
        if ($ins->execute()) $stats['completion_asks'] += 2; // count both
        $ins->close();
    }

    // Update blood_donations completion_asked_at
    $upd = $conn->prepare("UPDATE blood_donations SET completion_asked_at = NOW() WHERE id = ?");
    $upd->bind_param("i", $row['blood_donation_id']);
    $upd->execute();
    $upd->close();

    $mark = $conn->prepare("UPDATE scheduling_reminders SET sent_at = NOW() WHERE id = ?");
    $mark->bind_param("i", $row['id']);
    $mark->execute();
    $mark->close();
}
$completionStmt->close();

// 3. Create scheduling_reminders for blood_donations that donor just confirmed (no reminders yet)
$checkCol = $conn->query("SHOW COLUMNS FROM blood_donations LIKE 'donor_confirmed_at'");
if ($checkCol && $checkCol->num_rows > 0) {
    $newConfirmed = $conn->query("
        SELECT bd.id, bd.donation_date, bd.donation_time, bd.donor_confirmed_at
        FROM blood_donations bd
        WHERE bd.status = 'scheduled' AND bd.donor_confirmed = 1 AND bd.donor_confirmed_at IS NOT NULL
          AND NOT EXISTS (SELECT 1 FROM scheduling_reminders sr WHERE sr.blood_donation_id = bd.id)
    ");
    if ($newConfirmed) {
        while ($r = $newConfirmed->fetch_assoc()) {
            $dt = $r['donation_date'] . ' ' . ($r['donation_time'] ?? '10:00:00');
            $ts = strtotime($dt);
            $ins1 = $conn->prepare("INSERT INTO scheduling_reminders (blood_donation_id, type, scheduled_for) VALUES (?, '1day', ?)");
            $t1day = date('Y-m-d H:i:s', strtotime('-1 day', $ts));
            $ins1->bind_param("is", $r['id'], $t1day);
            $ins1->execute();
            $ins1->close();

            $ins2 = $conn->prepare("INSERT INTO scheduling_reminders (blood_donation_id, type, scheduled_for) VALUES (?, 'day_of', ?)");
            $tDay = date('Y-m-d H:i:s', strtotime('-2 hours', $ts));
            $ins2->bind_param("is", $r['id'], $tDay);
            $ins2->execute();
            $ins2->close();

            $ins3 = $conn->prepare("INSERT INTO scheduling_reminders (blood_donation_id, type, scheduled_for) VALUES (?, 'completion_ask', ?)");
            $t5h = date('Y-m-d H:i:s', strtotime('+5 hours', $ts));
            $ins3->bind_param("is", $r['id'], $t5h);
            $ins3->execute();
            $ins3->close();
        }
    }
}

echo json_encode(['stats' => $stats, 'timestamp' => $now]);
