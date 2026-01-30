<?php
require_once __DIR__ . '/includes/auth.php';

logoutUser();

header('Location: login.php?success=You have been logged out');
exit();