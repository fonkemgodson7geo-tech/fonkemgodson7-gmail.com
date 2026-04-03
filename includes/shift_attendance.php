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
        $message = 'Shift sign out recorded successfully.';
        return shiftGetTodayRecord($pdo, $userId);
    }

    $error = 'Unknown shift action.';
    return $record;
}
