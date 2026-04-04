<?php
// ================== XGGDAYGEAHUB 최종 업그레이드 버전 ==================
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

$admin_pass = "시우관리자123"; // ← 반드시 변경하세요!

// ====================== Executor 키 체크 ======================
if (isset($_GET['checkkey'])) {
    $input_key = trim($_GET['checkkey'] ?? '');
    $keys = json_decode(file_get_contents($keys_file), true);
    if (empty($input_key) || !isset($keys[$input_key])) {
        echo json_encode(['success' => false, 'message' => '존재하지 않는 키입니다.']);
        exit;
    }
    $k = $keys[$input_key];
    $now = time();
    $is_valid = ($k['expiry'] > $now) || ($k['expiry'] >= 999999999);
    echo json_encode([
        'success' => $is_valid,
        'plan'    => $k['plan'] ?? 'unknown',
        'expiry'  => ($k['expiry'] >= 999999999) ? '영구' : date('Y-m-d H:i', $k['expiry']),
        'message' => $is_valid ? '인증 성공' : '키가 만료되었습니다.'
    ]);
    exit;
}

// ====================== 12시간 무료 키 ======================
if (isset($_GET['generate12h'])) {
    if (!$completed) {
        echo json_encode(['success'=>false, 'message'=>'Linkvertise를 먼저 완료해주세요.']);
        exit;
    }
    $key = generate_key();
    $keys = json_decode(file_get_contents($keys_file), true);
    $keys[$key] = ['expiry'=>time() + 43200, 'plan'=>'12hour', 'created'=>time(), 'type'=>'free_12h'];
    file_put_contents($keys_file, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    unset($_SESSION['linkvertise_completed']);
    echo json_encode(['success'=>true, 'key'=>$key]);
    exit;
}

// ====================== 내 키 확인 (입금자 이름으로) ======================
if (isset($_GET['mykeys'])) {
    $depositor = trim($_GET['mykeys'] ?? '');
    if (empty($depositor)) {
        echo json_encode(['success'=>false, 'message'=>'입금자 이름을 입력해주세요.']);
        exit;
    }
    $orders = json_decode(file_get_contents($orders_file), true);
    $my_keys = [];
    foreach ($orders as $o) {
        if (isset($o['depositor']) && strtolower($o['depositor']) === strtolower($depositor) && !empty($o['key_assigned'])) {
            $my_keys[] = [
                'order_id' => $o['id'],
                'plan'     => $o['plan_name'],
                'key'      => $o['key_assigned'],
                'expiry'   => $o['expiry'] ?? '영구'
            ];
        }
    }
    echo json_encode(['success'=>true, 'keys'=>$my_keys]);
    exit;
}

// ====================== 관리자 - 키 발급 ======================
if (isset($_GET['admin_action']) && $_GET['admin_action'] === 'issue_key') {
    if ($_GET['pass'] !== $admin_pass) { echo "인증 실패"; exit; }
    $order_id = $_GET['order_id'] ?? '';
    if (empty($order_id)) exit;

    $orders = json_decode(file_get_contents($orders_file), true);
    $keys = json_decode(file_get_contents($keys_file), true);

    foreach ($orders as &$o) {
        if ($o['id'] === $order_id && $o['status'] === 'pending') {
            $new_key = generate_key();
            $expiry = ($o['plan'] === 'lifetime') ? 999999999 : time() + ($o['days'] * 86400);
            
            $o['status'] = 'completed';
            $o['key_assigned'] = $new_key;
            $o['expiry'] = $expiry;
            
            $keys[$new_key] = ['expiry'=>$expiry, 'plan'=>$o['plan'], 'created'=>time()];
            break;
        }
    }
    file_put_contents($orders_file, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    file_put_contents($keys_file, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "키가 성공적으로 발급되었습니다.";
    exit;
}

// ====================== 관리자 패널 ======================
if (isset($_GET['admin'])) {
    if ($_GET['admin'] !== $admin_pass) {
        echo "<h2 style='color:red;text-align:center;'>관리자 인증 실패</h2>";
        exit;
    }
    $orders = json_decode(file_get_contents($orders_file), true);
    ?>
    <!DOCTYPE html>
    <html lang="ko">
    <head><meta charset="UTF-8"><title>관리자 패널</title>
    <style>body{background:#0a0a0a;color:#eee;font-family:sans-serif;padding:20px;}
    table{width:100%;border-collapse:collapse;} th,td{border:1px solid #444;padding:10px;}
    th{background:#222;} button{padding:8px 16px;background:#00ff9d;color:#000;border:none;border-radius:6px;cursor:pointer;}</style>
    </head>
    <body>
    <h1>관리자 패널</h1>
    <h2>미처리 주문 목록</h2>
    <table>
        <tr><th>주문번호</th><th>플랜</th><th>금액</th><th>입금자</th><th>상태</th><th>관리</th></tr>
        <?php foreach($orders as $o): if($o['status'] === 'pending'): ?>
        <tr>
            <td><?= $o['id'] ?></td>
            <td><?= $o['plan_name'] ?></td>
            <td>₩<?= number_format($o['amount']) ?></td>
            <td><?= htmlspecialchars($o['depositor'] ?? '미입력') ?></td>
            <td>대기중</td>
            <td><button onclick="issueKey('<?= $o['id'] ?>')">구매 완료 & 키 발급</button></td>
        </tr>
        <?php endif; endforeach; ?>
    </table>
    <script>
    function issueKey(orderId) {
        if (confirm('키를 발급하시겠습니까?')) {
            fetch('index.php?admin_action=issue_key&order_id=' + orderId + '&pass=<?= $admin_pass ?>')
                .then(r => r.text()).then(txt => { alert(txt); location.reload(); });
        }
    }
    </script>
    </body></html>
    <?php exit;
}

// ====================== 키 구매 처리 ======================
if (isset($_GET['plan']) && isset($_POST['depositor'])) {
    $plan_code = $_GET['plan'];
    $depositor = trim($_POST['depositor']);
    if (empty($depositor)) {
        echo "<h2 style='color:red;text-align:center;'>입금자 이름을 입력해주세요!</h2>";
        exit;
    }
    if (isset($plans[$plan_code])) {
        $p = $plans[$plan_code];
        $order_id = 'ORD-' . strtoupper(substr(uniqid(), -8));
        $order = [
            'id' => $order_id,
            'plan' => $plan_code,
            'plan_name' => $p['name'],
            'amount' => $p['price'],
            'depositor' => $depositor,
            'status' => 'pending',
            'created' => time(),
            'key_assigned' => null,
            'expiry' => null
        ];
        $orders = json_decode(file_get_contents($orders_file), true);
        $orders[] = $order;
        file_put_contents($orders_file, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        ?>
        <!DOCTYPE html>
        <html lang="ko">
        <head><meta charset="UTF-8"><title>주문 완료</title>
        <style>body{background:#0a0a0a;color:#e0e0e0;text-align:center;padding:80px 20px;}
        .box{max-width:720px;margin:auto;background:#111;border:2px solid #00ff9d;border-radius:24px;padding:50px;}</style>
        </head>
        <body>
        <div class="box">
            <h1>✅ 주문이 접수되었습니다</h1>
            <p style="font-size:2rem;">주문번호: <?= $order_id ?></p>
            <p>입금자: <?= htmlspecialchars($depositor) ?></p>
            <p><?= $bank_info['bank'] ?> <?= $bank_info['account'] ?><br>예금주: <?= $bank_info['holder'] ?></p>
            <p style="color:#ff0;"><?= $bank_info['note'] ?></p>
            <a href="index.php?mykeys=<?= urlencode($depositor) ?>" style="padding:16px 40px;background:#00ff9d;color:#000;border-radius:50px;text-decoration:none;">내 키 확인하기</a>
        </div>
        </body></html>
        <?php exit;
    }
}

// ====================== 내 키 페이지 ======================
if (isset($_GET['mykeys_page'])) {
    $depositor = trim($_GET['mykeys_page'] ?? '');
    ?>
    <!DOCTYPE html>
    <html lang="ko">
    <head><meta charset="UTF-8"><title>내 키</title>
    <style>body{background:#0a0a0a;color:#e0e0e0;font-family:sans-serif;padding:40px;text-align:center;}
    .keybox{background:#111;padding:30px;border-radius:16px;border:2px solid #00ff9d;max-width:600px;margin:auto;}</style>
    </head>
    <body>
    <h1>내 키 목록</h1>
    <p>입금자: <?= htmlspecialchars($depositor) ?></p>
    <div id="keys"></div>
    <script>
    fetch('index.php?mykeys=<?= urlencode($depositor) ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.keys.length > 0) {
                let html = '';
                data.keys.forEach(k => {
                    html += `<div class="keybox" style="margin:20px 0;">
                        <p><strong>${k.plan}</strong> - ${k.order_id}</p>
                        <p style="font-size:1.6rem;color:#00ff9d;">${k.key}</p>
                        <button onclick="navigator.clipboard.writeText('${k.key}');alert('복사되었습니다!')">키 복사</button>
                    </div>`;
                });
                document.getElementById('keys').innerHTML = html;
            } else {
                document.getElementById('keys').innerHTML = '<p>아직 발급된 키가 없습니다. 입금 후 관리자 확인을 기다려주세요.</p>';
            }
        });
    </script>
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
        /* 이전 스타일 유지 (간단히 유지) */
        body {background:#0a0a0a;color:#e0e0e0;font-family:'Inter',sans-serif;margin:0;}
        .btn {padding:14px 32px;font-size:1.1rem;border-radius:50px;text-decoration:none;}
        .btn-primary {background:#00ff9d;color:#000;}
        .section {padding:80px 5%;max-width:1200px;margin:auto;}
        .card {background:#111;border:1px solid #333;border-radius:20px;padding:30px;}
    </style>
</head>
<body>

<header style="padding:1rem;background:rgba(10,10,10,0.98);">
    <span onclick="location.href='index.php'" style="cursor:pointer;font-size:2rem;font-weight:bold;color:#00ff9d;">XGGDAYGEAHUB</span>
</header>

<section class="section">
    <h1 style="text-align:center;">키 구매</h1>
    <?php foreach($plans as $code => $p): ?>
    <div class="card" style="margin:20px auto;max-width:500px;">
        <h3><?= $p['label'] ?> - ₩<?= number_format($p['price']) ?></h3>
        <form method="post" action="index.php?plan=<?= $code ?>">
            <input type="text" name="depositor" placeholder="입금자 이름 (필수)" required style="width:100%;padding:12px;margin:15px 0;">
            <button type="submit" class="btn btn-primary" style="width:100%;">구매하기</button>
        </form>
    </div>
    <?php endforeach; ?>
</section>

<section class="section" style="background:#111;">
    <h2 style="text-align:center;">내 키 확인</h2>
    <div style="max-width:500px;margin:auto;">
        <form action="index.php?mykeys_page=1" method="get">
            <input type="text" name="mykeys_page" placeholder="입금자 이름을 입력하세요" required style="width:100%;padding:15px;">
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px;">내 키 보기</button>
        </form>
    </div>
</section>

<!-- 12시간 무료 키 섹션 (기존 코드 유지) -->

<script>
// 제목 더블클릭 → 관리자
document.querySelector('header span').addEventListener('dblclick', () => {
    const pass = prompt("관리자 비밀번호:");
    if (pass) location.href = `index.php?admin=${encodeURIComponent(pass)}`;
});
</script>

</body>
</html>
