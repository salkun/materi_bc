<?php
session_start();
include '../koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header('Location: ../index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$messages = [];
$errors = [];

// Ambil data user saat ini
$stmt = $conn->prepare("SELECT username, password FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$resultUser = $stmt->get_result();
$currentUser = $resultUser->fetch_assoc();
$stmt->close();

if (!$currentUser) {
    // User tidak ditemukan, logout
    session_destroy();
    header("Location: ../index.php");
    exit;
}

// Handle Edit Profil
if (isset($_POST['update_profile'])) {
    $newUsername = trim($_POST['username']);
    $newPassword = trim($_POST['password']);
    $newNama  = trim($_POST['nama']);

    if (empty($newUsername) || empty($newPassword)) {
        $errors[] = "Username dan password tidak boleh kosong.";
    } else {
        // Cek username sudah dipakai user lain selain diri sendiri
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $newUsername, $userId);
        $stmt->execute();
        $resultCheck = $stmt->get_result();
        if ($resultCheck->num_rows > 0) {
            $errors[] = "Username sudah digunakan oleh pengguna lain.";
        } else {
            // Update username & password
            $stmtUpdate = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
            $stmtUpdate->bind_param("ssi", $newUsername, $newPassword, $userId);
            if ($stmtUpdate->execute()) {
                $messages[] = "Profil berhasil diperbarui.";
                // Update session username jika diperlukan
                $currentUser['username'] = $newUsername;
                $_SESSION['username'] = $newUsername;
            } else {
                $errors[] = "Gagal memperbarui profil.";
            }
            $stmtUpdate->close();
        }
        $stmt->close();
    }
}

// Handle tambah barang
if (isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $price = trim($_POST['price']);
    $imageName = null;

    if (empty($name) || empty($price) || !is_numeric($price)) {
        $errors[] = "Nama barang dan harga harus diisi dengan benar.";
    } else {
        // Proses upload gambar jika ada
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = $_FILES['image_file']['type'];
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "Tipe file gambar tidak diperbolehkan. Gunakan JPG, PNG, atau GIF.";
            } else {
                // Tentukan folder upload
                $uploadDir = '../images/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                // Buat nama file unik
                $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
                $imageName = uniqid('produk_', true) . '.' . $ext;
                $uploadFile = $uploadDir . $imageName;

                if (!move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadFile)) {
                    $errors[] = "Gagal meng-upload gambar.";
                }
            }
        }

        if (empty($errors)) {
            // Insert produk ke database
            $stmt = $conn->prepare("INSERT INTO products (name, price, image) VALUES (?, ?, ?)");
            $stmt->bind_param("sds", $name, $price, $imageName);
            if ($stmt->execute()) {
                $messages[] = "Barang baru berhasil ditambahkan.";
            } else {
                $errors[] = "Gagal menambahkan barang.";
            }
            $stmt->close();
        }
    }
}

// Tombol logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard User</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <a class="navbar-brand" href="../index.php">User Dashboard</a>
  <div class="ml-auto">
    <a href="?logout=1" class="btn btn-outline-light">Logout</a>
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

  <h3>Edit Profil</h3>
  <form method="POST" class="mb-5" style="max-width: 500px;">
    <div class="form-group">
      <label>Username</label>
      <input 
        type="text" 
        name="username" 
        class="form-control" 
        required
        value="<?=htmlspecialchars($currentUser['username'] ?? '')?>" />
    </div>
    <div class="form-group">
      <label>Password</label>
      <input 
        type="text" 
        name="password" 
        class="form-control" 
        required
        value="<?=htmlspecialchars($currentUser['password'] ?? '')?>" />
    </div>
    <div class="form-group">
      <label>Nama</label>
      <input 
        type="text" 
        name="nama" 
        class="form-control" 
        required
        value="<?=htmlspecialchars($currentUser['nama'] ?? '')?>" />
    </div>
    <button type="submit" name="update_profile" class="btn btn-primary">Simpan Perubahan</button>
  </form>

  <h3>Tambah Barang</h3>
  <form method="POST" class="mb-4" enctype="multipart/form-data">
    <div class="form-row">
        <div class="form-group col-md-4">
            <label>Nama Barang</label>
            <input type="text" name="name" class="form-control" required />
        </div>
        <div class="form-group col-md-2">
            <label>Harga</label>
            <input type="number" step="0.01" name="price" class="form-control" required />
        </div>
        <div class="form-group col-md-4">
            <label>Upload Gambar</label>
            <input type="file" name="image_file" class="form-control-file" required />
        </div>
        <div class="form-group col-md-2 d-flex align-items-end">
            <button type="submit" name="add_product" class="btn btn-primary btn-block">Tambah</button>
        </div>
    </div>
  </form>

</div>
</body>
</html>
