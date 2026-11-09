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

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_id      = intval($_POST['subject_id']);
    $attendance_date = $_POST['attendance_date'];
    $statuses        = $_POST['status']; // Array ya status [student_id => status]
    $academic_years  = $_POST['academic_year']; // Array ya miaka [student_id => year]
    $recorded_by     = $_SESSION['user_id'];

    if (!$subject_id || empty($attendance_date) || empty($statuses)) {
        header("Location: mark_attendance.php?error=1");
        exit();
    }

    try {
        $pdo->beginTransaction();

        foreach ($statuses as $student_id => $status) {
            $student_id = intval($student_id);
            $year       = intval($academic_years[$student_id]);

            // Futa mahudhurio ya mwanafunzi huyu ya tarehe hii na somo hili kama yalikuwepo (ili kuzuia duplicate na kuruhusu edit)
            $delete_stmt = $pdo->prepare("DELETE FROM student_attendance WHERE student_id = ? AND subject_id = ? AND attendance_date = ?");
            $delete_stmt->execute([$student_id, $subject_id, $attendance_date]);

            // Ingiza upya mahudhurio ya sasa
            $insert_stmt = $pdo->prepare("
                INSERT INTO student_attendance (student_id, subject_id, attendance_date, status, academic_year, recorded_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert_stmt->execute([$student_id, $subject_id, $attendance_date, $status, $year, $recorded_by]);
        }

        $pdo->commit();
        header("Location: mark_attendance.php?success=1");
        exit();

    } catch (\PDOException $e) {
        $pdo->rollBack();
        die("Kosa la kihifadhi: " . $e->getMessage());
    }
} else {
    header("Location: mark_attendance");
    exit();
}