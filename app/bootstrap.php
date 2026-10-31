<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

$config = require dirname(__DIR__) . '/config/config.php';
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db']['host'], $config['db']['port'], $config['db']['name']),
        $config['db']['user'],
        $config['db']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $exception) {
    http_response_code(500);
    exit('Koneksi database gagal. Periksa konfigurasi di config/config.php dan pastikan database sipintar sudah dibuat.');
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/actions.php';

if (!empty($_SESSION['user']['id'])) {
    $stmt = $pdo->prepare('SELECT u.id,u.name,u.email,u.tenant_id,u.student_number,u.cohort,u.phone,r.name AS role,i.name AS institution_name,i.status AS institution_status FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN institutions i ON i.id=u.tenant_id WHERE u.id=? AND u.is_active=1');
    $stmt->execute([$_SESSION['user']['id']]);
    $activeUser = $stmt->fetch();
    if ($activeUser) {
        $_SESSION['user'] = $activeUser;
    } else {
        unset($_SESSION['user']);
        session_regenerate_id(true);
    }
}
