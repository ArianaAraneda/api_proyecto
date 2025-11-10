<?php
/**
 * Clase de conexión a la base de datos con PDO
 */
class Bd {
    private $host = 'localhost';
    private $db_name = 'proyecto';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function connect(): PDO {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name}",
                $this->username,
                $this->password,
                [PDO::ATTR_EMULATE_PREPARES => false]
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch (PDOException $e) {
            echo 'Error de conexión: ' . $e->getMessage();
            exit;
        }
        return $this->conn;
    }
}
?>
