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

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

require_once '../config/db.php';

$course_id     = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$current_level = isset($_GET['current_level']) ? trim($_GET['current_level']) : '';
$exam_month    = isset($_GET['exam_month']) ? intval($_GET['exam_month']) : 0;
$subject_id    = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

if (!$course_id || empty($current_level) || !$exam_month || !$subject_id) {
    echo "<script>alert('Vigezo havijakamilika!'); window.location.href='teacher_dashboard.php';</script>";
    exit();
}

try {
    $courseStmt = $pdo->prepare("SELECT course_name, course_code FROM courses WHERE id = ?");
    $courseStmt->execute([$course_id]);
    $course = $courseStmt->fetch();

    $subjectStmt = $pdo->prepare("SELECT subject_name FROM subjects WHERE id = ?");
    $subjectStmt->execute([$subject_id]);
    $subject = $subjectStmt->fetch();

    // Kuvuta wanafunzi
    $studentsStmt = $pdo->prepare("SELECT id, reg_number, first_name, middle_name, last_name FROM students WHERE course_id = ? AND current_level = ? ORDER BY first_name ASC");
    $studentsStmt->execute([$course_id, $current_level]);
    $students = $studentsStmt->fetchAll();

    // Kuvuta alama zote zilizopo kwa darasa hili na somo hili kwa mwezi huu
    $existingMarksStmt = $pdo->prepare("SELECT student_id, marks, status FROM exam_marks WHERE subject_id = ? AND exam_month = ?");
    $existingMarksStmt->execute([$subject_id, $exam_month]);
    $existing_marks = [];
    
    // Kuziweka kwenye array ili iwe rahisi kuzitafuta kwa student_id
    while ($row = $existingMarksStmt->fetch()) {
        $existing_marks[$row['student_id']] = [
            'marks' => $row['marks'],
            'status' => $row['status']
        ];
    }

} catch (\PDOException $e) {
    die("Kosa la database: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingiza Alama - FDC SIMS</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        :root {
            --bg-space: #0a0b10;
            --neon-cyan: #00f3ff;
            --neon-purple: #b026ff;
            --text-main: #e2e8f0;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: radial-gradient(circle at top, #151124 0%, var(--bg-space) 100%); color: var(--text-main); min-height: 100vh; padding: 30px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .back-btn { color: var(--neon-cyan); text-decoration: none; font-size: 14px; display: inline-block; margin-bottom: 20px; border: 1px solid rgba(0, 243, 255, 0.2); padding: 6px 12px; border-radius: 6px; }
        .info-panel { background: var(--glass-bg); border: 1px solid var(--glass-border); padding: 20px; border-radius: 12px; margin-bottom: 25px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .info-box span { display: block; font-size: 11px; color: #8a8f98; text-transform: uppercase; }
        .info-box strong { font-size: 16px; color: #fff; }
        .table-panel { background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 16px; padding: 25px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 12px; color: var(--neon-cyan); border-bottom: 2px solid var(--glass-border); font-size: 13px; }
        td { padding: 14px 12px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px; }
        .marks-input { width: 80px; padding: 8px; background: rgba(0,0,0,0.4); border: 1px solid var(--glass-border); border-radius: 6px; color: #00f3ff; font-weight: bold; text-align: center; font-size: 15px; }
        .marks-input:disabled { background: rgba(255,0,0,0.1); border-color: rgba(255,0,0,0.3); color: #ff6b6b; cursor: not-allowed; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-pending { background: rgba(255, 165, 0, 0.2); color: orange; }
        .badge-approved { background: rgba(0, 255, 0, 0.2); color: #00ff00; }
        .save-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, var(--neon-cyan), var(--neon-purple)); border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; margin-top: 25px; }
        .save-btn:disabled { background: #333; color: #777; cursor: not-allowed; box-shadow: none; }
    </style>
</head>
<body>

    <div class="container">
        <a href="teacher_dashboard" class="back-btn">⬅️ Rudi Kwenye Uchaguzi</a>

        <div class="info-panel">
            <div class="info-box"><span>Fani / Kozi</span><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></div>
            <div class="info-box"><span>Darasa / Level</span><strong><?php echo htmlspecialchars($current_level); ?></strong></div>
            <div class="info-box"><span>Somo</span><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></div>
            <div class="info-box"><span>Kipindi</span><strong>Mwezi wa <?php echo $exam_month; ?></strong></div>
        </div>

        <div class="table-panel">
            <form action="save_marks" method="POST">
                <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                <input type="hidden" name="exam_month" value="<?php echo $exam_month; ?>">

                <?php if (count($students) > 0): 
                    $all_locked = true; // Kujua kama darasa zima limefungwa
                ?>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">S/N</th>
                                <th>NAMBA YA USAJILI</th>
                                <th>MAJINA KAMILI</th>
                                <th style="text-align: center;">HALI (STATUS)</th>
                                <th style="width: 120px; text-align: center;">ALAMA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sn = 1; foreach ($students as $student): 
                                // Kuangalia kama mwanafunzi huyu ana alama tayari
                                $has_marks = isset($existing_marks[$student['id']]);
                                $score_val = $has_marks ? $existing_marks[$student['id']]['marks'] : '';
                                $status = $has_marks ? $existing_marks[$student['id']]['status'] : 'new';
                                
                                $is_disabled = ($status === 'approved') ? 'disabled' : '';
                                if ($status !== 'approved') { $all_locked = false; }
                            ?>
                                <tr>
                                    <td><?php echo $sn++; ?></td>
                                    <td style="color: #a0aec0; font-family: monospace;"><?php echo htmlspecialchars($student['reg_number']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?></strong></td>
                                    <td style="text-align: center;">
                                        <?php if ($status === 'new'): ?>
                                            <span class="badge" style="background: rgba(255,255,255,0.05); color: #8a8f98;">Sio Kingizwa</span>
                                        <?php elseif ($status === 'pending'): ?>
                                            <span class="badge badge-pending">Pending Approval</span>
                                        <?php echo ""; else: ?>
                                            <span class="badge badge-approved">🔒 Approved (Locked)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <input type="number" name="marks[<?php echo $student['id']; ?>]" 
                                               class="marks-input" min="0" max="100" 
                                               value="<?php echo $score_val; ?>" 
                                               placeholder="-" required <?php echo $is_disabled; ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <button type="submit" class="save-btn" <?php echo $all_locked ? 'disabled' : ''; ?>>
                        <?php echo $all_locked ? 'MATOKEO YAMEFUNGWA (APPROVED) 🔒' : 'HIFADHI / SASISHA ALAMA 💾'; ?>
                    </button>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #8a8f98;">⚠️ Hakuna mwanafunzi.</div>
                <?php endif; ?>
            </form>
        </div>
    </div>

</body>
</html>