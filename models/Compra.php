<?php
// Importa la clase base Model, que provee la conexión PDO ($this->db)
// y funciones comunes para todos los modelos.
require_once __DIR__ . '/../core/Model.php';

// Modelo Compra: maneja registro de compras, detalles,
// y consulta de historial de compras del usuario.
class Compra extends Model {

    /* ========================================================
       CREAR COMPRA
       Inserta un registro principal en la tabla "compras".
       Retorna el ID de la compra generada.
    ======================================================== */
    public function crear(int $idUsuario, float $total): int {

        // Prepara la sentencia SQL para insertar una nueva compra.
        $stmt = $this->db->prepare("
            INSERT INTO compras (id_usuario, total)
            VALUES (?, ?)
        ");

        // Ejecuta la consulta con los parámetros correspondientes.
        $stmt->execute([$idUsuario, $total]);

        // Obtiene el ID autogenerado de la compra recién insertada.
        return (int)$this->db->lastInsertId();
    }

    /* ========================================================
       AGREGAR PRODUCTO A UNA COMPRA
       Inserta cada ítem del carrito en la tabla detalles_compra.
       Guarda cantidad, precio unitario y subtotal del producto.
    ======================================================== */
    public function agregarProducto(int $idCompra, int $idProducto, int $cantidad, float $precio): bool {

        // Prepara la consulta SQL de inserción del detalle.
        $stmt = $this->db->prepare("
            INSERT INTO detalles_compra (id_compra, id_producto, cantidad, precio_unitario, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");

        // Ejecuta el insert con los valores enviados.
        // El subtotal se calcula en este punto para evitar cálculos futuros.
        return $stmt->execute([
            $idCompra,
            $idProducto,
            $cantidad,
            $precio,
            $cantidad * $precio
        ]);
    }

    /* ========================================================
       OBTENER TODAS LAS COMPRAS DE UN USUARIO
       Retorna un historial ordenado desde la más reciente.
    ======================================================== */
    public function obtenerPorUsuario(int $idUsuario): array {

        // Prepara consulta SQL para obtener compras del usuario.
        $stmt = $this->db->prepare("
            SELECT *
            FROM compras
            WHERE id_usuario = ?
            ORDER BY id_compra DESC
        ");

        // Ejecuta consulta con el ID del usuario.
        $stmt->execute([$idUsuario]);

        // Retorna todas las compras; si no hay registros, devuelve array vacío.
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* ========================================================
       OBTENER UNA COMPRA POR SU ID
       Devuelve los datos básicos de la compra.
    ======================================================== */
    public function obtenerCompra(int $idCompra): ?array {

        // Prepara SQL para buscar una compra específica.
        $stmt = $this->db->prepare("
            SELECT *
            FROM compras
            WHERE id_compra = ?
            LIMIT 1
        ");

        // Ejecuta consulta con el ID de la compra.
        $stmt->execute([$idCompra]);

        // Extrae la fila encontrada o devuelve null si no existe.
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /* ========================================================
       OBTENER PRODUCTOS DE UNA COMPRA
       Devuelve todos los items comprados según el ID de compra.
    ======================================================== */
    public function obtenerProductos(int $idCompra): array {

        // Consulta SQL que une detalles_compra con la tabla de productos
        // para obtener información completa del ítem vendido.
        $stmt = $this->db->prepare("
            SELECT 
                dc.id_detalle_compra,
                dc.id_producto,
                dc.cantidad,
                dc.precio_unitario,
                dc.subtotal,
                p.nombre,
                p.descripcion,
                p.imagen
            FROM detalles_compra dc
            INNER JOIN products p ON p.id = dc.id_producto
            WHERE dc.id_compra = ?
        ");

        // Ejecuta la consulta.
        $stmt->execute([$idCompra]);

        // Devuelve los resultados o un array vacío si no hay detalles.
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
?>
