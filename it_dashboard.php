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

// ULINZI: Hakikisha mtu aliyefungua ukurasa huu amelogin na ni IT kweli
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'it') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

require_once '../config/db.php';

// -------------------------------------------------------------------------
// INJINI MPYA: KUSHUGHULIKIA KUPOST MATUKIO, MATANGAZO NA SLIDER YA WEBSITE
// -------------------------------------------------------------------------
$web_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. KUSHUGHULIKIA FOMU YA MATUKIO (YENYE PICHA)
    if (isset($_POST['posti_tukio'])) {
        $kichwa = trim($_POST['kichwa_cha_habari']);
        $maelezo = trim($_POST['maelezo']);
        
        $picha_jina = $_FILES['picha']['name'];
        $picha_tmp = $_FILES['picha']['tmp_name'];
        
        // NJIA YA UHAKIKA (Absolute Path): Inatafuta htdocs/FDC_WEBSITE/uploads/ moja kwa moja
        $folda_la_picha = $_SERVER['DOCUMENT_ROOT'] . "/FDC_WEBSITE/uploads/"; 
        
        if (empty($kichwa) || empty($maelezo) || empty($picha_jina)) {
            $web_msg = "<script>alert('❌ Tafadhali jaza uga zote na uchague picha ya tukio!');</script>";
        } else {
            // Kutengeneza jina la kipekee la picha
            $picha_mpya_jina = time() . "_" . basename($picha_jina);
            $target_file = $folda_la_picha . $picha_mpya_jina;
            
            if (!is_dir($folda_la_picha)) {
                mkdir($folda_la_picha, 0777, true);
            }
            
            if (move_uploaded_file($picha_tmp, $target_file)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO matukio (kichwa_cha_habari, maelezo, picha) VALUES (?, ?, ?)");
                    $stmt->execute([$kichwa, $maelezo, $picha_mpya_jina]);
                    $web_msg = "<script>alert('🚀 HODARI! Tukio jipya limechapishwa rasmi na picha yake kwenye website ya chuo!'); window.location.href='it_dashboard.php';</script>";
                } catch (\PDOException $e) {
                    $web_msg = "<script>alert('❌ Kosa la database: " . addslashes($e->getMessage()) . "');</script>";
                }
            } else {
                $web_msg = "<script>alert('❌ Imeshindikana kuhamisha picha kwenda folda la website. Hakikisha folder la FDC_WEBSITE/uploads lipo.');</script>";
            }
        }
    }

    // B. KUSHUGHULIKIA FOMU YA MATANGAZO (YENYE ATTACHMENT YA PDF)
    if (isset($_POST['posti_tangazo'])) {
        $kichwa_tangazo = trim($_POST['kichwa_cha_tangazo']);
        $maelezo_tangazo = trim($_POST['maelezo_tangazo']);
        
        $pdf_jina = $_FILES['fomu_pdf']['name'];
        $pdf_tmp = $_FILES['fomu_pdf']['tmp_name'];
        
        // NJIA YA UHAKIKA (Absolute Path): Inatafuta htdocs/FDC_WEBSITE/uploads/ moja kwa moja
        $folda_la_files = $_SERVER['DOCUMENT_ROOT'] . "/FDC_WEBSITE/uploads/"; 

        if (empty($kichwa_tangazo) || empty($maelezo_tangazo)) {
            $web_msg = "<script>alert('❌ Tafadhali jaza kichwa na maelezo ya tangazo!');</script>";
        } else {
            $pdf_mpya_jina = null;

            // Kama IT ameweka faili la fomu (PDF)
            if (!empty($pdf_jina)) {
                $pdf_mpya_jina = "fomu_" . time() . "_" . basename($pdf_jina);
                $target_pdf = $folda_la_files . $pdf_mpya_jina;
                
                if (!is_dir($folda_la_files)) {
                    mkdir($folda_la_files, 0777, true);
                }
                
                // Hamisha faili la PDF kwenda folda la uploads la website
                if (!move_uploaded_file($pdf_tmp, $target_pdf)) {
                    $web_msg = "<script>alert('❌ Imeshindikana kupakia faili la PDF kwenye folder.');</script>";
                    $pdf_mpya_jina = null;
                }
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO matangazo (kichwa_cha_tangazo, maelezo, fomu_pdf) VALUES (?, ?, ?)");
                $stmt->execute([$kichwa_tangazo, $maelezo_tangazo, $pdf_mpya_jina]);
                $web_msg = "<script>alert('📢 Tangazo limechapishwa kwenye ukurasa wa mbele wa chuo!'); window.location.href='it_dashboard.php';</script>";
            } catch (\PDOException $e) {
                $web_msg = "<script>alert('❌ Kosa la database: " . addslashes($e->getMessage()) . "');</script>";
            }
        }
    }

    // C. INJINI MPYA: KUSHUGHULIKIA KUPAKIA PICHA ZA HERO SLIDER
    if (isset($_POST['posti_slider'])) {
        $kichwa_slider = trim($_POST['kichwa_slider']);
        $maelezo_slider = trim($_POST['maelezo_slider']);
        
        $slider_jina = $_FILES['picha_slider']['name'];
        $slider_tmp = $_FILES['picha_slider']['tmp_name'];
        
        $folda_la_slider = $_SERVER['DOCUMENT_ROOT'] . "/FDC_WEBSITE/uploads/"; 
        
        if (empty($kichwa_slider) || empty($maelezo_slider) || empty($slider_jina)) {
            $web_msg = "<script>alert('❌ Tafadhali jaza uga zote na uchague picha kwa ajili ya Slider!');</script>";
        } else {
            $slider_mpya_jina = "slider_" . time() . "_" . basename($slider_jina);
            $target_slider = $folda_la_slider . $slider_mpya_jina;
            
            if (!is_dir($folda_la_slider)) {
                mkdir($folda_la_slider, 0777, true);
            }
            
            if (move_uploaded_file($slider_tmp, $target_slider)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO slider (kichwa, maelezo, picha) VALUES (?, ?, ?)");
                    $stmt->execute([$kichwa_slider, $maelezo_slider, $slider_mpya_jina]);
                    $web_msg = "<script>alert('📸 BANZAI! Picha mpya ya Slider imerushwa kikamilifu kwenye ukurasa wa mbele!'); window.location.href='it_dashboard.php';</script>";
                } catch (\PDOException $e) {
                    $web_msg = "<script>alert('❌ Kosa la database: " . addslashes($e->getMessage()) . "');</script>";
                }
            } else {
                $web_msg = "<script>alert('❌ Imeshindikana kuhamisha picha ya slider kwenda folda la website.');</script>";
            }
        }
    }
}
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// INJINI YA IT: KUSHUGHULIKIA DHARURA YA PASSWORD YA WATUMISHI
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_user_pass'])) {
    $username = trim($_POST['username']);
    $temporary_pass = $_POST['temporary_password'];

    if (empty($username) || empty($temporary_pass)) {
        echo "<script>alert('❌ Tafadhali jaza uga zote!');</script>";
    } else {
        try {
            // Kuangalia kama huyu mtumishi yupo kwenye mfumo
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user) {
                // Ku-hash password mpya ya dharura kuwa BCRYPT
                $hashed_password = password_hash($temporary_pass, PASSWORD_BCRYPT);
                
                // UPDATE: Tunabadilisha password na kulazimisha kubadilisha (requires_reset = 1)
                $update_stmt = $pdo->prepare("UPDATE users SET password = ?, requires_reset = 1 WHERE id = ?");
                $update_stmt->execute([$hashed_password, $user['id']]);
                
                echo "<script>alert('⚡ FORCE RESET IMEFANIVIWA!\n\nPassword ya [ $username ] imewekwa upya kuwa: $temporary_pass\n\nMtumishi atalazimika kuibadilisha akilogu in.'); window.location.href='it_dashboard.php';</script>";
                exit();
            } else {
                echo "<script>alert('❌ Kosa: Mtumiaji mwenye username hiyo hayupo kwenye mfumo!');</script>";
            }
        } catch (\PDOException $e) {
            die("Kosa la kiufundi la mfumo: " . $e->getMessage());
        }
    }
}

