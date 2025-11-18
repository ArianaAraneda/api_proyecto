<?php
// Importa la clase Database, encargada de crear la conexión PDO a la base de datos.
// Esta clase se encuentra dentro de /config/database.php
require_once __DIR__ . '/../config/database.php';

// Modelo Ticket: maneja todo lo relacionado con la tabla "tickets",
// incluyendo la creación del ticket, búsqueda por compra y búsqueda por número.
class Ticket {

    // Propiedad que almacena la conexión PDO.
    private $conn;

    // Constructor: inicializa la conexión a la base de datos.
    public function __construct() {

        // Crea una instancia de Database (clase de configuración de conexión)
        $db = new Database();

        // Establece la conexión y la guarda en la propiedad $conn.
        $this->conn = $db->connect();
    }

    /** 
     * CREA UN TICKET Y DEVUELVE EL ID
     * Inserta un nuevo registro en la tabla "tickets"
     * asignando la compra, el número del ticket y la ruta física del PDF.
     */
    public function crear($idCompra, $numeroTicket, $pdfRuta) {

        // Consulta SQL para insertar un ticket.
        $sql = "INSERT INTO tickets (id_compra, numero_ticket, pdf_ruta)
                VALUES (?, ?, ?)";

        // Prepara la consulta para evitar inyección SQL.
        $stmt = $this->conn->prepare($sql);

        // Ejecuta el INSERT con los parámetros enviados.
        $stmt->execute([$idCompra, $numeroTicket, $pdfRuta]);

        // Devuelve el ID autoincremental asignado al ticket.
        return $this->conn->lastInsertId();
    }

    /** 
     * OBTENER POR COMPRA
     * Busca un ticket correspondiente al ID de una compra.
     * Si no existe, retorna false o null según fetch().
     */
    public function obtenerPorCompra($idCompra) {

        // Consulta SQL para obtener un ticket según el ID de compra.
        $sql = "SELECT * FROM tickets WHERE id_compra = ?";

        // Prepara la sentencia.
        $stmt = $this->conn->prepare($sql);

        // Ejecuta consulta con el parámetro.
        $stmt->execute([$idCompra]);

        // Devuelve una única fila (si existe).
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** 
     * ALIAS 
     * Función alternativa por conveniencia semántica.
     * Internamente usa obtenerPorCompra.
     */
    public function buscarPorCompra($idCompra) {

        // Simplemente llama al método existente.
        return $this->obtenerPorCompra($idCompra);
    }

    /** 
     * OBTENER POR NÚMERO
     * Busca un ticket por su número "TCK-AAAA-NNNNNN".
     */
    public function obtenerPorNumero($numeroTicket) {

        // Consulta SQL para buscar el ticket por su número único.
        $sql = "SELECT * FROM tickets WHERE numero_ticket = ?";

        // Prepara declaración SQL.
        $stmt = $this->conn->prepare($sql);

        // Ejecuta consulta usando el número del ticket.
        $stmt->execute([$numeroTicket]);

        // Devuelve la fila asociada.
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
