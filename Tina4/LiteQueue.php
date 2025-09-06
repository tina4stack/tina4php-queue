<?php

namespace Tina4;

use SQLite3;
use Exception;

class LiteQueue implements QueueInterface {
    private SQLite3 $db;
    private string $table;

    /**
     * Constructor for the LiteQueue
     * @throws Exception
     */
    public function __construct(QueueConfig $config, string $topic) {
        $topic = str_replace("-", "_", $topic);
        $namePrefix = $config->prefix ? $config->prefix . '_' : '';
        $this->table = $namePrefix . $topic;
        $this->db = new SQLite3($config->litequeueDatabaseName);
        $createResult = $this->db->exec("CREATE TABLE IF NOT EXISTS {$this->table} (message_id TEXT PRIMARY KEY, data TEXT, status INT, in_time INT)");
        if ($createResult === false) {
            throw new Exception("Failed to create table: " . $this->db->lastErrorMsg());
        }
    }

    /**
     * Produces
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
            $response = new QueueMessage($body['message_id'], $value, $userId, 0, $body['in_time'], '0');
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

    public function consume(bool $acknowledge, ?callable $consumerCallback): void {
        try {
            $beginResult = $this->db->exec('BEGIN EXCLUSIVE TRANSACTION');
            if ($beginResult === false) {
                throw new Exception("Begin transaction failed: " . $this->db->lastErrorMsg());
            }
            $result = $this->db->query("SELECT * FROM {$this->table} WHERE status = 0 LIMIT 1");
            if ($result === false) {
                throw new Exception("Query failed: " . $this->db->lastErrorMsg());
            }
            $msg = $result->fetchArray(SQLITE3_ASSOC);
            if ($msg) {
                $data = json_decode($msg['data'], true);
                $response = new QueueMessage($msg['message_id'], $data['msg'], $data['user_id'], $msg['status'], $msg['in_time'], '0');
                if ($consumerCallback) {
                    $consumerCallback($this->db, null, $response);
                }
                if ($acknowledge) {
                    $updateResult = $this->db->exec("UPDATE {$this->table} SET status = 2 WHERE message_id = '" . $this->db->escapeString($msg['message_id']) . "'");
                    if ($updateResult === false) {
                        throw new Exception("Update failed: " . $this->db->lastErrorMsg());
                    }
                }
            }
            $commitResult = $this->db->exec('COMMIT');
            if ($commitResult === false) {
                throw new Exception("Commit failed: " . $this->db->lastErrorMsg());
            }
        } catch (Exception $e) {
            $this->db->exec('ROLLBACK');
            (new Debug())->error("Error consuming {$this->table}: " . $e->getMessage());
        }
    }
}