// --- KODI MPYA: IT DATABASE BACKUP ENGINE ---
if (isset($_GET['action']) && $_GET['action'] === 'backup') {
    $db_name = "mbinga_fdc_sims"; 
    $db_user = "root";
    $db_pass = "123Mbinga@_"; 
    
    $backup_file = $db_name . "_" . date("Y-m-d_H-i-s") . ".sql";
    $mysqldump_path = "C:\\xampp\\mysql\\bin\\mysqldump.exe";
    $command = "\"$mysqldump_path\" --user=$db_user --password=$db_pass --host=localhost $db_name";
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . basename($backup_file));
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    passthru($command);
    exit();
}

// --- KODI MPYA: IT FILES BACKUP ENGINE (ZIP) ---
if (isset($_GET['action']) && $_GET['action'] === 'backup_files') {
    $root_dir = realpath(__DIR__ . '/../'); 
    $zip_name = "mbinga_fdc_sims_files_" . date("Y-m-d_H-i-s") . ".zip";

    $zip = new ZipArchive();
    if ($zip->open($zip_name, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root_dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($root_dir) + 1);
                if (pathinfo($file_path, PATHINFO_EXTENSION) !== 'zip') {
                    $zip->addFile($file_path, $relative_path);
                }
            }
        }
        $zip->close();

        if (file_exists($zip_name)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename=' . basename($zip_name));
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($zip_name));
            
            readfile($zip_name);
            unlink($zip_name); 
            exit();
        }
    }
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
            --bg-space: #0a0b10;
            --body-gradient: radial-gradient(circle at top, #13112c 0%, var(--bg-space) 100%);
            --neon-cyan: #00f3ff;
            --neon-purple: #b026ff;
            --neon-pink: #ff0055;
            --text-main: #e2e8f0;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --input-bg: rgba(0, 0, 0, 0.3);
        }

        [data-theme="light"] {
            --bg-space: #f0f4f8;
            --body-gradient: radial-gradient(circle at top, #ffffff 0%, #dbeafe 100%);
            --neon-cyan: #2563eb;   
            --neon-purple: #1d4ed8; 
            --neon-pink: #dc2626;   
            --text-main: #1e293b;   
            --glass-bg: #ffffff;    
            --glass-border: #cbd5e1; 
            --input-bg: #ffffff;
        }

        * {
            margin: 0; padding: 0; box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif; transition: background 0.3s, color 0.3s, border-color 0.3s;
        }

        body {
            background: var(--body-gradient);
            color: var(--text-main);
            min-height: 100vh;
            padding: 30px;
        }

        .header-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            padding: 20px 40px;
            border-radius: 16px;
            margin-bottom: 30px;
        }
        p { font-size: 12px; color: #8a8f98; letter-spacing: 2px; text-transform: uppercase; margin-top: 5px; }

        h1 {
            font-size: 24px;
            background: linear-gradient(to right, var(--neon-cyan), var(--neon-purple));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logout-btn {
            color: #ff4b4b;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid rgba(255, 75, 75, 0.2);
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255, 75, 75, 0.1);
            box-shadow: 0 0 15px rgba(255, 75, 75, 0.2);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .panel {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
        }

        h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: var(--neon-cyan);
            letter-spacing: 1px;
        }

        .input-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-size: 12px;
            color: #a0aec0;
            margin-bottom: 6px;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            background: var(--input-bg);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: var(--text-main);
            font-size: 14px;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--neon-purple);
            box-shadow: 0 0 10px rgba(176, 38, 255, 0.2);
        }
        
        textarea {
            height: 90px;
            resize: none;
        }

        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--neon-cyan), var(--neon-purple));
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.3s;
        }

        button:hover {
            box-shadow: 0 0 20px rgba(0, 243, 255, 0.4);
        }

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
            background-color: #2563eb;
        }
        input:checked + .slider:before {
            transform: translateX(24px);
            background-color: #ffffff;
        }
    </style>
