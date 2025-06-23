<?php
session_start();
include('includes/config.php');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Fetch products for dropdown
$sql_products = "SELECT Product_ID, Product_Name, Unit, Category, Expiration_Date FROM products";
$stmt_products = $dbh->prepare($sql_products);
$stmt_products->execute();
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_inventory'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];

    // Insert into inventory
    $sql_insert = "INSERT INTO inventory (Product_ID, Quantity, Last_Updated) VALUES (:product_id, :quantity, NOW())";
    $stmt_insert = $dbh->prepare($sql_insert);
    $stmt_insert->execute([
        ':product_id' => $product_id,
        ':quantity' => $quantity
    ]);

    header("Location: inventory-report.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Add Inventory</title>
  <style>
    :root {
      --sidebar-width: 220px;
      --primary: #cc5050;
      --secondary: #d3c260;
      --bg-light: #fff8f0;
      --text-dark: #333;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Segoe UI", sans-serif; }
    body { display: flex; background-color: var(--bg-light); min-height: 100vh; }
    .sidebar {
      width: var(--sidebar-width);
      background: linear-gradient(to right, var(--primary), var(--secondary));
      color: white;
      padding-top: 2rem;
      position: fixed;
      top: 0; bottom: 0; left: 0;
    }
    .sidebar h2 { text-align: center; margin-bottom: 2rem; font-size: 1.5rem; }
    .sidebar ul { list-style: none; padding: 0; }
    .sidebar ul li { margin: 1rem 0; }
    .sidebar ul li a {
      color: white; text-decoration: none; padding: 0.8rem 1.5rem; display: block; transition: background 0.3s;
    }
    .sidebar ul li a:hover, .sidebar ul li a.active {
      background: rgba(255,255,255,0.2); border-left: 4px solid white;
    }
    .main-content {
      margin-left: var(--sidebar-width);
      padding: 2rem;
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
    }
    .main-content h1 { font-size: 2rem; margin-bottom: 1.5rem; color: var(--text-dark); text-align: center; }
    .add-form-container {
      background: #fff3cd;
      padding: 30px 30px 20px 30px;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.07);
      max-width: 400px;
      width: 100%;
      margin-top: 30px;
    }
    .add-form-container label {
      display: block;
      margin-bottom: 6px;
      font-weight: 500;
      color: #333;
    }
    .add-form-container select,
    .add-form-container input[type="number"] {
      width: 100%;
      padding: 7px;
      margin-bottom: 16px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 1rem;
    }
    .add-form-container .btn {
      background: #28a745;
      color: #fff;
      border: none;
      padding: 9px 18px;
      border-radius: 4px;
      font-size: 1rem;
      cursor: pointer;
      margin-right: 10px;
    }
    .add-form-container .btn-cancel {
      background: #dc3545;
      color: #fff;
      text-decoration: none;
      padding: 9px 18px;
      border-radius: 4px;
      font-size: 1rem;
      border: none;
      cursor: pointer;
    }
    @media (max-width: 700px) {
      .main-content { padding: 1rem; }
      .add-form-container { padding: 18px 8px 12px 8px; }
      .sidebar { width: 100px; padding-top: 1rem; }
      .sidebar h2 { font-size: 1rem; }
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <h2>FastBite</h2>
    <ul>
      <li><a href="admin-dashboard.php">Home</a></li>
      <li><a href="employee-database.php">Employees</a></li>
      <li><a href="requests.php">Requests</a></li>
      <li><a href="sales-report.php">Sales</a></li>
      <li><a href="inventory-report.php" class="active">Inventory</a></li>
      <li><a href="logout.php">Log out</a></li>
    </ul>
  </div>
  <div class="main-content">
    <h1>Add Inventory</h1>
    <div class="add-form-container">
      <form method="post">
        <label for="product_id">Product:</label>
        <select name="product_id" id="product_id" required>
          <option value="">-- Select Product --</option>
          <?php foreach ($products as $product): ?>
            <option value="<?= htmlspecialchars($product['Product_ID']) ?>">
              <?= htmlspecialchars($product['Product_Name']) ?> (<?= htmlspecialchars($product['Unit']) ?>, <?= htmlspecialchars($product['Category']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <label for="quantity">Quantity:</label>
        <input type="number" name="quantity" id="quantity" min="0" required>
        <button type="submit" name="add_inventory" class="btn">Add</button>
        <a href="inventory-report.php" class="btn-cancel">Cancel</a>
      </form>
    </div>
  </div>
</body>
</html>