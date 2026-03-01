<?php
/**
 * Event model
 * Handles calendar events and RSVPs.
 */
class Event
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ─── LIST / FETCH ─────────────────────────────────────────────────────────

    public function getAll(int $page, int $perPage, string $type = '', string $status = ''): array
    {
        [$where, $params] = $this->buildFilters($type, $status);
        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        return $this->db->fetchAll(
            "SELECT e.*,
                    CONCAT(m.first_name,' ',m.last_name) AS creator_name,
                    (SELECT COUNT(*) FROM event_rsvps er WHERE er.event_id=e.id AND er.response='attending')     AS attending_count,
                    (SELECT COUNT(*) FROM event_rsvps er WHERE er.event_id=e.id AND er.response='not_attending') AS not_attending_count,
                    (SELECT COUNT(*) FROM event_rsvps er WHERE er.event_id=e.id AND er.response='maybe')         AS maybe_count
             FROM   events e
             JOIN   users u ON u.id=e.created_by
             LEFT JOIN members m ON m.user_id=u.id
             $where
             ORDER BY e.start_date ASC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public function countAll(string $type = '', string $status = ''): int
    {
        [$where, $params] = $this->buildFilters($type, $status);
        $row = $this->db->fetchOne("SELECT COUNT(*) AS n FROM events e $where", $params);
        return (int)($row['n'] ?? 0);
    }

    /**
     * Upcoming events for the member feed (active, future or ongoing).
     */
    public function getUpcoming(int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT e.*,
                    CONCAT(m.first_name,' ',m.last_name) AS creator_name,
                    (SELECT COUNT(*) FROM event_rsvps er WHERE er.event_id=e.id AND er.response='attending') AS attending_count
             FROM   events e
             JOIN   users u ON u.id=e.created_by
             LEFT JOIN members m ON m.user_id=u.id
             WHERE  e.status='active' AND (e.end_date IS NULL OR e.end_date >= NOW())
                    OR (e.status='active' AND e.start_date >= NOW())
             ORDER BY e.start_date ASC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Events in a given month/year for calendar grid.
     */
    public function getByMonth(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $end   = date('Y-m-t 23:59:59', strtotime($start));
        return $this->db->fetchAll(
            "SELECT e.*,
                    (SELECT COUNT(*) FROM event_rsvps er WHERE er.event_id=e.id AND er.response='attending') AS attending_count
             FROM   events e
             WHERE  e.status != 'cancelled'
               AND  e.start_date BETWEEN ? AND ?
             ORDER BY e.start_date ASC",
            [$start, $end]
        );
    }

    public function getById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT e.*,
                    CONCAT(m.first_name,' ',m.last_name) AS creator_name,
                    (SELECT COUNT(*) FROM event_rsvps er WHERE er.event_id=e.id AND er.response='attending')     AS attending_count,
                    (SELECT COUNT(*) FROM event_rsvps er WHERE er.event_id=e.id AND er.response='not_attending') AS not_attending_count,
                    (SELECT COUNT(*) FROM event_rsvps er WHERE er.event_id=e.id AND er.response='maybe')         AS maybe_count
             FROM   events e
             JOIN   users u ON u.id=e.created_by
             LEFT JOIN members m ON m.user_id=u.id
             WHERE  e.id=?",
            [$id]
        ) ?: null;
    }

    public function getStats(): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*)                                     AS total,
                SUM(status='active' AND start_date >= NOW()) AS upcoming,
                SUM(status='completed')                      AS completed,
                SUM(status='cancelled')                      AS cancelled
             FROM events"
        );
        return $row ?? ['total' => 0, 'upcoming' => 0, 'completed' => 0, 'cancelled' => 0];
    }

    // ─── RSVP ─────────────────────────────────────────────────────────────────

    public function getUserRsvp(int $eventId, int $userId): ?string
    {
        $row = $this->db->fetchOne(
            "SELECT response FROM event_rsvps WHERE event_id=? AND user_id=?",
            [$eventId, $userId]
        );
        return $row['response'] ?? null;
    }

    public function getRsvpList(int $eventId): array
    {
        return $this->db->fetchAll(
            "SELECT er.response, er.created_at,
                    CONCAT(m.first_name,' ',m.last_name) AS member_name,
                    m.member_id AS member_code
             FROM   event_rsvps er
             JOIN   users u ON u.id=er.user_id
             LEFT JOIN members m ON m.user_id=u.id
             WHERE  er.event_id=?
             ORDER BY er.response ASC, m.first_name ASC",
            [$eventId]
        );
    }

    /**
     * Upsert RSVP. Returns new response or null if removed.
     */
    public function rsvp(int $eventId, int $userId, string $response): string
    {
        $existing = $this->getUserRsvp($eventId, $userId);

        if ($existing === $response) {
            // Toggle off (remove)
            $this->db->execute(
                "DELETE FROM event_rsvps WHERE event_id=? AND user_id=?",
                [$eventId, $userId]
            );
            return 'removed';
        }

        $this->db->execute(
            "INSERT INTO event_rsvps (event_id, user_id, response) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE response=?, created_at=NOW()",
            [$eventId, $userId, $response, $response]
        );
        return $response;
    }

    // ─── CREATE / UPDATE / DELETE ──────────────────────────────────────────────

    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO events (title, description, event_type, location, start_date, end_date, is_recurring, recurrence, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)",
            [
                $data['title'],
                $data['description'] ?? null,
                $data['event_type'],
                $data['location'] ?? null,
                $data['start_date'],
                $data['end_date'] ?? null,
                (int)($data['is_recurring'] ?? 0),
                $data['recurrence'] ?? null,
                $data['created_by'],
            ]
        );
    }

    public function update(int $id, array $data): bool
    {
        return $this->db->execute(
            "UPDATE events SET title=?,description=?,event_type=?,location=?,start_date=?,end_date=?,is_recurring=?,recurrence=?,updated_at=NOW() WHERE id=?",
            [
                $data['title'],
                $data['description'] ?? null,
                $data['event_type'],
                $data['location'] ?? null,
                $data['start_date'],
                $data['end_date'] ?? null,
                (int)($data['is_recurring'] ?? 0),
                $data['recurrence'] ?? null,
                $id,
            ]
        ) !== false;
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->db->execute(
            "UPDATE events SET status=?, updated_at=NOW() WHERE id=?", [$status, $id]
        ) !== false;
    }

    public function delete(int $id): bool
    {
        return $this->db->execute("DELETE FROM events WHERE id=?", [$id]) !== false;
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────────

    private function buildFilters(string $type, string $status): array
    {
        $where  = 'WHERE 1=1';
        $params = [];
        if ($type)   { $where .= ' AND e.event_type=?'; $params[] = $type; }
        if ($status) { $where .= ' AND e.status=?';     $params[] = $status; }
        return [$where, $params];
    }
}