</head>
<body>

    <?php if(!empty($web_msg)) echo $web_msg; ?>

    <div class="header-nav">
        <h1>Mbinga FDC — SIMS
            <p>PANEL YA IT</p>
        </h1>
        <span>Mtumiaji: <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></strong> (IT)</span>
        
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

     <div class="dashboard-grid">
        <!-- SAJILI WATUMISHI -->
        <div class="panel">
            <h2>Sajili Mtumiaji Mpya (Staff)</h2>
            <form action="save_user" method="POST">
                <div class="input-group">
                    <label>MAJINA KAMILI</label>
                    <input type="text" name="fullname" placeholder="Mfano: John Arusha Komba" required autocomplete="off">
                </div>
                <div class="input-group">
                    <label>BARUAPEPE / USERNAME</label>
                    <input type="email" name="username" placeholder="mkuu@fdc.go.tz" required autocomplete="off">
                </div>
                <div class="input-group">
                    <label>NENO LA SIRI (PASSWORD)</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <div class="input-group">
                    <label>MAJUKUMU KAZINI (ROLE)</label>
                    <select name="role" required>
                        <option value="">-- Chagua Role --</option>
                        <option value="admin">Mkuu wa Chuo (Admin)</option>
                        <option value="mratibu">Mratibu wa Taaluma</option>
                        <option value="teacher">Mwalimu wa Fani</option>
                        <option value="accountant">Mhasibu Mkuu</option>
                    </select>
                </div>
                <button type="submit">TENGENEZA ACCOUNT</button>
            </form>
        </div>

        <!-- USALAMA & RECOVERY -->
        <div class="dashboard-grid" style="grid-template-columns: 1fr; gap: 30px;">
            <div class="panel">
                <h2>Mbinga FDC Core Systems</h2>
                <p style="color: #8a8f98; line-height: 1.6; font-size: 14px;">
                    Hapa ndipo kitovu cha udhibiti wa usalama wa mfumo wa chuo. Kama mtaalamu wa IT, una mamlaka ya kuongeza akaunti za watendaji ili waweze kutekeleza majukumu yao.
                </p>
                <br>
                <div style="border-left: 3px solid var(--neon-purple); padding-left: 15px; font-size: 13px; color: #a0aec0;">
                    <strong>KUMBUKA USALAMA:</strong><br>
                    Wafanyakazi wote watasajiliwa humu na maneno yao ya siri yatafichwa kwa kutumia algorithm ya Cryptographic (Bcrypt) kabla ya kuhifadhiwa kwenye kanzidata ya chuo.
                </div>
            </div>

            <div class="panel" style="border: 1px solid rgba(255, 0, 85, 0.25);">
                <h2 style="color: var(--neon-pink);">⚡ Emergency Account Recovery</h2>
                <form method="POST" action="">
                    <div class="input-group">
                        <label>USERNAME / BARUAPEPE YA MTUMISHI</label>
                        <input type="email" name="username" placeholder="mhasibu@fdc.go.tz" required autocomplete="off">
                    </div>
                    <div class="input-group">
                        <label>PASSWORD YA MUDA (TEMPORARY PASSWORD)</label>
                        <input type="text" name="temporary_password" value="MbingaFDC@2026" style="color: var(--neon-cyan); font-weight: bold; background: rgba(0,0,0,0.5);" required>
                    </div>
                    <button type="submit" name="reset_user_pass" style="background: linear-gradient(135deg, var(--neon-pink), #cc0044);">
                        FORCE RESET & LOCK ACCOUNT 🔒
                    </button>
                </form>
            </div>
        </div>
        
        <!-- WEBSITE MANAGEMENT: MATUKIO -->
        <div class="panel" style="border: 1px solid var(--neon-cyan);">
            <h2><i class="fa-solid fa-newspaper"></i> Chapisha Tukio Jipya la Chuo</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="input-group">
                    <label>KICHWA CHA HABARI / TUKIO</label>
                    <input type="text" name="kichwa_cha_habari" placeholder="Mfano: Sherehe za Mahafali ya 2026" required autocomplete="off">
                </div>
                <div class="input-group">
                    <label>MAELEZO YA TUKIO</label>
                    <textarea name="maelezo" placeholder="Andika habari yote kwa urefu hapa..." required></textarea>
                </div>
                <div class="input-group">
                    <label>CHAGUA PICHA YA TUKIO (IMAGE FILE)</label>
                    <input type="file" name="picha" accept="image/*" required>
                </div>
                <button type="submit" name="posti_tukio" style="background: linear-gradient(135deg, var(--neon-cyan), #0088cc);">
                    📤 POST TUKIO KWENYE WEBSITE
                </button>
            </form>
        </div>

        <!-- WEBSITE MANAGEMENT: MATANGAZO -->
        <div class="panel" style="border: 1px solid #d47a00;">
            <h2 style="color: #d47a00;"><i class="fa-solid fa-bullhorn"></i> Chapisha Tangazo & Fomu ya Kujiunga</h2>
            <p style="color: #8a8f98; font-size: 13px; margin-bottom: 15px;">
                Unaweza kupost tangazo la kawaida au tangazo lenye ambatisho la fomu ya PDF (Mfano: Fomu ya kujiunga na chuo).
            </p>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="input-group">
                    <label>KICHWA CHA TANGAZO</label>
                    <input type="text" name="kichwa_cha_tangazo" placeholder="Mfano: Nafasi za Masomo / Fomu ya Kujiunga 2026" required autocomplete="off">
                </div>
                <div class="input-group">
                    <label>MAELEZO YA TANGAZO</label>
                    <textarea name="maelezo_tangazo" placeholder="Andika maelezo ya tangazo au sifa za kujiunga hapa..." required></textarea>
                </div>
                <div class="input-group">
                    <label>AMBATISHA FOMU YA PDF (SIO LAZIMA - OPTIONAL)</label>
                    <input type="file" name="fomu_pdf" accept=".pdf">
                </div>
                <button type="submit" name="posti_tangazo" style="background: linear-gradient(135deg, #d47a00, #ff9900);">
                    📢 POST TANGAZO SASA
                </button>
            </form>
        </div>

        <!-- FOMU MPYA YA WEBSITE MANAGEMENT: HERO SLIDER -->
        <div class="panel" style="border: 1px solid var(--neon-purple);">
            <h2><i class="fa-solid fa-images"></i> Pakia Picha Mpya kwenye Slider (Banner la Mbele)</h2>
            <p style="color: #8a8f98; font-size: 13px; margin-bottom: 15px;">
                Picha unayopakia hapa itaonekana moja kwa moja ikizunguka kwenye bango kuu la ukurasa wa mbele (Hero Slider) wa Website ya chuo.
            </p>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="input-group">
                    <label>KICHWA CHA PICHA (SLIDER TITLE)</label>
                    <input type="text" name="kichwa_slider" placeholder="Mfano: Karakana ya Kisasa ya Ufundi Magari" required autocomplete="off">
                </div>
                <div class="input-group">
                    <label>MAELEZO MAFUPI (SLIDER DESCRIPTION)</label>
                    <textarea name="maelezo_slider" placeholder="Andika maelezo mafupi yatakayoonekana juu ya picha..." required></textarea>
                </div>
                <div class="input-group">
                    <label>CHAGUA PICHA YA SLIDER (IMAGE FILE)</label>
                    <input type="file" name="picha_slider" accept="image/*" required>
                </div>
                <button type="submit" name="posti_slider" style="background: linear-gradient(135deg, var(--neon-purple), #8b5cf6);">
                    📸 RUSHA PICHA KWENYE SLIDER
                </button>
            </form>
        </div>

        <!-- BACKUPS -->
        <div class="panel" style="border: 1px solid rgba(0, 243, 255, 0.25); margin-top: 30px;">
            <h2>💾 Mfumo wa Backup (Kanzidata)</h2>
            <a href="it_dashboard.php?action=backup" style="display: block; text-align: center; background: linear-gradient(135deg, var(--neon-cyan), var(--neon-purple)); color: white; text-decoration: none; padding: 14px; border-radius: 8px; font-weight: bold; text-transform: uppercase;">
                📥 PAKUA BACKUP SASA (DOWNLOAD .SQL)
            </a>
        </div>
        <div class="panel" style="border: 1px solid var(--neon-purple); margin-top: 30px;">
            <h2>📁 Mfumo wa Backup (Source Codes & Files)</h2>
            <a href="it_dashboard.php?action=backup_files" style="display: block; text-align: center; background: linear-gradient(135deg, var(--neon-purple), #8b5cf6); color: white; text-decoration: none; padding: 14px; border-radius: 8px; font-weight: bold; text-transform: uppercase;">
                📦 PAKUA MA-FILE YOTE (DOWNLOAD CODE .ZIP)
            </a>
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