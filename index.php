<?php
session_start();

// ==========================================
// PENGATURAN KEAMANAN (PIN & SESI)
// ==========================================
$CORRECT_PIN = '1212'; // Ubah PIN 4 Digit Anda di sini
$SESSION_TIMEOUT = 300; // 5 menit (dalam detik)
$authError = '';

// Proses Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Cek Sesi (Timeout 5 Menit)
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    if (time() - $_SESSION['last_activity'] > $SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        $authError = "Sesi telah berakhir (5 menit). Silakan masukkan PIN kembali.";
    } else {
        // Perpanjang sesi setiap kali ada aktivitas / reload
        $_SESSION['last_activity'] = time(); 
    }
}

// Proses Submit PIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {
    if ($_POST['pin'] === $CORRECT_PIN) {
        $_SESSION['authenticated'] = true;
        $_SESSION['last_activity'] = time();
        header("Location: " . $_SERVER['PHP_SELF']); // Redirect untuk mencegah resubmission
        exit;
    } else {
        $authError = "PIN Salah! Silakan coba lagi.";
    }
}

$isAuthenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;


$responseHTML = 'Belum ada request yang dikirim...';

// Set teks default untuk textarea
$jsonPayloadText = '{
  "data": {
    "created_at": "2026-10-01 22:21:24",
    "customer_email": "email@gmail.com",
    "customer_name": "name",
    "customer_phone": "08xxxxx",
    "merchant_id": "M251XXXXXXXX",
    "payment_channel": "QRISREALTIME",
    "total_dibayar": 9270,
    "total_diterima": 9112,
    "updated_at": "2026-10-01 22:21:24"
  },
  "reference": "TP261001KVTXXXXXX",
  "reff_id": "NDP2026100122XXXXXX",
  "signature": "30310d2e8c642f836XXXXXXX",
  "status": "Success"
}';

