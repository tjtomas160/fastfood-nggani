<?php

session_start();
include('includes/config.php');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get inventory ID from query
if (!isset($_GET['id'])) {
    header('Location: inventory-report.php');
    exit;
}
$inventory_id = $_GET['id'];

// Fetch inventory and product info
$sql = "SELECT 
            i.Inventory_ID,
            i.Quantity,
            i.Last_Updated,
            p.Product_Name,
            p.Unit,
            p.Category,
            p.Expiration_Date
        FROM inventory i
        LEFT JOIN products p ON i.Product_ID = p.Product_ID
        WHERE i.Inventory_ID = :inventory_id";
$stmt = $dbh->prepare($sql);
$stmt->execute([':inventory_id' => $inventory_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    echo "Inventory item not found.";
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit-inventory'])) {
    $quantity = $_POST['quantity'];
    $expiration_date = $_POST['expiration_date'];

    $sql_update = "UPDATE inventory SET Quantity = :quantity, Last_Updated = NOW() WHERE Inventory_ID = :inventory_id";
    $stmt_update = $dbh->prepare($sql_update);
    $stmt_update->execute([
        ':quantity' => $quantity,
        ':inventory_id' => $inventory_id
    ]);

    // Optionally update expiration date in products table
    if (!empty($expiration_date)) {
        $sql_exp = "UPDATE products p
                    JOIN inventory i ON i.Product_ID = p.Product_ID
                    SET p.Expiration_Date = :expiration_date
                    WHERE i.Inventory_ID = :inventory_id";
        $stmt_exp = $dbh->prepare($sql_exp);
        $stmt_exp->execute([
            ':expiration_date' => $expiration_date,
            ':inventory_id' => $inventory_id
        ]);
    }

    header("Location: inventory-report.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Edit Inventory</title>
  <style>
    :root {
      --sidebar-width: 220px;
      --primary: #cc5050;
      --secondary: #d3c260;
      --bg-light: #fff8f0;
      --text-dark: #333;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: "Segoe UI", sans-serif;
    }

    body {
      display: flex;
      background-color: var(--bg-light);
      min-height: 100vh;
    }

    .sidebar {
      width: var(--sidebar-width);
      background: linear-gradient(to right, var(--primary), var(--secondary));
      color: white;
      padding-top: 2rem;
      position: fixed;
      top: 0;
      bottom: 0;
      left: 0;
    }

    .sidebar h2 {
      text-align: center;
      margin-bottom: 2rem;
      font-size: 1.5rem;
    }

    .sidebar ul {
      list-style: none;
      padding: 0;
    }

    .sidebar ul li {
      margin: 1rem 0;
    }

    .sidebar ul li a {
      color: white;
      text-decoration: none;
      padding: 0.8rem 1.5rem;
      display: block;
      transition: background 0.3s;
    }

    .sidebar ul li a:hover,
    .sidebar ul li a.active {
      background: rgba(255, 255, 255, 0.2);
      border-left: 4px solid white;
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

    .main-content h1 {
      font-size: 2rem;
      margin-bottom: 1.5rem;
      color: var(--text-dark);
      text-align: center;
    }

    .edit-form-container {
      background: #fff3cd;
      padding: 30px 30px 20px 30px;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.07);
      max-width: 400px;
      width: 100%;
      margin-top: 30px;
    }

    .edit-form-container label {
      display: block;
      margin-bottom: 6px;
      font-weight: 500;
      color: #333;
    }

    .edit-form-container input[type="number"],
    .edit-form-container input[type="date"] {
      width: 100%;
      padding: 7px;
      margin-bottom: 16px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 1rem;
    }

    .edit-form-container .btn {
      background: #28a745;
      color: #fff;
      border: none;
      padding: 9px 18px;
      border-radius: 4px;
      font-size: 1rem;
      cursor: pointer;
      margin-right: 10px;
    }

    .edit-form-container .btn-cancel {
      background: #dc3545;
      color: #fff;
      text-decoration: none;
      padding: 9px 18px;
      border-radius: 4px;
      font-size: 1rem;
      border: none;
      cursor: pointer;
    }

    .edit-form-container .info {
      margin-bottom: 18px;
      color: #333;
      font-size: 1.05rem;
      background: #fffbe6;
      padding: 10px 12px;
      border-radius: 6px;
    }

    @media (max-width: 700px) {
      .main-content {
        padding: 1rem;
      }
      .edit-form-container {
        padding: 18px 8px 12px 8px;
      }
      .sidebar {
        width: 100px;
        padding-top: 1rem;
      }
      .sidebar h2 {
        font-size: 1rem;
      }
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
    <h1>Edit Inventory</h1>
    <div class="edit-form-container">
      <div class="info">
        <strong>Product:</strong> <?= htmlspecialchars($item['Product_Name']) ?><br>
        <strong>Category:</strong> <?= htmlspecialchars($item['Category']) ?><br>
        <strong>Unit:</strong> <?= htmlspecialchars($item['Unit']) ?>
      </div>
      <form method="post">
        <label>Quantity:</label>
        <input type="number" name="quantity" value="<?= htmlspecialchars($item['Quantity']) ?>" min="0" required>
        <label>Expiration Date:</label>
        <input type="date" name="expiration_date" value="<?= htmlspecialchars($item['Expiration_Date']) ?>">
        <button type="submit" name="edit-inventory" class="btn">Save</button>
        <a href="inventory-report.php" class="btn-cancel">Cancel</a>
      </form>
    </div>
  </div>
</body>
</html>