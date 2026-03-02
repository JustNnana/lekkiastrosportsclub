<?php
/**
 * Poll model
 * Handles polls, options, and votes.
 */
class Poll
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ─── LIST / FETCH ─────────────────────────────────────────────────────────

    public function getAll(int $page, int $perPage, string $status = ''): array
    {
        $where  = $status ? "WHERE p.status = ?" : '';
        $params = $status ? [$status] : [];

        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        return $this->db->fetchAll(
            "SELECT p.*,
                    u.full_name AS creator_name,
                    u.email AS creator_email,
                    (SELECT COUNT(*) FROM poll_votes pv WHERE pv.poll_id = p.id)    AS total_votes,
                    (SELECT COUNT(*) FROM poll_options po WHERE po.poll_id = p.id)  AS option_count
             FROM   polls p
             JOIN   users u ON u.id = p.created_by
             $where
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public function countAll(string $status = ''): int
    {
        $where  = $status ? 'WHERE status = ?' : '';
        $params = $status ? [$status] : [];
        $row    = $this->db->fetchOne("SELECT COUNT(*) AS n FROM polls $where", $params);
        return (int)($row['n'] ?? 0);
    }

    /**
     * Active polls visible to members (status=active, deadline not passed).
     */
    public function getActive(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->db->fetchAll(
            "SELECT p.*,
                    u.full_name AS creator_name,
                    (SELECT COUNT(*) FROM poll_votes pv WHERE pv.poll_id = p.id) AS total_votes
             FROM   polls p
             JOIN   users u ON u.id = p.created_by
             WHERE  p.status = 'active' AND p.deadline >= NOW()
             ORDER BY p.deadline ASC
             LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );
    }

    public function countActive(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM polls WHERE status='active' AND deadline >= NOW()"
        );
        return (int)($row['n'] ?? 0);
    }

    public function getClosed(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->db->fetchAll(
            "SELECT p.*,
                    u.full_name AS creator_name,
                    (SELECT COUNT(*) FROM poll_votes pv WHERE pv.poll_id = p.id) AS total_votes
             FROM   polls p
             JOIN   users u ON u.id = p.created_by
             WHERE  p.status = 'closed' OR p.deadline < NOW()
             ORDER BY p.deadline DESC
             LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );
    }

    public function countClosed(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM polls WHERE status='closed' OR deadline < NOW()"
        );
        return (int)($row['n'] ?? 0);
    }

    public function getById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT p.*,
                    u.full_name AS creator_name,
                    u.email AS creator_email,
                    (SELECT COUNT(*) FROM poll_votes pv WHERE pv.poll_id = p.id) AS total_votes
             FROM   polls p
             JOIN   users u ON u.id = p.created_by
             WHERE  p.id = ?",
            [$id]
        ) ?: null;
    }

    public function getStats(): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*)                                          AS total,
                SUM(status='active' AND deadline >= NOW())        AS active,
                SUM(status='closed' OR deadline < NOW())          AS closed,
                (SELECT COUNT(*) FROM poll_votes)                 AS total_votes
             FROM polls"
        );
        return $row ?? ['total' => 0, 'active' => 0, 'closed' => 0, 'total_votes' => 0];
    }

    // ─── OPTIONS ──────────────────────────────────────────────────────────────

    /**
     * Returns options with per-option vote counts.
     */
    public function getOptions(int $pollId): array
    {
        return $this->db->fetchAll(
            "SELECT po.*,
                    COUNT(pv.id) AS votes
             FROM   poll_options po
             LEFT JOIN poll_votes pv ON pv.poll_option_id = po.id
             WHERE  po.poll_id = ?
             GROUP BY po.id
             ORDER BY po.sort_order ASC, po.id ASC",
            [$pollId]
        );
    }

    // ─── CREATE / UPDATE / DELETE ──────────────────────────────────────────────

    public function create(array $data, array $options): int
    {
        $pollId = $this->db->insert(
            "INSERT INTO polls (question, description, allow_change, deadline, created_by)
             VALUES (?, ?, ?, ?, ?)",
            [
                $data['question'],
                $data['description'] ?? null,
                (int)($data['allow_change'] ?? 0),
                $data['deadline'],
                $data['created_by'],
            ]
        );

        $this->saveOptions($pollId, $options);
        return $pollId;
    }

    public function update(int $id, array $data, array $options): bool
    {
        $this->db->execute(
            "UPDATE polls SET question=?, description=?, allow_change=?, deadline=? WHERE id=?",
            [
                $data['question'],
                $data['description'] ?? null,
                (int)($data['allow_change'] ?? 0),
                $data['deadline'],
                $id,
            ]
        );

        // Delete old options and votes, then re-insert
        $this->db->execute("DELETE FROM poll_options WHERE poll_id=?", [$id]);
        $this->saveOptions($id, $options);
        return true;
    }

    private function saveOptions(int $pollId, array $options): void
    {
        foreach (array_values($options) as $i => $text) {
            $text = trim($text);
            if ($text === '') continue;
            $this->db->insert(
                "INSERT INTO poll_options (poll_id, option_text, sort_order) VALUES (?,?,?)",
                [$pollId, $text, $i]
            );
        }
    }

    public function close(int $id): bool
    {
        return $this->db->execute(
            "UPDATE polls SET status='closed' WHERE id=?", [$id]
        ) !== false;
    }

    public function reopen(int $id): bool
    {
        return $this->db->execute(
            "UPDATE polls SET status='active' WHERE id=?", [$id]
        ) !== false;
    }

    public function delete(int $id): bool
    {
        return $this->db->execute("DELETE FROM polls WHERE id=?", [$id]) !== false;
    }

    // ─── VOTING ───────────────────────────────────────────────────────────────

    /**
     * Returns the user's current vote option_id for this poll, or null.
     */
    public function getUserVote(int $pollId, int $userId): ?int
    {
        $row = $this->db->fetchOne(
            "SELECT poll_option_id FROM poll_votes WHERE poll_id=? AND user_id=?",
            [$pollId, $userId]
        );
        return $row ? (int)$row['poll_option_id'] : null;
    }

    /**
     * Cast or change a vote. Returns ['success'=>bool, 'message'=>string].
     */
    public function vote(int $pollId, int $optionId, int $userId): array
    {
        $poll = $this->getById($pollId);
        if (!$poll) return ['success' => false, 'message' => 'Poll not found.'];

        if ($poll['status'] === 'closed' || strtotime($poll['deadline']) < time()) {
            return ['success' => false, 'message' => 'This poll is closed.'];
        }

        // Verify option belongs to this poll
        $option = $this->db->fetchOne(
            "SELECT id FROM poll_options WHERE id=? AND poll_id=?", [$optionId, $pollId]
        );
        if (!$option) return ['success' => false, 'message' => 'Invalid option.'];

        $existing = $this->getUserVote($pollId, $userId);

        if ($existing !== null) {
            if (!$poll['allow_change']) {
                return ['success' => false, 'message' => 'You have already voted and vote changes are not allowed.'];
            }
            if ($existing === $optionId) {
                return ['success' => false, 'message' => 'You already selected this option.'];
            }
            // Change vote
            $this->db->execute(
                "UPDATE poll_votes SET poll_option_id=?, voted_at=NOW() WHERE poll_id=? AND user_id=?",
                [$optionId, $pollId, $userId]
            );
            return ['success' => true, 'message' => 'Vote updated.'];
        }

        // New vote
        $this->db->insert(
            "INSERT INTO poll_votes (poll_id, poll_option_id, user_id) VALUES (?,?,?)",
            [$pollId, $optionId, $userId]
        );
        return ['success' => true, 'message' => 'Vote recorded.'];
    }

    /**
     * True if poll is effectively closed (status=closed OR deadline passed).
     */
    public function isClosed(array $poll): bool
    {
        return $poll['status'] === 'closed' || strtotime($poll['deadline']) < time();
    }
}
