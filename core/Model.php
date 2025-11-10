<?php
/**
 * Clase base para todos los modelos
 */
require_once __DIR__ . '/../config/Bd.php';

class Model {
    protected $db;

    public function __construct() {
        $database = new Bd();
        $this->db = $database->connect();
    }
}
?>
