<?php
require_once 'includes/config.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['inventory_id'])) {
    echo 'No inventory item selected.';
    exit;
}
$inventory_id = intval($_GET['inventory_id']);

// Fetch inventory item details using PDO
$stmt = $dbh->prepare("SELECT i.Inventory_ID, i.Quantity, p.Product_Name, p.Unit, p.Category, p.Expiration_Date FROM inventory i LEFT JOIN products p ON i.Product_ID = p.Product_ID WHERE i.Inventory_ID = ?");
$stmt->execute([$inventory_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) {
    echo 'Inventory item not found.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Inventory Item</title>
</head>
<body>
    <h2>Edit Inventory Item</h2>
    <form action="edit_inventory.php" method="POST">
        <input type="hidden" name="inventory_id" value="<?= $item['Inventory_ID'] ?>">
        <label>Product Name: <b><?= htmlspecialchars($item['Product_Name']) ?></b></label><br>
        <label>Unit: <b><?= htmlspecialchars($item['Unit']) ?></b></label><br>
        <label>Category: <b><?= htmlspecialchars($item['Category']) ?></b></label><br>
        <label>Expiration Date:</label>
        <input type="date" name="expiration_date" value="<?= htmlspecialchars($item['Expiration_Date']) ?>" required><br>
        <label>Quantity:</label>
        <input type="number" name="new_quantity" min="0" value="<?= $item['Quantity'] ?>" required><br><br>
        <button type="submit">Update Inventory</button>
    </form>
    <br>
    <a href="inventory-report.php">Back to Inventory Report</a>
</body>
</html>
