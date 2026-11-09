<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (empty($new_pass) || empty($confirm_pass)) {
        $error = "❌ Tafadhali jaza kote!";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "❌ Password mpya hazifanani!";
    } elseif ($new_pass === 'MbingaFDC@2026') { 
        $error = "❌ Huwezi kutumia password ile ile ya dharura ya IT! Weka ya kwako binafsi.";
    } else {
        try {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            
            // Tunabadilisha password KUWA MPYA na kurudisha requires_reset kuwa 0 (Kufungua akaunti)
            $stmt = $pdo->prepare("UPDATE users SET password = ?, requires_reset = 0 WHERE id = ?");
            $stmt->execute([$hashed, $_SESSION['user_id']]);
            
            // Kumsafisha na kumtoa nje ili alogin upya kwa usalama uliokamilika
            session_destroy();
            echo "<script>alert('✅ Umefanikiwa kuweka password yako binafsi! Tafadhali login sasa kwa kutumia password hiyo mpya.'); window.location.href='../login.php';</script>";
            exit();
        } catch (\PDOException $e) {
            $error = "Kosa la mfumo: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <title>Usalama: Badilisha Password Kwanza</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        body { background: #0c0e14; color: #fff; font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .lock-box { background: rgba(255,255,255,0.02); border: 2px solid #ffaa00; padding: 40px; border-radius: 12px; width: 100%; max-width: 400px; text-align: center; box-shadow: 0 0 30px rgba(255,170,0,0.1); }
        h2 { font-size: 20px; color: #ffaa00; margin-bottom: 10px; }
        p { font-size: 13px; color: #aaa; margin-bottom: 25px; line-height: 1.5; }
        .group { display: flex; flex-direction: column; text-align: left; gap: 8px; margin-bottom: 15px; }
        label { font-size: 11px; color: #8a8f98; font-weight: bold; }
        input { background: #000; border: 1px solid #333; padding: 12px; border-radius: 6px; color: #fff; outline: none; }
        input:focus { border-color: #ffaa00; }
        button { background: #ffaa00; color: #000; border: none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; text-transform: uppercase; margin-top: 10px; }
    </style>
</head>
<body>

<div class="lock-box">
    <h2>⚠️ Hatua ya Kiusalama Inahitajika</h2>
    <p>Password ya akaunti hii imewekwa upya na Afisa IT. Ili kulinda usiri wa data zako, lazima utengeneze password mpya ya kwako binafsi kabla ya kuendelea.</p>

    <?php if($error): ?>
        <div style="background: rgba(255,75,75,0.1); border: 1px solid #ff4b4b; color: #ff4b4b; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 20px; font-weight: bold;"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="group">
            <label>WEKA PASSWORD MPYA YA KWAKO</label>
            <input type="password" name="new_password" placeholder="Password yako mpya ya siri" required>
        </div>
        <div class="group">
            <label>REGELEZA PASSWORD MPYA</label>
            <input type="password" name="confirm_password" placeholder="Rudia password hiyo mpya" required>
        </div>
        <button type="submit">HIFADHI NA UFUNGUE AKAUNTI 🔓</button>
    </form>
</div>

</body>
</html>