<?php
/**
 * Payment — manages member payment records and Paystack integration
 */
class Payment
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ─── QUERIES ────────────────────────────────────────────────────────────

    /** All payments — admin view with optional filters */
    public function getAll(int $page = 1, int $perPage = 25, array $f = []): array
    {
        [$where, $params] = $this->buildFilters($f);
        $offset = ($page - 1) * $perPage;

        return $this->db->fetchAll(
            "SELECT pay.*, d.title AS due_title, d.frequency,
                    u.full_name, m.member_id AS member_code
             FROM payments pay
             JOIN members m ON m.id = pay.member_id
             JOIN users   u ON u.id = m.user_id
             JOIN dues    d ON d.id = pay.due_id
             WHERE $where
             ORDER BY pay.created_at DESC
             LIMIT ? OFFSET ?",
            [...$params, $perPage, $offset]
        );
    }

    public function countAll(array $f = []): int
    {
        [$where, $params] = $this->buildFilters($f);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM payments pay
             JOIN members m ON m.id = pay.member_id
             JOIN users   u ON u.id = m.user_id
             JOIN dues    d ON d.id = pay.due_id
             WHERE $where",
            $params
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function getById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT pay.*, d.title AS due_title, d.frequency, d.penalty_fee AS due_penalty_fee,
                    u.full_name, u.email, m.member_id AS member_code, m.phone
             FROM payments pay
             JOIN members m ON m.id = pay.member_id
             JOIN users   u ON u.id = m.user_id
             JOIN dues    d ON d.id = pay.due_id
             WHERE pay.id = ?',
            [$id]
        );
        return $row ?: null;
    }

    /** Payments for one member (paginated) */
    public function getByMember(int $memberId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->db->fetchAll(
            'SELECT pay.*, d.title AS due_title, d.frequency
             FROM payments pay
             JOIN dues d ON d.id = pay.due_id
             WHERE pay.member_id = ?
             ORDER BY pay.due_date DESC, pay.created_at DESC
             LIMIT ? OFFSET ?',
            [$memberId, $perPage, $offset]
        );
    }

    public function countByMember(int $memberId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM payments WHERE member_id = ?',
            [$memberId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    /** Pending + overdue payments for a member */
    public function getPendingByMember(int $memberId): array
    {
        return $this->db->fetchAll(
            "SELECT pay.*, d.title AS due_title, d.frequency, d.penalty_fee AS due_penalty_fee
             FROM payments pay
             JOIN dues d ON d.id = pay.due_id
             WHERE pay.member_id = ? AND pay.status IN ('pending','overdue')
             ORDER BY pay.due_date ASC",
            [$memberId]
        );
    }

    public function getByPaystackRef(string $ref): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM payments WHERE paystack_ref = ?',
            [$ref]
        );
        return $row ?: null;
    }

    /** Dashboard stats */
    public function getStats(): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*)                                                               AS total,
                SUM(status = 'paid')                                                   AS paid_count,
                SUM(status = 'pending')                                                AS pending_count,
                SUM(status = 'overdue')                                                AS overdue_count,
                SUM(CASE WHEN status = 'paid' THEN amount + penalty_applied ELSE 0 END)              AS total_collected,
                SUM(CASE WHEN status IN ('pending','overdue') THEN amount + penalty_applied ELSE 0 END) AS total_pending
             FROM payments"
        );
        return $row ?: ['total' => 0, 'paid_count' => 0, 'pending_count' => 0,
                        'overdue_count' => 0, 'total_collected' => 0, 'total_pending' => 0];
    }

    /** Revenue per month for chart (last N months) */
    public function getMonthlyRevenue(int $months = 6): array
    {
        return $this->db->fetchAll(
            "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month,
                    SUM(amount + penalty_applied) AS total
             FROM payments
             WHERE status = 'paid' AND payment_date >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY month
             ORDER BY month ASC",
            [$months]
        );
    }

    // ─── MUTATIONS ──────────────────────────────────────────────────────────

    /** Create a pending payment record (assign due to member) */
    public function assign(int $memberId, int $dueId, float $amount, string $dueDate): int
    {
        // Idempotent — skip if already assigned for same due + due_date
        $existing = $this->db->fetchOne(
            'SELECT id FROM payments WHERE member_id = ? AND due_id = ? AND due_date = ?',
            [$memberId, $dueId, $dueDate]
        );
        if ($existing) return (int)$existing['id'];

        return (int)$this->db->insert(
            "INSERT INTO payments (member_id, due_id, amount, due_date, status) VALUES (?, ?, ?, ?, 'pending')",
            [$memberId, $dueId, $amount, $dueDate]
        );
    }

    /** Bulk assign a due to all currently active members */
    public function assignToAll(int $dueId, float $amount, string $dueDate): int
    {
        $members = $this->db->fetchAll("SELECT id FROM members WHERE status = 'active'");
        $count   = 0;
        foreach ($members as $m) {
            $new = $this->assign((int)$m['id'], $dueId, $amount, $dueDate);
            if ($new > 0) $count++;
        }
        return $count;
    }

    /** Save a Paystack reference before redirecting */
    public function setPaystackRef(int $id, string $ref): bool
    {
        return (bool)$this->db->execute(
            'UPDATE payments SET paystack_ref = ? WHERE id = ?',
            [$ref, $id]
        );
    }

    /** Mark payment as paid (manual or Paystack) */
    public function markPaid(int $id, string $method = 'manual', ?string $ref = null, ?string $notes = null): bool
    {
        return (bool)$this->db->execute(
            "UPDATE payments
             SET status = 'paid', payment_method = ?, paystack_ref = COALESCE(?, paystack_ref),
                 payment_date = NOW(), notes = COALESCE(?, notes)
             WHERE id = ? AND status IN ('pending','overdue')",
            [$method, $ref, $notes, $id]
        );
    }

    /** Mark overdue and apply penalty */
    public function markOverdue(int $id, float $penalty = 0.00): bool
    {
        return (bool)$this->db->execute(
            "UPDATE payments SET status = 'overdue', penalty_applied = ?
             WHERE id = ? AND status = 'pending'",
            [$penalty, $id]
        );
    }

    /** Reverse a paid payment */
    public function reverse(int $id, ?string $notes = null): bool
    {
        return (bool)$this->db->execute(
            "UPDATE payments SET status = 'reversed', notes = COALESCE(?, notes)
             WHERE id = ? AND status = 'paid'",
            [$notes, $id]
        );
    }

    /** Scan all pending payments past due_date and mark them overdue */
    public function processOverdue(): int
    {
        $pending = $this->db->fetchAll(
            "SELECT pay.id, d.penalty_fee
             FROM payments pay
             JOIN dues d ON d.id = pay.due_id
             WHERE pay.status = 'pending' AND pay.due_date < CURDATE()"
        );
        $count = 0;
        foreach ($pending as $p) {
            if ($this->markOverdue((int)$p['id'], (float)$p['penalty_fee'])) $count++;
        }
        return $count;
    }

    // ─── PAYSTACK ───────────────────────────────────────────────────────────

    /**
     * Initialize a Paystack transaction.
     * Returns ['authorization_url' => ..., 'reference' => ...] on success or ['error' => ...].
     */
    public function initializePaystack(string $email, float $amountNaira, string $ref, int $paymentId): array
    {
        $body = json_encode([
            'email'     => $email,
            'amount'    => (int)round($amountNaira * 100), // kobo
            'reference' => $ref,
            'callback_url' => BASE_URL . 'payments/callback.php',
            'metadata'  => ['payment_id' => $paymentId, 'custom_fields' => []],
        ]);

        $ch = curl_init('https://api.paystack.co/transaction/initialize');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            return ['error' => 'Could not reach Paystack. Please try again.'];
        }

        $data = json_decode($response, true);
        if (!($data['status'] ?? false)) {
            return ['error' => $data['message'] ?? 'Paystack initialization failed.'];
        }

        return [
            'authorization_url' => $data['data']['authorization_url'],
            'reference'         => $data['data']['reference'],
        ];
    }

    /**
     * Verify a Paystack reference and mark payment as paid.
     * Returns ['success' => bool, 'message' => string, 'payment' => array|null]
     */
    public function verifyAndMarkPaid(string $ref): array
    {
        $ch = curl_init('https://api.paystack.co/transaction/verify/' . urlencode($ref));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . PAYSTACK_SECRET_KEY],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            return ['success' => false, 'message' => 'Could not reach Paystack API.', 'payment' => null];
        }

        $data = json_decode($response, true);
        if (!($data['status'] ?? false) || ($data['data']['status'] ?? '') !== 'success') {
            return ['success' => false, 'message' => 'Payment was not successful on Paystack.', 'payment' => null];
        }

        $payment = $this->getByPaystackRef($ref);
        if (!$payment) {
            return ['success' => false, 'message' => 'Payment record not found.', 'payment' => null];
        }

        // Already processed — idempotent
        if ($payment['status'] === 'paid') {
            return ['success' => true, 'message' => 'Already marked as paid.', 'payment' => $payment];
        }

        $this->markPaid((int)$payment['id'], 'paystack', $ref);

        return ['success' => true, 'message' => 'Payment verified.', 'payment' => $this->getById((int)$payment['id'])];
    }

    // ─── PRIVATE ────────────────────────────────────────────────────────────

    private function buildFilters(array $f): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($f['status'])) {
            $where[]  = 'pay.status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['due_id'])) {
            $where[]  = 'pay.due_id = ?';
            $params[] = (int)$f['due_id'];
        }
        if (!empty($f['member_id'])) {
            $where[]  = 'pay.member_id = ?';
            $params[] = (int)$f['member_id'];
        }
        if (!empty($f['search'])) {
            $where[]  = '(u.full_name LIKE ? OR m.member_id LIKE ? OR pay.paystack_ref LIKE ?)';
            $s        = '%' . $f['search'] . '%';
            $params   = [...$params, $s, $s, $s];
        }
        if (!empty($f['month'])) {
            $where[]  = "DATE_FORMAT(pay.due_date, '%Y-%m') = ?";
            $params[] = $f['month'];
        }

        return [implode(' AND ', $where), $params];
    }
}
