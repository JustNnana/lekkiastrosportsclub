<?php
/**
 * Tournament model
 * Handles tournaments, groups, teams, fixtures, and player stats.
 */
class Tournament
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ─── TOURNAMENTS ──────────────────────────────────────────────────────────

    public function getAll(int $page, int $perPage, string $status = ''): array
    {
        $where  = $status ? 'WHERE t.status=?' : '';
        $params = $status ? [$status] : [];
        $offset = ($page - 1) * $perPage;
        $params[] = $perPage; $params[] = $offset;

        return $this->db->fetchAll(
            "SELECT t.*,
                    CONCAT(m.first_name,' ',m.last_name) AS creator_name,
                    (SELECT COUNT(*) FROM tournament_groups tg WHERE tg.tournament_id=t.id)  AS group_count,
                    (SELECT COUNT(*) FROM tournament_teams tt
                     JOIN tournament_groups tg2 ON tg2.id=tt.group_id
                     WHERE tg2.tournament_id=t.id)                                           AS team_count,
                    (SELECT COUNT(*) FROM fixtures f WHERE f.tournament_id=t.id)             AS fixture_count
             FROM   tournaments t
             JOIN   users u ON u.id=t.created_by
             LEFT JOIN members m ON m.user_id=u.id
             $where
             ORDER BY t.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public function countAll(string $status = ''): int
    {
        $where  = $status ? 'WHERE status=?' : '';
        $params = $status ? [$status] : [];
        $row    = $this->db->fetchOne("SELECT COUNT(*) AS n FROM tournaments $where", $params);
        return (int)($row['n'] ?? 0);
    }

    public function getById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT t.*,
                    CONCAT(m.first_name,' ',m.last_name) AS creator_name,
                    (SELECT COUNT(*) FROM fixtures f WHERE f.tournament_id=t.id AND f.status='completed') AS completed_fixtures,
                    (SELECT COUNT(*) FROM fixtures f WHERE f.tournament_id=t.id)                          AS total_fixtures
             FROM   tournaments t
             JOIN   users u ON u.id=t.created_by
             LEFT JOIN members m ON m.user_id=u.id
             WHERE  t.id=?",
            [$id]
        ) ?: null;
    }

    public function getStats(): array
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total, SUM(status='setup') AS setup, SUM(status='active') AS active, SUM(status='completed') AS completed FROM tournaments"
        );
        return $row ?? ['total'=>0,'setup'=>0,'active'=>0,'completed'=>0];
    }

    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO tournaments (name, description, format, num_groups, start_date, created_by) VALUES (?,?,?,?,?,?)",
            [$data['name'], $data['description']??null, $data['format'], $data['num_groups'], $data['start_date']??null, $data['created_by']]
        );
    }

    public function update(int $id, array $data): bool
    {
        return $this->db->execute(
            "UPDATE tournaments SET name=?,description=?,format=?,num_groups=?,start_date=? WHERE id=?",
            [$data['name'], $data['description']??null, $data['format'], $data['num_groups'], $data['start_date']??null, $id]
        ) !== false;
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->db->execute("UPDATE tournaments SET status=? WHERE id=?", [$status, $id]) !== false;
    }

    public function delete(int $id): bool
    {
        return $this->db->execute("DELETE FROM tournaments WHERE id=?", [$id]) !== false;
    }

    // ─── GROUPS ───────────────────────────────────────────────────────────────

    public function getGroups(int $tournamentId): array
    {
        return $this->db->fetchAll(
            "SELECT tg.*, COUNT(tt.id) AS team_count
             FROM tournament_groups tg
             LEFT JOIN tournament_teams tt ON tt.group_id=tg.id
             WHERE tg.tournament_id=?
             GROUP BY tg.id ORDER BY tg.group_name ASC",
            [$tournamentId]
        );
    }

    public function createGroup(int $tournamentId, string $name): int
    {
        return $this->db->insert(
            "INSERT INTO tournament_groups (tournament_id, group_name) VALUES (?,?)", [$tournamentId, $name]
        );
    }

    public function deleteGroup(int $groupId): bool
    {
        return $this->db->execute("DELETE FROM tournament_groups WHERE id=?", [$groupId]) !== false;
    }

    // ─── TEAMS ────────────────────────────────────────────────────────────────

    public function getTeamsByGroup(int $groupId): array
    {
        return $this->db->fetchAll(
            "SELECT tt.*, COUNT(tm.member_id) AS member_count
             FROM tournament_teams tt
             LEFT JOIN team_members tm ON tm.team_id=tt.id
             WHERE tt.group_id=?
             GROUP BY tt.id ORDER BY tt.team_name ASC",
            [$groupId]
        );
    }

    public function getTeamById(int $teamId): ?array
    {
        return $this->db->fetchOne("SELECT * FROM tournament_teams WHERE id=?", [$teamId]) ?: null;
    }

    public function getAllTeams(int $tournamentId): array
    {
        return $this->db->fetchAll(
            "SELECT tt.*, tg.group_name
             FROM tournament_teams tt
             JOIN tournament_groups tg ON tg.id=tt.group_id
             WHERE tg.tournament_id=?
             ORDER BY tg.group_name, tt.team_name",
            [$tournamentId]
        );
    }

    public function createTeam(int $groupId, string $name): int
    {
        return $this->db->insert(
            "INSERT INTO tournament_teams (group_id, team_name) VALUES (?,?)", [$groupId, $name]
        );
    }

    public function deleteTeam(int $teamId): bool
    {
        return $this->db->execute("DELETE FROM tournament_teams WHERE id=?", [$teamId]) !== false;
    }

    public function getTeamMembers(int $teamId): array
    {
        return $this->db->fetchAll(
            "SELECT m.id, m.member_id AS member_code, CONCAT(m.first_name,' ',m.last_name) AS full_name
             FROM team_members tm
             JOIN members m ON m.id=tm.member_id
             WHERE tm.team_id=?
             ORDER BY m.first_name",
            [$teamId]
        );
    }

    public function addTeamMember(int $teamId, int $memberId): bool
    {
        // Check not already in another team in same tournament
        $team     = $this->getTeamById($teamId);
        $existing = $this->db->fetchOne(
            "SELECT tm.team_id FROM team_members tm
             JOIN tournament_teams tt ON tt.id=tm.team_id
             JOIN tournament_groups tg ON tg.id=tt.group_id
             WHERE tm.member_id=? AND tg.tournament_id=(SELECT tournament_id FROM tournament_groups WHERE id=?)",
            [$memberId, $team['group_id']]
        );
        if ($existing) return false;

        $this->db->execute(
            "INSERT IGNORE INTO team_members (team_id, member_id) VALUES (?,?)", [$teamId, $memberId]
        );
        return true;
    }

    public function removeTeamMember(int $teamId, int $memberId): bool
    {
        return $this->db->execute(
            "DELETE FROM team_members WHERE team_id=? AND member_id=?", [$teamId, $memberId]
        ) !== false;
    }

    // ─── FIXTURES ─────────────────────────────────────────────────────────────

    public function getFixtures(int $tournamentId, string $round = ''): array
    {
        $extra  = $round ? ' AND f.round=?' : '';
        $params = [$tournamentId];
        if ($round) $params[] = $round;

        return $this->db->fetchAll(
            "SELECT f.*,
                    ht.team_name AS home_team, at.team_name AS away_team,
                    hg.group_name AS home_group,  ag.group_name AS away_group
             FROM   fixtures f
             JOIN   tournament_teams ht ON ht.id=f.home_team_id
             JOIN   tournament_teams at ON at.id=f.away_team_id
             JOIN   tournament_groups hg ON hg.id=ht.group_id
             JOIN   tournament_groups ag ON ag.id=at.group_id
             WHERE  f.tournament_id=? $extra
             ORDER BY f.match_date ASC, f.id ASC",
            $params
        );
    }

    public function getFixtureById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT f.*,
                    ht.team_name AS home_team, at.team_name AS away_team
             FROM   fixtures f
             JOIN   tournament_teams ht ON ht.id=f.home_team_id
             JOIN   tournament_teams at ON at.id=f.away_team_id
             WHERE  f.id=?",
            [$id]
        ) ?: null;
    }

    public function createFixture(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO fixtures (tournament_id, home_team_id, away_team_id, round, match_date, location) VALUES (?,?,?,?,?,?)",
            [$data['tournament_id'], $data['home_team_id'], $data['away_team_id'], $data['round']??null, $data['match_date']??null, $data['location']??null]
        );
    }

    public function updateFixtureScore(int $id, int $homeScore, int $awayScore, string $status = 'completed'): bool
    {
        return $this->db->execute(
            "UPDATE fixtures SET home_score=?, away_score=?, status=? WHERE id=?",
            [$homeScore, $awayScore, $status, $id]
        ) !== false;
    }

    public function updateFixture(int $id, array $data): bool
    {
        return $this->db->execute(
            "UPDATE fixtures SET round=?, match_date=?, location=?, home_team_id=?, away_team_id=? WHERE id=?",
            [$data['round']??null, $data['match_date']??null, $data['location']??null, $data['home_team_id'], $data['away_team_id'], $id]
        ) !== false;
    }

    public function deleteFixture(int $id): bool
    {
        return $this->db->execute("DELETE FROM fixtures WHERE id=?", [$id]) !== false;
    }

    // ─── PLAYER STATS ─────────────────────────────────────────────────────────

    public function getFixtureStats(int $fixtureId): array
    {
        return $this->db->fetchAll(
            "SELECT ps.*, CONCAT(m.first_name,' ',m.last_name) AS player_name, m.member_id AS member_code
             FROM player_stats ps
             JOIN members m ON m.id=ps.member_id
             WHERE ps.fixture_id=?
             ORDER BY ps.goals DESC, ps.assists DESC",
            [$fixtureId]
        );
    }

    public function saveStat(int $fixtureId, int $memberId, int $goals, int $assists, int $yellow, int $red): void
    {
        $this->db->execute(
            "INSERT INTO player_stats (fixture_id, member_id, goals, assists, yellow_cards, red_cards)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE goals=?, assists=?, yellow_cards=?, red_cards=?",
            [$fixtureId, $memberId, $goals, $assists, $yellow, $red, $goals, $assists, $yellow, $red]
        );
    }

    public function getTournamentTopScorers(int $tournamentId, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT CONCAT(m.first_name,' ',m.last_name) AS player_name, m.member_id AS member_code,
                    tt.team_name,
                    SUM(ps.goals) AS total_goals, SUM(ps.assists) AS total_assists,
                    SUM(ps.yellow_cards) AS yellow_cards, SUM(ps.red_cards) AS red_cards
             FROM player_stats ps
             JOIN members m ON m.id=ps.member_id
             JOIN fixtures f ON f.id=ps.fixture_id
             JOIN team_members tm ON tm.member_id=ps.member_id
             JOIN tournament_teams tt ON tt.id=tm.team_id
             JOIN tournament_groups tg ON tg.id=tt.group_id
             WHERE f.tournament_id=? AND tg.tournament_id=?
             GROUP BY ps.member_id
             ORDER BY total_goals DESC, total_assists DESC
             LIMIT ?",
            [$tournamentId, $tournamentId, $limit]
        );
    }

    /**
     * Group standings: W/D/L/GF/GA/GD/Pts per team.
     */
    public function getGroupStandings(int $groupId): array
    {
        $teams = $this->getTeamsByGroup($groupId);
        $standings = [];

        foreach ($teams as $t) {
            $standings[$t['id']] = [
                'team_id'   => $t['id'],
                'team_name' => $t['team_name'],
                'P' => 0, 'W' => 0, 'D' => 0, 'L' => 0,
                'GF' => 0, 'GA' => 0, 'GD' => 0, 'Pts' => 0,
            ];
        }

        $fixtures = $this->db->fetchAll(
            "SELECT f.* FROM fixtures f
             JOIN tournament_teams ht ON ht.id=f.home_team_id
             JOIN tournament_groups tg ON tg.id=ht.group_id
             WHERE tg.id=? AND f.status='completed' AND f.home_score IS NOT NULL",
            [$groupId]
        );

        foreach ($fixtures as $f) {
            $h = $f['home_team_id']; $a = $f['away_team_id'];
            $hs = (int)$f['home_score']; $as = (int)$f['away_score'];
            if (!isset($standings[$h]) || !isset($standings[$a])) continue;

            $standings[$h]['P']++;  $standings[$a]['P']++;
            $standings[$h]['GF'] += $hs; $standings[$h]['GA'] += $as;
            $standings[$a]['GF'] += $as; $standings[$a]['GA'] += $hs;

            if ($hs > $as)      { $standings[$h]['W']++; $standings[$h]['Pts']+=3; $standings[$a]['L']++; }
            elseif ($hs < $as)  { $standings[$a]['W']++; $standings[$a]['Pts']+=3; $standings[$h]['L']++; }
            else                { $standings[$h]['D']++; $standings[$h]['Pts']++; $standings[$a]['D']++; $standings[$a]['Pts']++; }
        }

        foreach ($standings as &$s) { $s['GD'] = $s['GF'] - $s['GA']; }
        usort($standings, fn($a,$b) => $b['Pts']<=>$a['Pts'] ?: $b['GD']<=>$a['GD'] ?: $b['GF']<=>$a['GF']);
        return array_values($standings);
    }
}
