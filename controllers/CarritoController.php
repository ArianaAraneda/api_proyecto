<?php
// Incluye el archivo que contiene la clase Carrito.
// Esta clase maneja toda la lógica relacionada con los ítems del carrito en la base de datos.
require_once __DIR__ . '/../models/Carrito.php';

// Incluye el archivo con la clase User.
// Esta clase permite buscar usuarios, validar tokens y obtener sus datos.
require_once __DIR__ . '/../models/User.php';

// Incluye una clase utilitaria para enviar respuestas JSON homogéneas al frontend.
require_once __DIR__ . '/../utils/Response.php';

// Declara la clase CarritoController, responsable de recibir las solicitudes HTTP
// relacionadas con el carrito, validarlas y delegar la lógica al modelo correspondiente.
class CarritoController {

    // Propiedad que almacenará una instancia del modelo Carrito.
    private $carrito;

    // Propiedad que almacenará una instancia del modelo User.
    private $user;

    // El constructor se ejecuta cada vez que se crea un CarritoController.
    public function __construct() {

        // Instancia el modelo Carrito para poder interactuar con la base de datos.
        $this->carrito = new Carrito();

        // Instancia el modelo User para validar el token y obtener datos del usuario.
        $this->user = new User();
    }

    /** ==========================================
     *  OBTENER CARRITO
     *  ========================================== */
    // Este método recibe un token, valida su autenticidad y devuelve los productos del carrito del usuario.
    public function obtener(string $token): void {

        // Se busca el usuario correspondiente al token.
        // Si el token es inválido o expiró, este método devuelve null/false.
        $u = $this->user->findByToken($token);

        // Si no se encontró usuario válido, se retorna un error 403 y se interrumpe la ejecución.
        if (!$u) { 
            Response::json(['mensaje' => 'No autorizado'], 403); 
            return; 
        }

        // Llama al modelo Carrito para obtener todos los ítems del usuario autenticado.
        $items = $this->carrito->obtener((int)$u['id']);

        // Retorna los ítems como JSON con código 200.
        Response::json($items);
    }

    /** ==========================================
     *  AGREGAR PRODUCTO
     *  ========================================== */
    // Agrega un nuevo producto al carrito del usuario.
    public function agregar(string $token, array $data): void {

        // Valida el token del usuario.
        $u = $this->user->findByToken($token);

        // Si no está autorizado, se envía un 403 y se corta el flujo.
        if (!$u) { 
            Response::json(['mensaje' => 'No autorizado'], 403); 
            return; 
        }

        // Se intenta agregar el producto al carrito.
        // Si algún dato no viene en el request, se usa un valor por defecto.
        $ok = $this->carrito->agregar(
            (int)$u['id'],                         // ID del usuario
            (int)($data['id_producto'] ?? 0),      // ID del producto
            (int)($data['cantidad'] ?? 1),         // Cantidad
            (float)($data['precio_unitario'] ?? 0) // Precio unitario
        );

        // Luego de insertar/actualizar, se obtiene el carrito actualizado.
        $items = $this->carrito->obtener((int)$u['id']);

        // Se envía una respuesta con éxito o error.
        // Si la operación fue correcta, se responde con código 201 (creado).
        Response::json([
            'success' => (bool)$ok,
            'carrito' => $items
        ], $ok ? 201 : 400);
    }

    /** ==========================================
     *  ACTUALIZAR CANTIDAD
     *  ========================================== */
    // Actualiza la cantidad de un ítem específico del carrito.
    public function actualizar(string $token, int $idDetalleCarrito, array $data): void {

        // Valida el token del usuario.
        $u = $this->user->findByToken($token);

        // Si no es válido, se corta el proceso.
        if (!$u) { 
            Response::json(['mensaje' => 'No autorizado'], 403); 
            return; 
        }

        // Obtiene la cantidad que se desea establecer.
        // Si no viene en el request, se usa 1 como cantidad por defecto.
        $cantidad = (int)($data['cantidad'] ?? 1);

        // Actualiza el detalle del carrito.
        // Aquí se usa expresamente el ID del detalle del carrito, no el ID del producto.
        $ok = $this->carrito->actualizarDetalle(
            $idDetalleCarrito,
            $cantidad
        );

        // Recupera el carrito actualizado.
        $items = $this->carrito->obtener((int)$u['id']);

        // Devuelve la respuesta con éxito o error según el resultado.
        Response::json([
            'success' => (bool)$ok,
            'carrito' => $items
        ], $ok ? 200 : 400);
    }

    /** ==========================================
     *  ELIMINAR ITEM DEL CARRITO
     *  ========================================== */
    // Elimina un solo ítem del carrito utilizando su ID.
    public function eliminar(string $token, int $idDetalleCarrito): void {

        // Valida el token del usuario.
        $u = $this->user->findByToken($token);

        // Si es inválido, retorna error 403 y detiene el flujo.
        if (!$u) { 
            Response::json(['mensaje' => 'No autorizado'], 403); 
            return; 
        }

        // Llama al modelo Carrito para eliminar el registro específico del carrito.
        $ok = $this->carrito->eliminarDetalle($idDetalleCarrito);

        // Obtiene el carrito actualizado después de la eliminación.
        $items = $this->carrito->obtener((int)$u['id']);

        // Retorna el estado del carrito junto con un indicador de éxito o error.
        Response::json([
            'success' => (bool)$ok,
            'carrito' => $items
        ], $ok ? 200 : 400);
    }

    /** ==========================================
     *  VACIAR CARRITO
     *  ========================================== */
    // Elimina todos los ítems del carrito del usuario.
    public function vaciar(string $token): void {

        // Valida el token del usuario antes de proceder.
        $u = $this->user->findByToken($token);

        // Si no es válido, devuelve error y corta el flujo.
        if (!$u) { 
            Response::json(['mensaje' => 'No autorizado'], 403); 
            return; 
        }

        // Elimina todos los ítems del carrito del usuario enviado.
        $ok = $this->carrito->vaciar((int)$u['id']);

        // Devuelve un carrito vacío y un estado de éxito o fallo.
        Response::json([
            'success' => (bool)$ok,
            'carrito' => []
        ], $ok ? 200 : 400);
    }
}

?>
