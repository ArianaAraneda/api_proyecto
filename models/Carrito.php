<?php
// Importa el modelo base, que contiene la conexión PDO y funcionalidad común.
require_once __DIR__ . '/../core/Model.php';

// Clase Carrito que extiende del modelo base.
// Maneja toda la lógica relacionada con carritos de compra:
// obtener, agregar, actualizar, eliminar ítems y garantizar la existencia de un carrito activo.
class Carrito extends Model {

    /* ============================================================
       OBTENER CARRITO
       Obtiene todos los productos del carrito actual del usuario.
       Siempre garantiza que el carrito exista previamente.
    ============================================================ */
    public function obtener(int $idUsuario): array {

        // Asegura que el usuario tiene un carrito activo en estado "abierto".
        // Si no existe, lo crea y retorna su ID.
        $carritoId = $this->asegurarCarritoAbierto($idUsuario);

        // Consulta SQL para obtener los detalles del carrito, junto con información del producto.
        $sql = "SELECT 
                    dc.id_detalle_carrito,
                    dc.id_producto AS id_producto,
                    dc.cantidad,
                    dc.precio_unitario AS precio_unitario,
                    (dc.cantidad * dc.precio_unitario) AS subtotal,
                    p.nombre,
                    p.descripcion,
                    p.imagen
                FROM detalles_carrito dc
                INNER JOIN products p ON p.id = dc.id_producto
                WHERE dc.id_carrito = ?";

        // Prepara la consulta SQL.
        $stmt = $this->db->prepare($sql);

        // Ejecuta la consulta pasando el ID del carrito.
        $stmt->execute([$carritoId]);

        // Devuelve todos los resultados como array asociativo, o un array vacío si no hay ítems.
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* ============================================================
       AGREGAR PRODUCTO AL CARRITO
       Si el producto ya existe, suma cantidades.
       Si no, lo inserta como nuevo elemento.
    ============================================================ */
    public function agregar(int $idUsuario, int $idProducto, int $cantidad, float $precioUnit): bool {

        // Obtiene o crea el carrito del usuario.
        $carritoId = $this->asegurarCarritoAbierto($idUsuario);

        // Primero revisa si ya existe ese producto dentro del carrito.
        $check = $this->db->prepare("
            SELECT id_detalle_carrito, cantidad 
            FROM detalles_carrito 
            WHERE id_carrito = ? AND id_producto = ?
        ");
        $check->execute([$carritoId, $idProducto]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        // Si ya existe, se actualiza la cantidad sumando la nueva.
        if ($row) {
            $nueva = (int)$row['cantidad'] + $cantidad;

            // Se actualiza cantidad y precio unitario.
            $upd = $this->db->prepare("
                UPDATE detalles_carrito 
                SET cantidad = ?, precio_unitario = ? 
                WHERE id_detalle_carrito = ?
            ");
            return $upd->execute([$nueva, $precioUnit, $row['id_detalle_carrito']]);
        } 
        
        // Si no existe, se inserta una nueva fila.
        $ins = $this->db->prepare("
            INSERT INTO detalles_carrito (id_carrito, id_producto, cantidad, precio_unitario) 
            VALUES (?, ?, ?, ?)
        ");
        return $ins->execute([$carritoId, $idProducto, $cantidad, $precioUnit]);
    }

    /* ============================================================
       ACTUALIZAR DIRECTO POR id_detalle_carrito (MÉTODO NUEVO)
       Cambia la cantidad o elimina el ítem si la cantidad es <= 0.
    ============================================================ */
    public function actualizarDetalle(int $idDetalleCarrito, int $cantidad): bool {

        // Si la cantidad es menor o igual que cero, elimina directamente el ítem.
        if ($cantidad <= 0) {
            $del = $this->db->prepare("
                DELETE FROM detalles_carrito 
                WHERE id_detalle_carrito = ?
            ");
            return $del->execute([$idDetalleCarrito]);
        }

        // Si la cantidad es válida, actualiza el ítem correspondiente.
        $upd = $this->db->prepare("
            UPDATE detalles_carrito
            SET cantidad = ?
            WHERE id_detalle_carrito = ?
        ");
        return $upd->execute([$cantidad, $idDetalleCarrito]);
    }

    /* ============================================================
       ELIMINAR DIRECTO POR id_detalle_carrito (MÉTODO NUEVO)
       Elimina un ítem sin necesidad de conocer usuario ni producto.
    ============================================================ */
    public function eliminarDetalle(int $idDetalleCarrito): bool {
        
        // Elimina el registro del detalle del carrito correspondiente.
        $del = $this->db->prepare("
            DELETE FROM detalles_carrito 
            WHERE id_detalle_carrito = ?
        ");
        return $del->execute([$idDetalleCarrito]);
    }

    /* ============================================================
       MÉTODOS ANTIGUOS (COMPATIBILIDAD)
       Ya no se usan en el nuevo flujo, pero se conservan.
    ============================================================ */

    // Actualiza usando id_usuario + id_producto (ya no recomendado).
    public function actualizar(int $idUsuario, int $idProducto, int $cantidad): bool {

        // Obtiene o crea el carrito abierto del usuario.
        $carritoId = $this->asegurarCarritoAbierto($idUsuario);

        // Si la cantidad es menor o igual a cero, elimina el ítem.
        if ($cantidad <= 0) {
            $del = $this->db->prepare("
                DELETE FROM detalles_carrito 
                WHERE id_carrito = ? AND id_producto = ?
            ");
            return $del->execute([$carritoId, $idProducto]);
        }

        // Actualiza la cantidad del producto dentro del carrito.
        $upd = $this->db->prepare("
            UPDATE detalles_carrito 
            SET cantidad = ?
            WHERE id_carrito = ? AND id_producto = ?
        ");
        return $upd->execute([$cantidad, $carritoId, $idProducto]);
    }

    // Elimina usando id_usuario + id_producto (forma antigua).
    public function eliminar(int $idUsuario, int $idProducto): bool {

        // Garantiza el carrito.
        $carritoId = $this->asegurarCarritoAbierto($idUsuario);

        // Elimina el ítem específico.
        $del = $this->db->prepare("
            DELETE FROM detalles_carrito 
            WHERE id_carrito = ? AND id_producto = ?
        ");
        return $del->execute([$carritoId, $idProducto]);
    }

    /* ============================================================
       VACIAR TODO EL CARRITO
       Elimina todos los ítems del carrito del usuario.
    ============================================================ */
    public function vaciar(int $idUsuario): bool {

        // Obtiene el carrito activo.
        $carritoId = $this->asegurarCarritoAbierto($idUsuario);

        // Elimina todos los items del carrito.
        $del = $this->db->prepare("
            DELETE FROM detalles_carrito 
            WHERE id_carrito = ?
        ");

        return $del->execute([$carritoId]);
    }

    /* ============================================================
       ASEGURAR QUE EXISTA UN CARRITO ABIERTO
       Si el usuario no tiene uno, lo crea automáticamente.
    ============================================================ */
    private function asegurarCarritoAbierto(int $idUsuario): int {

        // Busca un carrito con estado "abierto" para el usuario.
        $sel = $this->db->prepare("
            SELECT id_carrito 
            FROM carrito 
            WHERE id_usuario = ? AND estado = 'abierto'
            LIMIT 1
        ");
        $sel->execute([$idUsuario]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);

        // Si ya existe un carrito abierto, devuelve su ID.
        if ($row) return (int)$row['id_carrito'];

        // Si no existe, crea uno nuevo.
        $ins = $this->db->prepare("
            INSERT INTO carrito (id_usuario, estado) 
            VALUES (?, 'abierto')
        ");
        $ins->execute([$idUsuario]);

        // Retorna el ID del carrito recién creado.
        return (int)$this->db->lastInsertId();
    }
}
?>
