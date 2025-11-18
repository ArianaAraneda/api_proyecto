<?php
// Importa el modelo Ticket, que interactúa con la base de datos para obtener información de tickets generados.
require_once __DIR__ . '/../models/Ticket.php';

// Importa la clase Response, que permite devolver respuestas JSON uniformes al frontend.
require_once __DIR__ . '/../utils/Response.php';

// Controlador encargado de manejar consultas y descargas de tickets.
// No genera tickets, solo los busca y los sirve.
class TicketController {

    // Método que obtiene un ticket según el ID de una compra.
    public function obtenerPorCompra(int $idCompra)
    {
        // Instancia el modelo Ticket.
        $ticketModel = new Ticket();

        // Busca dentro de la base de datos el ticket asociado a la compra indicada.
        // Si la compra tuvo un ticket generado durante la compra, se devuelve,
        // de lo contrario retorna false o null.
        $ticket = $ticketModel->buscarPorCompra($idCompra);

        // Si no se encontró ningún ticket asociado a la compra, se devuelve error 404.
        if (!$ticket) {
            return Response::json(["error" => "Ticket no encontrado"], 404);
        }

        // Si se encontró, simplemente se envía el ticket como respuesta JSON.
        return Response::json($ticket);
    }

    // Método encargado de entregar físicamente el PDF al navegador.
    public function descargar($numeroTicket)
    {
        // Construye la ruta física del archivo PDF según el nombre del ticket.
        // Los archivos se guardan en /public/tickets/ticket_<numero>.pdf
        $ruta = __DIR__ . '/../public/tickets/ticket_' . $numeroTicket . '.pdf';

        // Si el archivo no existe en el servidor, se responde con error 404.
        if (!file_exists($ruta)) {
            return Response::json(["error" => "PDF no encontrado"], 404);
        }

        // Si existe, se envían los encabezados HTTP apropiados para servir un PDF.
        header("Content-Type: application/pdf");

        // El encabezado Content-Disposition indica que se abrirá "inline" (en el navegador),
        // y que el archivo sugerido se llame ticket_<numero>.pdf en caso de descarga.
        header("Content-Disposition: inline; filename=ticket_$numeroTicket.pdf");

        // Envía el contenido real del PDF al navegador.
        // readfile lee el archivo y lo imprime directamente en la salida estándar.
        readfile($ruta);

        // exit es necesario para evitar que el script continúe ejecutándose después de enviar el PDF.
        exit;
    }
}
?>
