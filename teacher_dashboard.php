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

// 2. Kuvuta muunganisho wa Database ili tuanze kazi
require_once '../config/db.php';

$teacher_id = $_SESSION['user_id'];

try {
    /* MABORESHO MAKUBWA YA SQL QUERY:
      Tunatafuta Fani (Courses) ambazo huyu mwalimu ana masomo nazo pekee.
      Hii inazuia mwalimu kuona fani ambazo hazimuhusu kabisa!
    */
    $courses_stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.course_name, c.course_code 
        FROM courses c
        INNER JOIN subjects s ON s.course_id = c.id
        INNER JOIN teacher_subjects ts ON ts.subject_id = s.id
        WHERE ts.teacher_id = ?
        ORDER BY c.course_code ASC
    ");
    $courses_stmt->execute([$teacher_id]);
    $my_courses = $courses_stmt->fetchAll();

    /*
      MABORESHO YA MASOMO (SUBJECTS):
      Tunavuta masomo yote aliyopangiwa mwalimu huyu ili JavaScript iyafanyie uchujaji (Filter) 
      kulingana na Fani na Mwezi utakaoruhusiwa.
    */
    $subjects_stmt = $pdo->prepare("
        SELECT s.id, s.subject_name, s.subject_type, s.course_id 
        FROM subjects s
        INNER JOIN teacher_subjects ts ON ts.subject_id = s.id
        WHERE ts.teacher_id = ?
        ORDER BY s.subject_name ASC
    ");
    $subjects_stmt->execute([$teacher_id]);
    $all_subjects = $subjects_stmt->fetchAll();

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
            --bg-space: #07080d;
            --body-gradient: radial-gradient(circle at top, #0f1a2c 0%, var(--bg-space) 100%);
            --neon-cyan: #00f3ff;
            --neon-purple: #b026ff;
            --neon-orange: #ff9900;
            --text-main: #e2e8f0;
            --glass-bg: rgba(255, 255, 255, 0.02);
            --glass-border: rgba(255, 255, 255, 0.07);
            --input-bg: rgba(0, 0, 0, 0.4);
        }

        /* --- THEME MPYA: LIGHT MODE (WHITE & BLUE) --- */
        [data-theme="light"] {
            --bg-space: #f0f4f8;
            --body-gradient: radial-gradient(circle at top, #ffffff 0%, #dbeafe 100%); /* Muonekano mpya wa White & Blue */
            --neon-cyan: #2563eb;   /* Bright Blue */
            --neon-purple: #1d4ed8; /* Darker Blue */
            --neon-orange: #ea580c; /* Orange thabiti */
            --text-main: #1e293b;   /* Dark Slate Text */
            --glass-bg: #ffffff;    /* Solid White Card */
            --glass-border: #cbd5e1; /* Light Border nadhifu */
            --input-bg: #ffffff;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; transition: background 0.3s, color 0.3s, border-color 0.3s; }
        body { background: var(--body-gradient); color: var(--text-main); min-height: 100vh; padding: 30px; }
        
        .header-nav { display: flex; justify-content: space-between; align-items: center; background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--glass-border); padding: 20px 40px; border-radius: 16px; margin-bottom: 30px; }
        h1 { font-size: 24px; background: linear-gradient(to right, var(--neon-cyan), var(--neon-purple)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
        p { font-size: 12px; color: #8a8f98; letter-spacing: 2px; text-transform: uppercase; margin-top: 5px; }
        .logout-btn { color: #ff4b4b; text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid rgba(255, 75, 75, 0.2); padding: 8px 16px; border-radius: 8px; transition: all 0.3s; }
        .logout-btn:hover { background: rgba(255, 75, 75, 0.1); box-shadow: 0 0 15px rgba(255, 75, 75, 0.2); }
        
        .panel { background: var(--glass-bg); backdrop-filter: blur(15px); border: 1px solid var(--glass-border); border-radius: 20px; padding: 40px; max-width: 900px; margin: 0 auto 30px auto; }
        h2 { font-size: 20px; margin-bottom: 25px; color: var(--neon-cyan); letter-spacing: 0.5px; text-transform: uppercase; text-align: center; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .input-group { margin-bottom: 20px; }
        .full-width { grid-column: span 2; }
        
        label { display: block; font-size: 12px; color: #a0aec0; margin-bottom: 8px; font-weight: 600; letter-spacing: 0.5px; }
        input, select { width: 100%; padding: 14px; background: var(--input-bg); border: 1px solid var(--glass-border); border-radius: 10px; color: var(--text-main); font-size: 14px; font-weight: 500; }
        input:focus, select:focus { outline: none; border-color: var(--neon-purple); box-shadow: 0 0 15px rgba(176, 38, 255, 0.25); }
        select option { background: var(--bg-space); color: var(--text-main); }
        
        button { width: 100%; padding: 15px; background: linear-gradient(135deg, var(--neon-cyan), var(--neon-purple)); border: none; border-radius: 10px; color: white; font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 15px; transition: 0.3s; text-transform: uppercase; letter-spacing: 0.5px; }
        button:hover { box-shadow: 0 0 25px rgba(0, 243, 255, 0.45); transform: translateY(-1px); }

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
            <p>PANEL YA MAALIMU WA FANI (ACADEMICS)</p>
        </h1>
        <span>Mwalimu: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
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

    <div class="panel">
        <h2>Ingiza Alama za Mitihani ya Wanafunzi</h2>
        
        <form action="enter_marks" method="GET">
            <div class="form-grid">
                
                <div class="input-group">
                    <label>CHAGUA FANI / KOZI YAKO</label>
                    <select name="course_id" id="courseSelect" onchange="filterSubjects()" required>
                        <option value="">-- Chagua Fani --</option>
                        <?php foreach ($my_courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['course_code'] . " - " . $course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>CHAGUA LEVEL YA DARASA</label>
                    <select name="current_level" required>
                        <option value="Level I">Level I</option>
                        <option value="Level II">Level II</option>
                        <option value="N/A">N/A (Kwa ECD au kozi fupi)</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>MTIHANI WA MWEZI GANI?</label>
                    <select name="exam_month" id="monthSelect" onchange="filterSubjects()" required>
                        <option value="">-- Chagua Mwezi --</option>
                        <option value="3">Mwezi wa Tatu (March Exam)</option>
                        <option value="6">Mwezi wa Sita (Terminal Exam)</option>
                        <option value="9">Mwezi wa Tisa (September Exam)</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>SOMO UNALOFUNDISHA (SUBJECT)</label>
                    <select name="subject_id" id="subjectSelect" required>
                        <option value="">-- Chagua Somo --</option>
                    </select>
                </div>

                <div class="input-group full-width">
                    <label>MWAKA WA MASOMO (ACADEMIC YEAR)</label>
                    <select name="academic_year" required>
                        <?php 
                        $mwaka_sasa = date('Y');
                        for ($i = $mwaka_sasa - 1; $i <= $mwaka_sasa + 2; $i++) {
                            $selected = ($i == $mwaka_sasa) ? 'selected' : '';
                            echo "<option value='$i' $selected>$i</option>";
                        }
                        ?>
                    </select>
                </div>

            </div>

            <button type="submit">FUNGUA ORODHA YA WANAFUNZI 🔍</button>
        </form>
    </div>

    <div class="panel" style="border: 1px solid rgba(255, 153, 0, 0.2);">
        <h2 style="color: var(--neon-orange);">📅 Rejesta ya Mahudhurio ya Wanafunzi (Attendance)</h2>
        <form action="mark_attendance" method="GET">
            <div class="form-grid">
                <div class="input-group">
                    <label>CHAGUA FANI / KOZI</label>
                    <select name="course_id" required>
                        <option value="">-- Chagua Fani --</option>
                        <?php foreach ($my_courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['course_code'] . " - " . $course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>CHAGUA LEVEL YA DARASA</label>
                    <select name="current_level" required>
                        <option value="Level I">Level I</option>
                        <option value="Level II">Level II</option>
                        <option value="N/A">N/A (Kwa ECD au kozi fupi)</option>
                    </select>
                </div>

                <div class="input-group full-width">
                    <label>TAREHE YA LEO / YA MAHUDHURIO</label>
                    <input type="date" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            <button type="submit" style="background: linear-gradient(135deg, var(--neon-orange), #ff5500);">FUNGUA REJESTA YA DARASA 📝</button>
        </form>
    </div>

    <script>
        const allSubjects = <?php echo json_encode($all_subjects); ?>;

        function filterSubjects() {
            const courseSelect = document.getElementById('courseSelect');
            const monthSelect = document.getElementById('monthSelect');
            const subjectSelect = document.getElementById('subjectSelect');
            
            const selectedCourseId = courseSelect.value;
            const selectedMonth = monthSelect.value;

            subjectSelect.innerHTML = '<option value="">-- Chagua Somo --</option>';

            if (!selectedCourseId || !selectedMonth) return;

            const filtered = allSubjects.filter(sub => {
                if (sub.course_id != selectedCourseId) return false;
                if ((selectedMonth == "3" || selectedMonth == "9") && sub.subject_type === 'core_practical') {
                    return false; 
                }
                return true; 
            });

            filtered.forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub.id;
                
                let typeText = sub.subject_type === 'core_theory' ? 'Theory' : (sub.subject_type === 'core_practical' ? 'Practical' : 'Support');
                opt.textContent = `${sub.subject_name} (${typeText})`;
                
                subjectSelect.appendChild(opt);
            });
        }

        window.onload = filterSubjects;

        // --- CODE YA KUSWIPU THEME (DARK TO LIGHT) ---
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