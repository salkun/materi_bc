<?php
session_start();
include 'koneksi.php';

// Ambil semua produk
$sql = "SELECT * FROM products";
$resultProducts = $conn->query($sql);

// Cek apakah pengguna sudah login
$loggedIn = isset($_SESSION['user_id']);
$role = $loggedIn ? $_SESSION['role'] : '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Daftar Barang</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
.product-image {
    height: 180px;
    object-fit: cover;
}
</style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <a class="navbar-brand" href="#">Toko Sederhana</a>
    <div class="ml-auto">
        <?php if ($loggedIn): ?>
            <?php if ($role === 'admin'): ?>
                <a href="admin/dashboard.php" class="btn btn-outline-light">Dashboard Admin</a>
            <?php elseif ($role === 'user'): ?>
                <a href="user/dashboard.php" class="btn btn-outline-light">Dashboard User</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline-light">Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-outline-light">Login</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container mt-4">
    <h2 class="mb-4">Daftar Barang</h2>
    <?php if ($resultProducts && $resultProducts->num_rows > 0): ?>
        <div class="row">
            <?php while ($product = $resultProducts->fetch_assoc()): ?>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 shadow-sm">
                        <?php if (!empty($product['image']) && file_exists('images/' . $product['image'])): ?>
                            <img src="images/<?=htmlspecialchars($product['image'])?>" alt="<?=htmlspecialchars($product['name'])?>" class="card-img-top product-image">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/300x180?text=No+Image" alt="No Image" class="card-img-top product-image">
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?=htmlspecialchars($product['name'])?></h5>
                            <p class="card-text mb-4">Harga: Rp <?=number_format($product['price'], 2, ',', '.')?></p>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>Tidak ada barang tersedia.</p>
    <?php endif; ?>
</div>

</body>
</html>
