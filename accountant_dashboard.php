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

// --- MFUMO WA MABORESHO YA UJUMBE WA MAKOSA YA KIFEDHA ---
$success = isset($_GET['success']) ? "✅ Malipo yamesajiliwa kikamilifu kwenye mfumo!" : "";
$error = "";

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'overpayment') {
        $bal = isset($_GET['balance']) ? number_format(floatval($_GET['balance']), 2) : "0.00";
        $error = "❌ Kosa: Kiasi ulichoingiza kinazidi deni la mwanafunzi! Salio la juu linaloruhusiwa sasa ni TZS " . $bal;
    } elseif ($_GET['error'] === 'duplicate_receipt') {
        $error = "❌ Kosa: Namba hii ya risiti tayari imeshatumika kwenye mfumo!";
    } elseif ($_GET['error'] === 'invalid_inputs') {
        $error = "⚠️ Kosa: Hakikisha umejaza fomu yote na kiasi cha fedha kiwe sahihi!";
    } elseif ($_GET['error'] === 'student_not_found') {
        $error = "⚠️ Kosa: Mwanafunzi husika hajapatikana kwenye mfumo!";
    } else {
        $error = "❌ Kosa la kiufundi limejitokeza wakati wa kuhifadhi malipo.";
    }
}

try {
    // 1. Piga hesabu ya mapato ya jumla yaliyokusanywa
    $total_collected = $pdo->query("SELECT SUM(amount_paid) FROM fees_payments")->fetchColumn();
    $total_collected = $total_collected ? $total_collected : 0;

    // 2. Wanafunzi wote ambao HAWAJAMALIZA ADA tu ndio waonekane kwenye dropdown
    // IMEBORESHWA: Sasa hivi inavuta na academic_year halisi kutoka kwa mratibu
    $students_stmt = $pdo->query("
        SELECT s.id, s.reg_number, s.first_name, s.last_name, s.academic_year, c.course_fee,
               COALESCE(SUM(p.amount_paid), 0) AS total_paid
        FROM students s
        JOIN courses c ON s.course_id = c.id
        LEFT JOIN fees_payments p ON s.id = p.student_id
        GROUP BY s.id, s.reg_number, s.first_name, s.last_name, s.academic_year, c.course_fee
        HAVING total_paid < c.course_fee
        ORDER BY s.academic_year DESC, s.first_name ASC
    ");
    $students = $students_stmt->fetchAll();

    // 3. Vuta malipo yote ya hivi karibuni yaliyofanyika (Recent Payments)
    $payments_stmt = $pdo->query("
        SELECT p.id, p.receipt_number, p.amount_paid, p.payment_date, s.reg_number, s.first_name, s.last_name
        FROM fees_payments p
        JOIN students s ON p.student_id = s.id
        ORDER BY p.id DESC LIMIT 10
    ");
    $recent_payments = $payments_stmt->fetchAll();

} catch (\PDOException $e) {
    die("Kosa la kiufundi: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mhasibu Dashboard — SIMS</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        :root {
            /* Default: Dark Mode */
            --bg-space: #0a0b10;
            --body-gradient: radial-gradient(circle at top, #0f1c2e 0%, #0a0b10 100%);
            --neon-cyan: #00f3ff;
            --neon-purple: #b026ff;
            --neon-green: #00ff80;
            --text-main: #e2e8f0;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --input-bg: rgba(0, 0, 0, 0.3);
            --select-option-bg: #141622;
        }

        /* --- THEME MPYA: LIGHT MODE (WHITE & BLUE) --- */
        [data-theme="light"] {
            --bg-space: #f0f4f8;
            --body-gradient: radial-gradient(circle at top, #ffffff 0%, #dbeafe 100%); /* Muonekano mpya wa White & Blue */
            --neon-cyan: #2563eb;   /* Bright Blue */
            --neon-purple: #1d4ed8; /* Darker Blue */
            --neon-green: #16a34a;  /* Darker Green thabiti kwa mwanga mwema */
            --text-main: #1e293b;   /* Dark Slate Text */
            --glass-bg: #ffffff;    /* Solid White Card */
            --glass-border: #cbd5e1; /* Light Border nadhifu */
            --input-bg: #ffffff;
            --select-option-bg: #f8fafc;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; transition: background 0.3s, color 0.3s, border-color 0.3s; }
        body { background: var(--body-gradient); color: var(--text-main); min-height: 100vh; padding: 30px; }
        
        .header-nav { display: flex; justify-content: space-between; align-items: center; background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--glass-border); padding: 20px 40px; border-radius: 16px; margin-bottom: 30px; }
        h1 { font-size: 24px; background: linear-gradient(to right, var(--neon-cyan), var(--neon-purple)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
        p { font-size: 12px; color: #8a8f98; letter-spacing: 2px; text-transform: uppercase; margin-top: 5px; }
        .logout-btn { color: #ff4b4b; text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid rgba(255, 75, 75, 0.2); padding: 8px 16px; border-radius: 8px; transition: all 0.3s; }
        .logout-btn:hover { background: rgba(255, 75, 75, 0.1); box-shadow: 0 0 15px rgba(255, 75, 75, 0.2); }
        
        .stat-card { background: linear-gradient(135deg, rgba(0, 243, 255, 0.1), rgba(176, 38, 255, 0.05)); border: 1px solid var(--neon-cyan); padding: 20px; border-radius: 12px; text-align: center; margin-bottom: 30px; }
        .stat-card h3 { font-size: 14px; text-transform: uppercase; letter-spacing: 1px; color: #8a8f98; }
        .stat-card .amount { font-size: 32px; font-weight: bold; color: var(--neon-green); margin-top: 10px; text-shadow: 0 0 10px rgba(0, 255, 128, 0.3); }

        .dashboard-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; }
        .panel { background: var(--glass-bg); backdrop-filter: blur(15px); border: 1px solid var(--glass-border); border-radius: 20px; padding: 30px; }
        h2 { font-size: 18px; margin-bottom: 20px; color: var(--neon-cyan); letter-spacing: 1px; }
        
        .input-group { margin-bottom: 15px; }
        label { display: block; font-size: 12px; color: #a0aec0; margin-bottom: 6px; }
        input, select { width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid var(--glass-border); border-radius: 8px; color: var(--text-main); font-size: 14px; }
        input:focus, select:focus { outline: none; border-color: var(--neon-purple); box-shadow: 0 0 10px rgba(176, 38, 255, 0.2); }
        select option { background: var(--select-option-bg); color: var(--text-main); }
        
        button { width: 100%; padding: 12px; background: linear-gradient(135deg, var(--neon-cyan), var(--neon-purple)); border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; transition: 0.3s; }
        button:hover { box-shadow: 0 0 20px rgba(0, 243, 255, 0.4); }

        .alert { padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: rgba(0, 255, 128, 0.1); border: 1px solid var(--neon-green); color: var(--neon-green); }
        .alert-error { background: rgba(255, 75, 75, 0.1); border: 1px solid #ff4b4b; color: #ff4b4b; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; text-align: left; }
        th, td { padding: 12px; border-bottom: 1px solid var(--glass-border); }
        th { color: var(--neon-cyan); font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        tr:hover { background: rgba(255,255,255,0.02); }

        /* --- STYLE YA KITUFE CHA KUSWIPU (TOGGLE SWITCH) --- */
        .theme-switch-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-right: 15px;
        }
        .theme-switch-wrapper span {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1px;
            color: var(--text-main);
        }
        .theme-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }
        .theme-switch input { 
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #334155;
            transition: .4s;
            border-radius: 34px;
            border: 1px solid var(--glass-border);
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: #ffd700;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #2563eb; /* Rangi ya Blue ikiswipiwa kulia (On) */
        }
        input:checked + .slider:before {
            transform: translateX(24px);
            background-color: #ffffff;
        }
    </style>
</head>
<body>

    <div class="header-nav">
        <h1>Mbinga FDC — SIMS
            <p>PORTAL YA ACCOUNTS / FINANCE</p>
        </h1>
        <span>Mhasibu: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
        <div style="display: flex; gap: 10px; align-items: center;">
            
            <div class="theme-switch-wrapper">
                <span id="theme-label">DARK MODE</span>
                <label class="theme-switch" for="checkbox_theme">
                    <input type="checkbox" id="checkbox_theme" />
                    <div class="slider"></div>
                </label>
            </div>

            <a href="change_password" style="background: rgba(0, 243, 255, 0.1); border: 1px solid var(--neon-cyan); color: var(--neon-cyan); padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px;">🔑 BADILISHA PASSWORD</a>
            <a href="../logout.php" class="logout-btn">LOGOUT 🔒</a>
        </div>
    </div>

    <div class="stat-card">
        <h3>Jumla ya Makusanyo ya Ada (Total Revenue)</h3>
        <div class="amount">TZS <?php echo number_format($total_collected, 2); ?></div>
    </div>

    <?php if($success): ?> <div class="alert alert-success"><?php echo $success; ?></div> <?php endif; ?>
    <?php if($error): ?> <div class="alert alert-error"><?php echo $error; ?></div> <?php endif; ?>

    <div class="dashboard-grid">
        <div class="panel">
            <h2>Sajili Malipo ya Ada</h2>
            <form action="save_payment.php" method="POST">
                <div class="input-group">
                    <label>CHAGUA MWANAFUNZI (WENYE MADENI)</label>
                    <select name="student_id" required>
                        <option value="">-- Chagua Mwanafunzi --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>">
                                <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['reg_number'] . ') [Mwaka: '.$s['academic_year'].']'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>NAMBA YA RISITI (RECEIPT NUMBER)</label>
                    <input type="text" name="receipt_number" placeholder="Mfano: ER-90823" required autocomplete="off" style="text-transform: uppercase;">
                </div>

                <div class="input-group">
                    <label>KIASI ANACHOLIPA (TZS)</label>
                    <input type="number" step="0.01" name="amount_paid" placeholder="Mfano: 250000" required>
                </div>

                <div class="input-group">
                    <label>TAREHE YA MALIPO</label>
                    <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <button type="submit">WASILISHA MALIPO 💾</button>
            </form>
            
            <div style="margin-top: 25px; border-top: 1px dashed var(--glass-border); padding-top: 20px;">
                <h2 style="color: #ffaa00; font-size: 16px;">⚠️ Udhibiti wa Mwezi wa Saba</h2>
                <p style="color: #a0aec0; text-transform:none; font-size:13px; letter-spacing:0; margin-bottom:15px;">
                    Chuja na uondoe wanafunzi wote wenye madeni kabla ya chuo kufunguliwa nusu ya pili ya mwaka.
                </p>
                <a href="debtors_report" style="background: linear-gradient(135deg, #ffaa00, #ff4b4b); color: white; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; display: block; text-align: center; box-shadow: 0 0 15px rgba(255,170,0,0.2);">
                    📊 FUNGUA ORODHA YA MADENI (DEBTORS LIST) 🚀
                </a>
            </div>
        </div>

        <div class="panel">
            <h2>Malipo 10 ya Hivi Karibuni</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>REG NUMBER</th>
                            <th>JINA KAMILI</th>
                            <th>RISITI NO</th>
                            <th>TAREHE</th>
                            <th>KIASI</th>
                            <th>KITENDO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_payments) > 0): ?>
                            <?php foreach ($recent_payments as $p): ?>
                                <tr>
                                    <td style="font-family: monospace;"><?php echo htmlspecialchars($p['reg_number']); ?></td>
                                    <td><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></td>
                                    <td style="color: var(--neon-cyan);"><?php echo htmlspecialchars($p['receipt_number']); ?></td>
                                    <td><?php echo $p['payment_date']; ?></td>
                                    <td style="font-weight: bold; color: var(--neon-green);">TZS <?php echo number_format($p['amount_paid'], 2); ?></td>
                                    <td>
                                        <a href="delete_payment.php?id=<?php echo $p['id']; ?>" 
                                           onclick="return confirm('Je, una uhakika unataka kubatilisha malipo haya ya Risiti: <?php echo $p['receipt_number']; ?>?');" 
                                           style="color: #ff4b4b; text-decoration: none; font-weight: bold; background: rgba(255,75,75,0.1); padding: 4px 8px; border-radius: 4px; border: 1px solid #ff4b4b; font-size: 11px;">
                                           ❌ BATILISHA
                                        </a>
                                    </td>                           
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #777;">Hakuna malipo yaliyosajiliwa bado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const toggleSwitch = document.querySelector('#checkbox_theme');
        const themeLabel = document.querySelector('#theme-label');
        const currentTheme = localStorage.getItem('theme') ? localStorage.getItem('theme') : null;

        if (currentTheme) {
            document.documentElement.setAttribute('data-theme', currentTheme);

            if (currentTheme === 'light') {
                toggleSwitch.checked = true;
                themeLabel.textContent = "LIGHT MODE";
            }
        }

        function switchTheme(e) {
            if (e.target.checked) {
                document.documentElement.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
                themeLabel.textContent = "LIGHT MODE";
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                themeLabel.textContent = "DARK MODE";
            }    
        }

        toggleSwitch.addEventListener('change', switchTheme, false);
    </script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>