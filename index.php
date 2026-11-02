<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$currentUserId = $_SESSION['user_id'];
$currentRole = $_SESSION['user_role'];
$message = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $categoryId = intval($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($categoryId > 0 && !empty($description)) {
  
        $stmt = $pdo->prepare("INSERT INTO tickets (user_id, category_id, description) VALUES (?, ?, ?)");
        $stmt->execute([$currentUserId, $categoryId, $description]);
        header("Location: index.php?success=1");
        exit;
    } else {
        $message = "Заполните все поля!";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if ($currentRole === 'admin') {
        $ticketId = intval($_POST['ticket_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        if (in_array($newStatus, ['Новая', 'В работе', 'Решена'])) {
            $stmt = $pdo->prepare("UPDATE tickets SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $ticketId]);
            header("Location: index.php?updated=1");
            exit;
        }
    }
}


$categories = $pdo->query("SELECT * FROM categories")->fetchAll();


$statusFilter = $_GET['status_filter'] ?? '';
$queryStr = "SELECT t.*, u.full_name AS author_name, d.name AS dept, c.name AS cat 
             FROM tickets t
             LEFT JOIN users u ON t.user_id = u.id
             LEFT JOIN departments d ON u.department_id = d.id
             LEFT JOIN categories c ON t.category_id = c.id";

$conditions = [];
$params = [];


if ($currentRole !== 'admin') {
    $conditions[] = "t.user_id = ?";
    $params[] = $currentUserId;
}


if ($statusFilter) {
    $conditions[] = "t.status = ?";
    $params[] = $statusFilter;
}


if (count($conditions) > 0) {
    $queryStr .= " WHERE " . implode(" AND ", $conditions);
}

$queryStr .= " ORDER BY t.id DESC";


$stmt = $pdo->prepare($queryStr);
$stmt->execute($params);
$tickets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>HelpDesk</title>
    <style>

    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    * {
        box-sizing: border-box;
    }

    body { 
        font-family: 'Inter', Arial, sans-serif; 
        background-color: #f8fafc; 
        margin: 0; 
        padding: 30px 20px; 
        color: #1e293b; 
    }

    .container { 
        max-width: 1200px; 
        margin: 0 auto; 
    }

   
    .top { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .top h2 { 
        margin: 0; 
        font-size: 24px; 
        font-weight: 700; 
        color: #0f172a; 
    }

    .user-panel {
        display: flex;
        align-items: center;
        gap: 15px;
        background: #fff;
        padding: 8px 16px;
        border-radius: 50px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        font-size: 14px;
    }

    .user-panel strong {
        color: #2563eb;
    }

    .logout-btn {
        text-decoration: none;
        color: #ef4444;
        font-weight: 600;
        transition: color 0.2s ease;
    }

    .logout-btn:hover {
        color: #b91c1c;
    }

    
    .layout { 
        display: grid; 
        grid-template-columns: 320px 1fr; 
        gap: 25px; 
        align-items: start;
    }
    
    @media(max-width: 900px) { 
        .layout { grid-template-columns: 1fr; } 
    }


    .card { 
        background: #ffffff; 
        padding: 24px; 
        border-radius: 12px; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        border: 1px solid #f1f5f9;
    }

    .card h3 { 
        margin-top: 0; 
        margin-bottom: 20px; 
        font-size: 18px; 
        font-weight: 600; 
        color: #0f172a; 
    }

  
    form label { 
        display: block; 
        margin-bottom: 6px; 
        font-weight: 500; 
        font-size: 13px; 
        color: #475569;
    }

    form select, 
    form textarea, 
    form button { 
        width: 100%; 
        padding: 10px 12px; 
        margin-bottom: 16px; 
        border: 1px solid #cbd5e1; 
        border-radius: 8px; 
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        background: #f8fafc;
        transition: all 0.2s ease;
    }

    form select:focus, 
    form textarea:focus { 
        outline: none; 
        border-color: #3b82f6; 
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); 
    }

    form textarea {
        resize: vertical;
        min-height: 100px;
    }

    form button { 
        background: #2563eb; 
        color: white; 
        border: none; 
        cursor: pointer; 
        font-weight: 600; 
        font-size: 15px;
        margin-bottom: 0;
        transition: background 0.2s ease, transform 0.1s ease;
    }

    form button:hover { 
        background: #1d4ed8; 
    }
    
    form button:active {
        transform: scale(0.98); 
    }

  
    .filters { 
        display: flex; 
        gap: 10px; 
        margin-bottom: 20px; 
        align-items: center;
        flex-wrap: wrap;
    }

    .filters span {
        font-size: 14px;
        color: #64748b;
        margin-right: 5px;
    }

    .filters a { 
        text-decoration: none; 
        padding: 6px 14px; 
        border-radius: 20px; 
        background: #f1f5f9; 
        color: #475569; 
        font-size: 13px; 
        font-weight: 500;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }

    .filters a:hover {
        background: #e2e8f0;
    }

    .filters a.active { 
        background: #2563eb; 
        color: white; 
    }


    .table-container {
        overflow-x: auto; 
    }

    table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 14px; 
    }

    th, td { 
        padding: 14px 12px; 
        border-bottom: 1px solid #f1f5f9; 
        text-align: left; 
        vertical-align: middle; 
    }

    th { 
        background: #f8fafc; 
        font-weight: 600; 
        color: #64748b;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

  
    tbody tr {
        transition: background-color 0.15s ease;
    }

    tbody tr:hover {
        background-color: #f8fafc;
    }


    .badge { 
        padding: 5px 10px; 
        border-radius: 20px; 
        font-size: 12px; 
        font-weight: 600; 
        display: inline-block;
    }

    .b-new { 
        background: #eff6ff; 
        color: #1d4ed8; 
        border: 1px solid #bfdbfe;
    }

    .b-work { 
        background: #fefce8; 
        color: #a16207; 
        border: 1px solid #fef08a;
    }

    .b-done { 
        background: #f0fdf4; 
        color: #15803d; 
        border: 1px solid #bbf7d0;
    }

  
    .inline-form { 
        display: flex; 
        gap: 6px; 
        margin: 0; 
        align-items: center;
    }

    .inline-form select { 
        width: auto; 
        margin: 0; 
        padding: 6px 8px; 
        font-size: 12px; 
        border-radius: 6px;
    }

    .inline-form button { 
        width: auto; 
        padding: 6px 12px; 
        font-size: 12px; 
        margin: 0; 
        border-radius: 6px;
    }
</style>

</head>
<body>
<div class="container">
    <div class="top">
        <h2>Заявки</h2>
        <div>
            Вы: <?= htmlspecialchars($_SESSION['user_name']) ?> (<?= $currentRole ?>)
            <a href="?logout=1">Выйти</a>
        </div>
    </div>

    <div class="layout">
        <div class="card">
            <h3>Новая заявка</h3>
            <?php if ($message): ?><p style="color:red;"><?= $message ?></p><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <label>Категория:</label>
                <select name="category_id" required>
                    <option value="">-- Выбрать --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Описание:</label>
                <textarea name="description" required></textarea>
                <button type="submit">Отправить</button>
            </form>
        </div>

        <div class="card">
            <h3>Журнал</h3>
            <div style="margin-bottom:10px;">
                Фильтр: 
                <a href="index.php">Все</a> | 
                <a href="?status_filter=Новая">Новые</a> | 
                <a href="?status_filter=В работе">В работе</a> | 
                <a href="?status_filter=Решена">Решенные</a>
            </div>
            <table>
                <tr><th>ID</th><th>Автор</th><th>Проблема</th><th>Статус</th><?php if($currentRole==='admin'): ?><th>Действие</th><?php endif; ?></tr>
                <?php foreach ($tickets as $t): 
                    $badge = 'b-new';
                    if ($t['status']=='В работе') $badge='b-work';
                    if ($t['status']=='Решена') $badge='b-done';
                ?>
                <tr>
                    <td>#<?= $t['id'] ?></td>
                    <td><?= htmlspecialchars($t['author_name']) ?><br><small><?= htmlspecialchars($t['dept']) ?></small></td>
                    <td><b><?= htmlspecialchars($t['cat']) ?></b><br><?= htmlspecialchars($t['description']) ?></td>
                    <td><span class="badge <?= $badge ?>"><?= $t['status'] ?></span></td>
                    <?php if($currentRole==='admin'): ?>
                    <td>
                        <form method="POST" style="display:flex; gap:5px;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                            <select name="status" style="padding:4px; width:auto;">
                                <option <?= $t['status']=='Новая'?'selected':'' ?>>Новая</option>
                                <option <?= $t['status']=='В работе'?'selected':'' ?>>В работе</option>
                                <option <?= $t['status']=='Решена'?'selected':'' ?>>Решена</option>
                            </select>
                            <button type="submit" style="width:auto; padding:4px;">Ок</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>
