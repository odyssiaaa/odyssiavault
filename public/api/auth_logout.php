<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

logoutAuthUser();

jsonResponse([
    'status' => true,
    'data' => ['msg' => 'Logout berhasil.'],
]);
