<?php
// inventory_form_sample.php: Sample form for admin to add/edit inventory (with product name support)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Management</title>
</head>
<body>
    <h2>Add Stock</h2>
    <form action="add_stock.php" method="POST">
        <input type="text" name="product_name" placeholder="Product Name" required>
        <input type="number" name="new_quantity" placeholder="Enter stock" required>
        <button type="submit">Add Inventory</button>
    </form>
    <h2>Edit Stock</h2>
    <form action="edit_stock.php" method="POST">
        <input type="number" name="product_id" placeholder="Product ID (optional)">
        <input type="text" name="product_name" placeholder="Product Name (optional)">
        <input type="number" name="new_quantity" placeholder="Enter new stock" required>
        <button type="submit">Update Inventory</button>
    </form>
</body>
</html>
