<?php
/**
 * Rutas principales de la API
 */

require_once __DIR__ . '/../utils/Response.php';

// ==============================================
// CORS (desarrollo)
// ==============================================

// Lista de orígenes permitidos para consumir la API durante desarrollo
$allowed = ['http://localhost:4200', 'http://127.0.0.1:4200'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Si el origen está permitido, se habilita acceso CORS
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Valor por defecto cuando el origen no coincide
    header("Access-Control-Allow-Origin: http://localhost:4200");
}

// Métodos y cabeceras permitidas
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Respuesta inmediata a las peticiones preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==============================================
// HELPERS
// ==============================================

/**
 * Obtiene y decodifica el cuerpo de la solicitud.
 * - Si el Content-Type es JSON, se decodifica.
 * - Si es form-data o x-www-form-urlencoded, se usa $_POST.
 */
function readBody(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    if (!empty($_POST)) return $_POST;

    return [];
}

/**
 * Extrae el token JWT del encabezado Authorization.
 * Acepta formatos: "Authorization: Bearer token".
 */
function getBearerToken(): ?string {
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (!empty($headers['Authorization']) &&
            preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $m)) {
            return $m[1];
        }
    }

    if (!empty($_SERVER['HTTP_AUTHORIZATION']) &&
        preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
        return $m[1];
    }

    return null;
}

// ==============================================
// CARGA DE CONTROLADORES Y MODELOS
// ==============================================

require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../models/User.php';

// Carga el módulo de productos solo si está presente en el proyecto
$productController = null;
$productPath = __DIR__ . '/../controllers/ProductController.php';

if (file_exists($productPath)) {
    require_once $productPath;

    if (class_exists('ProductController')) {
        $productController = new ProductController();
    }
}

// ==============================================
// PROCESAMIENTO DE LA URL
// ==============================================

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Prefijo de la carpeta pública
$basePath = '/api_proyecto/public';

// Quita el prefijo base para obtener solo la ruta real consultada
if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

// Normaliza la ruta final
$uri = rtrim($uri, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

// ==============================================
// INSTANCIAS
// ==============================================

$userModel = new User();
$userController = new UserController();

// ==============================================
// ENRUTAMIENTO PRINCIPAL
// ==============================================

switch (true) {

    // -------- Usuarios --------

    // Registro de usuario
    case $uri === '/users/register' && $method === 'POST':
        $data = readBody();
        $userController->register($data);
        break;

    // Login de usuario
    case $uri === '/users/login' && $method === 'POST':
        $data = readBody();
        $userController->login($data);
        break;

    // Listado general de usuarios (requiere rol admin)
    case $uri === '/users' && $method === 'GET':
        $token = getBearerToken();
        $authUser = $userModel->findByToken($token);
        $userController->getAll($authUser);
        break;

    // -------- Productos --------

    case $uri === '/products' && $method === 'GET':
        if (!$productController) {
            Response::json(['mensaje' => 'Módulo de productos no disponible'], 501);
            break;
        }
        $productController->getAll();
        break;

    case $uri === '/products' && $method === 'POST':
        if (!$productController) {
            Response::json(['mensaje' => 'Módulo de productos no disponible'], 501);
            break;
        }

        // Valida token y rol antes de crear un producto
        $token = getBearerToken();
        $authUser = $userModel->findByToken($token);

        $productController->create($authUser);
        break;

    // Obtiene producto por ID
    case preg_match('/\/products\/(\d+)/', $uri, $m) && $method === 'GET':
        if (!$productController) {
            Response::json(['mensaje' => 'Módulo de productos no disponible'], 501);
            break;
        }
        $productController->get($m[1]);
        break;

    // Actualiza producto por ID
    case preg_match('/\/products\/(\d+)/', $uri, $m) && $method === 'PUT':
        if (!$productController) {
            Response::json(['mensaje' => 'Módulo de productos no disponible'], 501);
            break;
        }

        $token = getBearerToken();
        $authUser = $userModel->findByToken($token);

        $productController->update($m[1], $authUser);
        break;

    // Elimina producto por ID
    case preg_match('/\/products\/(\d+)/', $uri, $m) && $method === 'DELETE':
        if (!$productController) {
            Response::json(['mensaje' => 'Módulo de productos no disponible'], 501);
            break;
        }

        $token = getBearerToken();
        $authUser = $userModel->findByToken($token);

        $productController->delete($m[1], $authUser);
        break;

    // Ruta no encontrada
    default:
        Response::json(['mensaje' => 'Ruta no encontrada', 'uri' => $uri], 404);
        break;
}
