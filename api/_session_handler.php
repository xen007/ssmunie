<?php
/**
 * SSMUNIE — Gestionnaire de session MySQL (A5)
 * =============================================================
 * Stocke les sessions PHP dans la table `sessions` (ssmunie_meta)
 * au lieu des fichiers. Permet : révocation ciblée, listing admin,
 * audit (ip, user_agent), portabilité WAN.
 */

declare(strict_types=1);

class DbSessionHandler implements SessionHandlerInterface
{
    private mysqli $db;
    private string $table = 'sessions';

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = $this->db->prepare("SELECT payload FROM {$this->table} WHERE id = ? LIMIT 1");
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            return $row ? (string) $row['payload'] : '';
        } catch (Throwable $e) {
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $uid = $_SESSION['user']['id'] ?? null;
            $uid = $uid !== null ? (int) $uid : null;
            $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $now = time();

            $sql = "INSERT INTO {$this->table} (id, user_id, payload, last_activity, created_at, ip, user_agent)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                      user_id = VALUES(user_id),
                      payload = VALUES(payload),
                      last_activity = VALUES(last_activity),
                      ip = VALUES(ip),
                      user_agent = VALUES(user_agent)";
            $stmt = $this->db->prepare($sql);
            // user_id nullable : bind null OK (stocké NULL)
            $stmt->bind_param('sssiiss', $id, $uid, $data, $now, $now, $ip, $ua);
            $stmt->execute();
            $stmt->close();
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE last_activity < ?");
            $expire = time() - $max_lifetime;
            $stmt->bind_param('i', $expire);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        } catch (Throwable $e) {
            return false;
        }
    }
}
