<?php
/**
 * Clase de conexión a la base de datos con PDO
 */
class Database {

    public static function connect(): PDO {
        $host = 'localhost';
        $db_name = 'proyecto';   
        $username = 'root';
        $password = '';
        $conn = null;

        // Intenta crear una conexión PDO usando los datos de acceso declarados arriba.
        // También define el charset en UTF-8 para evitar problemas con acentos y caracteres especiales.
        try {
            $conn = new PDO(
                "mysql:host={$host};dbname={$db_name};charset=utf8",
                $username,
                $password,
                [
                    // Configura PDO para lanzar excepciones cuando ocurre un error
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                    // Desactiva la emulación de consultas preparadas para usar
                    // prepared statements nativos del motor MySQL
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

        // Si ocurre un error al intentar conectar, se captura la excepción
        // y se detiene la ejecución mostrando el mensaje del error.
        } catch (PDOException $e) {
            die('Error de conexión: ' . $e->getMessage());
        }

        // Retorna la instancia de PDO ya configurada.
        return $conn;
    }
}
?>
