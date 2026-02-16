<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
$isAdmin = (string)($user['role'] ?? 'user') === 'admin';

if (!ensureTicketTables($pdo)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Fitur tiket sedang tidak tersedia. Coba lagi beberapa saat.'],
    ], 500);
}

$limit = min(200, max(10, sanitizeQuantity($_GET['limit'] ?? 100)));
$statusFilter = mb_strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($statusFilter, ['all', 'open', 'answered', 'closed'], true)) {
    $statusFilter = 'all';
}

$query = trim((string)($_GET['q'] ?? ''));
$queryLike = $query === '' ? '' : '%' . $query . '%';
$filterUserId = sanitizeQuantity($_GET['user_id'] ?? '');

$conditions = [];
$params = [];

if (!$isAdmin) {
    $conditions[] = 't.user_id = :user_id';
    $params['user_id'] = (int)$user['id'];
} elseif ($filterUserId > 0) {
    $conditions[] = 't.user_id = :filter_user_id';
    $params['filter_user_id'] = $filterUserId;
}

if ($statusFilter !== 'all') {
    $conditions[] = 't.status = :status_filter';
    $params['status_filter'] = $statusFilter;
}

if ($queryLike !== '') {
    $conditions[] = '(t.subject LIKE :query OR t.id LIKE :query OR COALESCE(t.order_id, 0) LIKE :query OR u.username LIKE :query)';
    $params['query'] = $queryLike;
}

$whereSql = $conditions !== [] ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$sql = 'SELECT
            t.id,
            t.user_id,
            u.username,
            t.order_id,
            t.subject,
            t.category,
            t.priority,
            t.status,
            t.last_message_at,
            t.created_at,
            t.updated_at,
            (SELECT COUNT(*)
             FROM ticket_messages tm
             WHERE tm.ticket_id = t.id) AS total_messages,
            (SELECT tm2.message
             FROM ticket_messages tm2
             WHERE tm2.ticket_id = t.id
             ORDER BY tm2.id DESC
             LIMIT 1) AS last_message
        FROM tickets t
        INNER JOIN users u ON u.id = t.user_id
        ' . $whereSql . '
        ORDER BY t.last_message_at DESC, t.id DESC
        LIMIT :limit';

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue(':' . $key, $value, $type);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $tickets = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    error_log('Load tickets failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memuat daftar tiket.'],
    ], 500);
}

foreach ($tickets as &$ticket) {
    $lastMessage = trim((string)($ticket['last_message'] ?? ''));
    if ($lastMessage !== '' && mb_strlen($lastMessage) > 140) {
        $lastMessage = mb_substr($lastMessage, 0, 140) . '...';
    }
    $ticket['last_message'] = $lastMessage;
    $ticket['total_messages'] = (int)($ticket['total_messages'] ?? 0);
}
unset($ticket);

jsonResponse([
    'status' => true,
    'data' => [
        'status' => $statusFilter,
        'tickets' => $tickets,
        'is_admin' => $isAdmin,
    ],
]);
