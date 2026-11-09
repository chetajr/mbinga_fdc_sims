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

// Kuzuia ufikiaji usioruhusiwa - Mratibu tu ndiye anayeruhusiwa
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'mratibu') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kusafisha na kupokea data kutoka kwenye fomu
    $course_name = trim($_POST['course_name'] ?? '');
    $course_code = trim($_POST['course_code'] ?? '');
    $course_fee  = floatval($_POST['course_fee'] ?? 0);

    // Uhakiki wa mwanzo wa usalama
    if (empty($course_name) || empty($course_code) || $course_fee <= 0) {
        echo "<script>alert('Tafadhali jaza uga zote kwa usahihi ikiwemo kiasi cha ada!'); window.location.href='mratibu_dashboard.php';</script>";
        exit();
    }

    try {
        // Kuingiza kozi mpya pamoja na kiwango chake maalum cha ada
        $stmt = $pdo->prepare("INSERT INTO courses (course_name, course_code, course_fee) VALUES (?, ?, ?)");
        $stmt->execute([$course_name, $course_code, $course_fee]);
        
        echo "<script>alert('✅ Fani mpya imesajiliwa na ada yake imehifadhiwa kikamilifu!'); window.location.href='mratibu_dashboard.php';</script>";
        exit();

    } catch (\PDOException $e) {
        // Ikitokea kodi ya kozi inafanana na iliyopo
        if ($e->getCode() == 23000) {
            echo "<script>alert('⚠️ Kosa: Kodi ya fani hii (Course Code) tayari ipo kwenye mfumo!'); window.location.href='mratibu_dashboard.php';</script>";
        } else {
            die("Kosa la kiufundi: " . $e->getMessage());
        }
        exit();
    }
} else {
    header("Location: mratibu_dashboard.php");
    exit();
}