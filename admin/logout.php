<?php
require_once '../includes/auth.php';

requireDesignatedAdmin();

writeAuditLog(
	'admin logout confirmation viewed',
	'users',
	(int)($_SESSION['user']['id'] ?? 0),
	null,
	['username' => (string)($_SESSION['user']['username'] ?? '')]
);

header('Location: logout_confirm.php');
exit;
?>