// Proses cURL hanya jika sudah terautentikasi dan form webhook disubmit
if ($isAuthenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['pin'])) {
    $targetUrl = !empty($_POST['customUrl']) ? trim($_POST['customUrl']) : $_POST['targetSite'];
    
    // Ambil input JSON mentah
    $rawPayload = $_POST['jsonPayload'];
    
    // Simpan ke variabel text agar kotak textarea tidak ter-reset kosong saat direload
    $jsonPayloadText = $rawPayload;

    // --- SOLUSI DEFINITIF AUTO CLEAN COPAS ---
    // 1. Ubah berbagai jenis spasi gaib (Non-Breaking Space) menjadi spasi biasa
    $cleanPayload = str_replace(["\xC2\xA0", "\xA0", "\xE2\x80\x8B", "\xEF\xBB\xBF"], ' ', $rawPayload);
    
    // 2. Hapus karakter kontrol yang merusak JSON, KECUALI karakter newline (\n, \r) dan tab (\t)
    $cleanPayload = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $cleanPayload);

    // Coba Decode JSON yang sudah dibersihkan awalnya
    $decodedData = json_decode($cleanPayload, true);
    
    // 3. JIKA MASIH GAGAL: Bersihkan Enter (\n) yang tidak disengaja
    if (json_last_error() !== JSON_ERROR_NONE) {
        $superCleanPayload = preg_replace("/\r|\n/", "", $cleanPayload);
        $decodedData = json_decode($superCleanPayload, true);
    }

    if (json_last_error() !== JSON_ERROR_NONE) {
        $responseHTML = "<span class='text-red-600 font-black'>ERROR: Format JSON tidak valid!</span><br>" . json_last_error_msg();
    } else {
        // Encode ulang JSON yang sudah di-decode ke bentuk string yang 100% rapi
        $finalJsonString = json_encode($decodedData);

        // Eksekusi cURL
        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $finalJsonString);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($finalJsonString)
        ]);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $responseHTML = "
                <span class='text-red-600 font-black text-lg uppercase'>Request cURL Gagal!</span><br><br>
                <strong>Error:</strong> {$error}<br><br>
                <em>Pastikan URL target benar dan server tujuan sedang aktif.</em>
            ";
        } else {
            $statusColor = ($http_code >= 200 && $http_code <= 299) ? 'text-green-600' : 'text-red-600';
            $escapedResult = htmlspecialchars($result);
            $responseHTML = "
                <div class='font-bold mb-2 text-lg uppercase border-b-2 border-black pb-2'>Status Code: <span class='{$statusColor} px-2 py-1 bg-white border-2 border-black shadow-[2px_2px_0px_rgba(0,0,0,1)]'>{$http_code}</span></div>
                <div class='mt-3 font-bold uppercase'>Body:</div>
                <pre class='mt-2 whitespace-pre-wrap font-mono bg-white p-3 border-2 border-black'>" . ($escapedResult ? $escapedResult : '(Tidak ada body response)') . "</pre>
            ";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TokoPay Webhook Resender - LEON SB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #A3E635; 
            font-family: 'Space Grotesk', sans-serif;
            background-image: radial-gradient(#000 1px, transparent 1px);
            background-size: 20px 20px;
        }
        
        .neo-border { border: 3px solid #000; }
        .neo-shadow { box-shadow: 6px 6px 0px rgba(0,0,0,1); }
        .neo-shadow-sm { box-shadow: 4px 4px 0px rgba(0,0,0,1); }
        .neo-focus:focus { 
            box-shadow: 8px 8px 0px rgba(0,0,0,1); 
            transform: translate(-2px, -2px);
            outline: none;
        }
        .neo-button:active {
            box-shadow: 0px 0px 0px rgba(0,0,0,1);
            transform: translate(6px, 6px);
        }
    </style>
</head>
<body class="p-6 md:p-12 min-h-screen flex items-center justify-center">

    <?php if (!$isAuthenticated): ?>
        <!-- FORM LOGIN PIN -->
        <div class="w-full max-w-sm bg-[#FF90E8] p-8 neo-border neo-shadow">
            <h1 class="text-3xl font-bold mb-6 text-black uppercase tracking-tight text-center">Security<br>Lock</h1>
            
            <?php if ($authError): ?>
                <div class="bg-red-400 p-3 mb-6 neo-border neo-shadow-sm text-black font-bold text-sm">
                    <?= htmlspecialchars($authError) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-6">
                    <label for="pin" class="block font-bold text-black uppercase mb-2">Masukkan 4 Digit PIN:</label>
                    <input type="password" id="pin" name="pin" pattern="[0-9]{4}" maxlength="4" required placeholder="••••" autocomplete="off"
                           class="w-full p-3 text-center text-2xl font-bold tracking-[0.5em] neo-border neo-shadow-sm neo-focus transition-all bg-white">
                </div>
                <button type="submit" class="w-full bg-[#FFC900] text-black font-bold py-3 px-4 neo-border neo-shadow neo-button transition-all uppercase tracking-wider">
                    Buka Akses
                </button>
            </form>
        </div>
    <?php else: ?>
        <!-- FORM UTAMA WEBHOOK -->
        <div class="w-full max-w-4xl bg-[#FDE047] p-6 md:p-8 neo-border neo-shadow">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 border-b-4 border-black pb-4">
                <h1 class="text-3xl md:text-4xl font-black text-black uppercase tracking-tighter">
                    TokoPay Webhook Resender
                </h1>
                <a href="?logout=true" class="bg-red-500 text-white font-bold py-2 px-4 neo-border shadow-[4px_4px_0px_rgba(0,0,0,1)] active:shadow-none active:translate-x-1 active:translate-y-1 transition-all uppercase text-sm">
                    LOGOUT
                </a>
            </div>

            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Pilihan Target -->
                    <div>
                        <label for="targetSite" class="block font-bold text-black uppercase mb-2 bg-white inline-block px-2 border-2 border-black">Pilih Target:</label>
                        <select id="targetSite" name="targetSite" class="w-full bg-white neo-border neo-shadow-sm p-3 font-bold neo-focus transition-all">
                            <option value="https://web.com/payment/tokopay/callback" <?= (isset($_POST['targetSite']) && $_POST['targetSite'] == 'https://web.com/payment/tokopay/callback') ? 'selected' : '' ?>>WEB 1</option> <!-- ubah disini untuk template url webhook -->
                            <option value="https://web.com/payment/tokopay/callback" <?= (isset($_POST['targetSite']) && $_POST['targetSite'] == 'https://web.com/payment/tokopay/callback') ? 'selected' : '' ?>>WEB 2</option> <!-- ubah disini untuk template url webhook -->
                            <option value="https://web.site/callback.php" <?= (isset($_POST['targetSite']) && $_POST['targetSite'] == 'https://web.site/callback.php') ? 'selected' : '' ?>>WEB 3</option> <!-- ubah disini untuk template url webhook -->
                        </select>
                    </div>

                    <!-- Input URL Kustom -->
                    <div>
                        <label for="customUrl" class="block font-bold text-black uppercase mb-2 bg-white inline-block px-2 border-2 border-black">URL Kustom (Opsional):</label>
                        <input type="url" id="customUrl" name="customUrl" placeholder="https://..." value="<?= isset($_POST['customUrl']) ? htmlspecialchars($_POST['customUrl']) : '' ?>" 
                               class="w-full bg-white neo-border neo-shadow-sm p-3 font-bold neo-focus transition-all placeholder-gray-400">
                    </div>
                </div>

                <!-- Payload JSON -->
                <div class="mb-8">
                    <label for="jsonPayload" class="block font-bold text-black uppercase mb-2 bg-white inline-block px-2 border-2 border-black">Payload JSON TokoPay:</label>
                    <textarea id="jsonPayload" name="jsonPayload" rows="10" required 
                              class="w-full bg-white neo-border neo-shadow p-4 font-mono text-sm leading-relaxed neo-focus transition-all whitespace-pre"><?= htmlspecialchars($jsonPayloadText) ?></textarea>
                </div>

                <!-- Tombol Aksi -->
                <button type="submit" class="w-full bg-[#3B82F6] text-white text-xl font-black py-4 px-6 neo-border neo-shadow neo-button transition-all uppercase tracking-widest">
                    Send Webhook
                </button>
            </form>

            <!-- Area Hasil / Response -->
            <div class="mt-10 bg-[#FF90E8] p-6 neo-border neo-shadow">
                <h2 class="text-xl font-black text-black uppercase mb-4 tracking-wider">Response Webhook</h2>
                <div class="bg-[#F8FAFC] p-4 neo-border min-h-[120px] max-h-[400px] overflow-auto text-sm text-black">
                    <?= $responseHTML ?>
                </div>
            </div>
            
        </div>
    <?php endif; ?>

</body>
</html>