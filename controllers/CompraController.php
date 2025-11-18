<?php
// Importa el modelo Compra, encargado de registrar compras y sus detalles en la base de datos.
require_once __DIR__ . '/../models/Compra.php';

// Importa el modelo Carrito, que permite obtener, vaciar y manipular los ítems del carrito del usuario.
require_once __DIR__ . '/../models/Carrito.php';

// Importa el modelo User, necesario para validar el token del usuario y obtener sus datos.
require_once __DIR__ . '/../models/User.php';

// Importa el modelo Ticket, usado para almacenar los tickets generados
// y asociarlos a una compra dentro de la base de datos.
require_once __DIR__ . '/../models/Ticket.php';

// Clase utilitaria para enviar respuestas JSON homogéneas al frontend.
require_once __DIR__ . '/../utils/Response.php';

// Librería externa FPDF para crear PDFs.
require_once __DIR__ . '/../libs/fpdf/fpdf.php';

// Controlador encargado de finalizar compras, generar tickets y vaciar el carrito.
class CompraController {

    // Método principal llamado desde el endpoint de finalizar compra.
    public function finalizarCompra(?string $token)
    {
        // Verifica que se haya enviado un token.
        // Si no se envía, devuelve error 401 indicando que falta autenticación.
        if (!$token) {
            return Response::json(["error" => "Token no enviado"], 401);
        }

        // Se crea un objeto del modelo User para validar el token.
        $userModel = new User();

        // Busca en la base de datos al usuario correspondiente al token recibido.
        $usuario = $userModel->findByToken($token);

        // Si el token no es válido o expiró, devuelve error de autenticación.
        if (!$usuario) {
            return Response::json(["error" => "Token inválido"], 401);
        }

        // Se crea una instancia del modelo Carrito para obtener los ítems del usuario.
        $carritoModel = new Carrito();

        // Obtiene todos los productos del carrito según el ID del usuario autenticado.
        $carrito = $carritoModel->obtener($usuario['id']);

        // Si el carrito está vacío o no existe, se interrumpe la compra.
        if (!$carrito || empty($carrito)) {
            return Response::json(["error" => "El carrito está vacío"], 400);
        }

        // ---------------------------------------------
        // CALCULAR TOTAL DE LA COMPRA
        // ---------------------------------------------

        $total = 0;

        // Recorre cada ítem del carrito y suma precio × cantidad.
        foreach ($carrito as $item) {

            // El precio puede venir como 'precio' o 'precio_unitario' según la consulta SQL utilizada.
            $precio = isset($item['precio']) ? (float)$item['precio'] : (float)$item['precio_unitario'];

            // Convierte a entero la cantidad para evitar valores no válidos.
            $cantidad = (int)$item['cantidad'];

            // Calcula el subtotal del ítem y lo acumula al total general.
            $total += $precio * $cantidad;
        }

        // ---------------------------------------------
        // CREAR REGISTRO DE COMPRA EN LA DB
        // ---------------------------------------------

        // Se crea una instancia del modelo Compra.
        $compraModel = new Compra();

        // Genera en la base de datos una nueva compra con el ID del usuario y el total calculado.
        // Retorna el ID de la compra recién insertada.
        $idCompra = $compraModel->crear($usuario['id'], $total);

        // ---------------------------------------------
        // GUARDAR CADA ÍTEM DEL CARRITO COMO DETALLE
        // ---------------------------------------------

        foreach ($carrito as $item) {

            // Determina el precio como antes.
            $precio = isset($item['precio']) ? (float)$item['precio'] : (float)$item['precio_unitario'];

            $cantidad   = (int)$item['cantidad'];
            $idProducto = (int)$item['id_producto'];

            // Guarda cada producto del carrito como detalle dentro de la compra creada.
            $compraModel->agregarProducto($idCompra, $idProducto, $cantidad, $precio);
        }

        // ---------------------------------------------
        // GENERAR NÚMERO DE TICKET
        // ---------------------------------------------

        // Se crea un número de ticket único usando:
        // TCK - Año actual - ID de compra rellenado a 6 dígitos.
        $nroTicket = "TCK-" . date("Y") . "-" . str_pad($idCompra, 6, "0", STR_PAD_LEFT);

        // ---------------------------------------------
        // CREAR CARPETA DE TICKETS SI NO EXISTE
        // ---------------------------------------------

        // Ruta física donde se guardarán los PDFs dentro del servidor.
        $rutaCarpeta = __DIR__ . "/../public/tickets";

        // Si la carpeta no existe, la crea con permisos 0777.
        if (!file_exists($rutaCarpeta)) {
            mkdir($rutaCarpeta, 0777, true);
        }

        // Ruta física final del archivo PDF.
        $rutaPDF = $rutaCarpeta . "/ticket_" . $nroTicket . ".pdf";

        // Ruta accesible desde el navegador que se enviará al frontend.
        $pdfUrl = "/api_proyecto/public/tickets/ticket_" . $nroTicket . ".pdf";

        // ---------------------------------------------
        // GENERAR ARHIVO PDF DEL TICKET
        // ---------------------------------------------

        $this->generarPDFTicket($rutaPDF, $nroTicket, $carrito, $total);

        // ---------------------------------------------
        // GUARDAR EL TICKET EN LA BASE DE DATOS
        // ---------------------------------------------

        $ticketModel = new Ticket();

        // Se registra un ticket en la base de datos asociado a la compra.
        // Se guarda la ruta física completa donde quedó almacenado el PDF.
        $ticketModel->crear($idCompra, $nroTicket, $rutaPDF);

        // ---------------------------------------------
        // VACIAR EL CARRITO DESPUÉS DE LA COMPRA
        // ---------------------------------------------

        // Borra todos los ítems del carrito del usuario ahora que la compra finalizó.
        $carritoModel->vaciar($usuario['id']);

        // ---------------------------------------------
        // RESPUESTA JSON PARA ANGULAR
        // ---------------------------------------------

        return Response::json([
            "mensaje"       => "Compra finalizada correctamente",
            "id_compra"     => $idCompra,
            "numero_ticket" => $nroTicket,
            "pdf_url"       => $pdfUrl // URL accesible desde el frontend
        ]);
    }

