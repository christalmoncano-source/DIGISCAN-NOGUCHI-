<?php
// Database Configuration
$host = "localhost";
$username = "root";
$password = ""; // Default XAMPP password is empty
$database = "library_system";

try {
    $conn = mysqli_connect($host, $username, $password, $database);
} catch (mysqli_sql_exception $e) {
    die("<div style='padding: 20px; font-family: sans-serif; border: 1px solid #ff0000; background: #fff0f0; border-radius: 8px; max-width: 600px; margin: 40px auto; text-align: center;'>
            <h2 style='color: #d00;'>Database Connection Failed</h2>
            <p>The DigiScan system cannot connect to the database. This usually means your <strong>XAMPP MySQL</strong> service is not running.</p>
            <p style='background: #eee; padding: 10px; border-radius: 4px; font-size: 0.9rem;'><strong>Error:</strong> " . $e->getMessage() . "</p>
            <div style='margin-top: 20px;'>
                <strong>How to fix:</strong><br>
                1. Open the <strong>XAMPP Control Panel</strong>.<br>
                2. Find <strong>MySQL</strong> in the list.<br>
                3. Click the <strong>'Start'</strong> button next to it.
            </div>
            <p style='margin-top: 20px;'><button onclick='location.reload()' style='padding: 10px 20px; cursor: pointer; background: #4f46e5; color: white; border: none; border-radius: 5px;'>Retry Connection</button></p>
         </div>");
}

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
