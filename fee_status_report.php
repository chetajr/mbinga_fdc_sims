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

// Ulinzi wa mfumo - Mhasibu tu ndiye anaruhusiwa kuona ripoti hii
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'accountant') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

require_once '../config/db.php';

// Kupokea chujio la ada (all, completed, partial, unpaid)
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

try {
    // QUERY MAPINDUZI: Inavuta wanafunzi, kozi zao, ada ya kozi husika, na jumla ya kiasi walicholipa hadi sasa
    $sql = "SELECT s.id, s.reg_number, s.first_name, s.middle_name, s.last_name, c.course_code, s.current_level, c.course_fee,
            COALESCE(SUM(p.amount_paid), 0) AS total_paid
            FROM students s
            JOIN courses c ON s.course_id = c.id
            LEFT JOIN fees_payments p ON s.id = p.student_id
            GROUP BY s.id, s.reg_number, s.first_name, s.middle_name, s.last_name, c.course_code, s.current_level, c.course_fee";
            
    $stmt = $pdo->query($sql);
    $all_students = $stmt->fetchAll();

    // Mchakato wa kuchuja wanafunzi kulingana na hali ya ada zao dynamically
    $filtered_students = [];

    foreach ($all_students as $student) {
        $paid = floatval($student['total_paid']);
        $actual_fee = floatval($student['course_fee']); // Inasoma ada kutoka database ya kozi yake
        $balance = $actual_fee - $paid; // Kukokotoa deni linalobaki

        if ($status_filter === 'completed' && $paid >= $actual_fee) {
            $student['balance'] = 0;
            $filtered_students[] = $student;
        } elseif ($status_filter === 'partial' && $paid > 0 && $paid < $actual_fee) {
            $student['balance'] = $balance;
            $filtered_students[] = $student;
        } elseif ($status_filter === 'unpaid' && $paid == 0) {
            $student['balance'] = $actual_fee;
            $filtered_students[] = $student;
        } elseif ($status_filter === 'all') {
            $student['balance'] = $balance;
            $filtered_students[] = $student;
        }
    }

} catch (\PDOException $e) {
    die("Kosa la kiufundi la mfumo: " . $e->getMessage());
}

// Kuandaa vichwa vya ripoti kwa ajili ya uchapishaji
$report_title = "Orodha Kamili ya Hali ya Ada ya Wanafunzi";
if ($status_filter === 'completed') $report_title = "Orodha ya Wanafunzi Waliomaliza Ada ✅";
if ($status_filter === 'partial') $report_title = "Orodha ya Wanafunzi Wanaodaiwa (Walio lipa nusu) ⚠️";
if ($status_filter === 'unpaid') $report_title = "Orodha ya Wanafunzi Ambao Hawajalipa Kabisa ❌";
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <title>Ripoti ya Ada - Mbinga FDC</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { padding: 30px; background: #f4f6f9; color: #333; }
        
        .no-print { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .btn { padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; cursor: pointer; border: none; font-size: 14px; }
        .btn-back { background: #6c757d; color: white; }
        .btn-print { background: #28a745; color: white; box-shadow: 0 4px 10px rgba(40,167,69,0.3); }

        .sheet-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .header-block { text-align: center; margin-bottom: 30px; border-bottom: 3px double #333; padding-bottom: 20px; }
        .header-block h1 { font-size: 24px; text-transform: uppercase; }
        .header-block h2 { font-size: 16px; color: #555; margin-top: 5px; text-transform: uppercase; }
        
        .report-heading { font-size: 16px; font-weight: bold; margin-bottom: 20px; color: #0056b3; text-transform: uppercase; border-left: 5px solid #0056b3; padding-left: 10px; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th, td { border: 1px solid #999; padding: 10px 8px; text-align: left; }
        th { background: #f8f9fa; font-weight: bold; text-transform: uppercase; }
        .center-text { text-align: center; }
        .right-text { text-align: right; font-weight: bold; }

        @media print {
            body { padding: 0; background: white; }
            .no-print { display: none; }
            .sheet-container { box-shadow: none; padding: 0; }
            th { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <a href="accountant_dashboard" class="btn btn-back">⬅️ Rudi Kwenye Dashboard</a>
        <button onclick="window.print()" class="btn btn-print">🖨️ CHAPA RIPOTI HII (PRINT)</button>
    </div>

    <div class="sheet-container">
        <div class="header-block">
            <h1>MBINGA FOLK DEVELOPMENT COLLEGE (FDC)</h1>
            <h2>RIPOTI YA ACCOUNTS NA FEDHA ZA ADA</h2>
        </div>

        <div class="report-heading"><?php echo $report_title; ?></div>

        <table>
            <thead>
                <tr>
                    <th style="width: 40px;" class="center-text">S/N</th>
                    <th style="width: 130px;">REG NUMBER</th>
                    <th>JINA KAMILI YA MWANAFUNZI</th>
                    <th>FANI / KOZI</th>
                    <th class="center-text">LEVEL</th>
                    <th class="right-text">ADA YA KOZI</th>
                    <th class="right-text">KIASI ALICHOLIPA</th>
                    <th class="right-text">KIASI ANACHODAIWA</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($filtered_students) > 0): ?>
                    <?php $sn = 1; foreach ($filtered_students as $row): ?>
                        <tr>
                            <td class="center-text"><?php echo $sn++; ?></td>
                            <td style="font-family: monospace; font-weight: bold;"><?php echo htmlspecialchars($row['reg_number']); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                            <td class="center-text"><?php echo htmlspecialchars($row['current_level']); ?></td>
                            <td class="right-text" style="color: #333;">TZS <?php echo number_format($row['course_fee'], 2); ?></td>
                            <td class="right-text" style="color: #28a745;">TZS <?php echo number_format($row['total_paid'], 2); ?></td>
                            <td class="right-text" style="color: <?php echo $row['balance'] > 0 ? '#dc3545' : '#28a745'; ?>;">
                                TZS <?php echo number_format($row['balance'], 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px; color: #777; font-weight: bold;">
                            📭 Hakuna mwanafunzi aliyepatikana kwenye kundi hili kwa sasa.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>