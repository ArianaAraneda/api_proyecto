<?php
/**
 * Helper para devolver respuestas JSON
 */
class Response {
    public static function json($data, int $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
?>
