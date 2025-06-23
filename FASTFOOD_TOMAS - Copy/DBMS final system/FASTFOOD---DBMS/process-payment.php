<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include('includes/config.php');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['user_id']) || empty($data['items'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid session or empty cart']);
    exit;
}

$user_id = $_SESSION['user_id'];
$payment_method = $data['payment_method'];
$discount = $data['discount'];
$total_amount = $data['total_amount'];
$date = date('Y-m-d H:i:s');

try {
    // Insert order (status Pending)
    $stmt = $dbh->prepare("INSERT INTO `order` (customer_id, total_amount, order_date, order_status) VALUES (?, ?, ?, 'Pending')");
    $stmt->execute([$user_id, $total_amount, $date]);
    $order_id = $dbh->lastInsertId();

    // Insert payment info (with all required fields)
    $payment_status = 'Paid';
    $payment_date = date('Y-m-d H:i:s');
    $amount_paid = $total_amount;
    $stmt_payment = $dbh->prepare("INSERT INTO payment (order_id, payment_method, payment_status, payment_date, discount, amount_paid) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_payment->execute([$order_id, $payment_method, $payment_status, $payment_date, $discount, $amount_paid]);

    // Optionally, insert into receipt table
    $payment_id = $dbh->lastInsertId();
    $receipt_date = date('Y-m-d H:i:s');
    $stmt_receipt = $dbh->prepare("INSERT INTO receipt (payment_id, receipt_date) VALUES (?, ?)");
    $stmt_receipt->execute([$payment_id, $receipt_date]);

    // Insert order items (NO inventory deduction here)
    $stmt_item = $dbh->prepare("INSERT INTO order_details (order_id, item_id, quantity) VALUES (?, ?, ?)");
    foreach ($data['items'] as $item) {
        $stmt_item->execute([$order_id, $item['item_id'], $item['quantity']]);
    }

    // Insert into sales table for reporting
    $stmt_sales = $dbh->prepare("INSERT INTO sales (Sale_Date, Order_ID, Total_Amount, Discount) VALUES (?, ?, ?, ?)");
    $stmt_sales->execute([$date, $order_id, $total_amount, $discount]);

    // INVENTORY DEDUCTION BASED ON PRODUCT ID
    foreach ($data['items'] as $item) {
        $product_id = $item['item_id'];
        $qty_ordered = $item['quantity'];
        $deduct = $dbh->prepare("UPDATE inventory SET Quantity = Quantity - ?, Last_Updated = NOW() WHERE Product_ID = ?");
        $deduct->execute([$qty_ordered, $product_id]);
        // Log the new quantity and last updated
        $log_stmt = $dbh->prepare("SELECT Quantity, Last_Updated FROM inventory WHERE Product_ID = ?");
        $log_stmt->execute([$product_id]);
        $row = $log_stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Product_ID $product_id: New Quantity = {$row['Quantity']}, Last Updated = {$row['Last_Updated']}");
    }

    // Clear cart
    unset($_SESSION['cart']);

    echo json_encode([
        'status' => 'success',
        'order_id' => $order_id,
        'customer_id' => $user_id,
        'date' => $date
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
