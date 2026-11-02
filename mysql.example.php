<?php
// Database connection settings
// Copy this file to mysql.php and fill in your own credentials.
$host = 'localhost';        // or your database server address
$username = 'your_username';
$password = 'your_password';
$dbname = 'your_database_name';

// Create connection
$mysql = new mysqli($host, $username, $password, $dbname);

// Check connection error
if ($mysql->connect_error) {
    die('Database connection error: ' . $mysql->connect_error);
}

// Set character set
$mysql->set_charset('utf8mb4');
