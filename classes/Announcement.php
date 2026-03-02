<?php
/**
 * Announcement model
 * Handles announcements, comments, reactions, and read tracking.
 */
class Announcement
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ─── LIST / FETCH ─────────────────────────────────────────────────────────

    /**
     * All announcements for admin management (including drafts).
     */
    public function getAll(int $page, int $perPage, string $search = '', string $status = ''): array
    {
        [$where, $params] = $this->buildFilters($search, $status);

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT a.*,
                       u.email AS author_email,
                       u.full_name AS author_name,
                       (SELECT COUNT(*) FROM announcement_comments ac WHERE ac.announcement_id = a.id) AS comment_count,
                       (SELECT COUNT(*) FROM announcement_reactions ar WHERE ar.announcement_id = a.id) AS reaction_count
                FROM   announcements a
                JOIN   users u ON u.id = a.published_by
                $where
                ORDER BY a.is_pinned DESC, a.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;
        return $this->db->fetchAll($sql, $params);
    }

    public function countAll(string $search = '', string $status = ''): int
    {
        [$where, $params] = $this->buildFilters($search, $status);
        $row = $this->db->fetchOne("SELECT COUNT(*) AS n FROM announcements a $where", $params);
        return (int)($row['n'] ?? 0);
    }

    /**
     * Published announcements feed (members + admins). Respects scheduled_at.
     */
    public function getFeed(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->db->fetchAll(
            "SELECT a.*,
                    u.full_name AS author_name,
                    (SELECT COUNT(*) FROM announcement_comments ac WHERE ac.announcement_id = a.id) AS comment_count,
                    (SELECT COUNT(*) FROM announcement_reactions ar WHERE ar.announcement_id = a.id) AS reaction_count
             FROM   announcements a
             JOIN   users u ON u.id = a.published_by
             WHERE  a.is_published = 1
               AND  (a.scheduled_at IS NULL OR a.scheduled_at <= NOW())
             ORDER BY a.is_pinned DESC, a.created_at DESC
             LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );
    }

    public function countFeed(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM announcements
             WHERE is_published = 1 AND (scheduled_at IS NULL OR scheduled_at <= NOW())"
        );
        return (int)($row['n'] ?? 0);
    }

    public function getById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT a.*,
                    u.full_name AS author_name,
                    u.email AS author_email
             FROM   announcements a
             JOIN   users u ON u.id = a.published_by
             WHERE  a.id = ?",
            [$id]
        ) ?: null;
    }

    public function getStats(): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*)                                           AS total,
                SUM(is_published = 1)                             AS published,
                SUM(is_published = 0)                             AS drafts,
                SUM(is_pinned = 1 AND is_published = 1)           AS pinned
             FROM announcements"
        );
        return $row ?? ['total' => 0, 'published' => 0, 'drafts' => 0, 'pinned' => 0];
    }

    // ─── CREATE / UPDATE / DELETE ──────────────────────────────────────────────

    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO announcements (title, content, image_path, is_pinned, is_published, scheduled_at, published_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['title'],
                $data['content'],
                $data['image_path'] ?? null,
                (int)($data['is_pinned']    ?? 0),
                (int)($data['is_published'] ?? 0),
                $data['scheduled_at'] ?? null,
                $data['published_by'],
            ]
        );
    }

    public function update(int $id, array $data): bool
    {
        return $this->db->execute(
            "UPDATE announcements
             SET title=?, content=?, image_path=?, is_pinned=?, is_published=?, scheduled_at=?, updated_at=NOW()
             WHERE id=?",
            [
                $data['title'],
                $data['content'],
                $data['image_path'] ?? null,
                (int)($data['is_pinned']    ?? 0),
                (int)($data['is_published'] ?? 0),
                $data['scheduled_at'] ?? null,
                $id,
            ]
        ) !== false;
    }

    public function delete(int $id): bool
    {
        return $this->db->execute("DELETE FROM announcements WHERE id=?", [$id]) !== false;
    }

    public function publish(int $id): bool
    {
        return $this->db->execute(
            "UPDATE announcements SET is_published=1, scheduled_at=NULL WHERE id=?", [$id]
        ) !== false;
    }

    public function unpublish(int $id): bool
    {
        return $this->db->execute(
            "UPDATE announcements SET is_published=0 WHERE id=?", [$id]
        ) !== false;
    }

    public function togglePin(int $id): bool
    {
        return $this->db->execute(
            "UPDATE announcements SET is_pinned = NOT is_pinned WHERE id=?", [$id]
        ) !== false;
    }

    public function incrementViews(int $id): void
    {
        $this->db->execute("UPDATE announcements SET views = views + 1 WHERE id=?", [$id]);
    }

    // ─── COMMENTS ─────────────────────────────────────────────────────────────

    public function getComments(int $announcementId): array
    {
        return $this->db->fetchAll(
            "SELECT ac.*,
                    u.full_name AS author_name,
                    u.email AS author_email
             FROM   announcement_comments ac
             JOIN   users u ON u.id = ac.user_id
             WHERE  ac.announcement_id = ?
             ORDER BY ac.created_at ASC",
            [$announcementId]
        );
    }

    public function addComment(int $announcementId, int $userId, string $content, ?int $parentId = null): int
    {
        return $this->db->insert(
            "INSERT INTO announcement_comments (announcement_id, user_id, parent_id, content)
             VALUES (?, ?, ?, ?)",
            [$announcementId, $userId, $parentId, $content]
        );
    }

    public function deleteComment(int $commentId): bool
    {
        return $this->db->execute(
            "DELETE FROM announcement_comments WHERE id=?", [$commentId]
        ) !== false;
    }

    public function getComment(int $commentId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM announcement_comments WHERE id=?", [$commentId]
        ) ?: null;
    }

    // ─── REACTIONS ────────────────────────────────────────────────────────────

    /**
     * Returns reaction counts + current user's reaction (or null).
     */
    public function getReactions(int $announcementId, int $userId): array
    {
        $counts = $this->db->fetchAll(
            "SELECT reaction, COUNT(*) AS n
             FROM announcement_reactions
             WHERE announcement_id = ?
             GROUP BY reaction",
            [$announcementId]
        );

        $result = ['like' => 0, 'love' => 0, 'support' => 0, 'celebrate' => 0, 'user_reaction' => null];
        foreach ($counts as $row) {
            $result[$row['reaction']] = (int)$row['n'];
        }

        $mine = $this->db->fetchOne(
            "SELECT reaction FROM announcement_reactions WHERE announcement_id=? AND user_id=?",
            [$announcementId, $userId]
        );
        $result['user_reaction'] = $mine['reaction'] ?? null;
        return $result;
    }

    /**
     * Toggle reaction: same reaction → remove; different → replace; no reaction → add.
     */
    public function toggleReaction(int $announcementId, int $userId, string $reaction): string
    {
        $existing = $this->db->fetchOne(
            "SELECT reaction FROM announcement_reactions WHERE announcement_id=? AND user_id=?",
            [$announcementId, $userId]
        );

        if ($existing) {
            if ($existing['reaction'] === $reaction) {
                // Remove
                $this->db->execute(
                    "DELETE FROM announcement_reactions WHERE announcement_id=? AND user_id=?",
                    [$announcementId, $userId]
                );
                return 'removed';
            }
            // Replace
            $this->db->execute(
                "UPDATE announcement_reactions SET reaction=? WHERE announcement_id=? AND user_id=?",
                [$reaction, $announcementId, $userId]
            );
            return 'updated';
        }

        // Add new
        $this->db->insert(
            "INSERT INTO announcement_reactions (announcement_id, user_id, reaction) VALUES (?,?,?)",
            [$announcementId, $userId, $reaction]
        );
        return 'added';
    }

    // ─── READ TRACKING ────────────────────────────────────────────────────────

    public function markRead(int $userId): void
    {
        $this->db->execute(
            "INSERT INTO user_announcement_reads (user_id, last_read_at) VALUES (?, NOW())
             ON DUPLICATE KEY UPDATE last_read_at = NOW()",
            [$userId]
        );
    }

    public function getUnreadCount(int $userId): int
    {
        $row = $this->db->fetchOne(
            "SELECT last_read_at FROM user_announcement_reads WHERE user_id=?", [$userId]
        );

        if (!$row) {
            // Never read — count all published
            $r = $this->db->fetchOne(
                "SELECT COUNT(*) AS n FROM announcements
                 WHERE is_published=1 AND (scheduled_at IS NULL OR scheduled_at <= NOW())"
            );
            return (int)($r['n'] ?? 0);
        }

        $r = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM announcements
             WHERE is_published=1
               AND (scheduled_at IS NULL OR scheduled_at <= NOW())
               AND created_at > ?",
            [$row['last_read_at']]
        );
        return (int)($r['n'] ?? 0);
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────────

    private function buildFilters(string $search, string $status): array
    {
        $where  = 'WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $where   .= ' AND (a.title LIKE ? OR a.content LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($status === 'published') {
            $where .= ' AND a.is_published=1';
        } elseif ($status === 'draft') {
            $where .= ' AND a.is_published=0';
        } elseif ($status === 'pinned') {
            $where .= ' AND a.is_pinned=1 AND a.is_published=1';
        } elseif ($status === 'scheduled') {
            $where .= ' AND a.is_published=0 AND a.scheduled_at IS NOT NULL';
        }

        return [$where, $params];
    }
}
