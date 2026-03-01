<?php
/**
 * Lekki Astro Sports Club
 * User model — handles auth + member data loading
 */

class User
{
    private ?array $data = null;
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Load a user by their primary key */
    public function loadById(int $id): bool
    {
        $row = $this->db->fetchOne(
            "SELECT u.*, m.member_id, m.status AS member_status
             FROM users u
             LEFT JOIN members m ON m.user_id = u.id
             WHERE u.id = ? LIMIT 1",
            [$id]
        );
        if ($row) { $this->data = $row; return true; }
        return false;
    }

    /**
     * Authenticate by email OR member_id + password.
     * Returns true on success (call toArray() to get data).
     */
    public function authenticate(string $identifier, string $password): bool
    {
        $row = $this->db->fetchOne(
            "SELECT u.* FROM users u
             LEFT JOIN members m ON m.user_id = u.id
             WHERE (u.email = ? OR m.member_id = ?)
               AND u.status = 'active'
             LIMIT 1",
            [$identifier, $identifier]
        );

        if (!$row) return false;
        if (!password_verify($password, $row['password_hash'])) return false;

        $this->data = $row;
        return true;
    }

    // ===== GETTERS =====
    public function getId(): int             { return (int)($this->data['id'] ?? 0); }
    public function getFullName(): string    { return $this->data['full_name'] ?? ''; }
    public function getEmail(): string       { return $this->data['email'] ?? ''; }
    public function getRole(): string        { return $this->data['role'] ?? 'user'; }
    public function getMemberId(): string    { return $this->data['member_id'] ?? ''; }
    public function getMustChangePassword(): bool { return (bool)($this->data['must_change_password'] ?? false); }

    public function isAdmin(): bool          { return in_array($this->getRole(), ['admin', 'super_admin'], true); }
    public function isSuperAdmin(): bool     { return $this->getRole() === 'super_admin'; }

    /** Return data as array for loginUser() */
    public function toArray(): array
    {
        return [
            'id'        => $this->getId(),
            'full_name' => $this->getFullName(),
            'email'     => $this->getEmail(),
            'role'      => $this->getRole(),
        ];
    }
}
