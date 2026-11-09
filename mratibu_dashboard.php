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

// 2. Muunganisho wa Database
require_once '../config/db.php';

// --- MANTIKI YA KUMPANGIA MWALIMU SOMO ---
$ujumbe_allocation = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['panga_somo'])) {
    $teacher_id = $_POST['allocation_teacher_id'];
    $subject_id = $_POST['allocation_subject_id'];

    if (!empty($teacher_id) && !empty($subject_id)) {
        try {
            // Hakikisha somo halijapangwa tayari kwa mwalimu huyu
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_subjects WHERE teacher_id = ? AND subject_id = ?");
            $check_stmt->execute([$teacher_id, $subject_id]);
            
            if ($check_stmt->fetchColumn() > 0) {
                $ujumbe_allocation = "<div style='padding: 12px; background: rgba(255,165,0,0.1); border: 1px solid #ffa500; color: #ffa500; border-radius: 8px; margin-bottom: 15px; font-size:14px; font-weight:bold;'>⚠️ Mwalimu huyu tayari ameshapangiwa somo hili!</div>";
            } else {
                $insert_stmt = $pdo->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
                $insert_stmt->execute([$teacher_id, $subject_id]);
                $ujumbe_allocation = "<div style='padding: 12px; background: rgba(0,255,128,0.1); border: 1px solid #00ff80; color: #00ff80; border-radius: 8px; margin-bottom: 15px; font-size:14px; font-weight:bold;'>✅ Somo limepangiwa mwalimu kikamilifu!</div>";
            }
        } catch (\PDOException $e) {
            $ujumbe_allocation = "<div style='padding: 12px; background: rgba(255,75,75,0.1); border: 1px solid #ff4b4b; color: #ff4b4b; border-radius: 8px; margin-bottom: 15px; font-size:14px;'>❌ Hitilafu: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

try {
    // Vuta data za fani zote
    $stmt = $pdo->query("SELECT id, course_name, course_code FROM courses ORDER BY course_code ASC");
    $courses = $stmt->fetchAll();

    // SAHIHI: Vuta walimu kwa kutumia 'fullname' badala ya 'name' kuzuia Error 1054
    $teachers = $pdo->query("SELECT id, fullname FROM users WHERE role = 'teacher' ORDER BY fullname ASC")->fetchAll();
    
    // SAHIHI: Vuta masomo yote (bila kuweka s.current_level inayofanya SQL ikatae)
    $all_subjects = $pdo->query("
        SELECT s.id, s.subject_name, s.subject_type, c.course_code 
        FROM subjects s 
        INNER JOIN courses c ON s.course_id = c.id 
        ORDER BY c.course_code ASC, s.subject_name ASC
    ")->fetchAll();

} catch (\PDOException $e) {
    die("Kosa la kiufundi la mfumo: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mbinga FDC — SIMS</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        :root {
            /* Default: Dark Mode */
            --bg-space: #0a0b10;
            --body-gradient: radial-gradient(circle at top, #111e2e 0%, var(--bg-space) 100%);
            --neon-cyan: #00f3ff;
            --neon-purple: #b026ff;
            --neon-orange: #ffaa00;
            --text-main: #e2e8f0;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --input-bg: rgba(0, 0, 0, 0.3);
        }

        /* --- THEME MPYA: LIGHT MODE (WHITE & BLUE) --- */
        [data-theme="light"] {
            --bg-space: #f0f4f8;
            --body-gradient: radial-gradient(circle at top, #ffffff 0%, #dbeafe 100%); /* Muonekano wa White & Blue */
            --neon-cyan: #2563eb;   /* Bright Blue */
            --neon-purple: #1d4ed8; /* Darker Blue */
            --neon-orange: #ea580c; /* Orange nadhifu kwa light mode */
            --text-main: #1e293b;   /* Dark Slate Text */
            --glass-bg: #ffffff;    /* Solid White Card */
            --glass-border: #cbd5e1; /* Light Border */
            --input-bg: #ffffff;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; transition: background 0.3s, color 0.3s, border-color 0.3s; }
        body { background: var(--body-gradient); color: var(--text-main); min-height: 100vh; padding: 30px; }
        
        .header-nav { display: flex; justify-content: space-between; align-items: center; background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--glass-border); padding: 20px 40px; border-radius: 16px; margin-bottom: 30px; }
        h1 { font-size: 24px; background: linear-gradient(to right, var(--neon-cyan), var(--neon-purple)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
        p { font-size: 12px; color: #8a8f98; letter-spacing: 2px; text-transform: uppercase; margin-top: 5px; }
        .logout-btn { color: #ff4b4b; text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid rgba(255, 75, 75, 0.2); padding: 8px 16px; border-radius: 8px; transition: all 0.3s; }
        .logout-btn:hover { background: rgba(255, 75, 75, 0.1); box-shadow: 0 0 15px rgba(255, 75, 75, 0.2); }
        
        .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .panel { background: var(--glass-bg); backdrop-filter: blur(15px); border: 1px solid var(--glass-border); border-radius: 20px; padding: 30px; }
        h2 { font-size: 18px; margin-bottom: 20px; color: var(--neon-cyan); letter-spacing: 1px; text-transform: uppercase; }
        
        .input-group { margin-bottom: 15px; }
        label { display: block; font-size: 12px; color: #a0aec0; margin-bottom: 6px; letter-spacing: 0.5px; }
        input, select { width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid var(--glass-border); border-radius: 8px; color: var(--text-main); font-size: 14px; }
        input:focus, select:focus { outline: none; border-color: var(--neon-purple); box-shadow: 0 0 10px rgba(176, 38, 255, 0.2); }
        select option { background: var(--glass-bg); color: var(--text-main); }
        
        button, .btn-link { width: 100%; padding: 12px; background: linear-gradient(135deg, var(--neon-cyan), var(--neon-purple)); border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; margin-top: 10px; transition: 0.3s; font-size: 14px; text-transform: uppercase; text-align: center; text-decoration: none; display: block; }
        button:hover, .btn-link:hover { box-shadow: 0 0 20px rgba(0, 243, 255, 0.4); }
        
        .radio-group { display: flex; gap: 20px; margin-top: 5px; }
        .radio-option { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .radio-option input { width: auto; cursor: pointer; }
        
        .attendance-panel { background: linear-gradient(135deg, rgba(255,170,0,0.05), rgba(176,38,255,0.02)); border: 1px solid var(--glass-border); border-radius: 20px; padding: 30px; text-align: center; max-width: 700px; margin: 0 auto 30px auto; }

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
            <p>PANEL YA MRATIBU WA TAALUMA</p>
        </h1>
        <span>Mratibu: <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Mratibu'); ?></strong></span>
        <div>
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
    </div>

    <div class="dashboard-grid">
        
        <div class="panel">
            <h2>Sajili Fani / Kozi Mpya</h2>
            <form action="save_course" method="POST">
                <div class="input-group">
                    <label>KODI YA FANI (COURSE CODE)</label>
                    <input type="text" name="course_code" placeholder="Mfano: EL, MVM, ICT, ECD" required autocomplete="off" style="text-transform: uppercase;">
                </div>
                <div class="input-group">
                    <label>JINA LA FANI (COURSE NAME)</label>
                    <input type="text" name="course_name" placeholder="Mfano: Electrical Installation" required autocomplete="off">
                </div>
                <div class="input-group">
                    <label>MFUMO WA LEVEL (I & II)?</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" name="has_levels" value="1" id="level_yes" checked>
                            <label for="level_yes" style="display:inline; margin:0; cursor:pointer;">Ndio (Ina Level I & II)</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="has_levels" value="0" id="level_no">
                            <label for="level_no" style="display:inline; margin:0; cursor:pointer;">Hapana (Mwaka mmoja tu)</label>
                        </div>
                    </div>
                </div>
                <div class="input-group">
                    <label>KIASI CHA ADA CHA KOZI HII (TZS)</label>
                    <input type="number" step="0.01" name="course_fee" placeholder="Mfano: 600000" required>
                </div>
                <button type="submit">HIFADHI FANI</button>
            </form>
        </div>

        <div class="panel">
            <h2>Sajili Somo Kwenye Fani</h2>
            <form action="save_subject" method="POST">
                <div class="input-group">
                    <label>CHAGUA FANI / KOZI</label>
                    <select name="course_id" id="courseSelect" required>
                        <option value="">-- Chagua Fani --</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['course_code'] . " - " . $course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label>JINA LA SOMO</label>
                    <input type="text" name="subject_name" placeholder="Mfano: THEORY, MATHS, T/DRAW" required autocomplete="off" style="text-transform: uppercase;">
                </div>
                <div class="input-group">
                    <label>AINA YA SOMO (SUBJECT TYPE)</label>
                    <select name="subject_type" id="subjectTypeSelect" required>
                        <option value="">-- Chagua Aina --</option>
                        <option value="core_theory">Somo la Fani la Darasani (Core Theory)</option>
                        <option value="core_practical" id="opt_practical">Somo la Fani la Vitendo (Core Practical)</option>
                        <option value="support">Somo Bebezi / Saidizi (Support)</option>
                    </select>
                </div>
                <button type="submit">HIFADHI SOMO</button>
            </form>
        </div>

        <div class="panel" style="grid-column: span 2;">
            <h2>Panga Masomo ya Walimu (Subject Allocation)</h2>
            <?php echo $ujumbe_allocation; ?>
            <form action="" method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="input-group">
                        <label>CHAGUA MWALIMU ALIYESAJILIWA NA IT</label>
                        <select name="allocation_teacher_id" required>
                            <option value="">-- Chagua Mwalimu --</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['fullname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>CHAGUA SOMO LA KUMPA</label>
                        <select name="allocation_subject_id" required>
                            <option value="">-- Chagua Somo --</option>
                            <?php foreach ($all_subjects as $sub): ?>
                                <?php 
                                $typeText = $sub['subject_type'] === 'core_theory' ? 'Theory' : ($sub['subject_type'] === 'core_practical' ? 'Practical' : 'Support');
                                ?>
                                <option value="<?php echo $sub['id']; ?>">
                                    <?php echo htmlspecialchars("[" . $sub['course_code'] . "] " . $sub['subject_name'] . " (" . $typeText . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="panga_somo" style="background: linear-gradient(135deg, #00ffcc, var(--neon-purple)); color: #0a0b10;">HIFADHI UPANGAJI WA SOMO KWA MWALIMU 💾</button>
            </form>
        </div>

        <div class="panel" style="grid-column: span 2;">
            <h2>Sajili Mwanafunzi Mpya</h2>
            <form action="save_student" method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="input-group">
                        <label>JINA LA KWANZA (FIRST NAME)</label>
                        <input type="text" name="first_name" placeholder="Mfano: OSWIN" required autocomplete="off" style="text-transform: uppercase;">
                    </div>
                    <div class="input-group">
                        <label>JINA LA KATI (MIDDLE NAME - SIO LAZIMA)</label>
                        <input type="text" name="middle_name" placeholder="Mfano: HERMAN" autocomplete="off" style="text-transform: uppercase;">
                    </div>
                    <div class="input-group">
                        <label>JINA LA UKOO (LAST NAME)</label>
                        <input type="text" name="last_name" placeholder="Mfano: TILIA" required autocomplete="off" style="text-transform: uppercase;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="input-group">
                        <label>NAMBA YA USAJILI (REG NUMBER)</label>
                        <input type="text" name="reg_number" placeholder="Mfano: MBD/EL/2026/001" required autocomplete="off" style="text-transform: uppercase;">
                    </div>
                    <div class="input-group">
                        <label>NAMBA YA SIMU</label>
                        <input type="text" name="phone_number" placeholder="Mfano: 07XXXXXXXX" autocomplete="off">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
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
                            <option value="N/A">N/A (Kwa ECD, F2, F4)</option>
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label>MWAKA WA MASOMO (ACADEMIC YEAR)</label>
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
                <button type="submit">HIFADHI MWANAFUNZI</button>
            </form>
            <br/>
            <div style="margin-bottom: 5px; text-align: right;">
                <a href="view_students" style="background: linear-gradient(135deg, #00f3ff, #b026ff); color: white; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; display: inline-block; box-shadow: 0 0 15px rgba(0,243,255,0.3); font-size:13px;">
                    👥 FUNGUA ORODHA & CHAPA MAJINA YA WANAFUNZI 🚀
                </a>
            </div>
        </div>

        <div class="panel" style="grid-column: span 2;">
            <h2>Idhinisha Alama za Mitihani (Approve & Lock Marks)</h2>
            <form action="approve_marks" method="GET">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
                    <div class="input-group">
                        <label>CHAGUA MWAKWA WA MASOMO</label>
                        <select name="academic_year" required>
                            <?php 
                            for ($i = date('Y') - 1; $i <= date('Y') + 2; $i++) {
                                $selected = ($i == date('Y')) ? 'selected' : '';
                                echo "<option value='$i' $selected>$i</option>";
                            }
                            ?>
                        </select>
                    </div>
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
                </div>
                <button type="submit" style="background: linear-gradient(135deg, #00ffcc, var(--neon-purple)); color: #0a0b10;">TAFUTA ALAMA ZILIZOWASILISHWA 🔍</button>
            </form>
        </div>

        <div class="panel" style="grid-column: span 2; border: 1px dashed rgba(0, 243, 255, 0.4);">
            <h2>Angalia Broadsheet ya Matokeo (Broadsheet Reports)</h2>
            <form action="view_broadsheet" method="GET">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
                    <div class="input-group">
                        <label>CHAGUA MWAKA</label>
                        <select name="academic_year" required>
                            <?php 
                            for ($i = date('Y') - 1; $i <= date('Y') + 2; $i++) {
                                $selected = ($i == date('Y')) ? 'selected' : '';
                                echo "<option value='$i' $selected>$i</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>CHAGUA FANI</label>
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
                        <label>MWEZI WA RIPOTI</label>
                        <select name="exam_month" required>
                            <option value="3">Mwezi wa Tatu (March)</option>
                            <option value="6">Mwezi wa Sita (June)</option>
                            <option value="9">Mwezi wa Tisa (September)</option>
                        </select>
                    </div>
                </div>
                <button type="submit" style="background: linear-gradient(135deg, var(--neon-cyan), #0056b3); color: white;">FUNGUA RIPOTI YA BROADSHEET 📊</button>
            </form>
        </div>

    </div>

    <div class="attendance-panel">
        <h2 style="color: var(--neon-orange); margin-bottom: 10px;">📅 Udhibiti na Ripoti ya Mahudhurio</h2>
        <p style="color: #8a8f98; text-transform: none; font-size: 13px; letter-spacing: 0; margin-bottom: 20px;">
            Kagua asilimia (%) ya mahudhurio ya wanafunzi wote darasani ili kubaini waliokidhi vigezo vya 75% kabla ya mitihani ya chuo kuanza.
        </p>
        <a href="attendance_report" class="btn-link" style="background: linear-gradient(135deg, var(--neon-orange), #ff5500); color: white;">
            👁️ FUNGUA RIPOTI KUU YA MAHUDHURIO ➡️
        </a>
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