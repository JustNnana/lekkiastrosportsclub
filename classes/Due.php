<?php
/**
 * Due — manages dues (recurring/one-off membership fees)
 */
class Due
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Paginated list of dues with assignment stats */
    public function getAll(int $page = 1, int $perPage = 20, string $search = '', string $status = ''): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($search) {
            $where[]  = '(d.title LIKE ? OR d.description LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($status === 'active')   { $where[] = 'd.is_active = 1'; }
        if ($status === 'inactive') { $where[] = 'd.is_active = 0'; }

        $offset   = ($page - 1) * $perPage;
        $whereStr = implode(' AND ', $where);

        $rows = $this->db->fetchAll(
            "SELECT d.*, u.full_name AS created_by_name,
                    (SELECT COUNT(*) FROM payments p WHERE p.due_id = d.id) AS assigned_count,
                    (SELECT COUNT(*) FROM payments p WHERE p.due_id = d.id AND p.status = 'paid') AS paid_count
             FROM dues d
             JOIN users u ON u.id = d.created_by
             WHERE $whereStr
             ORDER BY d.created_at DESC
             LIMIT ? OFFSET ?",
            [...$params, $perPage, $offset]
        );
        return $rows;
    }

    public function countAll(string $search = '', string $status = ''): int
    {
        $where  = ['1=1'];
        $params = [];

        if ($search) {
            $where[]  = '(title LIKE ? OR description LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($status === 'active')   { $where[] = 'is_active = 1'; }
        if ($status === 'inactive') { $where[] = 'is_active = 0'; }

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM dues WHERE ' . implode(' AND ', $where),
            $params
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function getById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT d.*, u.full_name AS created_by_name
             FROM dues d JOIN users u ON u.id = d.created_by
             WHERE d.id = ?',
            [$id]
        );
        return $row ?: null;
    }

    /** All active dues (for dropdowns) */
    public function getActive(): array
    {
        return $this->db->fetchAll('SELECT * FROM dues WHERE is_active = 1 ORDER BY title ASC');
    }

    public function getStats(): array
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total,
                    SUM(is_active = 1) AS active,
                    SUM(is_active = 0) AS inactive
             FROM dues'
        );
        return $row ?: ['total' => 0, 'active' => 0, 'inactive' => 0];
    }

    public function create(array $data, int $createdBy): int
    {
        return (int)$this->db->insert(
            'INSERT INTO dues (title, description, amount, frequency, due_date, penalty_fee, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
            [
                $data['title'],
                $data['description'] ?: null,
                $data['amount'],
                $data['frequency'],
                $data['due_date'] ?: null,
                $data['penalty_fee'] ?? 0.00,
                $createdBy,
            ]
        );
    }

    public function update(int $id, array $data): bool
    {
        return (bool)$this->db->execute(
            'UPDATE dues SET title=?, description=?, amount=?, frequency=?, due_date=?, penalty_fee=?
             WHERE id=?',
            [
                $data['title'],
                $data['description'] ?: null,
                $data['amount'],
                $data['frequency'],
                $data['due_date'] ?: null,
                $data['penalty_fee'] ?? 0.00,
                $id,
            ]
        );
    }

    public function toggleActive(int $id): bool
    {
        return (bool)$this->db->execute(
            'UPDATE dues SET is_active = NOT is_active WHERE id = ?',
            [$id]
        );
    }

    /** Delete only if no payments exist for this due */
    public function delete(int $id): bool
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS cnt FROM payments WHERE due_id = ?', [$id]);
        if ((int)($row['cnt'] ?? 0) > 0) {
            return false; // payments exist, cannot delete
        }
        return (bool)$this->db->execute('DELETE FROM dues WHERE id = ?', [$id]);
    }
}
