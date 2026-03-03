<?php
/**
 * Helper Functions
 */

require_once __DIR__ . '/db.php';

// ── Settings ────────────────────────────────────────────────────────

function getSetting(string $key, string $default = ''): string {
    $db = getDB();
    $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? ($row['setting_value'] ?? $default) : $default;
}

function setSetting(string $key, string $value): void {
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
    $stmt->execute([$key, $value, $value]);
}

function getSettings(): array {
    $db = getDB();
    $stmt = $db->query('SELECT setting_key, setting_value FROM settings');
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

// ── Pricing ─────────────────────────────────────────────────────────

function getPricing(): array {
    $db = getDB();
    $stmt = $db->query('SELECT * FROM pricing ORDER BY sort_order, category');
    return $stmt->fetchAll();
}

function getPricingByKey(): array {
    $pricing = getPricing();
    $result = [];
    foreach ($pricing as $p) {
        $result[$p['item_key']] = $p;
    }
    return $result;
}

function getPricingByCategory(): array {
    $pricing = getPricing();
    $result = [];
    foreach ($pricing as $p) {
        $result[$p['category']][] = $p;
    }
    return $result;
}

function getPrice(string $key): float {
    $db = getDB();
    $stmt = $db->prepare('SELECT unit_price FROM pricing WHERE item_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (float)$row['unit_price'] : 0.0;
}

// ── Estimates ───────────────────────────────────────────────────────

function generateEstimateNumber(): string {
    $prefix = getSetting('estimate_prefix', 'EST');
    $date = date('ymd');
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM estimates WHERE estimate_number LIKE ?");
    $stmt->execute([$prefix . '-' . $date . '%']);
    $count = $stmt->fetch()['cnt'] + 1;
    return $prefix . '-' . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
}

function getEstimate(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare('SELECT e.*, c.name as client_name, c.phone as client_phone, c.email as client_email, c.address as client_address FROM estimates e LEFT JOIN clients c ON e.client_id = c.id WHERE e.id = ?');
    $stmt->execute([$id]);
    $estimate = $stmt->fetch();
    if ($estimate) {
        $estimate['items'] = getEstimateItems($id);
    }
    return $estimate ?: null;
}

function getEstimateItems(int $estimateId): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM estimate_items WHERE estimate_id = ? ORDER BY sort_order, id');
    $stmt->execute([$estimateId]);
    return $stmt->fetchAll();
}

function getEstimates(string $search = '', string $status = '', int $limit = 50, int $offset = 0): array {
    $db = getDB();
    $where = [];
    $params = [];

    if ($search) {
        $where[] = '(e.estimate_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)';
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    if ($status) {
        $where[] = 'e.status = ?';
        $params[] = $status;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT e.*, c.name as client_name, c.phone as client_phone 
            FROM estimates e 
            LEFT JOIN clients c ON e.client_id = c.id 
            $whereClause 
            ORDER BY e.created_at DESC 
            LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function countEstimates(string $search = '', string $status = ''): int {
    $db = getDB();
    $where = [];
    $params = [];
    if ($search) {
        $where[] = '(e.estimate_number LIKE ? OR c.name LIKE ?)';
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    if ($status) {
        $where[] = 'e.status = ?';
        $params[] = $status;
    }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT COUNT(*) as cnt FROM estimates e LEFT JOIN clients c ON e.client_id = c.id $whereClause";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetch()['cnt'];
}

function saveEstimate(array $data, array $items): int {
    $db = getDB();
    $db->beginTransaction();

    try {
        $isNew = empty($data['id']);

        if ($isNew) {
            $data['estimate_number'] = generateEstimateNumber();
            $validDays = (int)getSetting('estimate_validity_days', '30');
            $data['valid_until'] = date('Y-m-d', strtotime("+{$validDays} days"));
        }

        $fields = [
            'estimate_number', 'client_id', 'pool_length', 'pool_width',
            'pool_depth_shallow', 'pool_depth_deep', 'pool_shape', 'pool_material',
            'interior_finish', 'has_jacuzzi', 'jacuzzi_size', 'num_lights',
            'has_heating', 'heating_type', 'has_waterfall', 'has_water_feature',
            'has_auto_cover', 'has_pool_cleaner', 'has_deck', 'deck_material',
            'deck_area', 'has_fence', 'fence_type', 'fence_length',
            'subtotal', 'tax_rate', 'tax_amount', 'discount_percent',
            'discount_amount', 'total', 'notes', 'internal_notes',
            'status', 'valid_until'
        ];

        if ($isNew) {
            $cols = implode(', ', array_map(fn($f) => "`$f`", $fields));
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $db->prepare("INSERT INTO estimates ($cols) VALUES ($placeholders)");
            $values = array_map(fn($f) => $data[$f] ?? null, $fields);
            $stmt->execute($values);
            $estimateId = (int)$db->lastInsertId();
        } else {
            $estimateId = (int)$data['id'];
            $sets = implode(', ', array_map(fn($f) => "`$f` = ?", $fields));
            $stmt = $db->prepare("UPDATE estimates SET $sets, updated_at = NOW() WHERE id = ?");
            $values = array_map(fn($f) => $data[$f] ?? null, $fields);
            $values[] = $estimateId;
            $stmt->execute($values);

            // Delete old items
            $db->prepare('DELETE FROM estimate_items WHERE estimate_id = ?')->execute([$estimateId]);
        }

        // Insert line items
        $itemStmt = $db->prepare('INSERT INTO estimate_items (estimate_id, category, description, quantity, unit, unit_price, total, sort_order, is_custom) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($items as $i => $item) {
            $itemStmt->execute([
                $estimateId,
                $item['category'] ?? 'general',
                $item['description'] ?? '',
                $item['quantity'] ?? 1,
                $item['unit'] ?? 'each',
                $item['unit_price'] ?? 0,
                $item['total'] ?? 0,
                $item['sort_order'] ?? $i,
                $item['is_custom'] ?? 0
            ]);
        }

        $db->commit();

        // Audit log
        logAudit('estimate', $estimateId, $isNew ? 'create' : 'update', [
            'estimate_number' => $data['estimate_number'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'total' => $data['total'] ?? $data['subtotal'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'line_items_count' => count($items),
        ]);

        return $estimateId;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function deleteEstimate(int $id): bool {
    $db = getDB();
    // Capture info before deletion for audit
    $estimate = getEstimate($id);
    $stmt = $db->prepare('DELETE FROM estimates WHERE id = ?');
    $result = $stmt->execute([$id]);
    if ($result && $estimate) {
        logAudit('estimate', $id, 'delete', [
            'estimate_number' => $estimate['estimate_number'] ?? null,
            'client_name' => $estimate['client_name'] ?? null,
            'total' => $estimate['total'] ?? null,
        ]);
    }
    return $result;
}

function duplicateEstimate(int $id): ?int {
    $estimate = getEstimate($id);
    if (!$estimate) return null;

    $originalNumber = $estimate['estimate_number'];
    unset($estimate['id']);
    $estimate['estimate_number'] = generateEstimateNumber();
    $estimate['status'] = 'draft';
    $estimate['created_at'] = date('Y-m-d H:i:s');

    $items = [];
    foreach ($estimate['items'] as $item) {
        unset($item['id'], $item['estimate_id']);
        $items[] = $item;
    }

    $newId = saveEstimate($estimate, $items);
    if ($newId) {
        logAudit('estimate', $newId, 'duplicate', [
            'source_id' => $id,
            'source_number' => $originalNumber,
            'new_number' => $estimate['estimate_number'],
        ]);
    }
    return $newId;
}

// ── Clients ─────────────────────────────────────────────────────────

function getClient(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM clients WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getClients(string $search = '', int $limit = 50, int $offset = 0): array {
    $db = getDB();
    if ($search) {
        $stmt = $db->prepare("SELECT * FROM clients WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY name LIMIT $limit OFFSET $offset");
        $s = '%' . $search . '%';
        $stmt->execute([$s, $s, $s]);
    } else {
        $stmt = $db->query("SELECT * FROM clients ORDER BY name LIMIT $limit OFFSET $offset");
    }
    return $stmt->fetchAll();
}

function countClients(string $search = ''): int {
    $db = getDB();
    if ($search) {
        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM clients WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?');
        $s = '%' . $search . '%';
        $stmt->execute([$s, $s, $s]);
    } else {
        $stmt = $db->query('SELECT COUNT(*) as cnt FROM clients');
    }
    return (int)$stmt->fetch()['cnt'];
}

function saveClient(array $data): int {
    $db = getDB();
    $fields = ['name', 'phone', 'email', 'address', 'notes'];

    if (empty($data['id'])) {
        $cols = implode(', ', array_map(fn($f) => "`$f`", $fields));
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $stmt = $db->prepare("INSERT INTO clients ($cols) VALUES ($placeholders)");
        $stmt->execute(array_map(fn($f) => $data[$f] ?? null, $fields));
        $clientId = (int)$db->lastInsertId();
        logAudit('client', $clientId, 'create', [
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);
        return $clientId;
    } else {
        $sets = implode(', ', array_map(fn($f) => "`$f` = ?", $fields));
        $stmt = $db->prepare("UPDATE clients SET $sets WHERE id = ?");
        $values = array_map(fn($f) => $data[$f] ?? null, $fields);
        $values[] = (int)$data['id'];
        $stmt->execute($values);
        logAudit('client', (int)$data['id'], 'update', [
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);
        return (int)$data['id'];
    }
}

function deleteClient(int $id): bool {
    $db = getDB();
    $client = getClient($id);
    $stmt = $db->prepare('DELETE FROM clients WHERE id = ?');
    $result = $stmt->execute([$id]);
    if ($result && $client) {
        logAudit('client', $id, 'delete', [
            'name' => $client['name'] ?? null,
        ]);
    }
    return $result;
}

function getClientEstimateCount(int $clientId): int {
    $db = getDB();
    $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM estimates WHERE client_id = ?');
    $stmt->execute([$clientId]);
    return (int)$stmt->fetch()['cnt'];
}

// ── Security ────────────────────────────────────────────────────────

function generateCSRFToken(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCSRFToken(string $token): bool {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

// ── Formatting ──────────────────────────────────────────────────────

function formatCurrency(float $amount): string {
    $symbol = getSetting('currency_symbol', '$');
    return $symbol . number_format($amount, 2);
}

function formatDate(string $date): string {
    return date('M j, Y', strtotime($date));
}

function formatDateTime(string $date): string {
    return date('M j, Y g:i A', strtotime($date));
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function statusBadge(string $status): string {
    $colors = [
        'draft'    => 'badge-secondary',
        'sent'     => 'badge-primary',
        'approved' => 'badge-success',
        'rejected' => 'badge-danger',
    ];
    $class = $colors[$status] ?? 'badge-secondary';
    return '<span class="badge ' . $class . '">' . ucfirst(e($status)) . '</span>';
}

// ── Audit Log ───────────────────────────────────────────────────────

function getClientIP(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                // Normalize IPv6 loopback to IPv4
                if ($ip === '::1') {
                    return '127.0.0.1';
                }
                return $ip;
            }
        }
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return $ip === '::1' ? '127.0.0.1' : $ip;
}

function logAudit(string $entityType, ?int $entityId, string $action, array $extra = []): void {
    try {
        $db = getDB();
        $userRole = $_SESSION['user_role'] ?? 'unknown';
        $ip = getClientIP();
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $details = !empty($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $db->prepare('INSERT INTO audit_log (entity_type, entity_id, action, user_role, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$entityType, $entityId, $action, $userRole, $ip, $userAgent, $details]);
    } catch (Exception $e) {
        // Silently fail — audit should never break the app
        error_log('Audit log error: ' . $e->getMessage());
    }
}

function getAuditLogs(string $entityType = '', int $limit = 50, int $offset = 0): array {
    $db = getDB();
    $where = [];
    $params = [];
    if ($entityType) {
        $where[] = 'entity_type = ?';
        $params[] = $entityType;
    }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT * FROM audit_log $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function countAuditLogs(string $entityType = ''): int {
    $db = getDB();
    $where = [];
    $params = [];
    if ($entityType) {
        $where[] = 'entity_type = ?';
        $params[] = $entityType;
    }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM audit_log $whereClause");
    $stmt->execute($params);
    return (int)$stmt->fetch()['cnt'];
}

// ── Dashboard Stats ─────────────────────────────────────────────────

function getDashboardStats(): array {
    $db = getDB();
    return [
        'total_estimates' => (int)$db->query("SELECT COUNT(*) FROM estimates")->fetchColumn(),
        'draft_estimates' => (int)$db->query("SELECT COUNT(*) FROM estimates WHERE status='draft'")->fetchColumn(),
        'approved_estimates' => (int)$db->query("SELECT COUNT(*) FROM estimates WHERE status='approved'")->fetchColumn(),
        'total_clients' => (int)$db->query("SELECT COUNT(*) FROM clients")->fetchColumn(),
        'total_value' => (float)$db->query("SELECT COALESCE(SUM(total),0) FROM estimates WHERE status='approved'")->fetchColumn(),
        'month_estimates' => (int)$db->query("SELECT COUNT(*) FROM estimates WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())")->fetchColumn(),
    ];
}

// ── Pool Calculations ───────────────────────────────────────────────

function getShapeFactor(string $shape): float {
    return match ($shape) {
        'rectangular' => 1.0,
        'l-shaped'    => 0.85,
        'kidney'      => 0.80,
        'oval'        => 0.79,  // π/4
        'freeform'    => 0.75,
        default       => 1.0,
    };
}

function calculatePoolMetrics(array $data): array {
    $length = (float)($data['pool_length'] ?? 0);
    $width = (float)($data['pool_width'] ?? 0);
    $depthShallow = (float)($data['pool_depth_shallow'] ?? 0);
    $depthDeep = (float)($data['pool_depth_deep'] ?? 0);
    $shape = $data['pool_shape'] ?? 'rectangular';

    $avgDepth = ($depthShallow + $depthDeep) / 2;
    $shapeFactor = getShapeFactor($shape);

    $floorArea = $length * $width * $shapeFactor;
    $wallArea = 2 * ($length + $width) * $avgDepth * $shapeFactor;
    $surfaceArea = $floorArea + $wallArea;
    $volumeCuFt = $floorArea * $avgDepth;
    $volumeGallons = $volumeCuFt * 7.48;
    $perimeter = 2 * ($length + $width) * sqrt($shapeFactor);

    return [
        'floor_area'     => round($floorArea, 2),
        'wall_area'      => round($wallArea, 2),
        'surface_area'   => round($surfaceArea, 2),
        'volume_cuft'    => round($volumeCuFt, 2),
        'volume_gallons' => round($volumeGallons, 0),
        'perimeter'      => round($perimeter, 2),
        'avg_depth'      => round($avgDepth, 2),
    ];
}
