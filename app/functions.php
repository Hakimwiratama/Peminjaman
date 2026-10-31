<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Sesi formulir tidak valid atau kedaluwarsa. Muat ulang halaman, lalu coba lagi.');
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_tenant_id(): ?int
{
    $user = current_user();
    if (!$user) {
        return null;
    }
    if ($user['role'] === 'admin') {
        return isset($_SESSION['selected_tenant_id']) ? (int) $_SESSION['selected_tenant_id'] : null;
    }
    return isset($user['tenant_id']) ? (int) $user['tenant_id'] : null;
}

function is_platform_admin(): bool
{
    return current_user() && current_user()['role'] === 'admin';
}

function tenant_clause(string $alias, bool $includeGlobal = false): array
{
    $tenantId = current_tenant_id();
    if ($tenantId === null) {
        return is_platform_admin() ? ['', []] : [" AND {$alias}.tenant_id IS NULL", []];
    }
    if ($includeGlobal) {
        return [" AND ({$alias}.tenant_id = ? OR {$alias}.tenant_id IS NULL)", [$tenantId]];
    }
    return [" AND {$alias}.tenant_id = ?", [$tenantId]];
}

function feature_enabled(PDO $pdo, string $module): bool
{
    $tenantId = current_tenant_id();
    if (!$tenantId) {
        return is_platform_admin();
    }
    $stmt = $pdo->prepare('SELECT is_enabled FROM institution_modules WHERE tenant_id=? AND module_key=?');
    $stmt->execute([$tenantId, $module]);
    $enabled = $stmt->fetchColumn();
    return $enabled === false ? true : (bool) $enabled;
}

function valid_region_pair(PDO $pdo, string $provinceCode, string $regencyCode): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM region_codes r JOIN region_codes p ON p.code=r.parent_code WHERE r.code=? AND r.level=2 AND p.code=? AND p.level=1");
    $stmt->execute([$regencyCode, $provinceCode]);
    return (int) $stmt->fetchColumn() === 1;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('?page=login');
    }
}

function require_role(array $roles): void
{
    require_login();
    if (!in_array(current_user()['role'], $roles, true)) {
        http_response_code(403);
        exit('Anda tidak memiliki izin untuk membuka halaman ini.');
    }
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function record_activity(PDO $pdo, string $action, ?string $detail = null): void
{
    if (current_user()) {
        $stmt = $pdo->prepare('INSERT INTO activity_logs (tenant_id,user_id,action,detail,ip_address) VALUES (?,?,?,?,?)');
        $stmt->execute([current_tenant_id(), current_user()['id'], $action, $detail, $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}

function status_label(string $status): string
{
    return [
        'waiting' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak',
        'borrowed' => 'Sedang dipinjam', 'return_pending' => 'Menunggu pengembalian',
        'completed' => 'Selesai', 'overdue' => 'Terlambat',
    ][$status] ?? ucfirst($status);
}

function type_label(string $type): string
{
    return ['item' => 'Barang', 'book' => 'Buku', 'lab' => 'Laboratorium', 'room' => 'Ruangan'][$type] ?? $type;
}

function categories(PDO $pdo): array
{
    $tenantId = current_tenant_id();
    if ($tenantId === null) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT id,name,type FROM categories WHERE is_active=1 AND (tenant_id=? OR tenant_id IS NULL) ORDER BY name');
    $stmt->execute([$tenantId]);
    return $stmt->fetchAll();
}

function resource_data(PDO $pdo, string $type, int $id): ?array
{
    $tables = ['item' => 'items', 'book' => 'books', 'lab' => 'laboratory_equipments', 'room' => 'rooms'];
    if (!isset($tables[$type])) {
        return null;
    }
    $tenantId = current_tenant_id();
    if (!$tenantId && !is_platform_admin()) {
        return null;
    }
    $scope = $tenantId ? ' AND r.tenant_id=?' : '';
    $stmt = $pdo->prepare("SELECT r.*,c.name AS category_name FROM {$tables[$type]} r LEFT JOIN categories c ON c.id=r.category_id WHERE r.id=?{$scope}");
    $stmt->execute($tenantId ? [$id,$tenantId] : [$id]);
    return $stmt->fetch() ?: null;
}

function active_count(PDO $pdo, string $type, int $id): int
{
    $tenantId = current_tenant_id();
    $scope = $tenantId ? ' AND b.tenant_id=?' : '';
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(bd.quantity),0) FROM borrowing_details bd JOIN borrowings b ON b.id=bd.borrowing_id WHERE bd.resource_type=? AND bd.resource_id=? AND b.status IN ('approved','borrowed','return_pending'){$scope}");
    $stmt->execute($tenantId ? [$type,$id,$tenantId] : [$type,$id]);
    return (int) $stmt->fetchColumn();
}

function report_filters(): array
{
    $clauses = [];
    $params = [];
    foreach (['from' => 'start_date >= ?', 'to' => 'end_date <= ?'] as $key => $clause) {
        $value = (string) ($_GET[$key] ?? '');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date && $date->format('Y-m-d') === $value) {
            $clauses[] = 'b.' . $clause;
            $params[] = $value;
        }
    }
    $type = (string) ($_GET['type'] ?? '');
    if (in_array($type, ['item', 'book', 'lab', 'room'], true)) {
        $clauses[] = 'b.type = ?';
        $params[] = $type;
    }
    $userId = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT);
    if ($userId && $userId > 0) {
        $clauses[] = 'b.user_id = ?';
        $params[] = $userId;
    }
    if (current_tenant_id()) {
        $clauses[] = 'b.tenant_id = ?';
        $params[] = current_tenant_id();
    }
    return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
}

function csv_safe(mixed $value): mixed
{
    if (is_string($value) && preg_match('/^[\s]*[=+\-@]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

function render(string $page, array $data = [], ?PDO $pdo = null): void
{
    extract($data, EXTR_SKIP);
    require dirname(__DIR__) . '/views/header.php';
    require dirname(__DIR__) . "/views/{$page}.php";
    require dirname(__DIR__) . '/views/footer.php';
}
