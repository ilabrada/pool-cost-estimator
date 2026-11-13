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

// ── Ownership ───────────────────────────────────────────────────────

/**
 * SQL fragment restricting rows to the current user's own records.
 * Admin: no restriction. Estimator: only rows where estimator_id matches.
 *
 * @return array{0: string, 1: array} [" AND alias.estimator_id = ?", [id]] or ['', []]
 */
function ownerScope(string $alias): array {
    $estimatorId = currentEstimatorId();
    if ($estimatorId === null) {
        return ['', []];
    }
    return [" AND {$alias}.estimator_id = ?", [$estimatorId]];
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
    [$scopeSql, $scopeParams] = ownerScope('e');
    $stmt = $db->prepare("SELECT e.*, c.name as client_name, c.phone as client_phone, c.email as client_email, c.address as client_address, est.name as estimator_name
                          FROM estimates e
                          LEFT JOIN clients c ON e.client_id = c.id
                          LEFT JOIN estimators est ON e.estimator_id = est.id
                          WHERE e.id = ?$scopeSql");
    $stmt->execute(array_merge([$id], $scopeParams));
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
    $estimatorId = currentEstimatorId();
    if ($estimatorId !== null) {
        $where[] = 'e.estimator_id = ?';
        $params[] = $estimatorId;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT e.*, c.name as client_name, c.phone as client_phone, est.name as estimator_name
            FROM estimates e 
            LEFT JOIN clients c ON e.client_id = c.id 
            LEFT JOIN estimators est ON e.estimator_id = est.id
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
    $estimatorId = currentEstimatorId();
    if ($estimatorId !== null) {
        $where[] = 'e.estimator_id = ?';
        $params[] = $estimatorId;
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
            // Owner: keep an explicit owner (e.g. duplicating), otherwise the current user.
            // Estimators can only ever create records they own.
            $owner = currentEstimatorId();
            if ($owner === null && array_key_exists('estimator_id', $data)) {
                $owner = $data['estimator_id'] !== null ? (int)$data['estimator_id'] : null;
            }
            $insertFields = array_merge($fields, ['estimator_id']);
            $cols = implode(', ', array_map(fn($f) => "`$f`", $insertFields));
            $placeholders = implode(', ', array_fill(0, count($insertFields), '?'));
            $stmt = $db->prepare("INSERT INTO estimates ($cols) VALUES ($placeholders)");
            $values = array_map(fn($f) => $data[$f] ?? null, $fields);
            $values[] = $owner;
            $stmt->execute($values);
            $estimateId = (int)$db->lastInsertId();
        } else {
            $estimateId = (int)$data['id'];
            [$scopeSql, $scopeParams] = ownerScope('estimates');
            $sets = implode(', ', array_map(fn($f) => "`$f` = ?", $fields));
            $stmt = $db->prepare("UPDATE estimates SET $sets, updated_at = NOW() WHERE id = ?$scopeSql");
            $values = array_map(fn($f) => $data[$f] ?? null, $fields);
            $values[] = $estimateId;
            $stmt->execute(array_merge($values, $scopeParams));
            if ($stmt->rowCount() === 0 && !getEstimate($estimateId)) {
                throw new RuntimeException('Estimate not found.');
            }

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
    // Capture info before deletion for audit; also enforces ownership
    $estimate = getEstimate($id);
    if (!$estimate) {
        return false;
    }
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
    [$scopeSql, $scopeParams] = ownerScope('c');
    $stmt = $db->prepare("SELECT c.*, est.name as estimator_name FROM clients c LEFT JOIN estimators est ON c.estimator_id = est.id WHERE c.id = ?$scopeSql");
    $stmt->execute(array_merge([$id], $scopeParams));
    return $stmt->fetch() ?: null;
}

/**
 * @return array{0: string, 1: array} WHERE clause (search + ownership) and params for clients alias "c"
 */
function clientsWhere(string $search): array {
    [$scopeSql, $params] = ownerScope('c');
    $where = 'WHERE 1=1' . $scopeSql;
    if ($search) {
        $where .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
        $s = '%' . $search . '%';
        array_push($params, $s, $s, $s);
    }
    return [$where, $params];
}

function getClients(string $search = '', int $limit = 50, int $offset = 0): array {
    $db = getDB();
    [$where, $params] = clientsWhere($search);
    $stmt = $db->prepare("SELECT c.*, est.name as estimator_name FROM clients c LEFT JOIN estimators est ON c.estimator_id = est.id $where ORDER BY c.name LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function countClients(string $search = ''): int {
    $db = getDB();
    [$where, $params] = clientsWhere($search);
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM clients c $where");
    $stmt->execute($params);
    return (int)$stmt->fetch()['cnt'];
}

function saveClient(array $data): int {
    $db = getDB();
    $fields = ['name', 'phone', 'email', 'address', 'notes', 'tier'];

    if (empty($data['id'])) {
        $insertFields = array_merge($fields, ['estimator_id']);
        $cols = implode(', ', array_map(fn($f) => "`$f`", $insertFields));
        $placeholders = implode(', ', array_fill(0, count($insertFields), '?'));
        $stmt = $db->prepare("INSERT INTO clients ($cols) VALUES ($placeholders)");
        $values = array_map(fn($f) => $data[$f] ?? null, $fields);
        $values[] = currentEstimatorId();
        $stmt->execute($values);
        $clientId = (int)$db->lastInsertId();
        logAudit('client', $clientId, 'create', [
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);
        return $clientId;
    } else {
        if (!getClient((int)$data['id'])) {
            throw new RuntimeException('Client not found.');
        }
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
    $client = getClient($id); // enforces ownership
    if (!$client) {
        return false;
    }
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

// ── Email ───────────────────────────────────────────────────────────

/**
 * Send an email with an optional attachment, using SMTP or PHP's mail()
 * depending on the configured mail_method setting.
 *
 * @return array{success: bool, error: string}
 */
function sendEmailWithAttachment(
    string $toEmail,
    string $toName,
    string $subject,
    string $bodyHtml,
    string $attachmentContent = '',
    string $attachmentName = ''
): array {
    require_once __DIR__ . '/PHPMailer/Exception.php';
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';

    $settings = getSettings();
    $fromEmail = $settings['smtp_from_email'] ?: ($settings['business_email'] ?? '');
    $fromName = $settings['smtp_from_name'] ?: ($settings['business_name'] ?? 'Pool Estimator');

    if (!$fromEmail || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'No valid sender email is configured in Settings → Email.'];
    }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Recipient email address is invalid.'];
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        if (($settings['mail_method'] ?? 'mail') === 'smtp') {
            $mail->isSMTP();
            $mail->Host = $settings['smtp_host'] ?? '';
            $mail->Port = (int)($settings['smtp_port'] ?? 587);
            $encryption = $settings['smtp_encryption'] ?? 'tls';
            if ($encryption === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
            }
            if (!empty($settings['smtp_username'])) {
                $mail->SMTPAuth = true;
                $mail->Username = $settings['smtp_username'];
                $mail->Password = $settings['smtp_password'] ?? '';
            }
        } else {
            $mail->isMail();
        }

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo($fromEmail, $fromName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)));

        if ($attachmentContent !== '' && $attachmentName !== '') {
            $mail->addStringAttachment($attachmentContent, $attachmentName, 'base64', 'application/pdf');
        }

        $mail->send();
        return ['success' => true, 'error' => ''];
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
    }
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
        $userName = $_SESSION['user_name'] ?? null;
        $ip = getClientIP();
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $details = !empty($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $db->prepare('INSERT INTO audit_log (entity_type, entity_id, action, user_role, user_name, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$entityType, $entityId, $action, $userRole, $userName, $ip, $userAgent, $details]);
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
    $estimatorId = currentEstimatorId();
    $scope = $estimatorId === null ? '' : ' AND estimator_id = ?';
    $params = $estimatorId === null ? [] : [$estimatorId];
    $q = function (string $sql) use ($db, $scope, $params) {
        $stmt = $db->prepare($sql . $scope);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    };
    return [
        'total_estimates' => (int)$q("SELECT COUNT(*) FROM estimates WHERE 1=1"),
        'draft_estimates' => (int)$q("SELECT COUNT(*) FROM estimates WHERE status='draft'"),
        'approved_estimates' => (int)$q("SELECT COUNT(*) FROM estimates WHERE status='approved'"),
        'total_clients' => (int)$q("SELECT COUNT(*) FROM clients WHERE 1=1"),
        'total_value' => (float)$q("SELECT COALESCE(SUM(total),0) FROM estimates WHERE status='approved'"),
        'month_estimates' => (int)$q("SELECT COUNT(*) FROM estimates WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())"),
    ];
}

// ── Estimators (admin-managed users) ────────────────────────────────

function getEstimators(bool $activeOnly = false): array {
    $db = getDB();
    $where = $activeOnly ? 'WHERE e.active = 1' : '';
    $sql = "SELECT e.id, e.name, e.active, e.created_at, e.updated_at,
                   (SELECT COUNT(*) FROM clients c WHERE c.estimator_id = e.id) AS client_count,
                   (SELECT COUNT(*) FROM estimates x WHERE x.estimator_id = e.id) AS estimate_count
            FROM estimators e $where
            ORDER BY e.active DESC, e.name";
    return $db->query($sql)->fetchAll();
}

function getEstimatorById(int $id): ?array {
    $stmt = getDB()->prepare('SELECT id, name, active, created_at, updated_at FROM estimators WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Login is PIN-only, so a PIN must identify exactly one user.
 * Checks the admin PIN and every estimator PIN (active or not).
 */
function isPinInUse(string $pin, ?int $excludeEstimatorId = null, bool $checkAdmin = true): bool {
    if ($checkAdmin) {
        $adminHash = getSetting('pin_hash', '');
        if ($adminHash && password_verify($pin, $adminHash)) {
            return true;
        }
    }
    $rows = getDB()->query('SELECT id, pin_hash FROM estimators')->fetchAll();
    foreach ($rows as $row) {
        if ($excludeEstimatorId !== null && (int)$row['id'] === $excludeEstimatorId) {
            continue;
        }
        if (password_verify($pin, $row['pin_hash'])) {
            return true;
        }
    }
    return false;
}

/**
 * Find the active estimator whose PIN matches, for login.
 */
function findEstimatorByPin(string $pin): ?array {
    $rows = getDB()->query('SELECT id, name, pin_hash FROM estimators WHERE active = 1')->fetchAll();
    foreach ($rows as $row) {
        if (password_verify($pin, $row['pin_hash'])) {
            return ['id' => (int)$row['id'], 'name' => $row['name']];
        }
    }
    return null;
}

/**
 * Create or update an estimator. $data: id?, name, pin? (plain, already validated), active?
 */
function saveEstimator(array $data): int {
    $db = getDB();
    $name = trim($data['name'] ?? '');
    $pin = $data['pin'] ?? '';

    if (empty($data['id'])) {
        $stmt = $db->prepare('INSERT INTO estimators (name, pin_hash, active) VALUES (?, ?, 1)');
        $stmt->execute([$name, password_hash($pin, PASSWORD_DEFAULT)]);
        $id = (int)$db->lastInsertId();
        logAudit('estimator', $id, 'create', ['name' => $name]);
        return $id;
    }

    $id = (int)$data['id'];
    $before = getEstimatorById($id);
    $active = !empty($data['active']) ? 1 : 0;
    if ($pin !== '') {
        $stmt = $db->prepare('UPDATE estimators SET name = ?, active = ?, pin_hash = ? WHERE id = ?');
        $stmt->execute([$name, $active, password_hash($pin, PASSWORD_DEFAULT), $id]);
    } else {
        $stmt = $db->prepare('UPDATE estimators SET name = ?, active = ? WHERE id = ?');
        $stmt->execute([$name, $active, $id]);
    }

    $action = 'update';
    if ($before && (int)$before['active'] === 1 && $active === 0) {
        $action = 'deactivate';
    } elseif ($before && (int)$before['active'] === 0 && $active === 1) {
        $action = 'activate';
    }
    logAudit('estimator', $id, $action, [
        'name' => $name,
        'pin_changed' => $pin !== '',
    ]);
    return $id;
}

/**
 * Move all clients and estimates owned by one estimator to another owner.
 * $toId = null assigns them to the admin.
 *
 * @return array{clients: int, estimates: int}
 */
function reassignEstimatorRecords(int $fromId, ?int $toId): array {
    $db = getDB();
    $db->beginTransaction();
    try {
        $c = $db->prepare('UPDATE clients SET estimator_id = ? WHERE estimator_id = ?');
        $c->execute([$toId, $fromId]);
        $e = $db->prepare('UPDATE estimates SET estimator_id = ? WHERE estimator_id = ?');
        $e->execute([$toId, $fromId]);
        $db->commit();
    } catch (Exception $ex) {
        $db->rollBack();
        throw $ex;
    }
    $result = ['clients' => $c->rowCount(), 'estimates' => $e->rowCount()];
    logAudit('estimator', $fromId, 'reassign', array_merge($result, ['to_estimator_id' => $toId]));
    return $result;
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
