<?php
session_start();

define('JSON_DIR', __DIR__ . '/storage/json/');
define('BACKUP_DIR', JSON_DIR . 'backup/');
define('HISTORY_DIR', JSON_DIR . 'history/');

function initDirectoriesAndFiles() {
    foreach ([JSON_DIR, BACKUP_DIR, HISTORY_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    $firstFile = JSON_DIR . 'users_001.json';
    if (!file_exists($firstFile)) {
        writeJson('users_001.json', []);
    }
}

initDirectoriesAndFiles();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function readJson($file) {
    $path = JSON_DIR . $file;
    if (!file_exists($path)) return [];
    $content = file_get_contents($path);
    return json_decode($content, true) ?: [];
}

function writeJson($file, $data) {
    $path = JSON_DIR . $file;
    if (file_exists($path)) {
        $backupPath = BACKUP_DIR . pathinfo($file, PATHINFO_FILENAME) . '_' . date('Ymd_His') . '.json';
        copy($path, $backupPath);
    }
    $tmpPath = $path . '.tmp';
    file_put_contents($tmpPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($tmpPath, $path);
}

function getUsersFiles() {
    $files = [];
    $i = 1;
    while (true) {
        $f = 'users_' . str_pad($i, 3, '0', STR_PAD_LEFT) . '.json';
        if (!file_exists(JSON_DIR . $f)) break;
        $files[] = $f;
        $i++;
    }
    if (empty($files)) $files[] = 'users_001.json';
    return $files;
}

function updateUserBalance($id, $newBalance) {
    foreach (getUsersFiles() as $f) {
        $users = readJson($f);
        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                $u['balance'] = $newBalance;
                writeJson($f, $users);
                return true;
            }
        }
    }
    return false;
}

function saveHistory($userId, $type, $amount, $detail) {
    $file = HISTORY_DIR . $userId . '.json';
    $hist = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    array_unshift($hist, [
        'time' => date('Y-m-d H:i:s'),
        'type' => $type,
        'amount' => $amount,
        'detail' => $detail
    ]);
    file_put_contents($file, json_encode($hist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$message = '';
$action = $_POST['action'] ?? '';
$csrf = $_POST['csrf_token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $csrf === $_SESSION['csrf_token']) {

    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $withdraw_pw = $_POST['withdraw_pw'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $aff_input = strtoupper(trim($_POST['aff_code'] ?? ''));

        if (empty($username) || empty($password) || empty($withdraw_pw) || empty($phone)) {
            $message = "모든 항목을 입력해주세요.";
        } elseif (strlen($withdraw_pw) < 4) {
            $message = "환전 비밀번호는 4자 이상이어야 합니다.";
        } elseif (!preg_match('/^01[0-9]{8,9}$/', $phone)) {
            $message = "올바른 전화번호를 입력해주세요.";
        } else {
            $exists = false;
            foreach (getUsersFiles() as $f) {
                foreach (readJson($f) as $u) {
                    if ($u['username'] === $username) $exists = true;
                }
            }
            if ($exists) {
                $message = "이미 존재하는 아이디입니다.";
            } else {
                $aff_code = '';
                do {
                    $aff_code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
                    $duplicate = false;
                    foreach (getUsersFiles() as $f) {
                        foreach (readJson($f) as $u) {
                            if (isset($u['affiliate_code']) && $u['affiliate_code'] === $aff_code) $duplicate = true;
                        }
                    }
                } while ($duplicate);

                $newUser = [
                    'id' => 'u_' . bin2hex(random_bytes(8)),
                    'username' => $username,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'withdraw_pw' => password_hash($withdraw_pw, PASSWORD_DEFAULT),
                    'phone' => $phone,
                    'balance' => 0.0,
                    'affiliate_code' => $aff_code,
                    'referred_by' => $aff_input ?: null,
                    'created_at' => date('c'),
                    'last_attend' => null
                ];

                if ($aff_input) {
                    foreach (getUsersFiles() as $f) {
                        $users = readJson($f);
                        foreach ($users as &$u) {
                            if (isset($u['affiliate_code']) && $u['affiliate_code'] === $aff_input) {
                                $u['balance'] += 10000;
                                writeJson($f, $users);
                                break 2;
                            }
                        }
                    }
                }

                $files = getUsersFiles();
                $lastFile = end($files);
                $data = readJson($lastFile);

                if (count($data) < 1000) {
                    $data[] = $newUser;
                    writeJson($lastFile, $data);
                } else {
                    $nextFile = 'users_' . str_pad(count($files)+1, 3, '0', STR_PAD_LEFT) . '.json';
                    writeJson($nextFile, [$newUser]);
                }
                $message = "회원가입 완료! 로그인해주세요.";
            }
        }
    }

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $found = false;
        foreach (getUsersFiles() as $f) {
            $users = readJson($f);
            foreach ($users as $u) {
                if ($u['username'] === $username && password_verify($password, $u['password'])) {
                    $_SESSION['user'] = $u;
                    $found = true;
                    break 2;
                }
            }
        }
        $message = $found ? "로그인 성공!" : "아이디 또는 비밀번호가 틀렸습니다.";
    }

    if ($action === 'logout') {
        session_destroy();
        header("Location: index.php");
        exit;
    }

    if ($action === 'play_game' && isset($_SESSION['user'])) {
        $game = $_POST['game'] ?? '';
        $bet = (float)($_POST['bet'] ?? 0);

        if ($bet <= 0 || $_SESSION['user']['balance'] < $bet) {
            $message = "베팅 금액 오류 또는 잔고 부족";
        } else {
            $win = 0; $detail = "";

            if ($game === 'slot') {
                $symbols = ['🍒','🍋','🍉','⭐','7'];
                $r1 = $symbols[array_rand($symbols)];
                $r2 = $symbols[array_rand($symbols)];
                $r3 = $symbols[array_rand($symbols)];
                $detail = "$r1 $r2 $r3";
                if ($r1 === $r2 && $r2 === $r3) $win = $bet * 15;
                elseif ($r1 === $r2) $win = $bet * 4;
            } elseif ($game === 'crash') {
                $multi = round(mt_rand(120, 450) / 100, 2);
                $win = $bet * $multi;
                $detail = "{$multi}x";
            } elseif ($game === 'dice') {
                $roll = mt_rand(1, 6);
                $detail = "주사위 {$roll}";
                $win = ($roll >= 4) ? $bet * 1.8 : 0;
            }

            $profit = $win - $bet;
            $_SESSION['user']['balance'] += $profit;

            if ($profit < 0 && isset($_SESSION['user']['referred_by'])) {
                $loss = abs($profit);
                $commission = round($loss * 0.4);
                foreach (getUsersFiles() as $f) {
                    $users = readJson($f);
                    foreach ($users as &$u) {
                        if (isset($u['affiliate_code']) && $u['affiliate_code'] === $_SESSION['user']['referred_by']) {
                            $u['balance'] += $commission;
                            writeJson($f, $users);
                            saveHistory($u['id'], '커미션', $commission, $_SESSION['user']['username'] . ' 손실');
                            break 2;
                        }
                    }
                }
            }

            updateUserBalance($_SESSION['user']['id'], $_SESSION['user']['balance']);
            saveHistory($_SESSION['user']['id'], $game, $profit, $detail);

            $message = "[{$game}] {$detail} | " . ($profit >= 0 ? "당첨 +" : "손실 ") . number_format(abs($profit)) . "원";
        }
    }

    if ($action === 'withdraw' && isset($_SESSION['user'])) {
        $amount = (int)$_POST['amount'];
        $input_pw = $_POST['withdraw_pw'] ?? '';
        if ($amount < 10000 || $_SESSION['user']['balance'] < $amount) {
            $message = "환전 금액은 10,000원 이상이어야 합니다.";
        } elseif (!password_verify($input_pw, $_SESSION['user']['withdraw_pw'])) {
            $message = "환전 비밀번호가 틀렸습니다.";
        } else {
            $_SESSION['user']['balance'] -= $amount;
            updateUserBalance($_SESSION['user']['id'], $_SESSION['user']['balance']);
            saveHistory($_SESSION['user']['id'], '환전', -$amount, '환전 신청');
            $message = "{$amount}원 환전 신청 완료";
        }
    }
}

$user = $_SESSION['user'] ?? null;
$balance = $user['balance'] ?? 0;
$csrf_token = $_SESSION['csrf_token'];
$page = $_GET['page'] ?? 'main';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KKR-1777</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background:#0a0a0a; color:#ddd; }
        .menu-card { background:#1f1f1f; transition:all 0.2s; }
        .menu-card:active { transform:scale(0.93); background:#333; }
        .slot-reel { animation: slotSpin 1.8s ease-in-out; }
        @keyframes slotSpin { 0% { transform:rotateX(0deg); } 100% { transform:rotateX(3600deg); } }
    </style>
</head>
<body class="pb-24">

<div class="max-w-[480px] mx-auto bg-black min-h-screen">

    <header class="bg-gradient-to-r from-red-600 to-black p-4 flex justify-between items-center sticky top-0 z-50">
        <h1 class="text-3xl font-black text-white">고광렬 <span class="text-red-400">1777</span></h1>
        <?php if($user): ?>
        <div onclick="location.href='?page=profile'" class="cursor-pointer text-right">
            <div class="text-xs text-gray-400"><?=htmlspecialchars($user['username'])?></div>
            <div class="text-xl font-bold text-emerald-400"><?=number_format($balance)?> 원</div>
        </div>
        <?php endif; ?>
    </header>

    <?php if($message): ?>
    <div class="mx-4 mt-4 p-4 bg-zinc-800 border-l-4 border-red-500 rounded-2xl"><?=htmlspecialchars($message)?></div>
    <?php endif; ?>

    <?php if ($page === 'main'): ?>
    <div class="grid grid-cols-3 gap-3 p-4">
        <div onclick="location.href='?page=deposit'" class="menu-card p-6 rounded-2xl text-center cursor-pointer"><div class="text-5xl mb-2">💰</div><div class="text-sm">충전</div></div>
        <div onclick="location.href='?page=withdraw'" class="menu-card p-6 rounded-2xl text-center cursor-pointer"><div class="text-5xl mb-2">📤</div><div class="text-sm">환전</div></div>
        <div onclick="location.href='?page=profile'" class="menu-card p-6 rounded-2xl text-center cursor-pointer"><div class="text-5xl mb-2">👤</div><div class="text-sm">프로필</div></div>

        <div onclick="location.href='?page=slot'" class="menu-card p-6 rounded-2xl text-center cursor-pointer"><div class="text-5xl mb-2">🎰</div><div class="text-sm">슬롯</div></div>
        <div onclick="location.href='?page=crash'" class="menu-card p-6 rounded-2xl text-center cursor-pointer"><div class="text-5xl mb-2">🚀</div><div class="text-sm">크래시</div></div>
        <div onclick="location.href='?page=dice'" class="menu-card p-6 rounded-2xl text-center cursor-pointer"><div class="text-5xl mb-2">🎲</div><div class="text-sm">주사위</div></div>

        <div onclick="location.href='?page=history'" class="menu-card p-6 rounded-2xl text-center cursor-pointer"><div class="text-5xl mb-2">📋</div><div class="text-sm">배팅내역</div></div>
    </div>

    <?php elseif ($page === 'deposit'): ?>
    <div class="p-6">
        <h2 class="text-3xl font-bold mb-6">💰 충전하기</h2>
        <div class="bg-zinc-900 rounded-3xl p-8 text-center mb-8">
            <p class="text-lg">카카오뱅크</p>
            <p class="text-2xl font-bold mt-2">김시우</p>
            <p class="text-3xl font-mono text-emerald-400 mt-4">7777-03-0806539</p>
        </div>
        <input id="dep-amount" type="number" min="5000" value="50000" class="w-full bg-zinc-900 p-6 rounded-2xl text-3xl text-center">
        <button onclick="alert('충전 신청이 접수되었습니다.')" class="w-full mt-6 bg-emerald-600 py-6 rounded-2xl text-2xl font-bold">충전 신청</button>
    </div>

    <?php elseif ($page === 'withdraw'): ?>
    <div class="p-6">
        <h2 class="text-3xl font-bold mb-6">📤 환전하기</h2>
        <input id="wd-amount" type="number" min="10000" value="50000" class="w-full bg-zinc-900 p-6 rounded-2xl text-3xl text-center mb-4">
        <input id="wd-pw" type="password" placeholder="환전 비밀번호" class="w-full bg-zinc-900 p-6 rounded-2xl text-xl">
        <button onclick="withdrawSubmit()" class="w-full mt-8 bg-red-600 py-6 rounded-2xl text-2xl font-bold">환전 신청</button>
    </div>

    <?php elseif ($page === 'slot'): ?>
    <div class="p-6">
        <h2 class="text-3xl font-bold mb-6 text-center">🎰 슬롯 머신</h2>
        <div id="slot-result" class="text-7xl h-32 flex items-center justify-center bg-zinc-900 rounded-3xl mb-8">🍒 🍋 ⭐</div>
        <input id="slot-bet" type="number" value="10000" class="w-full bg-zinc-900 p-6 rounded-2xl text-3xl text-center mb-6">
        <button onclick="playSlot()" class="w-full bg-red-600 py-6 rounded-2xl text-2xl font-bold">스핀하기</button>
    </div>

    <?php elseif ($page === 'crash'): ?>
    <div class="p-6">
        <h2 class="text-3xl font-bold mb-6 text-center">🚀 크래시</h2>
        <div id="crash-result" class="text-6xl h-32 flex items-center justify-center bg-zinc-900 rounded-3xl mb-8">배율 대기중...</div>
        <input id="crash-bet" type="number" value="5000" class="w-full bg-zinc-900 p-6 rounded-2xl text-3xl text-center mb-6">
        <button onclick="playCrash()" class="w-full bg-red-600 py-6 rounded-2xl text-2xl font-bold">베팅하기</button>
    </div>

    <?php elseif ($page === 'dice'): ?>
    <div class="p-6">
        <h2 class="text-3xl font-bold mb-6 text-center">🎲 주사위</h2>
        <div id="dice-result" class="text-8xl h-32 flex items-center justify-center bg-zinc-900 rounded-3xl mb-8">🎲</div>
        <input id="dice-bet" type="number" value="10000" class="w-full bg-zinc-900 p-6 rounded-2xl text-3xl text-center mb-6">
        <button onclick="playDice()" class="w-full bg-red-600 py-6 rounded-2xl text-2xl font-bold">던지기</button>
    </div>

    <?php elseif ($page === 'profile'): ?>
    <div class="p-6">
        <h2 class="text-3xl font-bold mb-6">👤 프로필</h2>
        <div class="bg-zinc-900 rounded-3xl p-6 mb-6 text-center">
            <div class="text-5xl font-mono text-yellow-300"><?= htmlspecialchars($user['affiliate_code'] ?? '') ?></div>
            <button onclick="navigator.clipboard.writeText('<?= $user['affiliate_code'] ?? '' ?>'); alert('추천인 코드 복사됨')" class="mt-4 w-full bg-white text-black py-4 rounded-2xl font-bold">코드 복사하기</button>
        </div>
        <div class="bg-zinc-900 rounded-3xl p-6">
            <div class="flex justify-between py-3"><span>아이디</span><span><?= htmlspecialchars($user['username']) ?></span></div>
            <div class="flex justify-between py-3"><span>전화번호</span><span><?= htmlspecialchars($user['phone']) ?></span></div>
            <div class="flex justify-between py-3"><span>잔고</span><span class="text-emerald-400 font-bold"><?= number_format($balance) ?>원</span></div>
        </div>
    </div>

    <?php elseif ($page === 'history'): ?>
    <div class="p-6">
        <h2 class="text-3xl font-bold mb-6">📋 배팅 기록</h2>
        <?php 
        $histFile = HISTORY_DIR . ($user['id'] ?? '') . '.json';
        $history = file_exists($histFile) ? json_decode(file_get_contents($histFile), true) : [];
        foreach ($history as $h): ?>
            <div class="bg-zinc-900 p-4 rounded-2xl mb-3">
                <div class="flex justify-between"><span><?= $h['time'] ?></span><span class="<?= $h['amount'] >= 0 ? 'text-green-400' : 'text-red-400' ?>"><?= number_format($h['amount']) ?>원</span></div>
                <div class="text-xs text-gray-400"><?= htmlspecialchars($h['detail']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script>
function withdrawSubmit() {
    const form = new FormData();
    form.append('action', 'withdraw');
    form.append('amount', document.getElementById('wd-amount').value);
    form.append('withdraw_pw', document.getElementById('wd-pw').value);
    form.append('csrf_token', '<?= $csrf_token ?>');
    fetch('index.php', {method:'POST', body:form}).then(() => location.reload());
}

function playSlot() {
    const bet = document.getElementById('slot-bet').value;
    const form = new FormData();
    form.append('action', 'play_game');
    form.append('game', 'slot');
    form.append('bet', bet);
    form.append('csrf_token', '<?= $csrf_token ?>');
    fetch('index.php', {method:'POST', body:form}).then(() => location.reload());
}

function playCrash() {
    const bet = document.getElementById('crash-bet').value;
    const form = new FormData();
    form.append('action', 'play_game');
    form.append('game', 'crash');
    form.append('bet', bet);
    form.append('csrf_token', '<?= $csrf_token ?>');
    fetch('index.php', {method:'POST', body:form}).then(() => location.reload());
}

function playDice() {
    const bet = document.getElementById('dice-bet').value;
    const form = new FormData();
    form.append('action', 'play_game');
    form.append('game', 'dice');
    form.append('bet', bet);
    form.append('csrf_token', '<?= $csrf_token ?>');
    fetch('index.php', {method:'POST', body:form}).then(() => location.reload());
}
</script>

</body>
</html>
