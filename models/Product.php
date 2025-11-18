<?php
// Importa el modelo base que contiene la conexión PDO e infraestructura común
// disponible para todos los modelos de la aplicación.
require_once __DIR__ . '/../core/Model.php';

// Modelo Product, encargado de interactuar con la tabla "products".
// Permite leer, crear, actualizar y eliminar productos dentro del sistema.
class Product extends Model {

    /**
     * Obtiene todos los productos de la tabla products.
     * Retorna un arreglo asociativo con cada fila obtenida.
     */
    public function getAll() {

        // Ejecuta directamente una consulta simple, ya que no hay parámetros externos.
        // query() es segura siempre que no se incluyan valores del usuario.
        $stmt = $this->db->query("SELECT id, nombre, descripcion, precio, imagen, stock FROM products");

        // Retorna todas las filas como un array asociativo.
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un único producto según su ID.
     * Usa una consulta preparada para evitar inyección SQL.
     */
    public function getById($id) {

        // Prepara consulta asegurando que la variable $id no pueda romper la consulta SQL.
        $stmt = $this->db->prepare("
            SELECT id, nombre, descripcion, precio, imagen, stock 
            FROM products 
            WHERE id = ?
        ");

        // Ejecuta la consulta enviando el ID como parámetro.
        $stmt->execute([$id]);

        // Retorna una sola fila (o false si no se encontró).
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Inserta un producto nuevo en la base de datos.
     * Recibe los valores correspondientes a cada columna.
     */
    public function create($nombre, $descripcion, $precio, $imagen, $stock) {

        // Sentencia INSERT con placeholders para todos los campos.
        $stmt = $this->db->prepare("
            INSERT INTO products (nombre, descripcion, precio, imagen, stock) 
            VALUES (?, ?, ?, ?, ?)
        ");

        // Ejecuta la sentencia usando los valores proporcionados.
        return $stmt->execute([$nombre, $descripcion, $precio, $imagen, $stock]);
    }

    /**
     * Actualiza un producto existente identificado por su ID.
     * Reemplaza todos los campos, incluida la imagen si se proporciona.
     */
    public function update($id, $nombre, $descripcion, $precio, $imagen, $stock) {

        // Consulta UPDATE que reemplaza todos los valores del producto.
        $stmt = $this->db->prepare("
            UPDATE products 
            SET nombre=?, descripcion=?, precio=?, imagen=?, stock=? 
            WHERE id=?
        ");

        // Ejecuta actualización.
        return $stmt->execute([$nombre, $descripcion, $precio, $imagen, $stock, $id]);
    }

    /**
     * Elimina un producto según su ID.
     * Solo borra el registro en la tabla, no los archivos del servidor ni relaciones.
     */
    public function delete($id) {

        // Consulta preparada para evitar inyección SQL.
        $stmt = $this->db->prepare("
            DELETE FROM products 
            WHERE id=?
        ");

        // Ejecuta la eliminación del producto.
        return $stmt->execute([$id]);
    }
}
?>
