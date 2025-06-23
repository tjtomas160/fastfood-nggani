<?php
// add_stock.php: Admin adds new stock to inventory (with product name support)
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = trim($_POST['product_name']);
    $new_qty = intval($_POST['new_quantity']);

    if ($product_name === '' || $new_qty < 0) {
        echo '<div style="color:red;">Product name and quantity are required and must be valid.</div>';
        exit;
    }

    // Check if product already exists
    $stmt = $conn->prepare('SELECT product_id FROM inventory WHERE product_name = ?');
    $stmt->bind_param('s', $product_name);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        // Product exists, update quantity
        $stmt->bind_result($product_id);
        $stmt->fetch();
        $stmt->close();
        $update = $conn->prepare('UPDATE inventory SET quantity = quantity + ? WHERE product_id = ?');
        $update->bind_param('ii', $new_qty, $product_id);
        if ($update->execute()) {
            echo '<div style="color:green;">Stock updated successfully!</div>';
        } else {
            echo '<div style="color:red;">Error: ' . $update->error . '</div>';
        }
        $update->close();
    } else {
        $stmt->close();
        // Insert new product
        $insert = $conn->prepare('INSERT INTO inventory (product_name, quantity) VALUES (?, ?)');
        $insert->bind_param('si', $product_name, $new_qty);
        if ($insert->execute()) {
            echo '<div style="color:green;">Product added successfully!</div>';
        } else {
            echo '<div style="color:red;">Error: ' . $insert->error . '</div>';
        }
        $insert->close();
    }
    $conn->close();
}
?>
