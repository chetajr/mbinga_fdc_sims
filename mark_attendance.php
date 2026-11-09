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

// ULINZI: Hakikisha aliyelogin ni mwalimu
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

require_once '../config/db.php';

$teacher_id = $_SESSION['user_id'];
$success = isset($_GET['success']) ? "Mahudhurio yamehifadhiwa kikamilifu!" : "";
$error = isset($_GET['error']) ? "Kosa: Hakikisha umechagua vigezo vyote kwa usahihi!" : "";

// 1. KUVUTA MASOMO ALIYOPANGIWA MWALIMU
try {
    $sub_stmt = $pdo->prepare("
        SELECT s.id, s.subject_name, c.course_code 
        FROM teacher_subjects ts
        JOIN subjects s ON ts.subject_id = s.id
        JOIN courses c ON s.course_id = c.id
        WHERE ts.teacher_id = ?
    ");
    $sub_stmt->execute([$teacher_id]);
    $assigned_subjects = $sub_stmt->fetchAll();
} catch (\PDOException $e) {
    die("Kosa la kiufundi: " . $e->getMessage());
}

// 2. KUSHUGHULIKIA CHAGUZI ZA SOMO NA TAREHE
$selected_subject = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
$attendance_date = isset($_POST['attendance_date']) ? $_POST['attendance_date'] : date('Y-m-d');
$students = [];
$is_already_marked = false; // Flag ya kuangalia kama amesharatibu mahudhurio ya siku hiyo

if ($selected_subject > 0) {
    try {
        // HATUA YA KWANZA: Cheki kama mahudhurio ya tarehe hii na somo hili yameshawahi kuingizwa
        $check_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM student_attendance 
            WHERE subject_id = ? AND attendance_date = ?
        ");
        $check_stmt->execute([$selected_subject, $attendance_date]);
        
        if ($check_stmt->fetchColumn() > 0) {
            $is_already_marked = true; // Ndio, tayari alishajaza!
        }

        // HATUA YA PILI: Vuta orodha ya wanafunzi wa kozi husika
        $details_stmt = $pdo->prepare("SELECT course_id FROM subjects WHERE id = ?");
        $details_stmt->execute([$selected_subject]);
        $sub_details = $details_stmt->fetch();

        if ($sub_details) {
            $st_stmt = $pdo->prepare("
                SELECT id, reg_number, first_name, last_name, academic_year 
                FROM students 
                WHERE course_id = ?
                ORDER BY first_name ASC
            ");
            $st_stmt->execute([$sub_details['course_id']]);
            $students = $st_stmt->fetchAll();
        }
    } catch (\PDOException $e) {
        die("Kosa la kiufundi: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <title>Sajili Mahudhurio — SIMS</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 30px; background-color: #0f172a; color: #e2e8f0; }
        .panel { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 25px; border-radius: 12px; max-width: 900px; margin: 0 auto; }
        h1 { font-size: 20px; color: #00f3ff; margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; margin-bottom: 20px; align-items: flex-end; }
        select, input[type="date"] { width: 100%; padding: 10px; background: #1e293b; border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 6px; }
        button { background: #b026ff; color: white; border: none; padding: 10px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; }
        button:hover { background: #9333ea; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left; }
        th { color: #ffaa00; font-size: 11px; text-transform: uppercase; }
        .radio-group { display: flex; gap: 15px; }
        .radio-label { display: flex; align-items: center; gap: 5px; cursor: pointer; }
        .btn-save { background: #00ff80; color: #0a0b10; width: 100%; padding: 12px; margin-top: 20px; font-size: 16px; font-weight: bold; border: none; border-radius: 6px; cursor: pointer; }
        .btn-save:disabled { background: #334155 !important; color: #64748b !important; cursor: not-allowed; box-shadow: none !important; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: bold; }
        .success { background: rgba(0,255,128,0.1); border: 1px solid #00ff80; color: #00ff80; }
        .warning { background: rgba(255,170,0,0.1); border: 1px solid #ffaa00; color: #ffaa00; text-align: center; }
    </style>
</head>
<body>

    <div class="panel">
        <a href="teacher_dashboard" style="color: #cbd5e1; text-decoration: none; font-size: 14px;">⬅️ Rudi Dashboard</a>
        <h1 style="margin-top: 15px;">REKODI MAHUDHURIO YA WANAFUNZI</h1>

        <?php if($success): ?> 
            <div class="alert success"><?php echo $success; ?></div> 
        <?php endif; ?>

        <form method="POST" action="mark_attendance">
            <div class="form-grid">
                <div>
                    <label style="font-size: 12px; color: #94a3b8;">CHAGUA SOMO LAKO</label>
                    <select name="subject_id" required onchange="this.form.submit()">
                        <option value="">-- Chagua Somo --</option>
                        <?php foreach ($assigned_subjects as $sub): ?>
                            <option value="<?php echo $sub['id']; ?>" <?php echo $selected_subject === intval($sub['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sub['course_code'] . ' - ' . $sub['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size: 12px; color: #94a3b8;">TAREHE</label>
                    <input type="date" name="attendance_date" value="<?php echo htmlspecialchars($attendance_date); ?>" required onchange="this.form.submit()">
                </div>
                <div>
                    <button type="submit">PAKIA WANAFUNZI 🔄</button>
                </div>
            </div>
        </form>

        <?php if ($selected_subject > 0 && !empty($students)): ?>
            
            <?php if ($is_already_marked): ?>
                <div class="alert warning">
                    ⚠️ MAHUDHURIO YA SOMO HILI KWA TAREHE (<?php echo htmlspecialchars($attendance_date); ?>) TAYARI YAMESHAHIFADHIWA! HAURUHUSIWI KUJAZA TENA MARA MBILI.
                </div>
            <?php endif; ?>

            <form method="POST" action="save_attendance">
                <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($attendance_date); ?>">
                
                <table>
                    <thead>
                        <tr>
                            <th>REG NUMBER</th>
                            <th>JINA KAMILI</th>
                            <th width="30%">HALI YA MAHUDHURIO (STATUS)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $st): ?>
                            <input type="hidden" name="academic_year[<?php echo $st['id']; ?>]" value="<?php echo $st['academic_year']; ?>">
                            <tr>
                                <td style="font-family: monospace;"><?php echo htmlspecialchars($st['reg_number']); ?></td>
                                <td><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></td>
                                <td>
                                    <div class="radio-group">
                                        <label class="radio-label" style="color: #00ff80;">
                                            <input type="radio" name="status[<?php echo $st['id']; ?>]" value="Present" checked <?php echo $is_already_marked ? 'disabled' : ''; ?>> YUPO ✔️
                                        </label>
                                        <label class="radio-label" style="color: #ff4b4b;">
                                            <input type="radio" name="status[<?php echo $st['id']; ?>]" value="Absent" <?php echo $is_already_marked ? 'disabled' : ''; ?>> HAYUPO ❌
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <button type="submit" class="btn-save" <?php echo $is_already_marked ? 'disabled' : ''; ?>>
                    <?php echo $is_already_marked ? 'MAHUDHURIO YALISHAFUNGWA 🔒' : 'HIFADHI MAHUDHURIO YA LEO 💾'; ?>
                </button>
            </form>
        <?php elseif ($selected_subject > 0): ?>
            <p style="text-align: center; margin-top: 30px; color: #94a3b8;">Hakuna wanafunzi waliosajiliwa kwenye fani ya somo hili.</p>
        <?php endif; ?>
    </div>

</body>
</html>