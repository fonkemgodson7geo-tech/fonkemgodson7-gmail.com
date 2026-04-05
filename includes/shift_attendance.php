<?php

function shiftNow(): string
{
    return date('Y-m-d H:i:s');
}

function shiftToday(): string
{
    return date('Y-m-d');
}

function shiftGetTodayRecord(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT id, check_in, check_out, date, status, notes FROM attendance WHERE user_id = ? AND date = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId, shiftToday()]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function shiftLogEvent(PDO $pdo, int $userId, string $eventType, ?int $partnerUserId = null, ?string $note = null, string $status = 'recorded'): void
{
    $insert = $pdo->prepare('INSERT INTO shift_events (user_id, event_type, shift_date, partner_user_id, note, status) VALUES (?, ?, ?, ?, ?, ?)');
    $insert->execute([$userId, $eventType, shiftToday(), $partnerUserId, $note, $status]);
}

function shiftResolveSwapPartner(PDO $pdo, string $username): ?array
{
    $candidate = trim($username);
    if ($candidate === '') {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE lower(username) = lower(?) AND role IN ('doctor', 'intern', 'trainee') LIMIT 1");
    $stmt->execute([$candidate]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function shiftHandleAction(PDO $pdo, int $userId, string &$message, string &$error): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['shift_action'])) {
        return shiftGetTodayRecord($pdo, $userId);
    }

    verifyCsrf();
    $action = (string)($_POST['shift_action'] ?? '');
    $record = shiftGetTodayRecord($pdo, $userId);

    if ($action === 'sign_in') {
        if ($record && !empty($record['check_in']) && empty($record['check_out'])) {
            $error = 'You are already signed in for this shift.';
            return $record;
        }

        if ($record && !empty($record['check_out'])) {
            $error = 'Shift already completed for today.';
            return $record;
        }

        $insert = $pdo->prepare('INSERT INTO attendance (user_id, check_in, date, status) VALUES (?, ?, ?, ?)');
        $insert->execute([$userId, shiftNow(), shiftToday(), 'present']);
        shiftLogEvent($pdo, $userId, 'sign_in');
        $message = 'Shift sign in recorded successfully.';
        return shiftGetTodayRecord($pdo, $userId);
    }

    if ($action === 'sign_out') {
        if (!$record || empty($record['check_in'])) {
            $error = 'Sign in first before signing out.';
            return $record;
        }

        if (!empty($record['check_out'])) {
            $error = 'You have already signed out for this shift.';
            return $record;
        }

        $note = trim((string)($_POST['shift_note'] ?? ''));
        $update = $pdo->prepare('UPDATE attendance SET check_out = ?, notes = ? WHERE id = ?');
        $update->execute([shiftNow(), $note !== '' ? $note : null, (int)$record['id']]);
        shiftLogEvent($pdo, $userId, 'sign_out', null, $note !== '' ? $note : null);
        $message = 'Shift sign out recorded successfully.';
        return shiftGetTodayRecord($pdo, $userId);
    }

    if ($action === 'shift_change') {
        $note = trim((string)($_POST['shift_note'] ?? ''));
        if ($note === '') {
            $error = 'Add a note to record a shift change.';
            return $record;
        }

        shiftLogEvent($pdo, $userId, 'shift_change', null, $note);
        $message = 'Shift change recorded successfully.';
        return shiftGetTodayRecord($pdo, $userId);
    }

    if ($action === 'shift_swap') {
        $partnerUsername = trim((string)($_POST['shift_partner_username'] ?? ''));
        $note = trim((string)($_POST['shift_note'] ?? ''));
        $partner = shiftResolveSwapPartner($pdo, $partnerUsername);

        if (!$partner) {
            $error = 'Enter a valid doctor/intern/trainee username for shift swap.';
            return $record;
        }

        $partnerId = (int)$partner['id'];
        if ($partnerId === $userId) {
            $error = 'You cannot swap shift with yourself.';
            return $record;
        }

        shiftLogEvent($pdo, $userId, 'shift_swap', $partnerId, $note !== '' ? $note : null, 'requested');
        $message = 'Shift swap request recorded for ' . (string)$partner['username'] . '.';
        return shiftGetTodayRecord($pdo, $userId);
    }

    $error = 'Unknown shift action.';
    return $record;
}
