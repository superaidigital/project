<?php
$servername = "localhost";
$username = "sskpao_db"; // Your MySQL username (default for XAMPP is "root")
$password = "@Sskpao123"; // Your MySQL password (default for XAMPP is empty)
$dbname = "sskpao_db"; // The database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Set charset to utf8
$conn->set_charset("utf8");

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Check connection status
if (!$conn->ping()) {
  die("Cannot connect to the database");
}
?>