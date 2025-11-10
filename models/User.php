<?php
/**
 * Modelo User: gestiona operaciones con la tabla users
 */
require_once __DIR__ . '/../core/Model.php';

class User extends Model {

    // Registrar usuario (guarda password hasheada y rol por defecto 'cliente')
    public function register($nombre, $email, $password, $rol = 'cliente') {
        // Verificar email único
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['error' => 'email_exists'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (nombre, email, password, rol) VALUES (?, ?, ?, ?)");
        $ok = $stmt->execute([$nombre, $email, $hash, $rol]);
        return $ok;
    }

    // Login: verifica email/password y genera token
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT id, nombre, email, password, rol FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) return false;

        // Verificar contraseña
        if (!password_verify($password, $user['password'])) {
            return false;
        }

        // Generar token seguro
        $token = bin2hex(random_bytes(16));
        $stmt = $this->db->prepare("UPDATE users SET token = ? WHERE id = ?");
        $ok = $stmt->execute([$token, $user['id']]);

        // Depuración opcional: confirma si se actualizó
        // var_dump($ok, $stmt->rowCount());

        if (!$ok || $stmt->rowCount() === 0) {
            return false; // No se pudo guardar el token
        }

        // Devolver datos sin la contraseña, con token
        unset($user['password']);
        $user['token'] = $token;
        return $user;
    }

    // Buscar usuario por token (para autorizar)
    public function findByToken($token) {
        if (!$token) return false;
        $stmt = $this->db->prepare("SELECT id, nombre, email, rol FROM users WHERE token = ?");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtener todos los usuarios (solo admin)
    public function getAll() {
        $stmt = $this->db->query("SELECT id, nombre, email, rol FROM users");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
