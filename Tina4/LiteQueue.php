<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

use SQLite3;
use Exception;
use Generator;

class LiteQueue implements QueueInterface {
    private SQLite3 $db;
    private string $table;
    private QueueConfig $config;
    private string $topic;

    /**
     * @throws Exception
     */
    public function __construct(QueueConfig $config, string $topic) {
        $this->config = $config;
        $this->topic = $topic;

        $sanitized = str_replace("-", "_", $topic);
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $sanitized)) {
            throw new Exception("Invalid topic name: {$topic}. Only alphanumeric characters, hyphens and underscores are allowed.");
        }

        $namePrefix = $config->prefix ? $config->prefix . '_' : '';
        $this->table = $namePrefix . $sanitized;
        $this->db = new SQLite3($config->litequeueDatabaseName);
        $createResult = $this->db->exec("CREATE TABLE IF NOT EXISTS {$this->table} (message_id TEXT PRIMARY KEY, data TEXT, status INT, in_time INT)");
        if ($createResult === false) {
            throw new Exception("Failed to create table: " . $this->db->lastErrorMsg());
        }
    }

    /**
     * Produces a message to the SQLite queue.
     *
     * @param string $value
     * @param string|null $userId
     * @param callable|null $deliveryCallback
     * @return QueueMessage|Exception
     */
    public function produce(string $value, ?string $userId, ?callable $deliveryCallback): QueueMessage|Exception {
        try {
            $body = [
                'message_id' => uuid7(),
                'msg' => $value,
                'user_id' => $userId,
                'in_time' => hrtime(true)
            ];
            $stmt = $this->db->prepare("INSERT INTO {$this->table} (message_id, data, status, in_time) VALUES (:message_id, :data, 0, :in_time)");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $this->db->lastErrorMsg());
            }
            $stmt->bindValue(':message_id', $body['message_id'], SQLITE3_TEXT);
            $stmt->bindValue(':data', json_encode($body), SQLITE3_TEXT);
            $stmt->bindValue(':in_time', $body['in_time'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            if ($result === false) {
                throw new Exception("Execute failed: " . $this->db->lastErrorMsg());
            }
            $stmt->close();
            $response = new QueueMessage($body['message_id'], $value, $userId, 0, $body['in_time'], '0', $this->topic);
            if ($deliveryCallback) {
                $deliveryCallback($this->db, null, $response);
            }
            return $response;
        } catch (Exception $e) {
            if ($deliveryCallback) {
                $deliveryCallback($this->db, $e, null);
            }
            return $e;
        }
    }

    /**
     * Generator that yields messages from the SQLite queue.
     * Uses exponential backoff when queue is empty.
     *
     * @param bool $acknowledge
     * @param int $batchSize
     * @return Generator<int, QueueMessage|null, mixed, void>
     */
    public function consume(bool $acknowledge = true, int $batchSize = 1): Generator
    {
        $currentBackoff = $this->config->pollInterval;

        while (true) {
            $found = false;
            try {
                $this->db->exec('BEGIN EXCLUSIVE TRANSACTION');
                $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE status = 0 ORDER BY in_time ASC LIMIT :limit");
                $stmt->bindValue(':limit', $batchSize, SQLITE3_INTEGER);
                $result = $stmt->execute();

                $rows = [];
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $rows[] = $row;
                }
                $stmt->close();

                // Acknowledge inside the transaction, then commit before yielding
                if ($acknowledge) {
                    foreach ($rows as $row) {
                        $this->db->exec("UPDATE {$this->table} SET status = 2 WHERE message_id = '" . $this->db->escapeString($row['message_id']) . "'");
                    }
                }

                $this->db->exec('COMMIT');

                // Yield messages outside the transaction
                foreach ($rows as $row) {
                    $found = true;
                    $data = json_decode($row['data'], true);
                    yield new QueueMessage(
                        $row['message_id'],
                        $data['msg'],
                        $data['user_id'] ?? null,
                        $acknowledge ? 2 : 0,
                        $row['in_time'],
                        '0',
                        $this->topic
                    );
                }
            } catch (Exception $e) {
                @$this->db->exec('ROLLBACK');
                (new Debug())->error("Error consuming {$this->table}: " . $e->getMessage());
            }

            if ($found) {
                $currentBackoff = $this->config->pollInterval;
            } else {
                yield null;
                usleep((int)($currentBackoff * 1_000_000));
                $currentBackoff = min($currentBackoff * 2, $this->config->maxBackoff);
            }
        }
    }
}
