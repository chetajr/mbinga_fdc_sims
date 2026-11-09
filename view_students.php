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
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'mratibu') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

require_once '../config/db.php';

// Kupokea machaguo ya kuchuja (Filters)
$course_filter = isset($_GET['course_id']) ? trim($_GET['course_id']) : '';
$level_filter  = isset($_GET['current_level']) ? trim($_GET['current_level']) : '';

try {
    // 1. Kuvuta fani zote kwa ajili ya dropdown ya kuchujia
    $courses_stmt = $pdo->query("SELECT id, course_name, course_code FROM courses ORDER BY course_name ASC");
    $courses = $courses_stmt->fetchAll();

    // 2. Kujenga Query ya kuvuta wanafunzi kulingana na filter zilizochaguliwa
    $sql = "SELECT s.*, c.course_code, c.course_name 
            FROM students s 
            JOIN courses c ON s.course_id = c.id 
            WHERE 1=1";
    
    $params = [];

    if (!empty($course_filter)) {
        $sql .= " AND s.course_id = :course_id";
        $params['course_id'] = intval($course_filter);
    }

    if (!empty($level_filter)) {
        $sql .= " AND s.current_level = :current_level";
        $params['current_level'] = $level_filter;
    }

    $sql .= " ORDER BY c.course_code ASC, s.current_level ASC, s.first_name ASC";
    
    $students_stmt = $pdo->prepare($sql);
    $students_stmt->execute($params);
    $students = $students_stmt->fetchAll();

} catch (\PDOException $e) {
    die("Kosa la kiufundi: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <title>Orodha ya Wanafunzi - Mbinga FDC</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { padding: 30px; background: #f4f6f9; color: #333; }
        
        .no-print { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .filter-form { display: flex; gap: 15px; align-items: center; }
        select { padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; background: white; }
        
        .btn { padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; cursor: pointer; border: none; font-size: 14px; }
        .btn-back { background: #6c757d; color: white; }
        .btn-filter { background: #007bff; color: white; }
        .btn-print { background: #28a745; color: white; box-shadow: 0 4px 10px rgba(40,167,69,0.3); }

        .sheet-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); min-height: 842px; }
        .header-block { text-align: center; margin-bottom: 30px; border-bottom: 3px double #333; padding-bottom: 20px; }
        .header-block h1 { font-size: 24px; text-transform: uppercase; letter-spacing: 1px; }
        .header-block h2 { font-size: 16px; color: #555; margin-top: 5px; text-transform: uppercase; }
        
        .report-title { font-size: 15px; font-weight: bold; margin-bottom: 15px; text-transform: uppercase; color: #444; border-left: 4px solid #007bff; padding-left: 10px; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; }
        th, td { border: 1px solid #666; padding: 12px 10px; text-align: left; }
        th { background: #f8f9fa; font-weight: bold; text-transform: uppercase; font-size: 12px; }
        .center-text { text-align: center; }
        
        .total-count { margin-top: 20px; font-weight: bold; font-size: 15px; text-align: right; color: #0056b3; }

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
        <a href="mratibu_dashboard" class="btn btn-back">⬅️ Rudi Dashboard</a>
        
        <form method="GET" class="filter-form">
            <select name="course_id">
                <option value="">-- Fani Zote --</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $course_filter == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['course_code'] . " - " . $c['course_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="current_level">
                <option value="">-- Level Zote --</option>
                <option value="Level I" <?php echo $level_filter === 'Level I' ? 'selected' : ''; ?>>Level I</option>
                <option value="Level II" <?php echo $level_filter === 'Level II' ? 'selected' : ''; ?>>Level II</option>
                <option value="N/A" <?php echo $level_filter === 'N/A' ? 'selected' : ''; ?>>N/A (ECD/F2/F4)</option>
            </select>

            <button type="submit" class="btn btn-filter">CHUJA ORODHA 🔍</button>
        </form>

        <button onclick="window.print()" class="btn btn-print">🖨️ CHAPA ORODHA (PRINT)</button>
    </div>

    <div class="sheet-container">
        <div class="header-block">
            <h1>MBINGA FOLK DEVELOPMENT COLLEGE (FDC)</h1>
            <h2>OFFICIAL STUDENT REGISTER REPORT</h2>
        </div>

        <div class="report-title">
            Orodha ya Wanafunzi Kulingana na Vigezo Vilivyochaguliwa
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 50px;" class="center-text">S/N</th>
                    <th style="width: 160px;">NAMBA YA USAJILI</th>
                    <th>MAJINA KAMILI YA MWANAFUNZI</th>
                    <th>FANI / KOZI</th>
                    <th style="width: 100px;" class="center-text">LEVEL</th>
                    <th style="width: 120px;" class="center-text">MWAKA WA MASOMO</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($students) > 0): ?>
                    <?php $sn = 1; foreach ($students as $student): ?>
                        <tr>
                            <td class="center-text"><?php echo $sn++; ?></td>
                            <td style="font-family: monospace; font-weight: bold; color: #444;"><?php echo htmlspecialchars($student['reg_number']); ?></td>
                            <td><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['course_code'] . " - " . $student['course_name']); ?></td>
                            <td class="center-text"><?php echo htmlspecialchars($student['current_level']); ?></td>
                            <td class="center-text"><?php echo htmlspecialchars($student['academic_year']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 30px; color: #777;">
                            ⚠️ Hakuna mwanafunzi aliyepatikana kulingana na vichujio ulivyochagua.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="total-count">
            Jumla Kuu ya Wanafunzi Waliopo Kwenye Orodha: <?php echo count($students); ?>
        </div>
    </div>

</body>
</html>