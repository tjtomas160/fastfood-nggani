<?php
// edit_stock.php: Admin edits inventory stock for a product (by name or ID)
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
    $product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
    $new_qty = intval($_POST['new_quantity']);

    if ($new_qty < 0) {
        echo '<div style="color:red;">Quantity must be valid.</div>';
        exit;
    }

    if ($product_id) {
        $stmt = $conn->prepare('UPDATE inventory SET quantity = ? WHERE product_id = ?');
        $stmt->bind_param('ii', $new_qty, $product_id);
    } elseif ($product_name !== '') {
        $stmt = $conn->prepare('UPDATE inventory SET quantity = ? WHERE product_name = ?');
        $stmt->bind_param('is', $new_qty, $product_name);
    } else {
        echo '<div style="color:red;">Product ID or name required.</div>';
        exit;
    }

    if ($stmt->execute()) {
        echo '<div style="color:green;">Inventory updated successfully!</div>';
    } else {
        echo '<div style="color:red;">Error: ' . $stmt->error . '</div>';
    }
    $stmt->close();
    $conn->close();
}
?>
