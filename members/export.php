<?php
/**
 * Export members to CSV (works without PhpSpreadsheet)
 * For Excel/PDF export, install PhpSpreadsheet/DomPDF later.
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Member.php';

requireAdmin();

$status = in_array($_GET['status'] ?? '', ['active','inactive','suspended']) ? $_GET['status'] : '';
$format = in_array($_GET['format'] ?? 'csv', ['csv']) ? 'csv' : 'csv'; // extend later

$memberObj = new Member();
$members   = $memberObj->getAllForExport($status);

$filename = 'lasc-members-' . ($status ?: 'all') . '-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$out = fopen('php://output', 'w');

// BOM for Excel UTF-8
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// Header row
fputcsv($out, [
    'Member ID', 'Full Name', 'Email', 'Phone',
    'Date of Birth', 'Position', 'Status',
    'Address', 'Emergency Contact', 'Joined Date'
]);

foreach ($members as $m) {
    fputcsv($out, [
        $m['member_id'],
        $m['full_name'],
        $m['email'],
        $m['phone']             ?? '',
        $m['date_of_birth']     ?? '',
        $m['position']          ?? '',
        $m['status'],
        $m['address']           ?? '',
        $m['emergency_contact'] ?? '',
        $m['joined_at']         ?? '',
    ]);
}

fclose($out);
exit;
