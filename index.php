<?php
// ==================== DISCORD CLONE - SINGLE FILE DEMO (인증 없음) ====================
session_start();
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;

$dsn = "sqlite:./discord.db";
$pdo = new PDO($dsn);
$pdo->exec("CREATE TABLE IF NOT EXISTS servers (id INTEGER PRIMARY KEY, name TEXT, icon_url TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS channels (id INTEGER PRIMARY KEY, server_id INTEGER, name TEXT, type TEXT DEFAULT 'text', position INTEGER DEFAULT 0)");
$pdo->exec("CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY, channel_id INTEGER, user_id INTEGER, content TEXT, created_at TEXT)");

// ====================== POST 처리 ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create_server') {
            $name = $_POST['name'] ?? '새 서버';
            $icon = '';
            if (isset($_FILES['icon']) && $_FILES['icon']['error'] === 0) {
                $icon = 'uploads/' . basename($_FILES['icon']['name']);
                move_uploaded_file($_FILES['icon']['tmp_name'], $icon);
            }
            $stmt = $pdo->prepare("INSERT INTO servers (name, icon_url, created_at) VALUES (?, ?, datetime('now'))");
            $stmt->execute([$name, $icon]);
            $server_id = $pdo->lastInsertId();
            header("Location: ?server=$server_id");
            exit;
        }
        
        if ($_POST['action'] === 'create_channel') {
            $server_id = $_POST['server_id'];
            $name = $_POST['name'] ?? '새 채널';
            $stmt = $pdo->prepare("INSERT INTO channels (server_id, name) VALUES (?, ?)");
            $stmt->execute([$server_id, $name]);
            header("Location: ?server=$server_id");
            exit;
        }
        
        if ($_POST['action'] === 'send_message') {
            $channel_id = $_POST['channel_id'];
            $content = $_POST['content'];
            $stmt = $pdo->prepare("INSERT INTO messages (channel_id, user_id, content, created_at) VALUES (?, ?, ?, datetime('now'))");
            $stmt->execute([$channel_id, $_SESSION['user_id'], $content]);
            header("Location: ?server={$_POST['server_id']}&channel=$channel_id");
            exit;
        }
    }
}

// ====================== GET 파라미터 ======================
$current_server = $_GET['server'] ?? null;
$current_channel = $_GET['channel'] ?? null;

