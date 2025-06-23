<?php
session_start();
include('includes/config.php');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit-inventory'])) {
    $inventory_id = $_POST['inventory_id'];
    $quantity = $_POST['quantity'];
    $expiration_date = $_POST['expiration_date'];

    $sql_update = "UPDATE inventory SET Quantity = :quantity, Last_Updated = NOW() WHERE Inventory_ID = :inventory_id";
    $stmt = $dbh->prepare($sql_update);
    $stmt->execute([
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

    // Redirect to avoid resubmission
    header("Location: inventory-report.php");
    exit;
}

// Declare unit ranges for each status
define('OUT_OF_STOCK_THRESHOLD', 0);    // 0 units: Out of Stock
define('LOW_STOCK_MIN', 1);             // 1 unit is the minimum for Low Stock
define('LOW_STOCK_MAX', 20);            // 1-20 units: Low Stock
define('IN_STOCK_MIN', 21);             // 21+ units: In Stock

// Join inventory with products table
$sql = "SELECT 
            i.Inventory_ID,
            i.Quantity,
            i.Stock_Status,
            i.Last_Updated,
            p.Product_ID,
            p.Product_Name,
            p.Unit,
            p.Category,
            p.Expiration_Date
        FROM inventory i
        LEFT JOIN products p ON i.Product_ID = p.Product_ID";
$query = $dbh->prepare($sql);
$query->execute();
$items = $query->fetchAll(PDO::FETCH_ASSOC);

// Check if edit is requested
$edit_item = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    foreach ($items as $item) {
        if ($item['Inventory_ID'] == $edit_id) {
            $edit_item = $item;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Inventory Report</title>
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
    }

    .main-content h1 {
      font-size: 2rem;
      margin-bottom: 1rem;
      color: var(--text-dark);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
      margin-bottom: 2rem;
    }

    th, td {
      padding: 1rem;
      text-align: left;
      border-bottom: 1px solid #eee;
    }

    th {
      background: #f3f3f3;
    }

    .print-btn {
      background-color: var(--primary);
      color: white;
      padding: 10px 16px;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      cursor: pointer;
      margin-bottom: 1rem;
    }

    .print-btn:hover {
      background-color: #b84343;
    }

    .status-instock {
      color: #28a745;
      font-weight: bold;
    }
    .status-lowstock {
      color: #ffc107;
      font-weight: bold;
    }
    .status-outstock {
      color: #dc3545;
      font-weight: bold;
    }
    .status-unknown {
      color: #6c757d;
      font-weight: bold;
    }

    @media print {
      .sidebar,
      .print-btn {
        display: none !important;
      }

      .main-content {
        margin: 0;
        padding: 0;
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
      <li><a href="requests.php" class="active">Requests</a></li>
      <li><a href="sales-report.php">Sales</a></li>
      <li><a href="inventory-report.php" class="active">Inventory</a></li>
      <li><a href="logout.php">Log out</a></li>
    </ul>
  </div>

  <div class="main-content">
    <h1>Inventory Report</h1>
    <button class="print-btn" onclick="window.print()">Print Report</button>

    <div class="container">
      <table>
        <thead>
          <tr>
            <th>Inventory ID</th>
            <th>Product Name</th>
            <th>Quantity</th>
            <th>Unit</th>
            <th>Category</th>
            <th>Status</th>
            <th>Expiration Date</th>
            <th>Last Updated</th>
            <th>Action</th> <!-- Added Action column -->
          </tr>
        </thead>
        <tbody>
          <?php if ($items): ?>
            <?php foreach ($items as $item): ?>
              <?php
                $quantity = (int)$item['Quantity'];
                if ($quantity == 0) {
                    $stock_status = 'Out of Stock';
                } elseif ($quantity < 25) {
                    $stock_status = 'Low on Stock';
                } elseif ($quantity >= 25) {
                    $stock_status = 'In Stock';
                } else {
                    $stock_status = 'Unknown';
                }
              ?>
              <tr>
                <td><?= htmlspecialchars($item['Inventory_ID']) ?></td>
                <td><?= htmlspecialchars($item['Product_Name']) ?></td>
                <td><?= htmlspecialchars($item['Quantity']) ?></td>
                <td><?= htmlspecialchars($item['Unit']) ?></td>
                <td><?= htmlspecialchars($item['Category']) ?></td>
                <td><?= $stock_status ?></td>
                <td><?= htmlspecialchars($item['Expiration_Date']) ?></td>
                <td><?= htmlspecialchars($item['Last_Updated']) ?></td>
                <td>
                  <a href="edit-inventory.php?id=<?= urlencode($item['Inventory_ID']) ?>" 
                     style="background:#ffc107;color:#333;padding:6px 12px;border-radius:4px;text-decoration:none;font-size:0.95rem;">
                    Edit
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="9">No inventory data available.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <?php if ($edit_item): ?>
        <div style="background:#fff3cd;padding:20px;border-radius:8px;max-width:400px;margin:30px auto;">
          <h3>Edit Inventory (ID: <?= htmlspecialchars($edit_item['Inventory_ID']) ?>)</h3>
          <form method="post">
            <input type="hidden" name="inventory_id" value="<?= htmlspecialchars($edit_item['Inventory_ID']) ?>">
            <div style="margin-bottom:12px;">
              <label>Quantity:</label>
              <input type="number" name="quantity" value="<?= htmlspecialchars($edit_item['Quantity']) ?>" min="0" required style="width:100%;padding:6px;">
            </div>
            <div style="margin-bottom:12px;">
              <label>Expiration Date:</label>
              <input type="date" name="expiration_date" value="<?= htmlspecialchars($edit_item['Expiration_Date']) ?>" style="width:100%;padding:6px;">
            </div>
            <button type="submit" name="edit_inventory" style="background:#28a745;color:#fff;padding:8px 16px;border:none;border-radius:4px;">Save</button>
            <a href="inventory-report.php" style="margin-left:10px;color:#dc3545;text-decoration:none;">Cancel</a>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>
