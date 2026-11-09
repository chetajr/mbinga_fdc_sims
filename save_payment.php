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

// 1. ULINZI: Hakikisha ni mhasibu tu anayeingiza data hapa
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'accountant') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id     = intval($_POST['student_id']);
    $receipt_number = strtoupper(trim($_POST['receipt_number']));
    $amount_paid    = floatval($_POST['amount_paid']);
    $payment_date   = $_POST['payment_date']; 
    
    // Mhasibu aliyelogin kwa sasa (kutoka kwenye session) kwa ajili ya nguzo ya recorded_by
    $recorded_by    = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

    // Uhakiki wa mwanzo wa data
    if (empty($student_id) || empty($receipt_number) || $amount_paid <= 0 || empty($payment_date)) {
        header("Location: accountant_dashboard.php?error=invalid_inputs");
        exit();
    }

    try {
        // HATUA YA KWANZA: Vuta ada halisi ya kozi na mwaka wa masomo wa mwanafunzi huyu kutoka kwa mratibu
        $student_stmt = $pdo->prepare("
            SELECT s.academic_year, c.course_fee 
            FROM students s
            JOIN courses c ON s.course_id = c.id
            WHERE s.id = ?
        ");
        $student_stmt->execute([$student_id]);
        $student_data = $student_stmt->fetch();

        if (!$student_data) {
            header("Location: accountant_dashboard.php?error=student_not_found");
            exit();
        }

        $course_fee    = floatval($student_data['course_fee']);
        $academic_year = $student_data['academic_year']; // Inavuta kiotomatiki (Mfan: 2026)

        // HATUA YA PILI: Piga hesabu ya jumla aliyokwisha lipa huko nyuma
        $paid_stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM fees_payments WHERE student_id = ?");
        $paid_stmt->execute([$student_id]);
        $already_paid = floatval($paid_stmt->fetchColumn());

        // HATUA YA TATU: Kokotoa deni lililobaki
        $remaining_balance = $course_fee - $already_paid;

        // HATUA YA NNE: KUZUIA OVER-PAYMENT
        if ($amount_paid > $remaining_balance) {
            header("Location: accountant_dashboard.php?error=overpayment&balance=" . $remaining_balance);
            exit();
        }

        // HATUA YA SITA: Hakikisha risiti haijajirudia (Unique Receipt Control)
        $receipt_stmt = $pdo->prepare("SELECT COUNT(*) FROM fees_payments WHERE receipt_number = ?");
        $receipt_stmt->execute([$receipt_number]);
        if ($receipt_stmt->fetchColumn() > 0) {
            header("Location: accountant_dashboard.php?error=duplicate_receipt");
            exit();
        }

        // HATUA YA SABA: SAJILI MALIPO (Inajaza zote kulingana na picha ya phpMyAdmin)
        // Tumeongeza 'recorded_by' na 'academic_year' zote zikae sawa
        $insert_stmt = $pdo->prepare("
            INSERT INTO fees_payments (student_id, amount_paid, receipt_number, payment_date, academic_year, recorded_by) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert_stmt->execute([$student_id, $amount_paid, $receipt_number, $payment_date, $academic_year, $recorded_by]);

        // Imekamilika kikamilifu!
        header("Location: accountant_dashboard.php?success=1");
        exit();

    } catch (\PDOException $e) {
        // Ikitokea hitilafu yoyote ya ki-database, tunaweza ku-debug kwa kuona ujumbe halisi ukitaka
        // die("Database Error: " . $e->getMessage()); // Ondoa mabano mbele ya 'die' ukitaka kuona kosa nini hasa darasani
        header("Location: accountant_dashboard.php?error=db_error");
        exit();
    }
} else {
    header("Location: accountant_dashboard.php");
    exit();
}