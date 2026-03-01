<?php
/**
 * Lekki Astro Sports Club — Member Model
 * Handles all member + user account operations.
 */

class Member
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================================
    //  READ
    // =========================================================

    /** Get paginated members with optional search + status filter */
    public function getAll(int $page = 1, int $perPage = 20, string $search = '', string $status = ''): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $where  = [];

        if ($search !== '') {
            $where[]  = "(m.member_id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR m.phone LIKE ?)";
            $like     = '%' . $search . '%';
            $params   = array_merge($params, [$like, $like, $like, $like]);
        }

        if ($status !== '') {
            $where[]  = "m.status = ?";
            $params[] = $status;
        }

        $sql = "SELECT m.*, u.full_name, u.email, u.role, u.last_login_at
                FROM members m
                JOIN users u ON u.id = m.user_id"
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . " ORDER BY m.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /** Total count — mirrors getAll() filters for pagination */
    public function countAll(string $search = '', string $status = ''): int
    {
        $params = [];
        $where  = [];

        if ($search !== '') {
            $where[]  = "(m.member_id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR m.phone LIKE ?)";
            $like     = '%' . $search . '%';
            $params   = array_merge($params, [$like, $like, $like, $like]);
        }

        if ($status !== '') {
            $where[]  = "m.status = ?";
            $params[] = $status;
        }

        $sql = "SELECT COUNT(*) AS cnt
                FROM members m
                JOIN users u ON u.id = m.user_id"
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

        return (int)($this->db->fetchOne($sql, $params)['cnt'] ?? 0);
    }

    /** Load single member by members.id — returns merged member + user row */
    public function getById(int $id): array|false
    {
        return $this->db->fetchOne(
            "SELECT m.*, u.full_name, u.email, u.role, u.status AS user_status, u.last_login_at
             FROM members m JOIN users u ON u.id = m.user_id
             WHERE m.id = ? LIMIT 1",
            [$id]
        );
    }

    /** Summary counts for dashboard stats */
    public function getStats(): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*)                                         AS total,
                SUM(status = 'active')                          AS active,
                SUM(status = 'inactive')                        AS inactive,
                SUM(status = 'suspended')                       AS suspended,
                SUM(MONTH(created_at) = MONTH(NOW())
                 AND YEAR(created_at)  = YEAR(NOW()))           AS new_this_month,
                SUM(YEAR(created_at)  = YEAR(NOW()))            AS new_this_year
             FROM members"
        );
        return $row ?: ['total'=>0,'active'=>0,'inactive'=>0,'suspended'=>0,'new_this_month'=>0,'new_this_year'=>0];
    }

    // =========================================================
    //  CREATE
    // =========================================================

    /**
     * Create member account.
     * Returns ['success'=>true, 'member_id'=>'SC/…', 'temp_password'=>'…']
     *      or ['success'=>false, 'error'=>'…']
     */
    public function create(array $data): array
    {
        // Check email uniqueness
        $exists = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = ?", [$data['email']]
        );
        if ($exists) {
            return ['success' => false, 'error' => 'A member with this email already exists.'];
        }

        $memberId    = generateMemberId();
        $tempPw      = $this->generateTempPassword();
        $pwHash      = password_hash($tempPw, PASSWORD_BCRYPT, ['cost' => 12]);

        try {
            $this->db->beginTransaction();

            // 1. Create user account
            $userId = $this->db->insert(
                "INSERT INTO users (full_name, email, password_hash, role, status, must_change_password, created_at)
                 VALUES (?, ?, ?, 'user', 'active', 1, NOW())",
                [$data['full_name'], $data['email'], $pwHash]
            );

            // 2. Create member record
            $this->db->insert(
                "INSERT INTO members
                    (user_id, member_id, phone, date_of_birth, address,
                     emergency_contact, position, status, joined_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())",
                [
                    $userId,
                    $memberId,
                    $data['phone']             ?? null,
                    $data['date_of_birth']     ?: null,
                    $data['address']           ?? null,
                    $data['emergency_contact'] ?? null,
                    $data['position']          ?? null,
                ]
            );

            $this->db->commit();

            return [
                'success'       => true,
                'member_id'     => $memberId,
                'temp_password' => $tempPw,
                'user_id'       => $userId,
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Member::create error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'A database error occurred. Please try again.'];
        }
    }

    // =========================================================
    //  UPDATE
    // =========================================================

    public function update(int $id, array $data): bool
    {
        $member = $this->getById($id);
        if (!$member) return false;

        try {
            $this->db->beginTransaction();

            // Update user table
            $this->db->execute(
                "UPDATE users SET full_name = ?, email = ?, updated_at = NOW() WHERE id = ?",
                [$data['full_name'], $data['email'], $member['user_id']]
            );

            // Update member table
            $this->db->execute(
                "UPDATE members SET
                    phone             = ?,
                    date_of_birth     = ?,
                    address           = ?,
                    emergency_contact = ?,
                    position          = ?,
                    updated_at        = NOW()
                 WHERE id = ?",
                [
                    $data['phone']             ?? null,
                    $data['date_of_birth']     ?: null,
                    $data['address']           ?? null,
                    $data['emergency_contact'] ?? null,
                    $data['position']          ?? null,
                    $id,
                ]
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Member::update error: ' . $e->getMessage());
            return false;
        }
    }

    // =========================================================
    //  STATUS CHANGES
    // =========================================================

    public function setStatus(int $id, string $status): bool
    {
        $allowed = ['active', 'inactive', 'suspended'];
        if (!in_array($status, $allowed, true)) return false;

        $member = $this->getById($id);
        if (!$member) return false;

        $this->db->execute("UPDATE members SET status = ?, updated_at = NOW() WHERE id = ?", [$status, $id]);
        $this->db->execute("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?",   [$status, $member['user_id']]);
        return true;
    }

    // =========================================================
    //  DELETE
    // =========================================================

    /** Hard delete — cascades to members via FK */
    public function delete(int $id): bool
    {
        $member = $this->getById($id);
        if (!$member) return false;

        // Deleting user cascades to member (FK ON DELETE CASCADE)
        $this->db->execute("DELETE FROM users WHERE id = ?", [$member['user_id']]);
        return true;
    }

    // =========================================================
    //  EXPORT
    // =========================================================

    public function getAllForExport(string $status = ''): array
    {
        $where  = $status ? "WHERE m.status = ?" : '';
        $params = $status ? [$status] : [];

        return $this->db->fetchAll(
            "SELECT m.member_id, u.full_name, u.email, m.phone,
                    m.date_of_birth, m.position, m.status,
                    m.address, m.emergency_contact, m.joined_at
             FROM members m JOIN users u ON u.id = m.user_id
             {$where}
             ORDER BY m.created_at ASC",
            $params
        );
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    private function generateTempPassword(): string
    {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $pw    = '';
        for ($i = 0; $i < 10; $i++) {
            $pw .= $chars[random_int(0, strlen($chars) - 1)];
        }
        // Ensure at least one uppercase + one number
        return strtoupper($pw[0]) . substr($pw, 1, 7) . random_int(10, 99);
    }
}
