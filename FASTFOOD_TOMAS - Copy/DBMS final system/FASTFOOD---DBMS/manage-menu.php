<?php
session_start();
include('includes/config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$success_message = '';

// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id']) && isset($_POST['update_item'])) {
    $id = intval($_POST['edit_id']);
    $name = trim($_POST["edit_item_name"]);
    $desc = trim($_POST["edit_description"]);
    $category = $_POST["edit_category"];
    $price = floatval($_POST["edit_price"]);

    if ($name && $category && $price > 0) {
        try {
            $stmt = $dbh->prepare("UPDATE menu SET item_name = ?, description = ?, category = ?, price = ? WHERE item_id = ?");
            $stmt->execute([$name, $desc, $category, $price, $id]);
            $success_message = "<div class='success'>Item updated successfully!</div>";
        } catch (Exception $e) {
            $error_message = "<div class='error'>Error updating item: " . $e->getMessage() . "</div>";
        }
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    
    try {
        // Start transaction
        $dbh->beginTransaction();
        
        // First, delete related order details
        $stmt = $dbh->prepare("DELETE FROM order_details WHERE item_id = ?");
        $stmt->execute([$id]);
        
        // Then delete the menu item
        $stmt = $dbh->prepare("DELETE FROM menu WHERE item_id = ?");
        $stmt->execute([$id]);
        
        // Commit the transaction
        $dbh->commit();
        
        $success_message = "<div class='success'>Item and related order details deleted successfully!</div>";
    } catch (Exception $e) {
        // Rollback the transaction if something went wrong
        $dbh->rollBack();
        $success_message = "<div class='error'>Error deleting item: " . $e->getMessage() . "</div>";
    }
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $name = trim($_POST['item_name']);
    $desc = trim($_POST['description']);
    $category = $_POST['category'];
    $price = floatval($_POST['price']);
    $availability = $_POST['availability'];
    $prep_time = intval($_POST['preparation_time']);

    if ($name && $category && $price > 0 && isset($_POST['ingredients']) && is_array($_POST['ingredients']) && count($_POST['ingredients']) > 0) {
        $stmt = $dbh->prepare("INSERT INTO menu (item_name, description, category, price, availability, preparation_time) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $desc, $category, $price, $availability, $prep_time]);
        $item_id = $dbh->lastInsertId();
        // Save ingredients
        foreach ($_POST['ingredients'] as $ingredient) {
            $prod_id = intval($ingredient['product_id']);
            $qty_needed = intval($ingredient['quantity']);
            if ($prod_id && $qty_needed > 0) {
                $stmt = $dbh->prepare("INSERT INTO menu_ingredients (item_id, Product_ID, quantity_needed) VALUES (?, ?, ?)");
                $stmt->execute([$item_id, $prod_id, $qty_needed]);
            }
        }
        $success_message = "<div class='success'>Item added successfully!</div>";
    } else {
        $success_message = "<div class='error'>You must specify at least one ingredient (product name) for this menu item.</div>";
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $dbh->prepare("DELETE FROM menu WHERE item_id = ?");
    $stmt->execute([$id]);
    header("Location: manage-menu.php");
    exit;
}

// Change the query to order by item_id DESC

$stmt = $dbh->query("SELECT * FROM menu ORDER BY item_id DESC");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Fallback: sort items by item_id descending in PHP
usort($items, function($a, $b) {
    return $b['item_id'] - $a['item_id'];
});

// Fetch all inventory products for ingredient selection (use inventory join for accurate names)
$products_stmt = $dbh->query("SELECT i.Product_ID, p.Product_Name, p.Unit FROM inventory i LEFT JOIN products p ON i.Product_ID = p.Product_ID WHERE i.Quantity > 0");
$all_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Find unused products in inventory (by Product_ID, which is the product name in the inventory report)
// Exclude certain products from this check, e.g., spices or condiments not directly assigned to a single menu item.
$excluded_product_ids = [27]; // Add product IDs to this array to exclude them from the warning.
$query = "SELECT p.Product_ID, p.Product_Name FROM products p LEFT JOIN menu_ingredients mi ON p.Product_ID = mi.Product_ID WHERE mi.Product_ID IS NULL";
if (!empty($excluded_product_ids)) {
    $query .= " AND p.Product_ID NOT IN (" . implode(',', $excluded_product_ids) . ")";
}
$unused_products_stmt = $dbh->query($query);
$unused_products = $unused_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prevent adding or updating inventory for unused products
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $name = trim($_POST['item_name']);
    $desc = trim($_POST['description']);
    $category = $_POST['category'];
    $price = floatval($_POST['price']);
    $availability = $_POST['availability'];
    $prep_time = intval($_POST['preparation_time']);

    if ($name && $category && $price > 0 && isset($_POST['ingredients']) && is_array($_POST['ingredients']) && count($_POST['ingredients']) > 0) {
        $ingredient_ids = array();
        foreach ($_POST['ingredients'] as $ingredient) {
            $ingredient_ids[] = intval($ingredient['product_id']);
        }
        // Check if all products in inventory are used in at least one menu item
        $all_product_ids = array_map(function($prod) { return $prod['Product_ID']; }, $all_products);
        $unused_ids = array();
        foreach ($all_product_ids as $pid) {
            $used = $dbh->prepare("SELECT COUNT(*) FROM menu_ingredients WHERE Product_ID = ?");
            $used->execute([$pid]);
            if ($used->fetchColumn() == 0) {
                $unused_ids[] = $pid;
            }
        }
        if (count($unused_ids) > 0) {
            $success_message = "<div class='error'>All products in inventory must be used in at least one menu item. Unused Product IDs: ".implode(', ', $unused_ids)."</div>";
        } else {
            $stmt = $dbh->prepare("INSERT INTO menu (item_name, description, category, price, availability, preparation_time) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $desc, $category, $price, $availability, $prep_time]);
            $item_id = $dbh->lastInsertId();
            // Save ingredients
            foreach ($_POST['ingredients'] as $ingredient) {
                $prod_id = intval($ingredient['product_id']);
                $qty_needed = intval($ingredient['quantity']);
                if ($prod_id && $qty_needed > 0) {
                    $stmt = $dbh->prepare("INSERT INTO menu_ingredients (item_id, Product_ID, quantity_needed) VALUES (?, ?, ?)");
                    $stmt->execute([$item_id, $prod_id, $qty_needed]);
                }
            }
            $success_message = "<div class='success'>Item added successfully!</div>";
        }
    } else {
        $success_message = "<div class='error'>You must specify at least one ingredient (product name) for this menu item.</div>";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Menu - FastBite</title>
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
      margin-bottom: 1.5rem;
      color: var(--text-dark);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    }

    th, td {
      padding: 1rem;
      text-align: left;
      border-bottom: 1px solid #eee;
    }

    th {
      background: #f3f3f3;
    }

    .action-btn {
      padding: 0.4rem 0.8rem;
      margin-right: 0.5rem;
      font-size: 0.9rem;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }

    .edit-btn {
      background-color: #f0ad4e;
      color: white;
      font-size: 0.9rem;
      padding: 0.4rem 0.8rem;
      border-radius: 4px;
      cursor: pointer;
    }

    .delete-btn {
      background-color: #d9534f;
      color: white;
      font-size: 0.9rem;
      padding: 0.4rem 0.8rem;
      border-radius: 4px;
      cursor: pointer;
    }

    .success-message {
      background-color: #d4edda;
      color: #155724;
      padding: 1rem;
      border-radius: 4px;
      margin-bottom: 1rem;
      text-align: center;
    }

    @media (max-width: 768px) {
      .sidebar {
        position: static;
        width: 100%;
        display: flex;
        justify-content: space-around;
        height: auto;
      }

      .main-content {
        margin-left: 0;
        padding-top: 1rem;
      }

      table {
        font-size: 0.9rem;
      }
    }

    .modal-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.4);
      z-index: 1000;
    }
    .edit-modal {
      position: fixed;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      z-index: 1001;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .edit-modal-content {
      background: white;
      padding: 2rem;
      border-radius: 8px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.2);
      min-width: 320px;
    }
    .add-button {
      background: green;
      color: white;
      padding: 0.5rem 1rem;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.9rem;
    }

    .add-form {
      background: white;
      padding: 1rem;
      border-radius: 8px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    }

    .add-form input,
    .add-form textarea,
    .add-form select {
      width: 100%;
      margin-bottom: 0.5rem;
    }
        .edit-field {
      display: none;
      width: 100%;
      padding: 0.5rem;
      margin: 0;
      border: 1px solid #ddd;
      border-radius: 4px;
    }

    .edit-field:focus {
      outline: none;
      border-color: var(--primary);
    }

    .view-field {
      cursor: pointer;
      padding: 0.5rem;
    }

    .view-field:hover {
      background-color: #f8f9fa;
    }

    .edit-mode .view-field {
      display: none;
    }

    .edit-mode .edit-field {
      display: block;
    }

    .edit-mode .edit-btn {
      display: none;
    }

    .edit-mode .save-btn {
      display: inline-block;
    }

    .edit-mode .cancel-btn {
      display: inline-block;
    }

    .save-btn, .cancel-btn {
      display: none;
      background: var(--primary);
      color: white;
      padding: 0.4rem 0.8rem;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.9rem;
    }

    .cancel-btn {
      background: #ccc;
      color: #333;
    }

    .select-field {
      width: 100%;
      padding: 0.5rem;
      margin: 0;
      border: 1px solid #ddd;
      border-radius: 4px;
    }

    .select-field:focus {
      outline: none;
      border-color: var(--primary);
    }

    /* Align table cells */
    td {
      vertical-align: middle;
    }

    textarea {
      resize: none;
    }
  </style>
</head>
<body>

<nav class="sidebar">
  <h2>FastBite</h2>
  <ul>
    <li><a href="employee-dashboard.php">Home</a></li>
    <li><a href="process-order.php">Process Order</a></li>
    <li><a href="employee-requests.php">Requests</a></li>
    <li><a href="manage-menu.php" class="active">Manage Menu</a></li>
    <li><a href="logout.php">Log out</a></li>
  </ul>
</nav>

<div class="main-content">
  <h1 style="display: flex; justify-content: space-between; align-items: center;">
    Manage Menu
    <button class="action-btn add-btn" type="button" onclick="showAddForm('add-form')" style="margin-left: auto;">Add New Item</button>
  </h1>

  <form method="POST" onsubmit="return validateIngredients()" style="margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.5rem;">
    <div id="add-form" style="display: none;">
      <div style="display: flex; flex-direction: column; gap: 0.5rem;">
        <input type="text" name="item_name" placeholder="Item Name" required style="padding: 0.5rem;">
        <textarea name="description" placeholder="Description" required style="padding: 0.5rem; height: 80px; width: 100%;"></textarea>
        <select name="category" required style="padding: 0.5rem;">
          <option value="">Select Category</option>
          <option value="Burgers">Burgers</option>
          <option value="Fries & Sides">Fries & Sides</option>
          <option value="Drinks">Drinks</option>
          <option value="Desserts">Desserts</option>
        </select>
        <input type="number" name="price" placeholder="Price" min="1" required style="padding: 0.5rem;">
        <input type="hidden" name="availability" value="Available">
        <input type="hidden" name="preparation_time" value="15">
        <div id="ingredients-section">
          <label><b>Ingredients (Product Names from Inventory):</b> <span style="color:red">*</span></label>
          <div id="ingredients-list">
            <div class="ingredient-row">
              <select name="ingredients[0][product_id]" required>
                <option value="">Select Ingredient</option>
                <?php foreach ($all_products as $prod): ?>
                  <option value="<?= $prod['Product_ID'] ?>"><?= htmlspecialchars($prod['Product_Name']) ?> (<?= $prod['Unit'] ?>)</option>
                <?php endforeach; ?>
              </select>
              <input type="number" name="ingredients[0][quantity]" min="1" placeholder="Qty" required style="width:80px;">
              <button type="button" onclick="addIngredientRow()">+</button>
            </div>
          </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
          <button type="submit" name="add_item" style="background: var(--primary); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px;">Save</button>
          <button type="button" onclick="hideAddForm('add-form')" style="background: #ccc; color: #333; padding: 0.5rem 1rem; border: none; border-radius: 4px;">Cancel</button>
        </div>
      </div>
    </div>
  </form>

  <?php if ($success_message): ?>
  <div class="success-message"><?php echo $success_message; ?></div>
  <?php endif; ?>

  <?php if (count($unused_products) > 0): ?>
  <div class="error" style="background:#ffe0e0; color:#a00; padding:1em; margin-bottom:1em; border-radius:6px;">
    <b>Warning:</b> The following inventory products (Product IDs) are not used as ingredients in any menu item:<br>
    <ul>
      <?php foreach ($unused_products as $prod): ?>
        <li><?= htmlspecialchars($prod['Product_Name']) ?> (ID: <?= $prod['Product_ID'] ?>)</li>
      <?php endforeach; ?>
    </ul>
    Please assign these products as ingredients to at least one menu item to ensure all inventory is used.
  </div>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Item Name</th>
        <th>Description</th>
        <th>Category</th>
        <th>Price</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
     <?php foreach ($items as $item): ?>
      <tr data-item-id="<?= $item['item_id'] ?>">
        <td><?= $item['item_id'] ?></td>
        <td><?= htmlspecialchars($item['item_name']) ?></td>
        <td><?= htmlspecialchars($item['description']) ?></td>
        <td><?= htmlspecialchars($item['category']) ?></td>
        <td><?= number_format($item['price'], 2) ?></td>
        <td>
          <button class="action-btn edit-btn" type="button" onclick="showEditForm(<?= $item['item_id'] ?>)">Edit</button>
          <form method="POST" action="" style="display:inline;">
            <input type="hidden" name="delete_id" value="<?= $item['item_id'] ?>">
            <button class="action-btn delete-btn" type="submit" onclick="return confirm('Delete this item?')">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php foreach ($items as $item): ?>
  <div id="edit-modal-overlay-<?= $item['item_id'] ?>" class="modal-overlay" style="display:none;"></div>
  <div id="edit-form-<?= $item['item_id'] ?>" class="edit-modal" style="display: none;">
    <div class="edit-modal-content">
      <h3>Edit Item</h3>
      <form method="POST" style="display: flex; flex-direction: column; gap: 0.5rem;">
        <input type="hidden" name="edit_id" value="<?= $item['item_id'] ?>">
        <input type="text" name="edit_item_name" value="<?= htmlspecialchars($item['item_name']) ?>" required style="padding: 0.5rem;">
        <textarea name="edit_description" required style="padding: 0.5rem; height: 80px; width: 100%;"><?= htmlspecialchars($item['description']) ?></textarea>
        <select name="edit_category" required style="padding: 0.5rem;">
          <option value="Burgers" <?= $item['category'] == 'Burgers' ? 'selected' : '' ?>>Burgers</option>
          <option value="Fries & Sides" <?= $item['category'] == 'Fries & Sides' ? 'selected' : '' ?>>Fries & Sides</option>
          <option value="Drinks" <?= $item['category'] == 'Drinks' ? 'selected' : '' ?>>Drinks</option>
          <option value="Desserts" <?= $item['category'] == 'Desserts' ? 'selected' : '' ?>>Desserts</option>
        </select>
        <input type="number" name="edit_price" value="<?= $item['price'] ?>" min="1" required style="padding: 0.5rem;">
        <div style="display: flex; gap: 0.5rem;">
          <button type="submit" name="update_item" style="background: var(--primary); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px;">Update</button>
          <button type="button" onclick="hideEditForm('<?= $item['item_id'] ?>')" style="background: #ccc; color: #333; padding: 0.5rem 1rem; border: none; border-radius: 4px;">Cancel</button>
        </div>
      </form>
    </div>
  </div>
  <?php endforeach; ?>

  <script>
    function showAddForm(id) {
      document.getElementById(id).style.display = 'block';
    }

    function hideAddForm(id) {
      document.getElementById(id).style.display = 'none';
    }

    function showEditForm(itemId) {
      // Hide all modals first
      document.querySelectorAll('.edit-modal').forEach(function(modal) { modal.style.display = 'none'; });
      document.querySelectorAll('.modal-overlay').forEach(function(overlay) { overlay.style.display = 'none'; });
      // Show the selected modal and overlay
      document.getElementById('edit-form-' + itemId).style.display = 'flex';
      document.getElementById('edit-modal-overlay-' + itemId).style.display = 'block';
    }
    function hideEditForm(itemId) {
      document.getElementById('edit-form-' + itemId).style.display = 'none';
      document.getElementById('edit-modal-overlay-' + itemId).style.display = 'none';
    }

    let ingredientIndex = 1;
    function addIngredientRow() {
      const list = document.getElementById('ingredients-list');
      const row = document.createElement('div');
      row.className = 'ingredient-row';
      row.innerHTML = `
        <select name="ingredients[${ingredientIndex}][product_id]" required>
          <option value="">Select Ingredient</option>
          <?php foreach ($all_products as $prod): ?>
            <option value="<?= $prod['Product_ID'] ?>"><?= htmlspecialchars($prod['Product_Name']) ?> (<?= $prod['Unit'] ?>)</option>
          <?php endforeach; ?>
        </select>
        <input type="number" name="ingredients[${ingredientIndex}][quantity]" min="1" placeholder="Qty" required style="width:80px;">
        <button type="button" onclick="this.parentNode.remove()">-</button>
      `;
      list.appendChild(row);
      ingredientIndex++;
    }
    function validateIngredients() {
      const ingredientRows = document.querySelectorAll('#ingredients-list .ingredient-row');
      if (ingredientRows.length === 0) {
        alert('Please add at least one ingredient (product name) for this menu item.');
        return false;
      }
      let valid = true;
      ingredientRows.forEach(row => {
        const select = row.querySelector('select');
        const qty = row.querySelector('input[type=number]');
        if (!select.value || !qty.value || qty.value < 1) valid = false;
      });
      if (!valid) {
        alert('Please fill out all ingredient fields correctly.');
        return false;
      }
      return true;
    }
  </script>

</div>

</body>
</html>