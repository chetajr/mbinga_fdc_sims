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
// 1. ULINZI: Hakikisha aliyefungua ukurasa huu ana haki (Mratibu au Admin/Mkuu wa Chuo)
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'mratibu' && $_SESSION['user_role'] !== 'admin')) {
    header("Location: ../login.php?error=access_denied");
    exit();
}

require_once '../config/db.php';

// --- JINSI YA KUFUNGA KITUFE CHA NYUMA KWA AKILI (DYNAMIC BACK LINK) ---
$back_link = "mratibu_dashboard.php"; // Default ikiwa ni mratibu

if ($_SESSION['user_role'] === 'admin') {
    // Kama ni Mkuu wa chuo (admin), mfumo utamrudisha kwenye dashboard yake salama
    $back_link = "admin_dashboard.php"; 
}
// ---------------------------------------------------------------------

$course_id     = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$current_level = isset($_GET['current_level']) ? trim($_GET['current_level']) : '';
$exam_month    = isset($_GET['exam_month']) ? intval($_GET['exam_month']) : 0;
// Kupokea Mwaka wa Masomo uliotoka kwenye Fomu
$academic_year = isset($_GET['academic_year']) ? intval($_GET['academic_year']) : intval(date('Y'));

if (!$course_id || empty($current_level) || !$exam_month) {
    die("Vigezo havijakamilika kuandaa Broadsheet!");
}

