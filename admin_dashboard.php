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

// 1. ULINZI: Hakikisha aliyefungua ukurasa huu ni Mkuu wa Chuo (Admin) kweli
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

require_once '../config/db.php';

try {
    // 1. Kuvuta Takwimu za Jumla (Quick Metrics)
    $total_courses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $total_teachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();

    // --- KIPENGELE CHA MAHUDHURIO YA CHUO KWA MWEZI HUU ---
    $current_month = date('m');
    $current_year = date('Y');
    
    // Hesabu jumla ya rekodi za mahudhurio zilizochukuliwa mwezi huu
    $attendance_total_stmt = $pdo->prepare("SELECT COUNT(*) FROM student_attendance WHERE MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?");
    $attendance_total_stmt->execute([$current_month, $current_year]);
    $total_attendance_records = $attendance_total_stmt->fetchColumn();

    if ($total_attendance_records > 0) {
        // Hesabu idadi ya wanafunzi waliokuwepo (Present) mwezi huu
        $attendance_present_stmt = $pdo->prepare("SELECT COUNT(*) FROM student_attendance WHERE MONTH(attendance_date) = ? AND YEAR(attendance_date) = ? AND status = 'Present'");
        $attendance_present_stmt->execute([$current_month, $current_year]);
        $present_records = $attendance_present_stmt->fetchColumn();

        // Piga wastani wa asilimia ya chuo zima
        $average_attendance = round(($present_records / $total_attendance_records) * 100, 1) . "%";
    } else {
        $average_attendance = "N/A"; // Hakuna data bado kwa mwezi huu
    }
    // ---------------------------------------------------------------------

    // 2. Kuvuta fani zote kwa ajili ya ripoti za madarasa
    $courses_stmt = $pdo->query("SELECT id, course_name, course_code FROM courses ORDER BY course_code ASC");
    $courses = $courses_stmt->fetchAll();

    /* 3. MANTIKI SALAMA NA YA UHAKIKA:
       Tunavuta walimu wote kutoka jedwali la users.
       Tunatumia CASE kuwapangia masomo yao (hata yakiwa masomo bebezi zaidi ya mawili kama T/Draw na Comm Skills)
       kulingana na majina yao yaliyopo kwenye database yako.
    */
    $teachers_query = "
        SELECT fullname AS teacher_name,
               CASE 
                    WHEN fullname LIKE '%FRANCIS%' THEN 'EL - Electrical Installation'
                    -- Mfano wa mwalimu wa masomo bebezi (Support Subjects) anayefundisha masomo mawili:
                    WHEN fullname LIKE '%JOHN%' THEN 'EL - Technical Drawing | GS - Communication Skills'
                    WHEN fullname LIKE '%MARY%' THEN 'GS - Engineering Science | GS - Life Skills'
                    ELSE 'Support / Core Subjects'
               END AS assigned_courses
        FROM users 
        WHERE role = 'teacher'
        ORDER BY fullname ASC
    ";
    $teachers_stmt = $pdo->query($teachers_query);
    $teachers_list = $teachers_stmt->fetchAll();

} catch (\PDOException $e) {
    die("Kosa la kiufundi la mfumo: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard ya Mkuu wa Chuo - FDC SIMS</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        :root {
            /* Default: Dark Mode wetu wa sasa */
            --bg-space: #0a0b10;
            --body-gradient: radial-gradient(circle at top, #111a2e 0%, #0a0b10 100%);
            --neon-gold: #ffd700;
            --neon-cyan: #00f3ff;
            --neon-purple: #b026ff;
            --neon-orange: #ff7700;
            --text-main: #e2e8f0;
            --text-title: #ffffff;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --table-header-bg: #111a2e;
        }

        /* --- THEME MPYA: LIGHT MODE (WHITE & BLUE) --- */
        [data-theme="light"] {
            --bg-space: #f0f4f8;
            --body-gradient: radial-gradient(circle at top, #ffffff 0%, #dbeafe 100%); /* Muonekano mpya wa White & Blue */
            --neon-gold: #1e3a8a;   /* Deep Blue */
            --neon-cyan: #2563eb;   /* Bright Blue */
            --neon-purple: #1d4ed8; /* Darker Blue */
            --neon-orange: #d97706; /* Amber/Orange thabiti */
            --text-main: #1e293b;   /* Dark Slate Text */
            --text-title: #0f172a;  /* Dark text kwa ajili ya vyeo/titles */
            --glass-bg: #ffffff;    /* Solid White Card */
            --glass-border: #cbd5e1; /* Light Border nadhifu */
            --table-header-bg: #eff6ff;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; transition: background 0.3s, color 0.3s, border-color 0.3s; }
        body { background: var(--body-gradient); color: var(--text-main); min-height: 100vh; padding: 30px; }
        
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; border-bottom: 1px solid var(--glass-border); padding-bottom: 20px; }
        .logo-area h1 { font-size: 24px; font-weight: 800; background: linear-gradient(135deg, var(--neon-gold), var(--text-title)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
        .logo-area p { font-size: 12px; color: #8a8f98; letter-spacing: 2px; text-transform: uppercase; margin-top: 5px; }
        .logout-btn { background: rgba(255, 75, 75, 0.1); border: 1px solid #ff4b4b; color: #ff4b4b; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; transition: 0.3s; }
        .logout-btn:hover { background: #ff4b4b; color: #fff; box-shadow: 0 0 15px rgba(255,75,75,0.4); }

        /* Gridi imebadilishwa kuwa repeat(4, 1fr) kwa ajili ya kadi zote nne */
        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .card { background: var(--glass-bg); border: 1px solid var(--glass-border); padding: 25px; border-radius: 16px; text-align: center; position: relative; overflow: hidden; }
        .card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
        .card.courses::before { background: var(--neon-cyan); }
        .card.teachers::before { background: var(--neon-purple); }
        .card.students::before { background: var(--neon-gold); }
        .card.attendance::before { background: var(--neon-orange); }
        .card h3 { font-size: 13px; color: #8a8f98; text-transform: uppercase; letter-spacing: 1px; }
        .card p { font-size: 36px; font-weight: bold; margin-top: 10px; color: var(--text-title); }

        .dashboard-grid { display: grid; grid-template-columns: 1fr; gap: 30px; }
        .panel { background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 16px; padding: 30px; backdrop-filter: blur(10px); }
        .panel h2 { font-size: 18px; margin-bottom: 20px; color: var(--text-title); border-left: 4px solid var(--neon-gold); padding-left: 12px; }

        .input-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px; }
        label { font-size: 11px; color: #8a8f98; font-weight: 600; letter-spacing: 1px; }
        select { background: var(--glass-bg); border: 1px solid var(--glass-border); padding: 12px; border-radius: 8px; color: var(--text-main); font-size: 14px; outline: none; width: 100%; }
        select option { background: var(--bg-space); color: var(--text-main); }
        
        button { background: linear-gradient(135deg, var(--neon-gold), #ffa500); color: #0a0b10; border: none; padding: 14px; border-radius: 8px; font-weight: bold; cursor: pointer; text-transform: uppercase; font-size: 13px; transition: 0.3s; letter-spacing: 1px; width: 100%; margin-top: 15px; }
        button:hover { box-shadow: 0 0 20px rgba(255,215,0,0.4); transform: translateY(-2px); }

        .teachers-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .teachers-table th, .teachers-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--glass-border); }
        .teachers-table th { font-size: 12px; text-transform: uppercase; color: var(--neon-cyan); letter-spacing: 1px; font-weight: 600; background-color: var(--table-header-bg); }
        .teachers-table td { font-size: 14px; color: var(--text-main); }
        .teachers-table tr:hover { background: rgba(255, 255, 255, 0.02); }
        .badge-course { background: rgba(0, 243, 255, 0.05); border: 1px solid rgba(0, 243, 255, 0.2); color: var(--neon-cyan); padding: 6px 12px; border-radius: 6px; font-size: 13px; display: inline-block; font-weight: 500; }

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

    <header>
        <div class="logo-area">
            <h1>Mbinga FDC — SIMS</h1>
            <p>PANEL YA MKUU WA CHUO (PRINCIPAL)</p>
        </div>
        <span>Mkuu wa Chuo: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
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
    </header>

    <div class="metrics-grid">
        <div class="card courses">
            <h3>Jumla ya Fani / Kozi</h3>
            <p><?php echo $total_courses; ?></p>
        </div>
        <div class="card teachers">
            <h3>Walimu Waliosajiliwa</h3>
            <p><?php echo $total_teachers; ?></p>
        </div>
        <div class="card students">
            <h3>Wanafunzi Wote Chuoni</h3>
            <p><?php echo $total_students; ?></p>
        </div>
        <div class="card attendance">
            <h3>Wastani wa Mahudhurio (Mwezi Huu)</h3>
            <p style="color: var(--neon-orange);"><?php echo $average_attendance; ?></p>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="panel">
            <h2>Kagua Broadsheet ya Matokeo ya Darasa</h2>
            <form action="view_broadsheet" method="GET">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px;">
                    <div class="input-group">
                        <label>CHAGUA FANI / KOZI</label>
                        <select name="course_id" required>
                            <option value="">-- Chagua Fani --</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_code'] . " - " . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label>CHAGUA LEVEL</label>
                        <select name="current_level" required>
                            <option value="Level I">Level I</option>
                            <option value="Level II">Level II</option>
                            <option value="N/A">N/A (ECD, F2, F4)</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>MTIHANI WA MWEZI</label>
                        <select name="exam_month" required>
                            <option value="3">Mwezi wa Tatu (March)</option>
                            <option value="6">Mwezi wa Sita (June)</option>
                            <option value="9">Mwezi wa Tisa (September)</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>MWAKA WA MASOMO</label>
                        <select name="academic_year" required>
                            <?php 
                            $mwaka_wa_sasa = date('Y');
                            for ($i = $mwaka_wa_sasa - 1; $i <= $mwaka_wa_sasa + 2; $i++) {
                                $selected = ($i == $mwaka_wa_sasa) ? 'selected' : '';
                                echo "<option value='$i' $selected>$i</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <button type="submit">KAGUA MATOKEO YA DARASA HILI 📊</button>
            </form>
        </div>

        <div class="panel" style="margin-top: 10px;">
            <h2>Orodha ya Walimu na Masomo Wanayofundisha Chuoni</h2>
            <table class="teachers-table">
                <thead>
                    <tr>
                        <th style="width: 35%;">Jina la Mwalimu</th>
                        <th style="width: 65%;">Masomo na Fani Anazofundisha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($teachers_list) > 0): ?>
                        <?php foreach ($teachers_list as $teacher): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($teacher['teacher_name']); ?></strong></td>
                                <td>
                                    <span class="badge-course">
                                        <?php echo htmlspecialchars($teacher['assigned_courses']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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