<?php
/**
 * Document model
 * Handles file uploads, categories, and download tracking.
 */
class Document
{
    private Database $db;

    // Allowed MIME types for document uploads
    public const ALLOWED_TYPES = [
        'application/pdf'                                                       => 'pdf',
        'application/msword'                                                    => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel'                                              => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'    => 'xlsx',
        'application/vnd.ms-powerpoint'                                         => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'image/jpeg'                                                            => 'jpg',
        'image/png'                                                             => 'png',
        'text/plain'                                                            => 'txt',
    ];

    public const MAX_SIZE = 10 * 1024 * 1024; // 10 MB

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ─── LIST / FETCH ─────────────────────────────────────────────────────────

    public function getAll(int $page, int $perPage, string $search = '', string $category = ''): array
    {
        [$where, $params] = $this->buildFilters($search, $category);
        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        return $this->db->fetchAll(
            "SELECT d.*, u.full_name AS uploader_name, u.email AS uploader_email
             FROM   documents d
             JOIN   users u ON u.id = d.uploaded_by
             $where
             ORDER BY d.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public function countAll(string $search = '', string $category = ''): int
    {
        [$where, $params] = $this->buildFilters($search, $category);
        $row = $this->db->fetchOne("SELECT COUNT(*) AS n FROM documents d $where", $params);
        return (int)($row['n'] ?? 0);
    }

    public function getById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT d.*, u.full_name AS uploader_name
             FROM documents d
             JOIN users u ON u.id=d.uploaded_by
             WHERE d.id=?",
            [$id]
        ) ?: null;
    }

    public function getCategories(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT category, COUNT(*) AS cnt FROM documents WHERE category IS NOT NULL AND category != ''
             GROUP BY category ORDER BY category ASC"
        );
        return array_column($rows, 'cnt', 'category');
    }

    public function getStats(): array
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total, SUM(file_size) AS total_size, SUM(downloads) AS total_downloads
             FROM documents"
        );
        return $row ?? ['total' => 0, 'total_size' => 0, 'total_downloads' => 0];
    }

    // ─── CREATE / UPDATE / DELETE ──────────────────────────────────────────────

    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO documents (title, category, file_path, file_size, mime_type, uploaded_by)
             VALUES (?,?,?,?,?,?)",
            [
                $data['title'],
                $data['category'] ?? null,
                $data['file_path'],
                $data['file_size'],
                $data['mime_type'],
                $data['uploaded_by'],
            ]
        );
    }

    public function update(int $id, string $title, string $category): bool
    {
        return $this->db->execute(
            "UPDATE documents SET title=?, category=? WHERE id=?",
            [$title, $category ?: null, $id]
        ) !== false;
    }

    public function delete(int $id): ?string
    {
        $doc = $this->getById($id);
        if (!$doc) return null;
        $this->db->execute("DELETE FROM documents WHERE id=?", [$id]);
        return $doc['file_path']; // caller deletes the file
    }

    public function incrementDownloads(int $id): void
    {
        $this->db->execute("UPDATE documents SET downloads = downloads + 1 WHERE id=?", [$id]);
    }

    // ─── UPLOAD HELPER ────────────────────────────────────────────────────────

    /**
     * Validate and move an uploaded file.
     * Returns ['path' => string, 'size' => int, 'mime' => string] or ['error' => string].
     */
    public function handleUpload(array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'Upload failed (code ' . $file['error'] . ').'];
        }

        if ($file['size'] > self::MAX_SIZE) {
            return ['error' => 'File exceeds maximum size of 10 MB.'];
        }

        // Detect MIME from actual file, not browser header
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);

        if (!array_key_exists($mime, self::ALLOWED_TYPES)) {
            return ['error' => 'File type not allowed. Accepted: PDF, Word, Excel, PowerPoint, images, TXT.'];
        }

        $ext      = self::ALLOWED_TYPES[$mime];
        $filename = 'doc-' . uniqid('', true) . '.' . $ext;
        $dir      = UPLOAD_PATH . 'docs/';

        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $dest = $dir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['error' => 'Failed to save file.'];
        }

        return [
            'path' => UPLOAD_URL . 'docs/' . $filename,
            'size' => $file['size'],
            'mime' => $mime,
        ];
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────────

    public static function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)   return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    public static function typeIcon(string $mime): string
    {
        if (str_contains($mime, 'pdf'))         return 'fa-file-pdf text-danger';
        if (str_contains($mime, 'word') || str_contains($mime, 'document')) return 'fa-file-word text-primary';
        if (str_contains($mime, 'excel') || str_contains($mime, 'sheet'))   return 'fa-file-excel text-success';
        if (str_contains($mime, 'powerpoint') || str_contains($mime, 'presentation')) return 'fa-file-powerpoint text-warning';
        if (str_contains($mime, 'image'))       return 'fa-file-image text-info';
        return 'fa-file-alt text-muted';
    }

    private function buildFilters(string $search, string $category): array
    {
        $where  = 'WHERE 1=1';
        $params = [];
        if ($search)   { $where .= ' AND (d.title LIKE ? OR d.category LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
        if ($category) { $where .= ' AND d.category=?'; $params[] = $category; }
        return [$where, $params];
    }
}
