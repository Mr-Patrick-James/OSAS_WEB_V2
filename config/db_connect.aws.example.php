<?php
/**
 * AWS EC2 — copy to db_connect.local.php on the server:
 *   cp config/db_connect.aws.example.php config/db_connect.local.php
 */
$host   = 'localhost';
$user   = 'root';
$pass   = 'osas';
$dbname = 'osas';

$conn = @new mysqli($host, $user, $pass, $dbname);
