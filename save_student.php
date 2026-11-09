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

// 2. Kuvuta muunganisho wa Database
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Kupokea na kusafisha data zote kuwa herufi kubwa (Uppercase) ili zilingane na karatasi yako ya matokeo
    $first_name    = strtoupper(trim($_POST['first_name']));
    $middle_name   = !empty($_POST['middle_name']) ? strtoupper(trim($_POST['middle_name'])) : null;
    $last_name     = strtoupper(trim($_POST['last_name']));
    $reg_number    = strtoupper(trim($_POST['reg_number']));
    $phone_number  = trim($_POST['phone_number']);
    $course_id     = intval($_POST['course_id']);
    $current_level = trim($_POST['current_level']);
    $academic_year = trim($_POST['academic_year']);

    // Uhakiki wa mambo ya msingi
    if (empty($first_name) || empty($last_name) || empty($reg_number) || empty($course_id) || empty($current_level)) {
        echo "<script>alert('Tafadhali jaza nafasi zote za msingi!'); window.location.href='mratibu_dashboard.php';</script>";
        exit();
    }

    try {
        // 3. Kuangalia kama Reg Number hii tayari imeshatumika kwa mwanafunzi mwingine
        $checkReg = $pdo->prepare("SELECT id FROM students WHERE reg_number = :reg_number LIMIT 1");
        $checkReg->execute(['reg_number' => $reg_number]);

        if ($checkReg->rowCount() > 0) {
            echo "<script>alert('⚠️ Kosa: Namba ya Usajili [ $reg_number ] tayari imeshasajiliwa kwa mwanafunzi mwingine!'); window.location.href='mratibu_dashboard.php';</script>";
            exit();
        }

        // 4. Kuandika query ya kuingiza mwanafunzi mpya (Insert Query)
        $sql = "INSERT INTO students (reg_number, first_name, middle_name, last_name, phone_number, course_id, current_level, academic_year) 
                VALUES (:reg_number, :first_name, :middle_name, :last_name, :phone_number, :course_id, :current_level, :academic_year)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'reg_number'    => $reg_number,
            'first_name'    => $first_name,
            'middle_name'   => $middle_name,
            'last_name'     => $last_name,
            'phone_number'  => $phone_number,
            'course_id'     => $course_id,
            'current_level' => $current_level,
            'academic_year' => $academic_year
        ]);

        echo "<script>alert('🚀 Mwanafunzi [ ' + '$first_name ' + ('$middle_name' ? '$middle_name ' : '') + '$last_name' + ' ] amesajiliwa kikamilifu!'); window.location.href='mratibu_dashboard.php';</script>";
        exit();

    } catch (\PDOException $e) {
        die("Kuna tatizo limejitokeza kwenye database: " . $e->getMessage());
    }
} else {
    header("Location: mratibu_dashboard.php");
    exit();
}