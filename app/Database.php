<?php
/**
 * Lekki Astro Sports Club
 * Database — PDO singleton wrapper with prepared statements only.
 * Never concatenate user input into queries. Use placeholders always.
 */

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG === 'true') {
                die('Database connection failed: ' . $e->getMessage());
            }
            die('A database error occurred. Please try again later.');
        }
    }

    /** Returns the singleton instance */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Run a query and return all rows */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Run a query and return the first row */
    public function fetchOne(string $sql, array $params = []): array|false
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /** Run INSERT / UPDATE / DELETE — returns affected row count */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Run INSERT — returns last inserted ID */
    public function insert(string $sql, array $params = []): string
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->pdo->lastInsertId();
    }

    /** Begin a transaction */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /** Commit a transaction */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /** Rollback a transaction */
    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    /** Prevent cloning */
    private function __clone() {}
}