    // Método privado que crea el PDF real del ticket usando FPDF.
    private function generarPDFTicket(string $rutaPDF, string $nroTicket, array $items, float $total)
    {
        // Crea un nuevo archivo PDF en blanco.
        $pdf = new FPDF();

        // Añade una página al documento.
        $pdf->AddPage();

        // Encabezado del ticket.
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, "TICKET DE COMPRA", 0, 1, 'C');

        // Muestra el número del ticket.
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, "Numero de Ticket: $nroTicket", 0, 1);
        $pdf->Ln(5);

        // Encabezados de tabla de productos.
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(80, 8, "Producto", 1);
        $pdf->Cell(30, 8, "Precio", 1);
        $pdf->Cell(30, 8, "Cantidad", 1);
        $pdf->Cell(40, 8, "Subtotal", 1);
        $pdf->Ln();

        // Cuerpo de la tabla.
        $pdf->SetFont('Arial', '', 12);

        foreach ($items as $item) {

            $precio = isset($item['precio']) ? (float)$item['precio'] : (float)$item['precio_unitario'];
            $cantidad = (int)$item['cantidad'];
            $subtotal = $precio * $cantidad;

            // Imprime la fila de cada producto.
            $pdf->Cell(80, 8, $item['nombre'], 1);
            $pdf->Cell(30, 8, "$" . number_format($precio, 2), 1);
            $pdf->Cell(30, 8, $cantidad, 1);
            $pdf->Cell(40, 8, "$" . number_format($subtotal, 2), 1);
            $pdf->Ln();
        }

        $pdf->Ln(5);

        // Total final del ticket.
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, "TOTAL: $" . number_format($total, 2), 0, 1, 'R');

        // Guarda el archivo PDF en la ruta indicada.
        $pdf->Output('F', $rutaPDF);
    }
}
?>
