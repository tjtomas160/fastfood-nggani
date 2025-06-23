<?php
// migrate_orders_employee_id.php: Adds employee_id column to orders table if not exists
require_once 'includes/config.php';

// Check if column exists
$check = $conn->query("SHOW COLUMNS FROM `order` LIKE 'employee_id'");
if ($check && $check->num_rows === 0) {
    $sql = "ALTER TABLE `order` ADD COLUMN employee_id INT NULL, ADD FOREIGN KEY (employee_id) REFERENCES employees(employee_id)";
    if ($conn->query($sql) === TRUE) {
        echo "employee_id column added to orders table.";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} else {
    echo "employee_id column already exists.";
}
$conn->close();
?>
