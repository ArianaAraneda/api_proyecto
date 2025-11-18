<?php
// Importa el modelo Product, encargado de manejar consultas y operaciones sobre productos.
require_once __DIR__ . '/../models/Product.php';

// Importa el modelo User, necesario para validar tokens y verificar roles.
require_once __DIR__ . '/../models/User.php';

// Clase utilitaria para devolver respuestas JSON uniformes al frontend.
require_once __DIR__ . '/../utils/Response.php';

// Controlador que maneja todo lo relacionado con productos:
// - listar
// - obtener por ID
// - crear
// - actualizar
// - eliminar
class ProductController {

    // Propiedad que almacenará una instancia del modelo de productos.
    private $productModel;

    // Propiedad que almacenará el modelo de usuario para autenticar mediante token.
    private $userModel;

    // Constructor: se ejecuta al instanciar este controlador.
    public function __construct() {
        // Instancia el modelo Product.
        $this->productModel = new Product();

        // Instancia el modelo User.
        $this->userModel = new User();
    }

    // Método privado encargado de obtener el usuario autenticado mediante token Bearer.
    private function getAuthUser() {

        // Obtiene encabezados HTTP usando apache_request_headers si está disponible.
        // Si no existe, devuelve un array vacío para evitar errores.
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];

        // Inicializa variable token como null.
        $token = null;

        // Primera búsqueda: encabezado Authorization recibido por Apache.
        // Verifica que exista y contenga un formato Bearer válido mediante expresión regular.
        if (!empty($headers['Authorization']) &&
            preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $m)) {

            // Si coincide, extrae el token capturado en el grupo 1.
            $token = $m[1];
        }

        // Segunda búsqueda: caso donde PHP expone el header como HTTP_AUTHORIZATION.
        if (!$token && !empty($_SERVER['HTTP_AUTHORIZATION']) &&
            preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $m)) {

            $token = $m[1];
        }

        // Si no se detectó token en ningún encabezado, retorna false.
        if (!$token) return false;

        // Retorna los datos del usuario buscado mediante el token.
        return $this->userModel->findByToken($token);
    }

    // Método privado que exige que el usuario sea administrador para continuar.
    private function requireAdmin() {

        // Obtiene al usuario autenticado.
        $user = $this->getAuthUser();

        // Si no hay usuario válido o su rol no es "admin",
        // se detiene el flujo y se responde error 403.
        if (!$user || $user['rol'] !== 'admin') {
            Response::json(['mensaje' => 'No autorizado'], 403);
            exit;
        }

        // Si es admin, se devuelve el usuario para utilizarlo si es necesario.
        return $user;
    }

    // Lista todos los productos sin requerir autenticación.
    public function getAll() {

        // Obtiene todos los productos desde el modelo.
        $products = $this->productModel->getAll();

        // Los devuelve al frontend en formato JSON.
        Response::json($products);
    }

    // Obtiene un producto específico por su ID.
    public function getById($id) {

        // Obtiene el producto.
        $product = $this->productModel->getById($id);

        // Si existe, se devuelve con código 200.
        // Si no existe, se devuelve mensaje de error y código 404.
        Response::json($product ?: ['mensaje' => 'No encontrado'], $product ? 200 : 404);
    }

    // Crear un nuevo producto. Requiere rol administrador.
    public function create() {

        // Verifica que el usuario sea administrador antes de continuar.
        $this->requireAdmin();

        // Obtiene los valores enviados vía POST.
        $nombre      = $_POST['nombre']      ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $precio      = $_POST['precio']      ?? 0;
        $stock       = $_POST['stock']       ?? 0;

        // Nombre final del archivo de imagen (si se sube una).
        $imagenNombre = null;

        // Si se envió un archivo y no hubo error en la subida.
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {

            // Define la carpeta donde se guardarán las imágenes.
            $directorio = __DIR__ . '/../public/uploads/';

            // Crea la carpeta si no existe.
            if (!is_dir($directorio)) mkdir($directorio, 0777, true);

            // Genera un nombre único para evitar sobrescrituras.
            $imagenNombre = uniqid() . '_' . $_FILES['imagen']['name'];

            // Calcula la ruta destino completa.
            $rutaDestino = $directorio . $imagenNombre;

            // Mueve el archivo temporal a la carpeta definitiva.
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)) {

                // Si no se pudo mover la imagen, devuelve error.
                Response::json(["error" => "Error al guardar la imagen"], 500);
                return;
            }
        }

        // Crea el producto en la base de datos utilizando el modelo.
        $ok = $this->productModel->create($nombre, $descripcion, $precio, $imagenNombre, $stock);

        // Devuelve éxito o error según corresponda.
        Response::json(["success" => $ok], $ok ? 201 : 500);
    }

    // Actualiza un producto existente. Requiere rol administrador.
    public function update($id) {

        // Exige rol administrador.
        $this->requireAdmin();

        // Obtiene el producto actual para saber qué datos reemplazar o conservar.
        $productoActual = $this->productModel->getById($id);

        // Si el producto no existe, no se puede actualizar.
        if (!$productoActual) {
            Response::json(["error" => "Producto no encontrado"], 404);
            return;
        }

        // Si no se envía algún campo, se conserva el valor previo.
        $nombre      = $_POST['nombre']      ?? $productoActual['nombre'];
        $descripcion = $_POST['descripcion'] ?? $productoActual['descripcion'];
        $precio      = $_POST['precio']      ?? $productoActual['precio'];
        $stock       = $_POST['stock']       ?? $productoActual['stock'];

        // La imagen actual se mantiene a menos que el usuario suba una nueva.
        $imagenNombre = $productoActual['imagen'];

        // Si se envió una nueva imagen, se procede a guardarla.
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {

            // Carpeta de destino de imágenes.
            $directorio = __DIR__ . '/../public/uploads/';

            // Crear carpeta si no existe.
            if (!is_dir($directorio)) mkdir($directorio, 0777, true);

            // Crear nombre único para evitar colisiones.
            $imagenNombre = uniqid() . '_' . $_FILES['imagen']['name'];

            // Ruta destino completa.
            $rutaDestino = $directorio . $imagenNombre;

            // Mueve la imagen.
            move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino);
        }

        // Actualiza los datos del producto en la base.
        $ok = $this->productModel->update($id, $nombre, $descripcion, $precio, $imagenNombre, $stock);

        // Envía la respuesta.
        Response::json(["success" => $ok], $ok ? 200 : 500);
    }

    // Elimina un producto por su ID. Requiere ser administrador.
    public function delete($id) {

        // Verificar que el usuario sea admin.
        $this->requireAdmin();

        // Ejecuta el borrado en el modelo.
        $ok = $this->productModel->delete($id);

        // Devuelve el resultado.
        Response::json(["success" => $ok], $ok ? 200 : 500);
    }
}
?>
