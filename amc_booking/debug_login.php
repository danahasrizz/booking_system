<?php
echo "<h2>Password Hashes for All Users:</h2>";

$passwords = [
    'admin' => 'Admin@123',
    'staff1' => 'Staff@123',
    'student1' => 'Student@123',
    'student2' => 'Student@123'
];

foreach ($passwords as $username => $password) {
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    echo "<strong>$username</strong> (password: $password)<br>";
    echo "<code>$hash</code><br><br>";
}