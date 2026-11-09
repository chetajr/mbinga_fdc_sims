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

require_once '../config/db.php';

try {
    // Kuvuta fani zote kwa ajili ya filter dropdown
    $courses = $pdo->query("SELECT id, course_name, course_code FROM courses ORDER BY course_code ASC")->fetchAll();
} catch (\PDOException $e) {
    die("Kosa la kiufundi: " . $e->getMessage());
}

// Kupokea vigezo vya utafutaji (Filters)
$selected_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$selected_level = isset($_GET['current_level']) ? $_GET['current_level'] : '';
$selected_year = isset($_GET['academic_year']) ? intval($_GET['academic_year']) : date('Y');

$report_data = [];

if ($selected_course > 0 && !empty($selected_level)) {
    try {
        // 1. Vuta wanafunzi wote wa fani na level husika
        $st_stmt = $pdo->prepare("
            SELECT id, reg_number, first_name, last_name 
            FROM students 
            WHERE course_id = ? AND current_level = ? AND academic_year = ?
            ORDER BY first_name ASC
        ");
        $st_stmt->execute([$selected_course, $selected_level, $selected_year]);
        $students = $st_stmt->fetchAll();

        // 2. Hesabu jumla ya vipindi vyote vilivyofundishwa (vilivyorekodiwa mahudhurio) kwenye fani na level hii
        $period_stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT attendance_date, subject_id) as total_periods
            FROM student_attendance sa
            JOIN subjects s ON sa.subject_id = s.id
            WHERE s.course_id = ? AND sa.academic_year = ?
        ");
        $period_stmt->execute([$selected_course, $selected_year]);
        $total_periods = intval($period_stmt->fetchColumn());

        // kama kuna vipindi vilivyofundishwa, piga hesabu ya mahudhurio ya kila mwanafunzi
        foreach ($students as $student) {
            $present_stmt = $pdo->prepare("
                SELECT COUNT(*) FROM student_attendance 
                WHERE student_id = ? AND status = 'Present' AND academic_year = ?
            ");
            $present_stmt->execute([$student['id'], $selected_year]);
            $days_present = intval($present_stmt->fetchColumn());

            // Piga hesabu ya asilimia (%)
            $percentage = $total_periods > 0 ? round(($days_present / $total_periods) * 100, 1) : 100;

            $report_data[] = [
                'reg_number' => $student['reg_number'],
                'fullname' => $student['first_name'] . ' ' . $student['last_name'],
                'total_periods' => $total_periods,
                'days_present' => $days_present,
                'days_absent' => $total_periods - $days_present,
                'percentage' => $percentage
            ];
        }

    } catch (\PDOException $e) {
        die("Kosa la kutengeneza ripoti: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <title>Ripoti Kuu ya Mahudhurio — SIMS</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 30px; background-color: #0a0b10; color: #e2e8f0; }
        .panel { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 30px; border-radius: 16px; max-width: 1100px; margin: 0 auto; box-shadow: 0 4px 30px rgba(0,0,0,0.5); }
        h1 { font-size: 22px; color: #ffaa00; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; }
        .filter-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 15px; margin-bottom: 30px; align-items: flex-end; background: rgba(0,0,0,0.2); padding: 20px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); }
        select { width: 100%; padding: 12px; background: #141622; border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 6px; font-size: 14px; }
        select option { background: #141622; }
        label { display: block; font-size: 11px; color: #94a3b8; margin-bottom: 6px; font-weight: bold; text-transform: uppercase; }
        button { width: 100%; padding: 12px; background: linear-gradient(135deg, #ffaa00, #ff5500); border: none; border-radius: 6px; color: white; font-weight: bold; cursor: pointer; text-transform: uppercase; font-size: 14px; transition: 0.3s; }
        button:hover { box-shadow: 0 0 15px rgba(255,170,0,0.4); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: rgba(0,0,0,0.15); border-radius: 8px; overflow: hidden; }
        th, td { padding: 14px; border-bottom: 1px solid rgba(255,255,255,0.06); text-align: left; }
        th { background-color: rgba(255,255,255,0.02); color: #00f3ff; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:hover { background: rgba(255,255,255,0.02); }
        
        .badge { padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 13px; }
        .badge-success { background: rgba(0,255,128,0.15); color: #00ff80; border: 1px solid #00ff80; }
        .badge-danger { background: rgba(255,75,75,0.15); color: #ff4b4b; border: 1px solid #ff4b4b; font-weight: 900; animation: pulse 2s infinite; }
        
        .btn-print { background: #00f3ff; color: #0a0b10; padding: 10px 20px; text-decoration: none; font-weight: bold; border-radius: 6px; display: inline-block; font-size: 14px; margin-bottom: 20px; }
        .btn-print:hover { box-shadow: 0 0 15px rgba(0,243,255,0.4); }

        @media print {
            body { background: white; color: black; margin: 0; padding: 0; }
            .panel { border: none; box-shadow: none; padding: 0; max-width: 100%; }
            .filter-grid, .btn-print, .back-link { display: none !important; }
            th { background: #f1f5f9 !important; color: black !important; border: 1px solid #cbd5e1 !important; }
            td { border: 1px solid #cbd5e1 !important; color: black !important; }
            .badge-danger { color: red !important; background: none !important; border: none !important; text-decoration: underline; }
            .badge-success { color: green !important; background: none !important; border: none !important; }
        }
    </style>
</head>
<body>

    <div class="panel">
        <a href="mratibu_dashboard.php" class="back-link" style="color: #94a3b8; text-decoration: none; font-size: 14px;">⬅️ Rudi Kwenye Dashboard</a>
        <h1 style="margin-top: 15px;">📊 RIPOTI KUU YA MAHUDHURIO YA WANAFUNZI</h1>

        <form method="GET" action="attendance_report" class="filter-grid">
            <div>
                <label>Fani / Kozi</label>
                <select name="course_id" required>
                    <option value="">-- Chagua Fani --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selected_course === intval($c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Level</label>
                <select name="current_level" required>
                    <option value="">-- Chagua Level --</option>
                    <option value="Level I" <?php echo $selected_level === 'Level I' ? 'selected' : ''; ?>>Level I</option>
                    <option value="Level II" <?php echo $selected_level === 'Level II' ? 'selected' : ''; ?>>Level II</option>
                    <option value="N/A" <?php echo $selected_level === 'N/A' ? 'selected' : ''; ?>>N/A (ECD, F2, F4)</option>
                </select>
            </div>
            <div>
                <label>Mwaka wa Masomo</label>
                <select name="academic_year" required>
                    <?php 
                    for ($i = date('Y') - 1; $i <= date('Y') + 2; $i++) {
                        $selected = ($i == $selected_year) ? 'selected' : '';
                        echo "<option value='$i' $selected>$i</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <button type="submit">ZALISHA RIPOTI ⚡</button>
            </div>
        </form>

        <?php if ($selected_course > 0 && !empty($selected_level)): ?>
            
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <p style="color: #94a3b8; font-size: 14px; text-transform: none; letter-spacing: 0;">
                    Imepatikana ripoti ya wanafunzi wenye mahudhurio ya vipindi vyote viliovundishwa.
                </p>
                <a href="#" onclick="window.print()" class="btn-print">🖨️ CHAPA RIPOTI (PRINT)</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>NAMBA YA USAJILI</th>
                        <th>JINA KAMILI</th>
                        <th style="text-align: center;">JUMLA YA VIPINDI</th>
                        <th style="text-align: center; color: #00ff80;">ALIKUWEPO</th>
                        <th style="text-align: center; color: #ff4b4b;">HAKUWEPO</th>
                        <th style="text-align: center;">ASILIMIA (%)</th>
                        <th>HALI YA MTIHANI (STATUS)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($report_data)): ?>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td style="font-family: monospace; font-size: 14px;"><?php echo htmlspecialchars($row['reg_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                <td style="text-align: center; font-weight: bold;"><?php echo $row['total_periods']; ?></td>
                                <td style="text-align: center; color: #00ff80; font-weight: bold;"><?php echo $row['days_present']; ?></td>
                                <td style="text-align: center; color: #ff4b4b; font-weight: bold;"><?php echo $row['days_absent']; ?></td>
                                <td style="text-align: center; font-weight: bold; font-size: 15px; <?php echo $row['percentage'] < 75 ? 'color: #ff4b4b;' : 'color: #00ff80;'; ?>">
                                    <?php echo $row['percentage']; ?>%
                                </td>
                                <td>
                                    <?php if ($row['percentage'] >= 75): ?>
                                        <span class="badge badge-success">ANARUHUSIWA MTIHANI ✔️</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">(CHINI YA 75%)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #94a3b8; padding: 30px;">Hakuna wanafunzi waliosajiliwa kwenye fani na level hii kwa mwaka uliochaguliwa.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; margin-top: 40px; color: #94a3b8;">Tafadhali chagua Fani na Level hapo juu kisha bonyeza "Zalisha Ripoti".</p>
        <?php endif; ?>
    </div>

</body>
</html>