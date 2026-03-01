-- ============================================================
--  Lekki Astro Sports Club — Complete Database Schema
--  Engine: MySQL 8.0+ | Charset: utf8mb4
--  Database: demify_lekkiapp (created via cPanel)
--  Import: phpMyAdmin → select demify_lekkiapp → Import → this file
-- ============================================================

-- ============================================================
--  USERS & AUTH
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name            VARCHAR(150)  NOT NULL,
    email                VARCHAR(191)  NOT NULL UNIQUE,
    password_hash        VARCHAR(255)  NOT NULL,
    role                 ENUM('super_admin','admin','user') NOT NULL DEFAULT 'user',
    status               ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1)    NOT NULL DEFAULT 1,
    last_login_at        DATETIME      NULL,
    created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email  (email),
    INDEX idx_role   (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  MEMBERS
-- ============================================================

CREATE TABLE IF NOT EXISTS members (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED  NOT NULL UNIQUE,
    member_id         VARCHAR(30)   NOT NULL UNIQUE COMMENT 'SC/2026/000001',
    phone             VARCHAR(20)   NULL,
    date_of_birth     DATE          NULL,
    address           TEXT          NULL,
    emergency_contact VARCHAR(200)  NULL,
    position          VARCHAR(100)  NULL COMMENT 'Playing position e.g. Forward',
    photo             VARCHAR(255)  NULL,
    status            ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    joined_at         DATE          NOT NULL DEFAULT (CURRENT_DATE),
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_member_id (member_id),
    INDEX idx_status    (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  DUES & PAYMENTS
-- ============================================================

CREATE TABLE IF NOT EXISTS dues (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(150)  NOT NULL,
    description   TEXT          NULL,
    amount        DECIMAL(12,2) NOT NULL,
    frequency     ENUM('one_off','weekly','monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
    due_date      DATE          NULL,
    penalty_fee   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_by    INT UNSIGNED  NOT NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id        INT UNSIGNED  NOT NULL COMMENT 'FK → members.id',
    due_id           INT UNSIGNED  NOT NULL,
    amount           DECIMAL(12,2) NOT NULL,
    penalty_applied  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status           ENUM('pending','paid','overdue','reversed') NOT NULL DEFAULT 'pending',
    payment_method   ENUM('paystack','manual') NOT NULL DEFAULT 'paystack',
    paystack_ref     VARCHAR(100)  NULL UNIQUE,
    payment_date     DATETIME      NULL,
    due_date         DATE          NOT NULL,
    receipt_path     VARCHAR(255)  NULL,
    notes            TEXT          NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (due_id)    REFERENCES dues(id),
    INDEX idx_status      (status),
    INDEX idx_member_id   (member_id),
    INDEX idx_due_date    (due_date),
    INDEX idx_paystack_ref(paystack_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  ANNOUNCEMENTS
-- ============================================================

CREATE TABLE IF NOT EXISTS announcements (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(200)  NOT NULL,
    content        LONGTEXT      NOT NULL,
    image_path     VARCHAR(255)  NULL,
    is_pinned      TINYINT(1)    NOT NULL DEFAULT 0,
    is_published   TINYINT(1)    NOT NULL DEFAULT 0,
    scheduled_at   DATETIME      NULL,
    published_by   INT UNSIGNED  NOT NULL,
    views          INT UNSIGNED  NOT NULL DEFAULT 0,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (published_by) REFERENCES users(id),
    INDEX idx_published (is_published),
    INDEX idx_pinned    (is_pinned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS announcement_comments (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id  INT UNSIGNED NOT NULL,
    user_id          INT UNSIGNED NOT NULL,
    parent_id        INT UNSIGNED NULL COMMENT 'For threaded replies',
    content          TEXT         NOT NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)         REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id)       REFERENCES announcement_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS announcement_reactions (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id  INT UNSIGNED NOT NULL,
    user_id          INT UNSIGNED NOT NULL,
    reaction         ENUM('like','love','support','celebrate') NOT NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)         REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_reaction (announcement_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_announcement_reads (
    user_id      INT UNSIGNED NOT NULL,
    last_read_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  POLLS & VOTING
-- ============================================================

CREATE TABLE IF NOT EXISTS polls (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question     VARCHAR(300)  NOT NULL,
    description  TEXT          NULL,
    allow_change TINYINT(1)    NOT NULL DEFAULT 0,
    deadline     DATETIME      NOT NULL,
    status       ENUM('active','closed') NOT NULL DEFAULT 'active',
    created_by   INT UNSIGNED  NOT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS poll_options (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    poll_id    INT UNSIGNED NOT NULL,
    option_text VARCHAR(200) NOT NULL,
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS poll_votes (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    poll_id        INT UNSIGNED NOT NULL,
    poll_option_id INT UNSIGNED NOT NULL,
    user_id        INT UNSIGNED NOT NULL,
    voted_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id)        REFERENCES polls(id)        ON DELETE CASCADE,
    FOREIGN KEY (poll_option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    UNIQUE KEY uq_user_poll (poll_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  EVENTS & CALENDAR
-- ============================================================

CREATE TABLE IF NOT EXISTS events (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200)  NOT NULL,
    description  TEXT          NULL,
    event_type   ENUM('training','match','meeting','social','other') NOT NULL DEFAULT 'other',
    location     VARCHAR(200)  NULL,
    start_date   DATETIME      NOT NULL,
    end_date     DATETIME      NULL,
    is_recurring TINYINT(1)    NOT NULL DEFAULT 0,
    recurrence   VARCHAR(50)   NULL COMMENT 'weekly, monthly, etc.',
    status       ENUM('active','cancelled','completed') NOT NULL DEFAULT 'active',
    created_by   INT UNSIGNED  NOT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_start_date (start_date),
    INDEX idx_status     (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_rsvps (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id   INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    response   ENUM('attending','not_attending','maybe') NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    UNIQUE KEY uq_rsvp (event_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  TOURNAMENTS
-- ============================================================

CREATE TABLE IF NOT EXISTS tournaments (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(200)  NOT NULL,
    description  TEXT          NULL,
    format       ENUM('league','knockout','group_knockout') NOT NULL DEFAULT 'group_knockout',
    num_groups   TINYINT UNSIGNED NOT NULL DEFAULT 2,
    start_date   DATE          NULL,
    status       ENUM('setup','active','completed') NOT NULL DEFAULT 'setup',
    created_by   INT UNSIGNED  NOT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tournament_groups (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    group_name    VARCHAR(50)  NOT NULL COMMENT 'Group A, Group B…',
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tournament_teams (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id   INT UNSIGNED NOT NULL,
    team_name  VARCHAR(100) NOT NULL,
    FOREIGN KEY (group_id) REFERENCES tournament_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS team_members (
    team_id   INT UNSIGNED NOT NULL,
    member_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (team_id, member_id),
    FOREIGN KEY (team_id)   REFERENCES tournament_teams(id)  ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id)           ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fixtures (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    home_team_id  INT UNSIGNED NOT NULL,
    away_team_id  INT UNSIGNED NOT NULL,
    round         VARCHAR(50)  NULL COMMENT 'Group Stage, Quarter-Final…',
    match_date    DATETIME     NULL,
    location      VARCHAR(200) NULL,
    home_score    TINYINT UNSIGNED NULL,
    away_score    TINYINT UNSIGNED NULL,
    status        ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    event_id      INT UNSIGNED NULL COMMENT 'Linked calendar event',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id)    ON DELETE CASCADE,
    FOREIGN KEY (home_team_id)  REFERENCES tournament_teams(id),
    FOREIGN KEY (away_team_id)  REFERENCES tournament_teams(id),
    FOREIGN KEY (event_id)      REFERENCES events(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS player_stats (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fixture_id INT UNSIGNED NOT NULL,
    member_id  INT UNSIGNED NOT NULL,
    goals      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    assists    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    yellow_cards TINYINT UNSIGNED NOT NULL DEFAULT 0,
    red_cards  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (fixture_id) REFERENCES fixtures(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id)  REFERENCES members(id)  ON DELETE CASCADE,
    UNIQUE KEY uq_player_fixture (fixture_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  DOCUMENTS & CERTIFICATES
-- ============================================================

CREATE TABLE IF NOT EXISTS documents (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(200)  NOT NULL,
    category      VARCHAR(100)  NULL,
    file_path     VARCHAR(255)  NOT NULL,
    file_size     INT UNSIGNED  NULL,
    mime_type     VARCHAR(100)  NULL,
    downloads     INT UNSIGNED  NOT NULL DEFAULT 0,
    uploaded_by   INT UNSIGNED  NOT NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  NOTIFICATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    type       VARCHAR(60)  NOT NULL COMMENT 'payment_due, new_announcement, event_reminder…',
    title      VARCHAR(200) NOT NULL,
    message    TEXT         NOT NULL,
    link       VARCHAR(255) NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  PASSWORD RESETS
-- ============================================================

CREATE TABLE IF NOT EXISTS password_resets (
    user_id    INT UNSIGNED NOT NULL PRIMARY KEY,
    token      VARCHAR(64)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  AUDIT LOG
-- ============================================================

CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  NULL,
    action      VARCHAR(100)  NOT NULL,
    entity_type VARCHAR(60)   NULL,
    entity_id   INT UNSIGNED  NULL,
    description TEXT          NULL,
    ip_address  VARCHAR(45)   NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user   (user_id),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  DEFAULT SUPER ADMIN (change password immediately after setup)
--  Password: Admin@2026  (bcrypt hash below)
-- ============================================================

INSERT IGNORE INTO users (full_name, email, password_hash, role, status, must_change_password)
VALUES (
    'Super Admin',
    'superadmin@lasc.com',
    '$2y$12$3L1XsJ5F1V0CpXy4x3Cq6OvpB0OGiY8dZ1IFxq9zVWyQ2L4zDHvKm',
    'super_admin',
    'active',
    1
);
