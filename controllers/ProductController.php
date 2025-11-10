<?php
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';

/**
 * Controlador de productos.
 * Expone endpoints CRUD y valida rol de administrador mediante token Bearer.
 */
class ProductController {
    /** @var Product */
    private $productModel;

    /** @var User */
    private $userModel;

    /**
     * Inicializa los modelos de dominio requeridos.
     */
    public function __construct() {
        $this->productModel = new Product();
        $this->userModel = new User();
    }

    // ==============================
    // Helpers de autenticación
    // ==============================

    /**
     * Obtiene el usuario autenticado leyendo el header "Authorization: Bearer <token>".
     * Soporta tanto apache_request_headers() como $_SERVER['HTTP_AUTHORIZATION'].
     *
     * @return array|false Arreglo de usuario si el token es válido, o false en caso contrario.
     */
    private function getAuthUser() {
        // Intenta obtener todos los headers mediante la función de Apache (si existe).
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
        $token = null;

        // Caso 1: Cabecera estándar "Authorization"
        if (!empty($headers['Authorization'])) {
            // Extrae el token con un patrón Bearer <token>
            if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $match)) {
                $token = $match[1];
            }
        }

        // Caso 2: Entorno donde el servidor pasa la cabecera como HTTP_AUTHORIZATION
        if (!$token && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
            if (preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $match)) {
                $token = $match[1];
            }
        }

        // Si no hay token, no hay usuario autenticado
        if (!$token) return false;

        // Busca el usuario asociado al token
        return $this->userModel->findByToken($token);
    }

    /**
     * Exige que el usuario autenticado tenga rol "admin".
     * En caso contrario, responde 403 y detiene la ejecución.
     *
     * @return array Usuario autenticado con rol admin.
     */
    private function requireAdmin() {
        $user = $this->getAuthUser();

        // Valida autenticación y rol
        if (!$user || $user['rol'] !== 'admin') {
            Response::json(['mensaje' => 'No autorizado'], 403);
            exit; // Importante: detiene el flujo para evitar continuar el endpoint
        }

        return $user;
    }

    // ==============================
    // GET /products  (público)
    // ==============================

    /**
     * Lista todos los productos.
     * Respuesta: 200 OK con arreglo de productos.
     */
    public function getAll() {
        $products = $this->productModel->getAll();
        Response::json($products);
    }

    // ==============================
    // GET /products/{id} (público)
    // ==============================

    /**
     * Obtiene un producto por su identificador.
     *
     * @param mixed $id Identificador del producto (normalmente int).
     * Respuestas: 
     *  - 200 OK con el producto si existe
     *  - 404 Not Found si no se encuentra
     */
    public function getById($id) {
        $product = $this->productModel->getById($id);
        Response::json($product ?: ['mensaje' => 'No encontrado'], $product ? 200 : 404);
    }

    // ==============================
    // POST /products  (solo admin)
    // ==============================

    /**
     * Crea un nuevo producto. Requiere rol admin.
     * Campos esperados (multipart/form-data o application/x-www-form-urlencoded):
     *  - nombre (string)
     *  - descripcion (string)
     *  - precio (float|int)
     *  - stock (int)
     *  - imagen (file) opcional
     *
     * Respuestas:
     *  - 201 Created en caso de éxito
     *  - 500 Internal Server Error si falla la inserción o subida de imagen
     *  - 403 Forbidden si no es admin
     */
    public function create() {
        // Valida que el usuario sea administrador
        $this->requireAdmin();

        // Lee campos del cuerpo de la solicitud
        $nombre = $_POST['nombre'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $precio = $_POST['precio'] ?? 0;
        $stock = $_POST['stock'] ?? 0;
        $imagenNombre = null;

        // Manejo de archivo de imagen (opcional)
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
            $directorio = __DIR__ . '/../public/uploads/';

            // Crea el directorio si no existe (permisos 0777 y recursivo)
            if (!is_dir($directorio)) {
                mkdir($directorio, 0777, true);
            }

            // Genera un nombre único para evitar colisiones
            $imagenNombre = uniqid() . '_' . basename($_FILES['imagen']['name']);
            $rutaDestino = $directorio . $imagenNombre;

            // Mueve el archivo subido al destino final
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)) {
                Response::json(["error" => "Error al guardar la imagen"], 500);
                return; // Finaliza si la subida falla
            }
        }

        // Crea el producto en la capa de modelo
        $resultado = $this->productModel->create($nombre, $descripcion, $precio, $imagenNombre, $stock);

        // Devuelve respuesta acorde al resultado
        if ($resultado) {
            Response::json(["mensaje" => "Producto creado correctamente"], 201);
        } else {
            Response::json(["error" => "Error al crear el producto"], 500);
        }
    }

    // ==============================
    // PUT /products/{id}  (solo admin)
    // ==============================

    /**
     * Actualiza un producto existente por ID. Requiere rol admin.
     * Espera un cuerpo urlencoded enviado por PUT.
     *
     * @param mixed $id Identificador del producto a actualizar.
     * Respuestas:
     *  - 200 OK con mensaje de éxito
     *  - 500 Internal Server Error si falla la actualización
     *  - 403 Forbidden si no es admin
     */
    public function update($id) {
        // Valida que el usuario sea administrador
        $this->requireAdmin();

        // Lee el cuerpo de la petición PUT (formato application/x-www-form-urlencoded)
        parse_str(file_get_contents("php://input"), $_PUT);

        // Obtiene campos con valores por defecto si no se envían
        $nombre = $_PUT['nombre'] ?? '';
        $descripcion = $_PUT['descripcion'] ?? '';
        $precio = $_PUT['precio'] ?? 0;
        $imagen = $_PUT['imagen'] ?? ''; // Aquí se espera el nombre de imagen ya almacenada, si aplica
        $stock = $_PUT['stock'] ?? 0;

        // Ejecuta la actualización en el modelo
        $resultado = $this->productModel->update($id, $nombre, $descripcion, $precio, $imagen, $stock);

        // Respuesta según resultado
        if ($resultado) {
            Response::json(["mensaje" => "Producto actualizado correctamente"]);
        } else {
            Response::json(["error" => "Error al actualizar el producto"], 500);
        }
    }

    // ==============================
    // DELETE /products/{id}  (solo admin)
    // ==============================

    /**
     * Elimina un producto por ID. Requiere rol admin.
     *
     * @param mixed $id Identificador del producto a eliminar.
     * Respuestas:
     *  - 200 OK con mensaje de éxito
     *  - 500 Internal Server Error si falla el borrado
     *  - 403 Forbidden si no es admin
     */
    public function delete($id) {
        // Valida que el usuario sea administrador
        $this->requireAdmin();

        // Ejecuta eliminación en el modelo
        $resultado = $this->productModel->delete($id);

        // Respuesta según resultado
        if ($resultado) {
            Response::json(["mensaje" => "Producto eliminado correctamente"]);
        } else {
            Response::json(["error" => "Error al eliminar el producto"], 500);
        }
    }
}
?>
