<?php
session_start();
include '../koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// Initialize variables for messages and errors
$editUser = null;
$editProduct = null;
$messages = [];
$errors = [];

// Handle User CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add or update User
    if (isset($_POST['user_submit'])) {
        $userId = $_POST['user_id'] ?? null;
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role = $_POST['role'];
        $nama = trim($_POST['nama']);

        if (empty($username) || empty($password) || empty($role) || empty($nama)) {
            $errors[] = "Semua kolom user harus diisi!";
        } else {
            if ($userId) {
                // Update user
                $stmt = $conn->prepare("UPDATE users SET username=?, password=?, role=?, nama=? WHERE id=?");
                $stmt->bind_param("ssssi", $username, $password, $role, $nama, $userId);
                if ($stmt->execute()) {
                    $messages[] = "User berhasil diupdate.";
                } else {
                    $errors[] = "Gagal mengupdate user.";
                }
                $stmt->close();
            } else {
                // Check if username exists
                $resultCheck = $conn->query("SELECT id FROM users WHERE username = '".$conn->real_escape_string($username)."'");
                if ($resultCheck->num_rows > 0) {
                    $errors[] = "Username sudah digunakan!";
                } else {
                    // Insert new user
                    $stmt = $conn->prepare("INSERT INTO users (username, password, role, nama) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $username, $password, $role, $nama);
                    if ($stmt->execute()) {
                        $messages[] = "User berhasil ditambahkan.";
                    } else {
                        $errors[] = "Gagal menambahkan user.";
                    }
                    $stmt->close();
                }
            }
        }
    }
    // Add or update Product
    if (isset($_POST['product_submit'])) {
        $productId = $_POST['product_id'] ?? null;
        $productName = trim($_POST['name']);
        $productPrice = trim($_POST['price']);
        $errorsUpload = [];

        // Basic validation
        if (empty($productName) || empty($productPrice) || !is_numeric($productPrice)) {
            $errors[] = "Semua kolom barang harus diisi dengan benar!";
        } else {
            // Process image upload if file provided
            $imageName = null;
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $fileType = $_FILES['image_file']['type'];
                if (!in_array($fileType, $allowedTypes)) {
                    $errorsUpload[] = "Tipe file gambar tidak diperbolehkan. Gunakan JPG, PNG, atau GIF.";
                } else {
                    // Upload directory
                    $uploadDir = '../images/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    // Unique file name
                    $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
                    $imageName = uniqid('produk_', true) . '.' . $ext;
                    $uploadFile = $uploadDir . $imageName;

                    if (!move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadFile)) {
                        $errorsUpload[] = "Gagal meng-upload gambar.";
                    }
                }
            }

            if (count($errorsUpload) > 0) {
                $errors = array_merge($errors, $errorsUpload);
            } else {
                if ($productId) {
                    // Get old image to delete if replaced
                    $oldImage = null;
                    $resOld = $conn->query("SELECT image FROM products WHERE id = ".(int)$productId);
                    if ($resOld && $resOld->num_rows) {
                        $oldData = $resOld->fetch_assoc();
                        $oldImage = $oldData['image'];
                    }

                    // If no new image uploaded, keep old image
                    if (!$imageName) {
                        $imageName = $oldImage;
                    } else {
                        // Delete old image if different
                        if ($oldImage && $oldImage !== $imageName && file_exists($uploadDir . $oldImage)) {
                            unlink($uploadDir . $oldImage);
                        }
                    }

                    // Update product record
                    $stmt = $conn->prepare("UPDATE products SET name=?, price=?, image=? WHERE id=?");
                    $stmt->bind_param("sdsi", $productName, $productPrice, $imageName, $productId);
                    if ($stmt->execute()) {
                        $messages[] = "Barang berhasil diupdate.";
                    } else {
                        $errors[] = "Gagal mengupdate barang.";
                    }
                    $stmt->close();
                } else {
                    // Insert new product
                    $stmt = $conn->prepare("INSERT INTO products (name, price, image) VALUES (?, ?, ?)");
                    $stmt->bind_param("sds", $productName, $productPrice, $imageName);
                    if ($stmt->execute()) {
                        $messages[] = "Barang berhasil ditambahkan.";
                    } else {
                        $errors[] = "Gagal menambahkan barang.";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Handle Delete user
if (isset($_GET['delete_user'])) {
    $deleteUserId = (int) $_GET['delete_user'];
    $conn->query("DELETE FROM users WHERE id=$deleteUserId");
    header("Location: dashboard.php");
    exit;
}

// Handle Delete product
if (isset($_GET['delete_product'])) {
    $deleteProductId = (int) $_GET['delete_product'];
    // Before deleting product, attempt to delete image file if exists
    $res = $conn->query("SELECT image FROM products WHERE id=$deleteProductId");
    if ($res && $res->num_rows > 0) {
        $data = $res->fetch_assoc();
        $imageFile = $data['image'];
        $imagePath = '../images/' . $imageFile;
        if ($imageFile && file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
    $conn->query("DELETE FROM products WHERE id=$deleteProductId");
    header("Location: dashboard.php");
    exit;
}

// Handle Edit user prefilling
if (isset($_GET['edit_user'])) {
    $editUserId = (int) $_GET['edit_user'];
    $resultUser = $conn->query("SELECT * FROM users WHERE id=$editUserId");
    $editUser = $resultUser->fetch_assoc();
}

// Handle Edit product prefilling
if (isset($_GET['edit_product'])) {
    $editProductId = (int) $_GET['edit_product'];
    $resultProduct = $conn->query("SELECT * FROM products WHERE id=$editProductId");
    $editProduct = $resultProduct->fetch_assoc();
}

// Fetch all users and products
$resultUsers = $conn->query("SELECT * FROM users");
$resultProducts = $conn->query("SELECT * FROM products");
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Dashboard Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
.product-image {
    height: 100px;
    width: auto;
    object-fit: cover;
}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <a class="navbar-brand" href="../index.php">Toko Sederhana</a>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item active"><a href="#userSection" class="nav-link">User Management</a></li>
            <li class="nav-item"><a href="#productSection" class="nav-link">Barang Management</a></li>
        </ul>
        <form class="form-inline my-2 my-lg-0">
            <a href="?logout=1" class="btn btn-outline-light">Logout</a>
        </form>
    </div>
</nav>
<div class="container mt-4">

<?php if ($messages): ?>
    <div class="alert alert-success">
        <?php foreach ($messages as $msg) echo htmlspecialchars($msg) . "<br>"; ?>
    </div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $err) echo htmlspecialchars($err) . "<br>"; ?>
    </div>
<?php endif; ?>

<h3 id="userSection">Manajemen User</h3>
<form method="POST" class="mb-4">
    <input type="hidden" name="user_id" value="<?= htmlspecialchars($editUser['id'] ?? '') ?>">
    <div class="form-row">
        <div class="form-group col-md-3">
            <label>Nama</label>
            <input type="text" name="nama" class="form-control" required value="<?= htmlspecialchars($editUser['nama'] ?? '') ?>">
        </div>
        <div class="form-group col-md-3">
            <label>Username</label>
            <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($editUser['username'] ?? '') ?>">
        </div>
        <div class="form-group col-md-3">
            <label>Password</label>
            <input type="text" name="password" class="form-control" required value="<?= htmlspecialchars($editUser['password'] ?? '') ?>">
        </div>
        <div class="form-group col-md-2">
            <label>Role</label>
            <select name="role" class="form-control" required>
                <option value="user" <?= (isset($editUser['role']) && $editUser['role']=='user') ? 'selected' : '' ?>>User</option>
                <option value="admin" <?= (isset($editUser['role']) && $editUser['role']=='admin') ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>
        <div class="form-group col-md-1 d-flex align-items-end">
            <button type="submit" name="user_submit" class="btn btn-primary btn-block"><?= $editUser ? 'Update' : 'Tambah' ?></button>
        </div>
    </div>
</form>

<table class="table table-bordered table-hover">
<thead class="thead-light">
    <tr>
        <th>ID</th><th>Nama</th><th>Username</th><th>Role</th><th>Aksi</th>
    </tr>
</thead>
<tbody>
    <?php while ($user = $resultUsers->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($user['id']) ?></td>
        <td><?= htmlspecialchars($user['nama']) ?></td>
        <td><?= htmlspecialchars($user['username']) ?></td>
        <td><?= htmlspecialchars($user['role']) ?></td>
        <td>
            <a href="?edit_user=<?= $user['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
            <a href="?delete_user=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus user ini?')">Hapus</a>
        </td>
    </tr>
    <?php endwhile; ?>
</tbody>
</table>

<h3 id="productSection" class="mt-5">Manajemen Barang</h3>
<form method="POST" class="mb-4" enctype="multipart/form-data">
    <input type="hidden" name="product_id" value="<?= htmlspecialchars($editProduct['id'] ?? '') ?>">
    <div class="form-row">
        <div class="form-group col-md-4">
            <label>Nama Barang</label>
            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>">
        </div>
        <div class="form-group col-md-2">
            <label>Harga</label>
            <input type="number" step="0.01" name="price" class="form-control" required value="<?= htmlspecialchars($editProduct['price'] ?? '') ?>">
        </div>
        <div class="form-group col-md-4">
            <label>Upload Gambar</label>
            <input type="file" name="image_file" class="form-control-file" <?= $editProduct ? '' : 'required' ?>>
            <?php if (!empty($editProduct['image']) && file_exists('../images/' . $editProduct['image'])): ?>
                <small>Gambar saat ini: <?= htmlspecialchars($editProduct['image']) ?></small><br>
                <img src="../images/<?= htmlspecialchars($editProduct['image']) ?>" alt="Gambar Produk" style="height:80px; object-fit:cover; margin-top:5px;">
            <?php endif; ?>
        </div>
        <div class="form-group col-md-2 d-flex align-items-end">
            <button type="submit" name="product_submit" class="btn btn-primary btn-block"><?= $editProduct ? 'Update' : 'Tambah' ?></button>
        </div>
    </div>
</form>

<table class="table table-bordered table-hover">
<thead class="thead-light">
    <tr>
        <th>ID</th><th>Nama Barang</th><th>Harga</th><th>Gambar</th><th>Aksi</th>
    </tr>
</thead>
<tbody>
    <?php while ($product = $resultProducts->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($product['id']) ?></td>
        <td><?= htmlspecialchars($product['name']) ?></td>
        <td>Rp <?= number_format($product['price'], 2, ',', '.') ?></td>
        <td>
            <?php if (!empty($product['image']) && file_exists('../images/' . $product['image'])): ?>
                <img src="../images/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="height:50px; object-fit:cover;">
            <?php else: ?>
                <span>Tidak ada</span>
            <?php endif; ?>
        </td>
        <td>
            <a href="?edit_product=<?= $product['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
            <a href="?delete_product=<?= $product['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus barang ini?')">Hapus</a>
        </td>
    </tr>
    <?php endwhile; ?>
</tbody>
</table>
</div>
</body>
</html>
