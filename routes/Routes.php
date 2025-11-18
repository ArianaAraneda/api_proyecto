<?php
// Importa la clase Response para enviar respuestas JSON uniformes.
require_once __DIR__ . '/../utils/Response.php';

// Activa la visualización de errores para desarrollo.
// En producción esto debe estar desactivado.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* ==========================
   CORS
   Configura permisos para permitir peticiones desde Angular (localhost:4200).
========================== */

// Orígenes permitidos
$allowed = ['http://localhost:4200'];

// Obtiene el origen de la petición o usa '*' si no existe.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

// Cabecera que permite solicitudes desde el origen especificado.
header("Access-Control-Allow-Origin: $origin");

// Métodos HTTP permitidos.
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Cabeceras custom permitidas (incluye Authorization para tokens).
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Permitimos envío de cookies / credenciales.
header("Access-Control-Allow-Credentials: true");

// Si es solicitud preflight (OPTIONS), finalizamos inmediatamente.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

/* ==========================
   HELPERS
========================== */

// Lee el cuerpo JSON de una solicitud y lo convierte a array PHP.
function readBody() {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?: []; // Devuelve array vacío si no es JSON.
}

// Obtiene token Bearer del header Authorization.
function getBearerToken() {

    // Usa getallheaders si existe (dependiendo del servidor).
    if (function_exists('getallheaders')) {
        $headers = getallheaders();

        // Busca el header Authorization con formato: Bearer <token>
        if (!empty($headers['Authorization']) &&
            preg_match('/Bearer\s+(\S+)/', $headers['Authorization'], $m))
            return $m[1];
    }

    // Alternativa en servidores que exponen el header como HTTP_AUTHORIZATION
    if (!empty($_SERVER['HTTP_AUTHORIZATION']) &&
        preg_match('/Bearer\s+(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $m))
        return $m[1];

    // Caso apache con variable REDIRECT_HTTP_AUTHORIZATION
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) &&
        preg_match('/Bearer\s+(\S+)/', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $m))
        return $m[1];

    // Si no se encontró el token, se devuelve null.
    return null;
}

/* ==========================
   CONTROLADORES
   Importación e instanciación de cada controlador de la API.
========================== */
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../controllers/CarritoController.php';
require_once __DIR__ . '/../controllers/CompraController.php';
require_once __DIR__ . '/../controllers/TicketController.php';

// Instancia de cada controlador, disponible para las rutas.
$userController = new UserController();
$productController = new ProductController();
$carritoController = new CarritoController();
$compraController = new CompraController();
$ticketController = new TicketController();

/* ==========================
   URI
   Obtiene y normaliza la ruta solicitada por el cliente.
========================== */

// Obtiene la URI en bruto.
$uri = $_SERVER['REQUEST_URI'];

// Elimina el prefijo "/api_proyecto/public" para quedarnos solo con la ruta real.
$uri = str_replace('/api_proyecto/public', '', $uri);

// Elimina query strings si existen (ej: ?page=2).
$uri = strtok($uri, '?');

// Método HTTP (GET, POST, PUT, DELETE...)
$method = $_SERVER['REQUEST_METHOD'];

/* ==========================
   RUTAS
   Router manual basado en switch(true).
========================== */

switch (true) {

    /* ==========================
       USERS
    =========================== */

    case $uri === '/users/login' && $method === 'POST':
        $userController->login(readBody());
        break;

    case $uri === '/users/register' && $method === 'POST':
        $userController->register(readBody());
        break;

    /* ==========================
       PRODUCTS
    =========================== */

    case $uri === '/products' && $method === 'GET':
        $productController->getAll();
        break;

    /* ==========================
       CARRITO
    =========================== */

    // Obtener carrito del usuario autenticado.
    case $uri === '/carrito' && $method === 'GET':
        $carritoController->obtener(getBearerToken());
        break;

    // Agregar producto al carrito.
    case $uri === '/carrito/agregar' && $method === 'POST':
        $carritoController->agregar(getBearerToken(), readBody());
        break;

    // Actualizar cantidad por ID de detalle del carrito.
    case preg_match('/^\/carrito\/actualizar\/(\d+)$/', $uri, $m) && $method === 'PUT':
        $carritoController->actualizar(getBearerToken(), (int)$m[1], readBody());
        break;

    // Eliminar ítem del carrito por su ID de detalle.
    case preg_match('/^\/carrito\/eliminar\/(\d+)$/', $uri, $m) && $method === 'DELETE':
        $carritoController->eliminar(getBearerToken(), (int)$m[1]);
        break;

    // Vaciar el carrito completo.
    case $uri === '/carrito/vaciar' && $method === 'DELETE':
        $carritoController->vaciar(getBearerToken());
        break;

    /* ==========================
       COMPRAS
    =========================== */

    // Finalizar compra.
    case $uri === '/compras/finalizar' && $method === 'POST':
        $compraController->finalizarCompra(getBearerToken());
        break;

    // Listar compras del usuario.
    case $uri === '/compras' && $method === 'GET':
        $compraController->listarCompras(getBearerToken());
        break;

    /* ==========================
       TICKETS
       Importante:
       Primero ruta de ticket por compra,
       luego descarga del PDF
    =========================== */

    // Obtener ticket según el ID de compra.
    case preg_match('/^\/ticket\/compra\/(\d+)$/', $uri, $m) && $method === 'GET':
        $ticketController->obtenerPorCompra((int)$m[1]);
        break;

    // Descargar archivo PDF del ticket según número.
    case preg_match('/^\/ticket\/([A-Za-z0-9\-]+)$/', $uri, $m) && $method === 'GET':
        $ticketController->descargar($m[1]);
        break;

    /* ==========================
       RUTA NO ENCONTRADA (404)
    =========================== */
    default:
        Response::json(["error" => "Ruta no encontrada", "ruta" => $uri], 404);
        break;
}
