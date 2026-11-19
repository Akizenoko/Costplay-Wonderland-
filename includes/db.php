<?php
// ─────────────────────────────────────────────────────────────
// DB Connection — Costplay Wonderland
// ─────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'costplay_wonderland');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

/**
 * Helper: Run a prepared statement and return results
 */
function db_query(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    return $rows;
}

/**
 * Helper: Run a prepared statement and return first row
 */
function db_query_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array {
    $rows = db_query($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

/**
 * Helper: Run a prepared insert/update/delete and return affected rows or insert ID
 */
function db_execute(mysqli $conn, string $sql, string $types = '', array $params = []): int {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $id = $stmt->insert_id ?: $stmt->affected_rows;
    $stmt->close();
    return $id;
}
