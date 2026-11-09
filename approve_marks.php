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

$course_id     = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$current_level = isset($_GET['current_level']) ? trim($_GET['current_level']) : '';
$exam_month    = isset($_GET['exam_month']) ? intval($_GET['exam_month']) : 0;

if (!$course_id || empty($current_level) || !$exam_month) {
    echo "<script>alert('Vigezo havijakamilika!'); window.location.href='mratibu_dashboard.php';</script>";
    exit();
}

// Kushughulikia kitendo cha kubonyeza "Approve"
if (isset($_POST['action']) && $_POST['action'] === 'approve_subject') {
    $sub_id_to_approve = intval($_POST['subject_id']);
    
    try {
        // Kugeuza status kuwa 'approved' kwa wanafunzi wote wa kozi hii kwenye somo hili
        $updateStmt = $pdo->prepare("
            UPDATE exam_marks 
            SET status = 'approved' 
            WHERE subject_id = ? AND exam_month = ? 
            AND student_id IN (SELECT id FROM students WHERE course_id = ? AND current_level = ?)
        ");
        $updateStmt->execute([$sub_id_to_approve, $exam_month, $course_id, $current_level]);
        
        echo "<script>alert('🔒 Alama za somo hilo zimeidhinishwa na kufungwa kikamilifu!');</script>";
    } catch (\PDOException $e) {
        die("Kosa la kiufundi: " . $e->getMessage());
    }
}

try {
    // Kuvuta taarifa za kozi
    $courseStmt = $pdo->prepare("SELECT course_name, course_code FROM courses WHERE id = ?");
    $courseStmt->execute([$course_id]);
    $course = $courseStmt->fetch();

    // Kuvuta masomo yote ya kozi hii
    $subjectsStmt = $pdo->prepare("SELECT id, subject_name, subject_type FROM subjects WHERE course_id = ?");
    $subjectsStmt->execute([$course_id]);
    $subjects = $subjectsStmt->fetchAll();

} catch (\PDOException $e) {
    die("Kosa la database: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Idhinisha Alama - FDC SIMS</title>
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
        body { background: radial-gradient(circle at top, #11222e 0%, var(--bg-space) 100%); color: var(--text-main); min-height: 100vh; padding: 30px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .back-btn { color: var(--neon-cyan); text-decoration: none; font-size: 14px; display: inline-block; margin-bottom: 20px; border: 1px solid rgba(0, 243, 255, 0.2); padding: 6px 12px; border-radius: 6px; }
        .info-panel { background: var(--glass-bg); border: 1px solid var(--glass-border); padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; gap: 30px; }
        .info-box span { display: block; font-size: 11px; color: #8a8f98; text-transform: uppercase; }
        .info-box strong { font-size: 16px; color: #fff; }
        .table-panel { background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 16px; padding: 25px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 12px; color: var(--neon-cyan); border-bottom: 2px solid var(--glass-border); font-size: 13px; }
        td { padding: 14px 12px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px; }
        .btn-approve { background: #00ffcc; color: #0a0b10; border: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-approve:hover { box-shadow: 0 0 10px #00ffcc; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>

    <div class="container">
        <a href="mratibu_dashboard" class="back-btn">⬅️ Rudi Kwenye Dashboard</a>

        <div class="info-panel">
            <div class="info-box"><span>Fani</span><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></div>
            <div class="info-box"><span>Darasa / Level</span><strong><?php echo htmlspecialchars($current_level); ?></strong></div>
            <div class="info-box"><span>Kipindi cha Mtihani</span><strong>Mwezi wa <?php echo $exam_month; ?></strong></div>
        </div>

        <div class="table-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Hali ya Masomo (Subject Logs)</h2>
            <a href="view_broadsheet.php?course_id=<?php echo $course_id; ?>&current_level=<?php echo urlencode($current_level); ?>&exam_month=<?php echo $exam_month; ?>" 
               style="background: linear-gradient(135deg, #00f3ff, #b026ff); color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; box-shadow: 0 0 15px rgba(0,243,255,0.3);">
               📊 FUNGUA BROADSHEET YA MATOKEO 🚀
            </a>
        </div><br/>
            <table>
                <thead>
                    <tr>
                        <th>JINA LA SOMO</th>
                        <th>AINA</th>
                        <th style="text-align: center;">HALI (STATUS)</th>
                        <th style="text-align: center;">KITENDO (ACTION)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $subject): 
                        // Kuangalia kama kuna alama zozote zilizoingizwa na mwalimu kwa somo hili na darasa hili
                        $checkStmt = $pdo->prepare("
                            SELECT status FROM exam_marks 
                            WHERE subject_id = ? AND exam_month = ? 
                            AND student_id IN (SELECT id FROM students WHERE course_id = ? AND current_level = ?)
                            LIMIT 1
                        ");
                        $checkStmt->execute([$subject['id'], $exam_month, $course_id, $current_level]);
                        $markStatus = $checkStmt->fetch();

                        if (!$markStatus) {
                            $status_text = 'Bado Haijaingizwa';
                            $badge_style = 'background: rgba(255,255,255,0.05); color: #8a8f98;';
                            $can_approve = false;
                        } elseif ($markStatus['status'] === 'pending') {
                            $status_text = 'Inasubiri Kuidhinishwa (Pending)';
                            $badge_style = 'background: rgba(255, 165, 0, 0.2); color: orange;';
                            $can_approve = true;
                        } else {
                            $status_text = '🔒 Zimefungwa (Approved)';
                            $badge_style = 'background: rgba(0, 255, 0, 0.2); color: #00ff00;';
                            $can_approve = false;
                        }
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                            <td style="color: #a0aec0;"><?php echo htmlspecialchars($subject['subject_type']); ?></td>
                            <td style="text-align: center;">
                                <span class="badge" style="<?php echo $badge_style; ?>"><?php echo $status_text; ?></span>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($can_approve): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="approve_subject">
                                        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                        <button type="submit" class="btn-approve">APPROVE & LOCK 🔒</button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: #555;">Hakuna Kitendo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>