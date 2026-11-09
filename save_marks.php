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

// 1. ULINZI: Hakikisha aliyefungua ukurasa huu ni Mwalimu kweli
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

// 2. Kuvuta muunganisho wa Database
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = intval($_POST['subject_id']);
    $exam_month = intval($_POST['exam_month']);
    $academic_year = '2026'; // Mwaka wa masomo wa sasa
    
    // Kupokea array ya alama: [student_id => score]
    $marks_array = isset($_POST['marks']) ? $_POST['marks'] : [];

    if (!$subject_id || !$exam_month || empty($marks_array)) {
        echo "<script>alert('⚠️ Kosa: Hakuna data za alama zilizopokelewa!'); window.location.href='teacher_dashboard.php';</script>";
        exit();
    }

    try {
        // 3. Query iliyorekebishwa kusoma nguzo ya 'marks' na 'academic_year' zilizopo kwenye phpMyAdmin yako
        $sql = "INSERT INTO exam_marks (student_id, subject_id, exam_month, marks, academic_year) 
                VALUES (:student_id, :subject_id, :exam_month, :marks, :academic_year)
                ON DUPLICATE KEY UPDATE marks = :marks_update";
        
        $stmt = $pdo->prepare($sql);

        // Kuanza mzunguko wa kupitia alama za kila mwanafunzi
        foreach ($marks_array as $student_id => $score) {
            if ($score === '') continue; // Kama sanduku liliachwa wazi, ruka

            $score_value = intval($score);

            // Uhakiki wa ziada wa kiusalama
            if ($score_value < 0 || $score_value > 100) {
                echo "<script>alert('⚠️ Kosa: Alama lazima ziwe kati ya 0 na 100!'); window.location.href='teacher_dashboard.php';</script>";
                exit();
            }

            // Execute query kwa kutumia majina sahihi ya nguzo zako
            $stmt->execute([
                'student_id'    => intval($student_id),
                'subject_id'    => $subject_id,
                'exam_month'    => $exam_month,
                'marks'         => $score_value,
                'academic_year' => $academic_year,
                'marks_update'  => $score_value
            ]);
        }

        // 4. Ujumbe wa ushindi
        echo "<script>alert('🎉 Alama zote zimehifadhiwa na kusasishwa kikamilifu kwenye meza ya exam_marks!'); window.location.href='teacher_dashboard.php';</script>";
        exit();

    } catch (\PDOException $e) {
        die("Kuna tatizo limejitokeza wakati wa kuhifadhi alama: " . $e->getMessage());
    }
} else {
    header("Location: teacher_dashboard.php");
    exit();
}