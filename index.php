<?php
// ================== XGGDAYGEAHUB 완전 통합 최종 버전 ==================
$bank_info = [
    'bank'    => '카카오뱅크',
    'account' => '7777-03-0806539',
    'holder'  => '김시우',
    'note'    => '입금 시 메모에 반드시 주문번호(ORD-XXXXXX)를 적어주세요!'
];

$plans = [
    '1day'     => ['name'=>'1일 키',     'price'=>1000,  'days'=>1,     'label'=>'1 Day Key'],
    '30day'    => ['name'=>'30일 키',    'price'=>30000, 'days'=>30,    'label'=>'30 Day Key'],
    'lifetime' => ['name'=>'영구 키',    'price'=>50000, 'days'=>999999,'label'=>'Lifetime Key']
];

$orders_file = __DIR__ . '/orders.json';
$keys_file   = __DIR__ . '/keys.json';

if (!file_exists($orders_file)) file_put_contents($orders_file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
if (!file_exists($keys_file)) file_put_contents($keys_file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

function generate_key() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $part = '';
    for ($i = 0; $i < 12; $i++) $part .= $chars[rand(0, strlen($chars)-1)];
    return 'XGG-' . $part;
}

session_start();
$completed = $_SESSION['linkvertise_completed'] ?? false;
$linkvertise_url = "https://linkvertise.com/3039668/b1qqzRi8cJlM?o=sharing" . base64_encode("https://api-xggdaygeahub.ct.ws/index.php?return=1");

// ====================== Executor 키 체크 ======================
if (isset($_GET['checkkey'])) {
    $input_key = trim($_GET['checkkey'] ?? '');
    $keys = json_decode(file_get_contents($keys_file), true);

    if (empty($input_key)) {
        echo json_encode(['success' => false, 'message' => '키를 입력해주세요.']);
        exit;
    }

    if (isset($keys[$input_key])) {
        $k = $keys[$input_key];
        $now = time();
        $is_valid = ($k['expiry'] > $now) || ($k['expiry'] >= 999999999);

        if ($is_valid) {
            echo json_encode([
                'success' => true,
                'plan'    => $k['plan'] ?? 'unknown',
                'expiry'  => ($k['expiry'] >= 999999999) ? '영구' : date('Y-m-d H:i', $k['expiry']),
                'message' => '인증 성공'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => '키가 만료되었습니다.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '존재하지 않는 키입니다.']);
    }
    exit;
}

// ====================== 12시간 무료 키 ======================
if (isset($_GET['generate12h'])) {
    if (!$completed) {
        echo json_encode(['success'=>false, 'message'=>'Linkvertise를 먼저 완료해주세요.']);
        exit;
    }
    $key = generate_key();
    $expiry = time() + 43200; // 12시간
    $keys = json_decode(file_get_contents($keys_file), true);
    $keys[$key] = ['expiry'=>$expiry, 'plan'=>'12hour', 'created'=>time(), 'type'=>'free_12h'];
    file_put_contents($keys_file, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    unset($_SESSION['linkvertise_completed']);
    echo json_encode(['success'=>true, 'key'=>$key]);
    exit;
}

// ====================== 관리자 패널 (더블클릭) ======================
if (isset($_GET['admin'])) {
    $admin_pass = "시우관리자123";   // ← 여기 반드시 강력한 비밀번호로 변경하세요!

    if ($_GET['admin'] === $admin_pass) {
        $orders = json_decode(file_get_contents($orders_file), true);
        $keys = json_decode(file_get_contents($keys_file), true);
        ?>
        <!DOCTYPE html>
        <html lang="ko">
        <head><meta charset="UTF-8"><title>관리자 패널 - XGGDAYGEAHUB</title>
        <style>body{background:#0a0a0a;color:#e0e0e0;font-family:sans-serif;padding:20px;}
        table{width:100%;border-collapse:collapse;margin:20px 0;} th,td{border:1px solid #444;padding:12px;text-align:left;}
        th{background:#1f1f1f;}</style>
        </head>
        <body>
        <h1>🔧 관리자 패널</h1>
        <h2>주문 목록</h2>
        <table><tr><th>주문번호</th><th>플랜</th><th>금액</th><th>상태</th><th>발급키</th></tr>
        <?php foreach($orders as $o): ?>
        <tr>
            <td><?= htmlspecialchars($o['id']) ?></td>
            <td><?= htmlspecialchars($o['plan_name']) ?></td>
            <td>₩<?= number_format($o['amount']) ?></td>
            <td><?= $o['status'] ?></td>
            <td><?= $o['key_assigned'] ?? '미발급' ?></td>
        </tr>
        <?php endforeach; ?>
        </table>

        <h2>발급된 모든 키</h2>
        <table><tr><th>키</th><th>플랜</th><th>만료일</th></tr>
        <?php foreach($keys as $k => $v): ?>
        <tr>
            <td><?= htmlspecialchars($k) ?></td>
            <td><?= htmlspecialchars($v['plan']) ?></td>
            <td><?= ($v['expiry'] >= 999999999) ? '영구' : date('Y-m-d H:i', $v['expiry']) ?></td>
        </tr>
        <?php endforeach; ?>
        </table>
        </body></html>
        <?php exit;
    } else {
        echo "<h2 style='color:red;text-align:center;'>관리자 인증 실패</h2>";
        exit;
    }
}

// ====================== 키 구매 처리 ======================
if (isset($_GET['plan'])) {
    $plan_code = $_GET['plan'];
    if (isset($plans[$plan_code])) {
        $p = $plans[$plan_code];
        $order_id = 'ORD-' . strtoupper(substr(uniqid(), -8));
        $order = ['id'=>$order_id, 'plan'=>$plan_code, 'plan_name'=>$p['name'], 'amount'=>$p['price'], 'status'=>'pending', 'created'=>time(), 'key_assigned'=>null, 'expiry'=>null];
        $orders = json_decode(file_get_contents($orders_file), true);
        $orders[] = $order;
        file_put_contents($orders_file, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        ?>
        <!DOCTYPE html>
        <html lang="ko">
        <head><meta charset="UTF-8"><title>주문 완료</title>
        <style>body{background:#0a0a0a;color:#e0e0e0;font-family:sans-serif;text-align:center;padding:80px 20px;}
        .box{max-width:720px;margin:auto;background:#111;border:2px solid #00ff9d;border-radius:24px;padding:50px;}</style>
        </head>
        <body>
        <div class="box">
            <h1>✅ 주문이 접수되었습니다</h1>
            <p style="font-size:2rem;margin:25px 0;">주문번호: <?= $order_id ?></p>
            <p><?= $bank_info['bank'] ?> <?= $bank_info['account'] ?><br>예금주: <?= $bank_info['holder'] ?></p>
            <p style="color:#ff0;margin:30px 0;"><?= $bank_info['note'] ?><br><strong>입금 확인 후 키가 자동 발급됩니다.</strong></p>
            <a href="index.php?check=<?= $order_id ?>" style="padding:16px 40px;background:#00ff9d;color:#000;border-radius:50px;text-decoration:none;font-size:1.2rem;">주문 상태 확인</a>
        </div>
        </body></html>
        <?php exit;
    }
}

if (isset($_GET['check'])) {
    $order_id = $_GET['check'];
    $orders = json_decode(file_get_contents($orders_file), true);
    $found = null;
    foreach ($orders as $o) if ($o['id'] === $order_id) {$found = $o; break;}
    ?>
    <!DOCTYPE html>
    <html lang="ko">
    <head><meta charset="UTF-8"><title>주문 확인</title>
    <style>body{background:#0a0a0a;color:#e0e0e0;font-family:sans-serif;text-align:center;padding:80px 20px;}
    .box{max-width:700px;margin:auto;background:#111;border:2px solid #00ff9d;border-radius:24px;padding:50px;}</style>
    </head>
    <body>
    <div class="box">
        <?php if (!$found): ?>
            <h2>존재하지 않는 주문번호입니다.</h2>
        <?php elseif ($found['status'] === 'pending'): ?>
            <h2>⏳ 입금 확인 중입니다</h2>
            <p>관리자가 확인 후 키를 발급해드립니다.</p>
        <?php else: ?>
            <h2>✅ 키 발급 완료!</h2>
            <p style="font-size:1.8rem;">키: <strong style="color:#00ff9d;"><?= $found['key_assigned'] ?? '미발급' ?></strong></p>
            <button onclick="navigator.clipboard.writeText('<?= $found['key_assigned'] ?? '' ?>');alert('복사되었습니다!')" 
                    style="padding:14px 40px;background:#00ff9d;color:#000;border:none;border-radius:50px;font-size:1.1rem;">키 복사하기</button>
        <?php endif; ?>
    </div>
    </body></html>
    <?php exit;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XGGDAYGEAHUB | Rivals Script</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap');
        :root {--primary:#00ff9d;--cyan:#22d3ee;}
        * {box-sizing:border-box;}
        body {background:#0a0a0a;color:#e0e0e0;font-family:'Inter',sans-serif;margin:0;padding:0;line-height:1.6;}
        header {background:rgba(10,10,10,0.98);padding:1rem 5%;position:fixed;width:100%;z-index:100;border-bottom:1px solid #222;}
        .logo {font-family:'Space Grotesk',sans-serif;font-size:2rem;font-weight:700;background:linear-gradient(90deg,var(--primary),var(--cyan));-webkit-background-clip:text;-webkit-text-fill-color:transparent;cursor:pointer;}
        .hero {min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;background:radial-gradient(circle,#1a1a2e,#0a0a0a);padding:100px 20px;}
        .hero h1 {font-size:3.6rem;font-weight:700;background:linear-gradient(90deg,#00ff9d,#22d3ee,#c026d3);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:0;cursor:pointer;}
        .hero p {font-size:1.45rem;margin:20px 0;}
        .btn {padding:14px 32px;font-size:1.1rem;font-weight:600;border-radius:50px;text-decoration:none;transition:0.3s;display:inline-block;}
        .btn-primary {background:var(--primary);color:#000;}
        .btn-primary:hover {transform:translateY(-4px);box-shadow:0 10px 25px rgba(0,255,157,0.4);}
        .section {padding:90px 5%;max-width:1200px;margin:0 auto;}
        .card {background:#111;border:1px solid #333;border-radius:20px;padding:35px;text-align:center;transition:0.4s;}
        .card:hover {border-color:var(--primary);transform:translateY(-8px);}
        .price {font-size:2.5rem;font-weight:700;color:var(--primary);margin:15px 0;}
        .grid {display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:25px;}
        @media (max-width: 768px) {
            .hero h1 {font-size:2.9rem;}
            .hero p {font-size:1.25rem;}
            .section {padding:70px 5%;}
            .card {padding:25px;}
        }
    </style>
</head>
<body>

<header>
    <div style="max-width:1200px;margin:auto;display:flex;justify-content:space-between;align-items:center;">
        <span class="logo" id="logo">XGGDAYGEAHUB</span>
        <a href="#free" class="btn" style="background:#222;color:white;">12시간 무료</a>
    </div>
</header>

<section class="hero">
    <div>
        <h1 id="mainTitle">XGGDAYGEAHUB</h1>
        <p>Roblox Rivals Premium Script</p>
        <div style="margin-top:35px;">
            <a href="#plans" class="btn btn-primary" style="margin:0 10px;">키 구매하기</a>
            <a href="#free" class="btn" style="background:#1f2937;color:white;">12시간 무료 키 받기</a>
        </div>
    </div>
</section>

<section id="plans" class="section">
    <h2 style="text-align:center;font-size:2.7rem;margin-bottom:50px;">키 구매</h2>
    <div class="grid">
        <?php foreach($plans as $code => $p): ?>
        <div class="card">
            <h3 style="font-size:1.6rem;"><?= $p['label'] ?></h3>
            <div class="price">₩<?= number_format($p['price']) ?></div>
            <a href="index.php?plan=<?= $code ?>" class="btn btn-primary" style="margin-top:25px;width:100%;">구매하기</a>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section id="free" class="section" style="background:#111;">
    <h2 style="text-align:center;font-size:2.5rem;margin-bottom:40px;">🎁 12시간 무료 키</h2>
    <div style="max-width:720px;margin:auto;background:#0a0a0a;padding:55px;border-radius:24px;border:3px solid #00ff9d;text-align:center;">
        <?php if (!$completed): ?>
            <a href="<?= htmlspecialchars($linkvertise_url) ?>" target="_blank">
                <button class="btn" style="font-size:1.35rem;padding:22px 85px;">Linkvertise 보고 12시간 키 받기</button>
            </a>
        <?php else: ?>
            <p style="color:#00ff9d;font-size:1.55rem;">✅ 광고 시청 완료!</p>
            <button onclick="generate12h()" class="btn" style="font-size:1.35rem;padding:22px 85px;margin-top:25px;">12시간 키 생성하기</button>
            <div id="keyResult" style="margin-top:45px;font-size:1.45rem;display:none;color:#00ff9d;"></div>
        <?php endif; ?>
    </div>
</section>

<script>
// 관리자 더블클릭 (제목 또는 로고)
function openAdmin() {
    const pass = prompt("관리자 비밀번호를 입력하세요:");
    if (pass) {
        window.location.href = "index.php?admin=" + encodeURIComponent(pass);
    }
}
document.getElementById('mainTitle').addEventListener('dblclick', openAdmin);
document.getElementById('logo').addEventListener('dblclick', openAdmin);

// 12시간 키 생성
async function generate12h() {
    const btn = document.querySelector('button[onclick="generate12h()"]');
    if (btn) { btn.disabled = true; btn.textContent = "생성 중..."; }
    try {
        const res = await fetch('index.php?generate12h=1');
        const data = await res.json();
        if (data.success) {
            document.getElementById('keyResult').innerHTML = `
                키: <strong>${data.key}</strong><br><br>
                <small>12시간 동안 사용 가능합니다</small>
            `;
            document.getElementById('keyResult').style.display = 'block';
        } else {
            alert(data.message || "오류 발생");
        }
    } catch(e) {
        alert("서버 연결 오류");
    }
}
</script>

</body>
</html>
