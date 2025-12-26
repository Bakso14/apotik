<?php
namespace Core;

class Database {
    private static $instance; // mysqli

    private function __construct() {}

    public static function instance(): \mysqli {
        if (!self::$instance) {
            $mysqli = new \mysqli(ENV['DB_HOST'], ENV['DB_USER'], ENV['DB_PASS'], ENV['DB_NAME'], ENV['DB_PORT']);
            if ($mysqli->connect_errno) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'DB connection failed', 'detail' => $mysqli->connect_error]);
                exit;
            }
            $mysqli->set_charset('utf8mb4');
            // Set MySQL session time zone if configured
            if (!empty(ENV['DB_TIMEZONE'])) {
                // Use quoted string for named tz or offset like +07:00
                $tz = ENV['DB_TIMEZONE'];
                // Suppress errors softly; if fails, continue with server default
                @$mysqli->query("SET time_zone='".$mysqli->real_escape_string($tz)."'");
            }
            self::$instance = $mysqli;
        }
        return self::$instance;
    }

    public static function query(string $sql): \mysqli_stmt {
        $db = self::instance();
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            self::fail('Prepare failed: '.$db->error);
        }
        if (!$stmt->execute()) {
            self::fail('Execute failed: '.$stmt->error);
        }
        return $stmt;
    }

    public static function execute(string $sql, string $types = '', array $params = []): \mysqli_stmt {
        $db = self::instance();
        $stmt = $db->prepare($sql);
        if (!$stmt) self::fail('Prepare failed: '.$db->error);
        if ($types && $params) {
            // Build an array of references as required by bind_param
            $bindParams = [ $types ];
            foreach ($params as $i => $_) {
                $bindParams[] =& $params[$i];
            }
            if (!call_user_func_array([$stmt, 'bind_param'], $bindParams)) {
                self::fail('Bind failed: '.$stmt->error);
            }
        }
        if (!$stmt->execute()) self::fail('Execute failed: '.$stmt->error);
        return $stmt;
    }


    public static function select(string $sql, string $types = '', array $params = []): array {
        $stmt = self::execute($sql, $types, $params);
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    private static function fail(string $message): void {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
}
