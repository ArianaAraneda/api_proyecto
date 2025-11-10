<?php
require_once __DIR__ . '/../core/Model.php';

class Product extends Model {

    /**
     * Obtiene todos los productos de la tabla products.
     * Retorna un arreglo asociativo con cada fila obtenida.
     */
    public function getAll() {
        $stmt = $this->db->query("SELECT id, nombre, descripcion, precio, imagen, stock FROM products");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un único producto según su ID.
     * Usa una consulta preparada para evitar inyección SQL.
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT id, nombre, descripcion, precio, imagen, stock FROM products WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Inserta un producto nuevo en la base de datos.
     * Recibe los valores correspondientes a cada columna.
     */
    public function create($nombre, $descripcion, $precio, $imagen, $stock) {
        $stmt = $this->db->prepare(
            "INSERT INTO products (nombre, descripcion, precio, imagen, stock) VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([$nombre, $descripcion, $precio, $imagen, $stock]);
    }

    /**
     * Actualiza un producto existente identificado por su ID.
     * Reemplaza todos los campos, incluida la imagen si se proporciona.
     */
    public function update($id, $nombre, $descripcion, $precio, $imagen, $stock) {
        $stmt = $this->db->prepare(
            "UPDATE products SET nombre=?, descripcion=?, precio=?, imagen=?, stock=? WHERE id=?"
        );
        return $stmt->execute([$nombre, $descripcion, $precio, $imagen, $stock, $id]);
    }

    /**
     * Elimina un producto según su ID.
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id=?");
        return $stmt->execute([$id]);
    }
}
?>
