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
// 1. ULINZI: Hakikisha aliyefungua faili hili amesajiliwa na ni IT kweli
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'it') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

// 2. Kuvuta muunganisho wa Database kutoka folda la config
// Kumbuka tupo ndani ya folda la 'dashboards', ndio maana tunarudi nyuma kwa ../
require_once '../config/db.php';

// 3. Kuhakikisha data zimetumwa kwa njia ya POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Kupokea na kusafisha data
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role     = trim($_POST['role']);
    
    // Uhakiki wa haraka (Validation)
    if (empty($fullname) || empty($username) || empty($password) || empty($role)) {
        echo "<script>alert('Tafadhali jaza nafasi zote!'); window.location.href='it_dashboard.php';</script>";
        exit();
    }
    
    try {
        // 4. Kuangalia kama Email/Username hiyo tayari imetumiwa na mfanyakazi mwingine
        $checkEmail = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
        $checkEmail->execute(['username' => $username]);
        
        if ($checkEmail->rowCount() > 0) {
            // Kama ipo, mfumo unakataa na kumrudisha IT kwenye dashboard
            echo "<script>alert('⚠️ Kosa: Baruapepe hii tayari imeshasajiliwa kwa mtumiaji mwingine!'); window.location.href='it_dashboard.php';</script>";
            exit();
        }
        
        // 5. Kuficha neno la siri (Password Hashing) kuelekea 2050
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // 6. Kuandaa query ya kuingiza data (Insert Statements)
        $sql = "INSERT INTO users (username, password, fullname, role) VALUES (:username, :password, :fullname, :role)";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            'username' => $username,
            'password' => $hashed_password,
            'fullname' => $fullname,
            'role'     => $role
        ]);
        
        // 7. Kumpa IT ujumbe wa mafanikio na kumrudisha kwenye dashboard
        echo "<script>alert('🚀 Ukamilisho: Akaunti ya [ " . htmlspecialchars($fullname) . " ] imetengenezwa kikamilifu!'); window.location.href='it_dashboard.php';</script>";
        exit();
        
    } catch (\PDOException $e) {
        // Ikitokea hitilafu yoyote ya mfumo
        die("Kuna tatizo limejitokeza wakati wa kuhifadhi: " . $e->getMessage());
    }
    
} else {
    // Kama mtu akijaribu kuingia kwenye faili hili bila kujaza fomu
    header("Location: it_dashboard.php");
    exit();
}