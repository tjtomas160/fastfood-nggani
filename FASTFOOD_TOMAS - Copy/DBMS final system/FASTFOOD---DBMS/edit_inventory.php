<?php
require_once 'includes/config.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventory_id = intval($_POST['inventory_id']);
    $new_qty = intval($_POST['new_quantity']);
    $expiration_date = $_POST['expiration_date'];

    // Get Product_ID for this inventory item
    $stmt = $dbh->prepare("SELECT Product_ID FROM inventory WHERE Inventory_ID = ?");
    $stmt->execute([$inventory_id]);
    $product_id = $stmt->fetchColumn();

    // Update inventory quantity and last updated
    $stmt = $dbh->prepare("UPDATE inventory SET Quantity = ?, Last_Updated = NOW() WHERE Inventory_ID = ?");
    $success1 = $stmt->execute([$new_qty, $inventory_id]);

    // Update expiration date in products table
    $success2 = true;
    if ($product_id) {
        $stmt = $dbh->prepare("UPDATE products SET Expiration_Date = ? WHERE Product_ID = ?");
        $success2 = $stmt->execute([$expiration_date, $product_id]);
    }

    if ($success1 && $success2) {
        header('Location: inventory-report.php?msg=Inventory updated');
        exit;
    } else {
        echo "Error updating inventory.";
    }
}
?>
