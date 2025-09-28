<?php
$password = 'admin123'; // choose your password
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;
