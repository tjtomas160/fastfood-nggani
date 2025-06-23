<?php
session_start();
include('includes/config.php');

// Check if user is admin or employee
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    header('Location: login.php');
    exit;
}

// HANDLE ORDER APPROVAL & DEDUCT INVENTORY (RECIPE-BASED)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_order'])) {
    $order_id = $_POST['order_id'];
    try {
        $dbh->beginTransaction();
        // Get order items (menu items and their quantities)
        $stmt = $dbh->prepare("SELECT item_id, quantity FROM order_details WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Check all ingredients
        foreach ($items as $item) {
            $item_id = $item['item_id'];
            $qty_ordered = $item['quantity'];
            $ing_stmt = $dbh->prepare("SELECT ingredient_id, quantity_required FROM product_ingredients WHERE item_id = ?");
            $ing_stmt->execute([$item_id]);
            $ingredients = $ing_stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($ingredients as $ingredient) {
                $ingredient_id = $ingredient['ingredient_id'];
                $total_needed = $ingredient['quantity_required'] * $qty_ordered;
                $check = $dbh->prepare("SELECT Quantity FROM inventory WHERE Product_ID = ?");
                $check->execute([$ingredient_id]);
                $stock = $check->fetchColumn();
                if ($stock === false || $stock < $total_needed) {
                    throw new Exception('Insufficient stock for ingredient ID ' . $ingredient_id);
                }
            }
        }
        // Deduct all ingredients
        foreach ($items as $item) {
            $item_id = $item['item_id'];
            $qty_ordered = $item['quantity'];
            $ing_stmt = $dbh->prepare("SELECT ingredient_id, quantity_required FROM product_ingredients WHERE item_id = ?");
            $ing_stmt->execute([$item_id]);
            $ingredients = $ing_stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($ingredients as $ingredient) {
                $ingredient_id = $ingredient['ingredient_id'];
                $total_needed = $ingredient['quantity_required'] * $qty_ordered;
                $deduct = $dbh->prepare("UPDATE inventory SET Quantity = Quantity - ?, Last_Updated = NOW() WHERE Product_ID = ?");
                $deduct->execute([$total_needed, $ingredient_id]);
            }
        }
        // Update order status
        $dbh->prepare("UPDATE `order` SET order_status = 'Approved' WHERE order_id = ?")->execute([$order_id]);
        $dbh->commit();
        $success_msg = "Order #$order_id approved and inventory updated.";
    } catch (Exception $e) {
        $dbh->rollBack();
        $error_msg = "Error processing order: " . $e->getMessage();
    }
}
?>
<!-- Example order approval form (to be placed in your order list/table) -->
<!--
<form method="POST" action="order-requests.php">
  <input type="hidden" name="order_id" value="ORDER_ID_HERE">
  <button type="submit" name="approve_order">Approve</button>
</form>
-->
