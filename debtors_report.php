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

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'accountant') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

require_once '../config/db.php';

// Kuchuja kulingana na chaguzi za Mhasibu (Filters)
$selected_year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';
$selected_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

try {
    // 1. Vuta orodha ya miaka ya masomo iliyopo kwa ajili ya chujio
    $years_stmt = $pdo->query("SELECT DISTINCT academic_year FROM students ORDER BY academic_year DESC");
    $academic_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. Vuta orodha ya fani/kozi zote kwa ajili ya chujio
    $courses = $pdo->query("SELECT id, course_name, course_code FROM courses ORDER BY course_name ASC")->fetchAll();

    // 3. QUERY KUU: Inatafuta wanafunzi WENYE MADENI TU (Walicholipa ni chini ya Ada ya Kozi)
    $sql = "
        SELECT s.id, s.reg_number, s.first_name, s.last_name, s.academic_year,
               c.course_name, c.course_code, c.course_fee,
               COALESCE(SUM(p.amount_paid), 0) AS total_paid,
               (c.course_fee - COALESCE(SUM(p.amount_paid), 0)) AS remaining_balance
        FROM students s
        JOIN courses c ON s.course_id = c.id
        LEFT JOIN fees_payments p ON s.id = p.student_id
        WHERE 1=1
    ";

    $params = [];

    // Kama amechagua mwaka wa masomo
    if (!empty($selected_year)) {
        $sql .= " AND s.academic_year = ?";
        $params[] = $selected_year;
    }

    // Kama amechagua kozi/fani maalum
    if ($selected_course > 0) {
        $sql .= " AND s.course_id = ?";
        $params[] = $selected_course;
    }

    $sql .= " GROUP BY s.id, s.reg_number, s.first_name, s.last_name, s.academic_year, c.course_name, c.course_code, c.course_fee";
    
    // Muhimu: Hapa tunahakikisha wanaonekana wale tu ambao total_paid ni ndogo kuliko ada halisi (Wenye madeni)
    $sql .= " HAVING total_paid < c.course_fee";
    $sql .= " ORDER BY s.first_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $debtors = $stmt->fetchAll();

} catch (\PDOException $e) {
    die("Kosa la kiufundi la Ripoti: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ripoti ya Madeni ya Ada — SIMS</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        :root {
            --bg-space: #0a0b10;
            --neon-orange: #ffaa00;
            --neon-cyan: #00f3ff;
            --text-main: #e2e8f0;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: radial-gradient(circle at top, #1c1405 0%, var(--bg-space) 100%); color: var(--text-main); min-height: 100vh; padding: 30px; }
        
        .nav-container { display: flex; justify-content: space-between; align-items: center; background: var(--glass-bg); border: 1px solid var(--glass-border); padding: 15px 30px; border-radius: 12px; margin-bottom: 30px; }
        .back-btn { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--glass-border); color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: bold; }
        .back-btn:hover { background: rgba(255, 255, 255, 0.1); }
        
        h1 { font-size: 24px; color: var(--neon-orange); text-shadow: 0 0 10px rgba(255,170,0,0.2); }
        
        .filter-card { background: var(--glass-bg); border: 1px solid var(--glass-border); padding: 20px; border-radius: 12px; margin-bottom: 30px; }
        .filter-form { display: flex; gap: 15px; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; gap: 6px; flex: 1; }
        label { font-size: 12px; color: #8a8f98; font-weight: 600; }
        select { padding: 10px; background: #0f111a; border: 1px solid var(--glass-border); border-radius: 6px; color: white; font-size: 14px; outline: none; }
        
        .btn-filter { background: var(--neon-orange); color: #0a0b10; border: none; padding: 11px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; text-transform: uppercase; }
        .btn-print { background: linear-gradient(135deg, var(--neon-cyan), #00aa44); color: #0a0b10; border: none; padding: 11px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; text-transform: uppercase; text-decoration: none; display: inline-block; text-align: center; }

        .report-panel { background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 16px; padding: 25px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--glass-border); }
        th { background: rgba(255,255,255,0.02); color: var(--neon-orange); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        tr:hover { background: rgba(255,255,255,0.02); }
        
        .badge-debt { background: rgba(255, 75, 75, 0.15); border: 1px solid #ff4b4b; color: #ff4b4b; padding: 3px 8px; border-radius: 4px; font-weight: bold; }

        /* MUUNDO MAALUM WA KU-PRINT (PRINT MEDIA CSS) */
        @media print {
            body { background: white !important; color: black !important; padding: 0; }
            .nav-container, .filter-card, .back-btn, .btn-print, .btn-filter { display: none !important; }
            .report-panel { border: none !important; background: transparent !important; padding: 0 !important; }
            h1 { color: black !important; text-align: center; margin-bottom: 20px; text-shadow: none !important; }
            table { width: 100%; border: 1px solid #000; }
            th, td { border: 1px solid #000 !important; color: black !important; padding: 8px; }
            th { background: #f2f2f2 !important; color: black !important; }
            .badge-debt { background: transparent !important; border: none !important; color: black !important; padding: 0; }
        }
    </style>
</head>
<body>

    <!-- Menyu ya Juu -->
    <div class="nav-container">
        <div>
            <a href="accountant_dashboard" class="back-btn">⬅️ RUDI DASHBOARD</a>
        </div>
        <h1>ORODHA YA WANAFUNZI WENYE MADENI YA ADA</h1>
        <div>
            <button onclick="window.print();" class="btn-print">🖨️ PRINT RIPOTI (MWEZI WA 7) 🚀</button>
        </div>
    </div>

    <!-- Sehemu ya Kuchuja Data (Filters) -->
    <div class="filter-card">
        <form method="GET" action="debtors_report.php" class="filter-form">
            <div class="form-group">
                <label>CHUJA KWA MWAKA WA MASOMO</label>
                <select name="academic_year">
                    <option value="">-- Miaka Yote --</option>
                    <?php foreach ($academic_years as $year): ?>
                        <option value="<?php echo htmlspecialchars($year); ?>" <?php echo $selected_year === $year ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($year); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>CHUJA KWA FANI / KOZI</label>
                <select name="course_id">
                    <option value="0">-- Fani Zote --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selected_course === intval($c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-filter">TAFUTA 🔍</button>
        </form>
    </div>

    <!-- Jedwali la Ripoti -->
    <div class="report-panel">
        <h2 style="font-size: 16px; margin-bottom: 15px; color: #fff;">
            Wanafunzi Wanaodaiwa kwa sasa (Idadi: <?php echo count($debtors); ?>)
        </h2>
        
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Namba ya Usajili (Reg No)</th>
                        <th>Jina Kamili</th>
                        <th>Fani/Kozi</th>
                        <th>Mwaka Masomo</th>
                        <th>Ada ya Kozi</th>
                        <th>Kiasi Alicholipa</th>
                        <th>Deni Linalobaki (Balance)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($debtors) > 0): ?>
                        <?php 
                        $total_debts = 0; 
                        foreach ($debtors as $row): 
                            $total_debts += $row['remaining_balance'];
                        ?>
                            <tr>
                                <td style="font-family: monospace; font-weight: bold; color: var(--neon-cyan);"><?php echo htmlspecialchars($row['reg_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['academic_year']); ?></td>
                                <td>TZS <?php echo number_format($row['course_fee'], 2); ?></td>
                                <td style="color: #00ff80;">TZS <?php echo number_format($row['total_paid'], 2); ?></td>
                                <td>
                                    <span class="badge-debt">TZS <?php echo number_format($row['remaining_balance'], 2); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Laini ya mwisho inayoonyesha jumla kuu ya madeni ya chuo -->
                        <tr style="background: rgba(255,255,255,0.05); font-weight: bold;">
                            <td colspan="6" style="text-align: right; color: #fff; font-size: 14px;">JUMLA KUU YA MADENI YA CHUO INAYODAIWA:</td>
                            <td style="color: #ff4b4b; font-size: 14px; text-decoration: underline;">TZS <?php echo number_format($total_debts, 2); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #8a8f98; padding: 30px;">
                                🎉 Safi sana! Hakuna mwanafunzi anayedaiwa kulingana na chujio hili.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>