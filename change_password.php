<?php
session_start();
// 1. WEKA MUDA WA MAXIMUM TIMEOUT (Dakika 10 = Sekunde 600)
$timeout_duration = 600; 

// 2. Angalia kama kuna kumbukumbu ya muda wa mwisho mtumiaji alivyokuwa active
if (isset($_SESSION['last_activity'])) {
    // Piga hesabu ya sekunde zilizopita tangu mara ya mwisho kuwa active
    $elapsed_time = time() - $_SESSION['last_activity'];
    
    // Kama muda uliopita ni mkubwa kuliko sekunde 600 (Dakika 10)
    if ($elapsed_time > $timeout_duration) {
        // Safisha session na mtoe nje ya mfumo (Logout)
        session_unset();
        session_destroy();
        header("Location: ../login.php?error=session_expired");
        exit();
    }
}

// 3. Kila akifungua au ku-refresh ukurasa wowote, sasisha muda wa sasa kama "Last Activity"
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';

$msg = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_pass = $_POST['current_password'];
    $new_pass     = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass !== $confirm_pass) {
        $error = "❌ Password mpya hazifanani!";
    } else {
        try {
            // Kuvuta password ya sasa kutoka database
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            // Kuhakiki kama password ya sasa iko sawa
            if ($user && password_verify($current_pass, $user['password'])) {
                // Kustore password mpya kwa usalama (Hashing)
                $hashed_password = password_hash($new_pass, PASSWORD_BCRYPT);
                
                $update_stmt = $pdo->prepare("UPDATE users TYPE_UNSPECIFIED SET password = ? WHERE id = ?");
                $update_stmt->execute([$hashed_password, $_SESSION['user_id']]);
                
                $msg = "✅ Password imebadilishwa kikamilifu!";
            } else {
                $error = "❌ Password ya sasa sio sahihi!";
            }
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
    <title>Badilisha Password - FDC SIMS</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        body { background: #0a0b10; color: #fff; font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .box { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 30px; border-radius: 12px; width: 100%; max-width: 400px; backdrop-filter: blur(10px); }
        h2 { font-size: 20px; margin-bottom: 20px; text-align: center; color: #00f3ff; }
        .group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px; }
        label { font-size: 12px; color: #8a8f98; }
        input { background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08); padding: 12px; border-radius: 6px; color: #fff; outline: none; }
        button { background: linear-gradient(135deg, #00f3ff, #b026ff); color: #fff; border: none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 10px; width: 100%; }
        .alert { padding: 10px; border-radius: 6px; text-align: center; font-size: 13px; margin-bottom: 15px; font-weight: bold; }
        .btn-back { display: block; text-align: center; margin-top: 15px; color: #8a8f98; text-decoration: none; font-size: 13px; }
    </style>
</head>
<body>

<div class="box">
    <h2>🔑 Badilisha Password</h2>
    
    <?php if($msg): ?> <div class="alert" style="background: rgba(0,255,102,0.1); border: 1px solid #00ff66; color: #00ff66;"><?php echo $msg; ?></div> <?php endif; ?>
    <?php if($error): ?> <div class="alert" style="background: rgba(255,75,75,0.1); border: 1px solid #ff4b4b; color: #ff4b4b;"><?php echo $error; ?></div> <?php endif; ?>

    <form method="POST">
        <div class="group">
            <label>PASSWORD YA SASA</label>
            <input type="password" name="current_password" required>
        </div>
        <div class="group">
            <label>PASSWORD MPYA</label>
            <input type="password" name="new_password" required>
        </div>
        <div class="group">
            <label>REGELEZA PASSWORD MPYA</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit">UPDATE PASSWORD 🔐</button>
    </form>
    
    <a href="javascript:history.back()" class="btn-back">⬅️ Ghairi / Rudi Nyuma</a>
</div>

</body>
</html>