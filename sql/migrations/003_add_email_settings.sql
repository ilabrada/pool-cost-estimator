-- Migration 003: Add email/SMTP settings for emailing estimate PDFs to clients
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('mail_method', 'mail'),
    ('smtp_host', ''),
    ('smtp_port', '587'),
    ('smtp_encryption', 'tls'),
    ('smtp_username', ''),
    ('smtp_password', ''),
    ('smtp_from_email', ''),
    ('smtp_from_name', '');
