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
// 1. ULINZI: Hakikisha aliyefungua ukurasa huu ni Mratibu kweli
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'mratibu') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

// 2. Kuvuta muunganisho wa Database
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_id    = intval($_POST['course_id']);
    $subject_name = strtoupper(trim($_POST['subject_name'])); // Masomo yawe herufi kubwa kama kwenye karatasi ya matokeo
    $subject_type = trim($_POST['subject_type']); // core_theory, core_practical, au support

    if (empty($course_id) || empty($subject_name) || empty($subject_type)) {
        echo "<script>alert('Tafadhali jaza nafasi zote!'); window.location.href='mratibu_dashboard.php';</script>";
        exit();
    }

    try {
        // 3. Kuangalia kama somo hili tayari limeshasajiliwa kwenye fani hii kuzuia urudufishaji
        $checkStmt = $pdo->prepare("SELECT id FROM subjects WHERE course_id = :course_id AND subject_name = :subject_name LIMIT 1");
        $checkStmt->execute([
            'course_id'    => $course_id,
            'subject_name' => $subject_name
        ]);

        if ($checkStmt->rowCount() > 0) {
            echo "<script>alert('⚠️ Onyo: Somo hili tayari lipo kwenye fani hii!'); window.location.href='mratibu_dashboard.php';</script>";
            exit();
        }

        // 4. Kuhifadhi somo jipya
        $sql = "INSERT INTO subjects (course_id, subject_name, subject_type) VALUES (:course_id, :subject_name, :subject_type)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'course_id'    => $course_id,
            'subject_name' => $subject_name,
            'subject_type' => $subject_type
        ]);

        echo "<script>alert('✅ Somo la [ $subject_name ] limeunganishwa kwenye fani kikamilifu!'); window.location.href='mratibu_dashboard.php';</script>";
        exit();

    } catch (\PDOException $e) {
        die("Kuna tatizo limejitokeza: " . $e->getMessage());
    }
} else {
    header("Location: mratibu_dashboard.php");
    exit();
}