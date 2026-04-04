<?php
$bank_info = [
    'bank'    => '카카오뱅크',
    'account' => '7777-03-0806539',
    'holder'  => '김시우',
    'note'    => '입금 시 주문번호(ORD-XXXXXX)를 메모에 입력해주세요!'
];

$plans = [
    '1day'     => ['name'=>'1일 키','price'=>1000,'days'=>1,'label'=>'1 Day Key'],
    '30day'    => ['name'=>'30일 키','price'=>30000,'days'=>30,'label'=>'30 Day Key'],
    'lifetime' => ['name'=>'영구 키','price'=>50000,'days'=>999999,'label'=>'Lifetime Key']
];

$orders_file = __DIR__ . '/orders.json';
$keys_file   = __DIR__ . '/keys.json';

if (!file_exists($orders_file)) file_put_contents($orders_file, json_encode([], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
if (!file_exists($keys_file)) file_put_contents($keys_file, json_encode([], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

function generate_key() {
    return 'XGG-' . strtoupper(substr(md5(uniqid()), 0, 12));
}

session_start();
$admin_pass = "시우관리자123";

if (isset($_GET['checkkey'])) {
    $keys = json_decode(file_get_contents($keys_file), true);
    $k = $keys[$_GET['checkkey']] ?? null;
    if (!$k) {
        echo json_encode(['success'=>false]); exit;
    }
    $valid = ($k['expiry'] > time()) || ($k['expiry'] >= 999999999);
    echo json_encode([
        'success'=>$valid,
        'expiry'=>$k['expiry'] >= 999999999 ? '영구' : date('Y-m-d H:i',$k['expiry'])
    ]);
    exit;
}

if (isset($_GET['mykeys'])) {
    $orders = json_decode(file_get_contents($orders_file), true);
    $result = [];
    foreach ($orders as $o) {
        if (($o['depositor'] ?? '') === $_GET['mykeys'] && $o['key_assigned']) {
            $result[] = $o;
        }
    }
    echo json_encode(['success'=>true,'keys'=>$result]);
    exit;
}

if (isset($_GET['admin_action'])) {
    if ($_GET['pass'] !== $admin_pass) exit;

    $orders = json_decode(file_get_contents($orders_file), true);
    $keys = json_decode(file_get_contents($keys_file), true);

    foreach ($orders as &$o) {
        if ($o['id'] === $_GET['order_id'] && $o['status']==='pending') {
            $key = generate_key();
            $expiry = $o['plan']==='lifetime' ? 999999999 : time()+($o['days']*86400);
            $o['status']='completed';
            $o['key_assigned']=$key;
            $o['expiry']=$expiry;
            $keys[$key]=['expiry'=>$expiry];
        }
    }

    file_put_contents($orders_file,json_encode($orders,JSON_PRETTY_PRINT));
    file_put_contents($keys_file,json_encode($keys,JSON_PRETTY_PRINT));

    echo "발급 완료";
    exit;
}

if (isset($_GET['plan']) && isset($_POST['depositor'])) {
    $p = $plans[$_GET['plan']];
    $id = 'ORD-'.strtoupper(substr(uniqid(),-6));
    $order = [
        'id'=>$id,
        'plan'=>$p['name'],
        'amount'=>$p['price'],
        'depositor'=>$_POST['depositor'],
        'status'=>'pending',
        'days'=>$p['days']
    ];
    $orders = json_decode(file_get_contents($orders_file), true);
    $orders[] = $order;
    file_put_contents($orders_file,json_encode($orders,JSON_PRETTY_PRINT));
    echo "<h1 style='text-align:center;color:#0f0;'>주문 완료<br>$id</h1>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>XGGDAYGEAHUB</title>

<style>
body {background:#0a0a0a;color:#eee;font-family:sans-serif;margin:0;}
header {display:flex;justify-content:space-between;padding:15px;background:#111;align-items:center;}
.logo {color:#00ff9d;font-size:1.8rem;cursor:pointer;}
.nav-btn {margin-left:10px;padding:8px 15px;background:#222;color:#fff;border:none;border-radius:20px;cursor:pointer;}
.nav-btn.active {background:#00ff9d;color:#000;}
.section {display:none;padding:50px;text-align:center;}
.section.active {display:block;}
.card {background:#111;padding:20px;margin:20px auto;max-width:400px;border-radius:15px;}
</style>

</head>
<body>

<header>
<div class="logo" onclick="showTab('shop')">XGGDAYGEAHUB</div>
<div>
<button id="btn-shop" class="nav-btn active" onclick="showTab('shop')">기본</button>
<button id="btn-mykeys" class="nav-btn" onclick="showTab('mykeys')">내 키</button>
</div>
</header>

<div id="shop" class="section active">
<h1>키 구매</h1>

<?php foreach($plans as $code=>$p): ?>
<div class="card">
<h3><?= $p['label'] ?> - ₩<?= number_format($p['price']) ?></h3>
<form method="post" action="?plan=<?= $code ?>">
<input name="depositor" placeholder="입금자 이름" required>
<br><br>
<button>구매</button>
</form>
</div>
<?php endforeach; ?>
</div>

<div id="mykeys" class="section">
<h1>내 키</h1>
<input id="name" placeholder="입금자 이름">
<button onclick="loadKeys()">조회</button>
<div id="result"></div>
</div>

<script>
function showTab(tab){
document.getElementById('shop').classList.remove('active');
document.getElementById('mykeys').classList.remove('active');
document.getElementById(tab).classList.add('active');
document.getElementById('btn-shop').classList.remove('active');
document.getElementById('btn-mykeys').classList.remove('active');
document.getElementById('btn-'+tab).classList.add('active');
}

function loadKeys(){
const name=document.getElementById('name').value;
fetch('?mykeys='+name)
.then(r=>r.json())
.then(d=>{
let html='';
d.keys.forEach(k=>{
html+=`<p>${k.plan} - ${k.key_assigned}</p>`;
});
document.getElementById('result').innerHTML=html||'없음';
});
}

document.querySelector('.logo').ondblclick=()=>{
const p=prompt("관리자 비번");
if(p) location.href='?admin='+p;
}
</script>

</body>
</html>
