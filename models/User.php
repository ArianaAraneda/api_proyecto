<?php
// Importa el modelo base, que contiene la conexión PDO ($this->db)
// y métodos comunes disponibles para todos los modelos.
require_once __DIR__ . '/../core/Model.php';

// Modelo User: maneja registro, login, validación por token y listado de usuarios.
// Se encarga completamente de la autenticación y almacenamiento de usuarios.
class User extends Model {

    // --------------------------------------------------------------
    // REGISTRAR NUEVO USUARIO
    // --------------------------------------------------------------
    public function register($nombre, $email, $password, $rol = 'cliente') {

        // Verifica si ya existe un usuario con ese email.
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        // Si ya existe, devuelve un mensaje estándar que el controlador traducirá.
        if ($stmt->fetch()) {
            return ['error' => 'email_exists'];
        }

        // Crea un hash seguro de la contraseña usando bcrypt
        // (PASSWORD_DEFAULT siempre usa el algoritmo recomendado por PHP).
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Inserta el nuevo usuario con su contraseña hasheada.
        $stmt = $this->db->prepare("
            INSERT INTO users (nombre, email, password, rol) 
            VALUES (?, ?, ?, ?)
        ");

        // Ejecuta la inserción.
        $ok = $stmt->execute([$nombre, $email, $hash, $rol]);

        // Devuelve true/false indicando éxito o fallo.
        return $ok;
    }

    // --------------------------------------------------------------
    // LOGIN CON VERIFICACIÓN Y GENERACIÓN DE TOKEN
    // --------------------------------------------------------------
    public function login($email, $password) {

        // Busca el usuario por email, incluyendo el hash para validar contraseña.
        $stmt = $this->db->prepare("
            SELECT id, nombre, email, password, rol 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);

        // Obtiene la fila como array asociativo.
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si no existe o la contraseña no coincide con el hash almacenado, falla.
        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        // Genera un token seguro para la sesión del usuario.
        // random_bytes genera bytes criptográficamente seguros.
        $token = bin2hex(random_bytes(16));

        // Guarda el token en la base de datos asociado a ese usuario.
        $update = $this->db->prepare("
            UPDATE users 
            SET token = ? 
            WHERE id = ?
        ");
        $update->execute([$token, $user['id']]);

        // Elimina la contraseña del array por seguridad.
        unset($user['password']);

        // Añade el token para devolverlo al controlador.
        $user['token'] = $token;

        return $user;
    }

    // --------------------------------------------------------------
    // BUSCAR USUARIO POR TOKEN
    // --------------------------------------------------------------
    public function findByToken($token) {

        // Si no se envía token, responde inmediatamente con false.
        if (!$token) return false;

        // Busca al usuario cuyo token coincida.
        $stmt = $this->db->prepare("
            SELECT id, nombre, email, rol 
            FROM users 
            WHERE token = ?
        ");
        $stmt->execute([$token]);

        // Devuelve sus datos básicos o false si no existe.
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // --------------------------------------------------------------
    // LISTAR TODOS LOS USUARIOS (solo administradores)
    // --------------------------------------------------------------
    public function getAll() {

        // SELECT directo, ya que no se envían parámetros externos.
        $stmt = $this->db->query("
            SELECT id, nombre, email, rol 
            FROM users
        ");

        // Devuelve todos los usuarios registrados.
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