try {
    // 1. Taarifa za Fani
    $courseStmt = $pdo->prepare("SELECT course_name, course_code FROM courses WHERE id = ?");
    $courseStmt->execute([$course_id]);
    $course = $courseStmt->fetch();

    // 2. LOGIC YA MWEZI: Masomo yote ya fani hii (Headers za meza)
    // Kama mwezi ni 3 au 9, tunazuia 'core_practical' isitokee kabisa kwenye ripoti
    if ($exam_month == 3 || $exam_month == 9) {
        $subjectsStmt = $pdo->prepare("
            SELECT id, subject_name, subject_type 
            FROM subjects 
            WHERE course_id = ? AND subject_type != 'core_practical' 
            ORDER BY id ASC
        ");
    } else {
        // Mwezi wa 6 unaonyesha masomo yote (Theory na Practical)
        $subjectsStmt = $pdo->prepare("
            SELECT id, subject_name, subject_type 
            FROM subjects 
            WHERE course_id = ? 
            ORDER BY id ASC
        ");
    }
    $subjectsStmt->execute([$course_id]);
    $subjects = $subjectsStmt->fetchAll();

    if (empty($subjects)) {
        die("<div style='color:white; background:#ff4b4b; padding:15px; border-radius:8px; text-align:center;'>⚠️ Hakuna masomo yaliyopatikana kulingana na vigezo vya mwezi huu!</div>");
    }

    // 3. Orodha ya Wanafunzi wa fani hii, level hii NA MWAKA HUU WA MASOMO
    $studentsStmt = $pdo->prepare("
        SELECT id, reg_number, CONCAT(first_name, ' ', IFNULL(middle_name,''), ' ', last_name) AS fullname 
        FROM students 
        WHERE course_id = ? AND current_level = ? AND academic_year = ?
        ORDER BY fullname ASC
    ");
    $studentsStmt->execute([$course_id, $current_level, $academic_year]);
    $students = $studentsStmt->fetchAll();

    // 4. Kuandaa Broadsheet Data
    $broadsheet_data = [];

    foreach ($students as $student) {
        $student_id = $student['id'];
        $scores = [];
        $total_score = 0;
        $subject_count = 0;

        foreach ($subjects as $subject) {
            $sub_id = $subject['id'];
            
            // Kuvuta alama kutoka kwenye jedwali la marks
            $marksStmt = $pdo->prepare("
                SELECT marks FROM exam_marks 
                WHERE student_id = ? AND subject_id = ? AND exam_month = ? AND academic_year = ?
            ");
            $marksStmt->execute([$student_id, $sub_id, $exam_month, $academic_year]);
            $mark = $marksStmt->fetchColumn();

            if ($mark !== false && $mark !== null) {
                $score_val = floatval($mark);
                $scores[$sub_id] = $score_val;
                $total_score += $score_val;
                $subject_count++;
            } else {
                $scores[$sub_id] = null; // Mwanafunzi hana alama bado
            }
        }

        // Kokotoa Wastani (Average)
        $average = $subject_count > 0 ? round($total_score / $subject_count, 1) : 0;

        // Kupata Daraja (Grade) kulingana na wastani
        if ($average >= 75) { $grade = 'DISTINCTION'; $color = '#00ff80'; }
        elseif ($average >= 65) { $grade = 'CREDIT'; $color = '#00f3ff'; }
        elseif ($average >= 50) { $grade = 'PASS'; $color = '#ffaa00'; }
        else { $grade = 'FAIL'; $color = '#ff4b4b'; }

        $broadsheet_data[] = [
            'reg_number' => $student['reg_number'],
            'name'       => $student['fullname'],
            'scores'     => $scores,
            'total'      => $total_score,
            'average'    => $average,
            'grade'      => $grade,
            'color'      => $color
        ];
    }

} catch (\PDOException $e) {
    die("Hitilafu ya Kiufundi: " . $e->getMessage());
}

// Tafsiri ya Jina la Mwezi kwa ajili ya Kichwa cha Habari
$miezi = [3 => "MARCH (MTIHANI WA KWANZA)", 6 => "JUNE (MID-YEAR EXAMINATION)", 9 => "SEPTEMBER (MTIHANI WA TATU)"];
$jina_la_mwezi = $miezi[$exam_month] ?? "UNKNOWN";
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <title>Broadsheet — <?php echo htmlspecialchars($course['course_code']); ?></title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 30px; background-color: #f8fafc; color: #334155; }
        .back-panel { margin-bottom: 20px; }
        .back-btn { background: #0f172a; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block; }
        .back-btn:hover { background: #1e293b; }
        .report-header { text-align: center; margin-bottom: 30px; border-bottom: 3px double #cbd5e1; padding-bottom: 15px; }
        .report-header h2 { margin: 0; font-size: 24px; color: #1e293b; text-transform: uppercase; }
        .report-header p { margin: 5px 0 0 0; font-size: 14px; color: #64748b; font-weight: 600; letter-spacing: 1px; }
        
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 20px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: center; font-size: 13px; }
        th, td { border: 1px solid #e2e8f0; padding: 10px 8px; }
        th { background-color: #f1f5f9; color: #1e293b; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .left-text { text-align: left; font-weight: 600; }
        .highlight { background-color: #f8fafc; font-weight: bold; }
        .print-btn { background: #059669; color: white; padding: 10px 20px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; float: right; }
        .print-btn:hover { background: #047857; }

        /* --- MBINU YA NEPI/WATERMARK YA MBINGA FDC YA KISASA --- */
        .broadsheet-wrapper {
            position: relative;
            width: 100%;
        }
        /* Hii inatengeneza nembo ya nyuma isiyoingiliana na jedwali */
        .broadsheet-wrapper::before {
            content: "";
            position: fixed;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 4200px;
            height: 920px;
            background-image: url('../assets/images/logo.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.04; /* Laini sana kwenye screen isisumbue macho */
            pointer-events: none;
            z-index: 0;
        }

        @media print { 
            .back-panel, .print-btn { display: none; } 
            body { margin: 10px; } 
            
            /* Lazimisha kivinjari kitoe picha ya background kwenye karatasi */
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            /* Kwenye karatasi inaongezewa weusi kidogo (0.06) ili wino wa printer uitoe vizuri */
            .broadsheet-wrapper::before {
                opacity: 0.06;
            }
        }
    </style>
</head>
<body>

    <div class="back-panel">
        <a href="<?php echo $back_link; ?>" class="back-btn">⬅️ RUDI DASHBOARD</a>
        <button class="print-btn" onclick="window.print()">🖨️ CHAPA RIPOTI (PRINT)</button>
    </div>

    <div class="broadsheet-wrapper">

        <div class="report-header">
            <h2>MBINGA FIELD DEVELOPMENT COLLEGE (FDC)</h2>
            <p>OFFICIAL BROADSHEET — <?php echo htmlspecialchars($course['course_name'] . " (" . $course['course_code'] . ")"); ?></p>
            <p>NGAZI: <?php echo htmlspecialchars($current_level); ?> | MTIHANI: <?php echo htmlspecialchars($jina_la_mwezi); ?> | MWAKA: <?php echo $academic_year; ?></p>
        </div>

        <div class="table-container">
            <table style="position: relative; z-index: 1; background: transparent;">
                <thead>
                    <tr>
                        <th width="5%">SN</th>
                        <th width="15%">REG NUMBER</th>
                        <th width="25%">STUDENT FULL NAME</th>
                        <?php foreach ($subjects as $subject): ?>
                            <th>
                                <?php 
                                // Kifupi cha aina ya somo kusaidia upana wa meza
                                $short_type = $subject['subject_type'] === 'core_theory' ? 'TH' : ($subject['subject_type'] === 'core_practical' ? 'PR' : 'SUP');
                                echo htmlspecialchars($subject['subject_name'] . " ($short_type)"); 
                                ?>
                            </th>
                        <?php endforeach; ?>
                        <th class="highlight">TOTAL</th>
                        <th class="highlight">Average</th>
                        <th class="highlight">Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $position = 1; 
                    foreach ($broadsheet_data as $row): 
                    ?>
                        <tr>
                            <td class="highlight"><?php echo $position++; ?></td>
                            <td style="font-family: monospace; color: #475569; font-weight: 500;"><?php echo htmlspecialchars($row['reg_number']); ?></td>
                            <td class="left-text" style="padding-left: 10px; color: #1e293b;"><?php echo htmlspecialchars($row['name']); ?></td>
                            
                            <?php foreach ($subjects as $subject): 
                                $score = $row['scores'][$subject['id']];
                            ?>
                                <td style="<?php echo $score !== null && $score < 50 ? 'color: red; font-weight: bold;' : ''; ?>">
                                    <?php echo $score !== null ? $score : '-'; ?>
                                </td>
                            <?php endforeach; ?>

                            <td class="highlight"><?php echo $row['total']; ?></td>
                            <td class="highlight" style="color: #2563eb;"><?php echo $row['average']; ?>%</td>
                            <td class="highlight" style="color: <?php echo $row['color']; ?>;"><?php echo $row['grade']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($broadsheet_data)): ?>
                        <tr>
                            <td colspan="<?php echo (3 + count($subjects) + 3); ?>" style="padding: 20px; color: #64748b;">
                                Hakuna wanafunzi waliosajiliwa kwenye darasa hili kwa mwaka wa masomo uliochaguliwa.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>