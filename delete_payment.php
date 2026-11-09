<?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'accountant') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

require_once '../config/db.php';

$payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($payment_id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM fees_payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        
        echo "<script>alert('✅ Malipo yamebatilishwa na kufutwa kikamilifu!'); window.location.href='accountant_dashboard.php';</script>";
        exit();
    } catch (\PDOException $e) {
        die("Kosa la kiufundi: " . $e->getMessage());
    }
} else {
    header("Location: accountant_dashboard");
    exit();
}