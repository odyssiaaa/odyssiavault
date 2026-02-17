<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);

if (!ensureTicketTables($pdo)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Fitur customer service sedang tidak tersedia.'],
    ], 500);
}

$limit = min(200, max(10, sanitizeQuantity($_GET['limit'] ?? 80)));
$statusFilter = mb_strtolower(trim((string)($_GET['status'] ?? 'active')));
if (!in_array($statusFilter, ['active', 'all', 'open', 'answered', 'closed'], true)) {
    $statusFilter = 'active';
}

$conditions = ['t.category LIKE :category_like'];
$params = [
    'category_like' => 'Customer Service Chat%',
];

if ($statusFilter === 'active') {
    $conditions[] = "t.status <> 'closed'";
} elseif ($statusFilter !== 'all') {
    $conditions[] = 't.status = :status_filter';
    $params['status_filter'] = $statusFilter;
}

$whereSql = $conditions !== [] ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$sql = 'SELECT
            t.id,
            t.user_id,
            u.username,
            t.subject,
            t.category,
            t.priority,
            t.status,
            t.last_message_at,
            t.updated_at,
            (SELECT tm.message
             FROM ticket_messages tm
             WHERE tm.ticket_id = t.id
             ORDER BY tm.id DESC
             LIMIT 1) AS last_message,
            (SELECT tm.sender_role
             FROM ticket_messages tm
             WHERE tm.ticket_id = t.id
             ORDER BY tm.id DESC
             LIMIT 1) AS last_sender_role
        FROM tickets t
        INNER JOIN users u ON u.id = t.user_id
        ' . $whereSql . '
        ORDER BY t.last_message_at DESC, t.id DESC
        LIMIT :limit';

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    error_log('CS chat admin list failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memuat daftar chat customer service.'],
    ], 500);
}

foreach ($rows as &$row) {
    $mode = stripos((string)($row['category'] ?? ''), 'ADMIN') !== false ? 'admin' : 'bot';
    $lastMessage = trim((string)($row['last_message'] ?? ''));
    if ($lastMessage !== '' && mb_strlen($lastMessage) > 180) {
        $lastMessage = mb_substr($lastMessage, 0, 180) . '...';
    }

    $row['mode'] = $mode;
    $row['last_message'] = $lastMessage;
}
unset($row);

jsonResponse([
    'status' => true,
    'data' => [
        'status' => $statusFilter,
        'chats' => $rows,
    ],
]);

