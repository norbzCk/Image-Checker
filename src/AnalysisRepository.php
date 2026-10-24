<?php
declare(strict_types=1);

final class AnalysisRepository
{
    public function __construct(private mysqli $db)
    {
    }

    /** @param array<string,mixed> $f */
    public function create(string $ownerHash, string $clientHash, string $storedName, array $f): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO image_analyses
             (owner_hash, client_hash, original_filename, stored_filename, mime_type,
              file_size, width, height, sha256)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'sssssiiis',
            $ownerHash, $clientHash, $f['original'], $storedName, $f['mime'],
            $f['size'], $f['width'], $f['height'], $f['sha256']
        );
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    /** @param array<string,mixed> $result decoded inference JSON */
    public function markDone(int $id, array $result): void
    {
        $verdict = (string) ($result['verdict'] ?? '');
        if (!in_array($verdict, ['LIKELY_AI', 'LIKELY_REAL', 'UNCERTAIN', 'AI_DECLARED'], true)) {
            throw new RuntimeException('Inference returned an unknown verdict.');
        }

        $probability = round((float) ($result['ai_probability'] ?? 0.0), 4);
        $model       = mb_substr((string) ($result['model']['name'] ?? ''), 0, 150);
        $c2pa        = $result['c2pa'] ?? [];
        $present     = array_key_exists('present', $c2pa) && $c2pa['present'] !== null ? (int) (bool) $c2pa['present'] : null;
        $aiDeclared  = $present === null ? null : (int) (bool) ($c2pa['ai_declared'] ?? false);
        $signals     = json_encode(['c2pa' => $c2pa, 'hints' => $result['hints'] ?? []], JSON_UNESCAPED_SLASHES);
        $latency     = (int) ($result['latency_ms'] ?? 0);

        $stmt = $this->db->prepare(
            "UPDATE image_analyses
             SET status = 'DONE', verdict = ?, ai_probability = ?, model_name = ?,
                 c2pa_present = ?, c2pa_ai_declared = ?, signals_json = ?,
                 latency_ms = ?, error_message = NULL, analyzed_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('sdsiisii', $verdict, $probability, $model, $present, $aiDeclared, $signals, $latency, $id);
        $stmt->execute();
        $stmt->close();
    }

    public function markFailed(int $id, string $message): void
    {
        $message = mb_substr($message, 0, 500);
        $stmt = $this->db->prepare("UPDATE image_analyses SET status = 'FAILED', error_message = ? WHERE id = ?");
        $stmt->bind_param('si', $message, $id);
        $stmt->execute();
        $stmt->close();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id, string $ownerHash): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM image_analyses WHERE id = ? AND owner_hash = ?');
        $stmt->bind_param('is', $id, $ownerHash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function recent(string $ownerHash, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, original_filename, file_size, status, verdict, ai_probability, created_at
             FROM image_analyses WHERE owner_hash = ? ORDER BY id DESC LIMIT ?'
        );
        $stmt->bind_param('si', $ownerHash, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** Removes the row and returns the stored filename so the caller can unlink the file. */
    public function delete(int $id, string $ownerHash): ?string
    {
        $row = $this->find($id, $ownerHash);
        if ($row === null) {
            return null;
        }
        $stmt = $this->db->prepare('DELETE FROM image_analyses WHERE id = ? AND owner_hash = ?');
        $stmt->bind_param('is', $id, $ownerHash);
        $stmt->execute();
        $stmt->close();
        return (string) $row['stored_filename'];
    }

    public function countRecentByClient(string $clientHash, int $seconds): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM image_analyses
             WHERE client_hash = ? AND created_at > (NOW() - INTERVAL ? SECOND)'
        );
        $stmt->bind_param('si', $clientHash, $seconds);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return (int) $count;
    }
}
