<?php
require_once '../includes/auth.php';

requireLogin();
header('Location: logout_confirm.php');
exit;
?>