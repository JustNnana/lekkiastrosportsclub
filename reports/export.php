<?php
/**
 * Reports — CSV export handler
 * Exports: members | payments | financial summary
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireAdmin();

$type = sanitize($_GET['type'] ?? '');
if (!in_array($type, ['members', 'payments', 'financial'])) {
    flashError('Invalid export type.');
    redirect('reports/index.php');
}

$db = Database::getInstance();

// Helper — stream CSV without loading everything into memory
function csvStream(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM so Excel opens correctly
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// ─── MEMBERS EXPORT ──────────────────────────────────────────────────────────
if ($type === 'members') {
    $rows = $db->fetchAll(
        "SELECT m.member_code,
                m.first_name, m.last_name,
                u.email,
                m.phone,
                m.membership_type,
                m.status,
                m.gender,
                m.date_of_birth,
                m.address,
                m.emergency_contact_name,
                m.emergency_contact_phone,
                DATE_FORMAT(m.joined_date,'%d/%m/%Y') AS joined
         FROM members m
         JOIN users u ON u.id = m.user_id
         ORDER BY m.member_code ASC"
    );

    $headers = [
        'Member ID', 'First Name', 'Last Name', 'Email', 'Phone',
        'Membership Type', 'Status', 'Gender', 'Date of Birth',
        'Address', 'Emergency Contact', 'Emergency Phone', 'Date Joined',
    ];

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            $r['member_code'], $r['first_name'], $r['last_name'],
            $r['email'], $r['phone'],
            ucwords(str_replace('_', ' ', $r['membership_type'])),
            ucfirst($r['status']), ucfirst($r['gender'] ?? ''),
            $r['date_of_birth'] ?? '', $r['address'] ?? '',
            $r['emergency_contact_name'] ?? '', $r['emergency_contact_phone'] ?? '',
            $r['joined'],
        ];
    }

    csvStream('members-' . date('Y-m-d') . '.csv', $headers, $data);
}

// ─── PAYMENTS EXPORT ─────────────────────────────────────────────────────────
if ($type === 'payments') {
    $rows = $db->fetchAll(
        "SELECT p.id AS payment_id,
                m.member_code,
                CONCAT(m.first_name,' ',m.last_name) AS member_name,
                d.name AS due_name,
                d.amount AS due_amount,
                p.amount AS paid_amount,
                p.status,
                p.method,
                p.reference,
                DATE_FORMAT(p.paid_at,'%d/%m/%Y %H:%i') AS paid_at,
                DATE_FORMAT(md.created_at,'%d/%m/%Y') AS due_assigned
         FROM payments p
         JOIN member_dues md ON md.id = p.member_due_id
         JOIN members m ON m.id = md.member_id
         JOIN dues d ON d.id = md.due_id
         ORDER BY p.paid_at DESC"
    );

    $headers = [
        'Payment ID', 'Member ID', 'Member Name', 'Due Name',
        'Due Amount (₦)', 'Paid Amount (₦)', 'Status',
        'Method', 'Reference', 'Paid At', 'Due Assigned',
    ];

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            $r['payment_id'], $r['member_code'], $r['member_name'],
            $r['due_name'], $r['due_amount'], $r['paid_amount'],
            ucfirst($r['status']), ucfirst($r['method'] ?? ''),
            $r['reference'] ?? '', $r['paid_at'] ?? '', $r['due_assigned'],
        ];
    }

    csvStream('payments-' . date('Y-m-d') . '.csv', $headers, $data);
}

// ─── FINANCIAL SUMMARY EXPORT ─────────────────────────────────────────────────
if ($type === 'financial') {
    // Monthly revenue for all time
    $monthly = $db->fetchAll(
        "SELECT DATE_FORMAT(paid_at,'%Y-%m') AS period,
                COUNT(*) AS transactions,
                SUM(amount) AS total_revenue
         FROM payments
         WHERE status = 'paid'
         GROUP BY period ORDER BY period DESC"
    );

    // Per-due summary
    $perDue = $db->fetchAll(
        "SELECT d.name,
                COUNT(md.id) AS assigned,
                SUM(CASE WHEN md.status='paid' THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN md.status='overdue' THEN 1 ELSE 0 END) AS overdue_count,
                SUM(CASE WHEN md.status='pending' THEN 1 ELSE 0 END) AS pending_count,
                d.amount AS unit_amount,
                SUM(p.amount) AS total_collected
         FROM dues d
         LEFT JOIN member_dues md ON md.due_id = d.id
         LEFT JOIN payments p ON p.member_due_id = md.id AND p.status = 'paid'
         GROUP BY d.id ORDER BY d.name ASC"
    );

    // Combined into one file: two sections separated by a blank row
    $headers1 = ['Period (YYYY-MM)', 'Transactions', 'Total Revenue (₦)'];
    $headers2 = ['Due Name', 'Assigned', 'Paid', 'Overdue', 'Pending', 'Unit Amount (₦)', 'Total Collected (₦)'];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="financial-summary-' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");

    fputcsv($out, ['MONTHLY REVENUE SUMMARY']);
    fputcsv($out, $headers1);
    foreach ($monthly as $r) {
        fputcsv($out, [$r['period'], $r['transactions'], $r['total_revenue']]);
    }

    fputcsv($out, []); // blank row
    fputcsv($out, ['DUE / FEE BREAKDOWN']);
    fputcsv($out, $headers2);
    foreach ($perDue as $r) {
        fputcsv($out, [
            $r['name'], $r['assigned'],
            $r['paid_count'], $r['overdue_count'], $r['pending_count'],
            $r['unit_amount'], $r['total_collected'] ?? 0,
        ]);
    }

    fclose($out);
    exit;
}
