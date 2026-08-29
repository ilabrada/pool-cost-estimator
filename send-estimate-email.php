<?php
/**
 * AJAX Endpoint - Emails an estimate PDF to a client
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!verifyCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$recipientEmail = trim($_POST['recipient_email'] ?? '');
$recipientName = trim($_POST['recipient_name'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

$estimate = $id ? getEstimate($id) : null;
if (!$estimate) {
    echo json_encode(['success' => false, 'error' => 'Estimate not found.']);
    exit;
}

if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Please provide a valid recipient email address.']);
    exit;
}

if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'The PDF could not be generated for sending.']);
    exit;
}

$pdfContent = file_get_contents($_FILES['pdf']['tmp_name']);
if ($pdfContent === false || $pdfContent === '') {
    echo json_encode(['success' => false, 'error' => 'The PDF file was empty or unreadable.']);
    exit;
}

$subject = $subject !== '' ? $subject : ('Your Estimate ' . $estimate['estimate_number']);
$bodyHtml = nl2br(e($message !== '' ? $message : 'Please find your pool estimate attached.'));
$attachmentName = 'Estimate-' . $estimate['estimate_number'] . '.pdf';

$result = sendEmailWithAttachment($recipientEmail, $recipientName, $subject, $bodyHtml, $pdfContent, $attachmentName);

if ($result['success']) {
    // Mark draft estimates as sent once successfully emailed
    if ($estimate['status'] === 'draft') {
        $db = getDB();
        $stmt = $db->prepare('UPDATE estimates SET status = ? WHERE id = ?');
        $stmt->execute(['sent', $id]);
    }
    logAudit('estimate', $id, 'email_sent', [
        'recipient_email' => $recipientEmail,
        'estimate_number' => $estimate['estimate_number'],
    ]);
}

echo json_encode($result);
