<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

if ($conn) {
    echo "✅ Database connected successfully!";
} else {
    echo "❌ Connection failed";
}