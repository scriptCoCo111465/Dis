<?php
// ================== XGGDAYGEAHUB 최종 index.php (관리자 더블클릭 기능 추가) ==================
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

if (isset($_GET['return']) && $_GET['return'] == 1) {
    $_SESSION['linkvertise_completed'] = true;
    header("Location: index.php");
    exit;
}

// ====================== 키 체크 (Executor용) ======================
if (isset($_GET['checkkey'])) {
    $input_key = $_GET['checkkey'] ?? '';
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
    $expiry = time() + 43200;
    $keys = json_decode(file_get_contents($keys_file), true);
    $keys[$key] = ['expiry'=>$expiry, 'plan'=>'12hour', 'created'=>time(), 'type'=>'free_12h'];
    file_put_contents($keys_file, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    unset($_SESSION['linkvertise_completed']);
    echo json_encode(['success'=>true, 'key'=>$key]);
    exit;
}

// ====================== 관리자 페이지 ======================
if (isset($_GET['admin'])) {
    // 여기에 관리자 비밀번호를 넣으세요 (보안을 위해 반드시 변경!)
    $admin_pass = "시우관리자123";   // ← 원하는 비밀번호로 변경하세요!

    if ($_GET['admin'] === $admin_pass) {
        $orders = json_decode(file_get_contents($orders_file), true);
        $keys = json_decode(file_get_contents($keys_file), true);
        ?>
        <!DOCTYPE html>
        <html lang="ko">
        <head><meta charset="UTF-8"><title>관리자 패널</title>
        <style>body{background:#0a0a0a;color:#e0e0e0;font-family:sans-serif;padding:20px;}
        table{width:100%;border-collapse:collapse;margin:20px 0;} th,td{border:1px solid #333;padding:10px;text-align:left;}
        th{background:#222;}</style>
        </head>
        <body>
        <h1>관리자 패널</h1>
        <h2>주문 목록</h2>
        <table><tr><th>주문번호</th><th>플랜</th><th>금액</th><th>상태</th><th>키</th></tr>
        <?php foreach($orders as $o): ?>
        <tr>
            <td><?= $o['id'] ?></td>
            <td><?= $o['plan_name'] ?></td>
            <td>₩<?= number_format($o['amount']) ?></td>
            <td><?= $o['status'] ?></td>
            <td><?= $o['key_assigned'] ?? '미발급' ?></td>
        </tr>
        <?php endforeach; ?>
        </table>

        <h2>발급된 키 목록</h2>
        <table><tr><th>키</th><th>플랜</th><th>만료</th></tr>
        <?php foreach($keys as $k => $v): ?>
        <tr>
            <td><?= $k ?></td>
            <td><?= $v['plan'] ?></td>
            <td><?= ($v['expiry'] >= 999999999) ? '영구' : date('Y-m-d H:i', $v['expiry']) ?></td>
        </tr>
        <?php endforeach; ?>
        </table>
        </body></html>
        <?php exit;
    } else {
        echo "관리자 인증 실패";
        exit;
    }
}

// ====================== 주문 처리 ======================
if (isset($_GET['plan'])) {
    // ... (기존 주문 코드 유지, 생략 없이 그대로 사용)
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
            <p style="font-size:2rem;margin:20px 0;">주문번호: <?= $order_id ?></p>
            <p><?= $bank_info['bank'] ?> <?= $bank_info['account'] ?><br>예금주: <?= $bank_info['holder'] ?></p>
            <p style="color:#ff0;margin:25px 0;"><?= $bank_info['note'] ?><br><strong>입금 확인 후 키가 자동 발급됩니다.</strong></p>
            <a href="index.php?check=<?= $order_id ?>" style="padding:16px 40px;background:#00ff9d;color:#000;border-radius:50px;text-decoration:none;font-size:1.2rem;">주문 상태 확인</a>
        </div>
        </body></html>
        <?php exit;
    }
}

if (isset($_GET['check'])) {
    // ... (기존 주문 확인 코드 유지)
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
            <p style="font-size:1.8rem;">키: <strong style="color:#00ff9d;"><?= $found['key_assigned'] ?></strong></p>
            <button onclick="navigator.clipboard.writeText('<?= $found['key_assigned'] ?>');alert('복사되었습니다!')" 
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
        body {background:#0a0a0a;color:#e0e0e0;font-family:'Inter',sans-serif;margin:0;padding:0;}
        header {background:rgba(10,10,10,0.98);padding:1rem 5%;position:fixed;width:100%;z-index:100;border-bottom:1px solid #222;}
        .logo {font-family:'Space Grotesk',sans-serif;font-size:2rem;font-weight:700;background:linear-gradient(90deg,var(--primary),var(--cyan));-webkit-background-clip:text;-webkit-text-fill-color:transparent;cursor:pointer;}
        .hero {min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;background:radial-gradient(circle,#1a1a2e,#0a0a0a);padding:80px 20px;}
        .hero h1 {font-size:3.5rem;font-weight:700;background:linear-gradient(90deg,#00ff9d,#22d3ee,#c026d3);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:0;cursor:pointer;}
        .section {padding:80px 5%;max-width:1200px;margin:0 auto;}
        .card {background:#111;border:1px solid #333;border-radius:20px;padding:30px;text-align:center;transition:0.4s;}
        .card:hover {border-color:var(--primary);transform:translateY(-8px);}
        .price {font-size:2.4rem;font-weight:700;color:var(--primary);margin:15px 0;}
        .grid {display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:25px;}
        @media (max-width: 768px) {
            .hero h1 {font-size:2.8rem;}
            .section {padding:60px 5%;}
        }
    </style>
</head>
<body>

<header>
    <div style="max-width:1200px;margin:auto;display:flex;justify-content:space-between;align-items:center;">
        <a href="index.php" class="logo" id="logo">XGGDAYGEAHUB</a>
        <a href="#free" class="btn" style="background:#222;color:white;padding:12px 24px;border-radius:50px;">12시간 무료</a>
    </div>
</header>

<section class="hero">
    <div>
        <h1 id="mainTitle">XGGDAYGEAHUB</h1>
        <p>Roblox Rivals Premium Script</p>
        <div style="margin-top:30px;">
            <a href="#plans" class="btn btn-primary" style="margin:0 8px;">키 구매하기</a>
            <a href="#free" class="btn" style="background:#1f2937;color:white;">12시간 무료 키</a>
        </div>
    </div>
</section>

<!-- plans와 free 섹션은 이전과 동일하게 유지 (공간 절약을 위해 생략하지 않고 그대로 사용하세요) -->
<!-- ... 기존 plans 섹션과 free 섹션 코드 그대로 붙여넣기 ... -->

<script>
// 제목 더블클릭 → 관리자 페이지 이동
document.getElementById('mainTitle').addEventListener('dblclick', function() {
    const pass = prompt("관리자 비밀번호를 입력하세요:");
    if (pass) {
        window.location.href = "index.php?admin=" + encodeURIComponent(pass);
    }
});

document.getElementById('logo').addEventListener('dblclick', function() {
    const pass = prompt("관리자 비밀번호를 입력하세요:");
    if (pass) {
        window.location.href = "index.php?admin=" + encodeURIComponent(pass);
    }
});
</script>

</body>
</html>