$servers = $pdo->query("SELECT * FROM servers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

if ($current_server) {
    $stmt = $pdo->prepare("SELECT * FROM channels WHERE server_id = ? ORDER BY position");
    $stmt->execute([$current_server]);
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($current_channel) {
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE channel_id = ? ORDER BY id DESC LIMIT 50");
        $stmt->execute([$current_channel]);
        $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discord Clone</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; }
        .channel-list { scrollbar-width: thin; }
    </style>
</head>
<body class="bg-neutral-900 text-white flex h-screen overflow-hidden">

<!-- 서버 리스트 -->
<div class="w-18 bg-neutral-950 flex flex-col items-center py-3 space-y-3 overflow-y-auto">
    <?php foreach ($servers as $s): ?>
        <a href="?server=<?= $s['id'] ?>" class="w-12 h-12 rounded-2xl overflow-hidden border-2 <?= $current_server == $s['id'] ? 'border-white' : 'border-transparent hover:rounded-xl' ?>">
            <?php if ($s['icon_url']): ?>
                <img src="<?= $s['icon_url'] ?>" class="w-full h-full object-cover">
            <?php else: ?>
                <div class="w-full h-full bg-indigo-600 flex items-center justify-center text-xl font-bold"><?= mb_substr($s['name'], 0, 1) ?></div>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
    <a href="?create=1" class="w-12 h-12 rounded-2xl bg-neutral-800 hover:bg-neutral-700 flex items-center justify-center text-3xl">+</a>
</div>

<!-- 서버 내부 -->
<div class="w-60 bg-neutral-800 flex flex-col">
    <div class="p-3 font-semibold border-b border-neutral-700">
        <?php if ($current_server): ?>
            <?php 
            $stmt = $pdo->prepare("SELECT name FROM servers WHERE id = ?");
            $stmt->execute([$current_server]);
            echo $stmt->fetchColumn();
            ?>
        <?php else: ?>
            Discord Clone
        <?php endif; ?>
    </div>
    
    <div class="flex-1 overflow-y-auto channel-list p-2">
        <?php if ($current_server): ?>
            <div class="flex justify-between items-center px-2 py-1 text-xs uppercase text-neutral-400">
                <span>채널</span>
                <button onclick="document.getElementById('createChannelModal').showModal()" class="hover:text-white">+</button>
            </div>
            <?php foreach ($channels as $ch): ?>
                <a href="?server=<?= $current_server ?>&channel=<?= $ch['id'] ?>" 
                   class="flex items-center gap-2 px-2 py-1 hover:bg-neutral-700 rounded <?= $current_channel == $ch['id'] ? 'bg-neutral-700' : '' ?>">
                    <i class="fa-solid fa-hashtag text-neutral-400"></i>
                    <span><?= htmlspecialchars($ch['name']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- 메인 채팅 영역 -->
<div class="flex-1 flex flex-col">
    <div class="h-12 border-b border-neutral-700 flex items-center px-4 font-medium">
        <?php if ($current_channel): ?>
            # <?= htmlspecialchars($pdo->query("SELECT name FROM channels WHERE id = $current_channel")->fetchColumn()) ?>
        <?php else: ?>
            서버를 선택해주세요
        <?php endif; ?>
    </div>
    
    <div class="flex-1 p-4 overflow-y-auto space-y-4" id="chat-area">
        <?php if (isset($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="flex gap-3">
                    <div class="w-8 h-8 bg-neutral-600 rounded-full flex-shrink-0"></div>
                    <div>
                        <div class="text-sm">
                            <span class="font-medium">User<?= $msg['user_id'] ?></span>
                            <span class="text-neutral-500 text-xs ml-2"><?= $msg['created_at'] ?></span>
                        </div>
                        <div><?= htmlspecialchars($msg['content']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php if ($current_channel): ?>
    <form method="POST" class="p-4 border-t border-neutral-700">
        <input type="hidden" name="action" value="send_message">
        <input type="hidden" name="channel_id" value="<?= $current_channel ?>">
        <input type="hidden" name="server_id" value="<?= $current_server ?>">
        <div class="flex gap-2">
            <input type="text" name="content" placeholder="메시지를 입력하세요..." 
                   class="flex-1 bg-neutral-700 rounded-lg px-4 py-3 focus:outline-none">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 px-6 rounded-lg">전송</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<!-- 서버 생성 모달 -->
<dialog id="createServerModal" class="bg-neutral-800 text-white p-6 rounded-xl w-96">
    <h2 class="text-xl mb-4">서버 만들기</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create_server">
        <input type="text" name="name" placeholder="서버 이름" class="w-full bg-neutral-700 p-3 rounded mb-4" required>
        <input type="file" name="icon" accept="image/*" class="mb-4">
        <div class="flex justify-end gap-3">
            <button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2">취소</button>
            <button type="submit" class="bg-indigo-600 px-6 py-2 rounded">만들기</button>
        </div>
    </form>
</dialog>

<!-- 채널 생성 모달 -->
<dialog id="createChannelModal" class="bg-neutral-800 text-white p-6 rounded-xl w-96">
    <h2 class="text-xl mb-4">채널 만들기</h2>
    <form method="POST">
        <input type="hidden" name="action" value="create_channel">
        <input type="hidden" name="server_id" value="<?= $current_server ?>">
        <input type="text" name="name" placeholder="채널 이름" class="w-full bg-neutral-700 p-3 rounded mb-4" required>
        <div class="flex justify-end gap-3">
            <button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2">취소</button>
            <button type="submit" class="bg-indigo-600 px-6 py-2 rounded">만들기</button>
        </div>
    </form>
</dialog>

<script>
if (window.location.search === '?create=1') {
    document.getElementById('createServerModal').showModal();
}
</script>
</body>
</html>
