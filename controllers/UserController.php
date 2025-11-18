<?php
/**
 * Controlador de usuarios
 */

// Importa el modelo User, encargado de manejar registros, logins y búsquedas.
require_once __DIR__ . '/../models/User.php';

// Importa la clase Response, utilizada para enviar respuestas JSON consistentes al frontend.
require_once __DIR__ . '/../utils/Response.php';

// Controlador encargado de manejar peticiones relacionadas a usuarios:
// - Registro
// - Login
// - Listado (solo administradores)
class UserController {

    // Instancia del modelo User.
    private $model;

    // Constructor: inicializa el modelo User para ser usado por los métodos del controlador.
    public function __construct() {
        $this->model = new User();
    }

    // -----------------------------------------------------
    // POST /users/register
    // Método encargado de registrar un nuevo usuario.
    // -----------------------------------------------------
    public function register($data) {

        // Valida que los campos obligatorios estén presentes.
        // Si falta alguno, se retorna error 400 (Bad Request).
        if (empty($data['nombre']) || empty($data['email']) || empty($data['password'])) {
            Response::json(['mensaje' => 'Faltan datos obligatorios'], 400);
            return;
        }

        // Si no se envía rol explícitamente, se asigna "cliente" por defecto.
        $rol = $data['rol'] ?? 'cliente';

        // Intenta registrar al usuario utilizando el modelo.
        // El modelo devuelve el ID nuevo o un array con error si el email ya existe.
        $res = $this->model->register($data['nombre'], $data['email'], $data['password'], $rol);

        // Si el modelo devuelve un array con clave "error" indicando email duplicado,
        // se responde con código 409 (Conflict).
        if (is_array($res) && isset($res['error']) && $res['error'] === 'email_exists') {
            Response::json(['mensaje' => 'Email ya registrado'], 409);
            return;
        }

        // Si el registro fue exitoso, $res contendrá true o un ID.
        // Se envía código 201 (Created) en ese caso, o 500 si algo falló internamente.
        Response::json(['success' => (bool)$res], $res ? 201 : 500);
    }

    // -----------------------------------------------------
    // POST /users/login
    // Autentica un usuario y devuelve token + datos básicos.
    // -----------------------------------------------------
    public function login($data) {

        // Valida que email y password se hayan enviado.
        if (empty($data['email']) || empty($data['password'])) {
            Response::json(['mensaje' => 'Faltan credenciales'], 400);
            return;
        }

        // Llama al modelo para validar usuario y contraseña.
        // El modelo devuelve el usuario completo si es correcto, o false si falla.
        $user = $this->model->login($data['email'], $data['password']);

        // Si no coincide email/contraseña, se devuelve error 401 (No autenticado).
        if (!$user) {
            Response::json(['mensaje' => 'Credenciales incorrectas'], 401);
            return;
        }

        // Si las credenciales son correctas, devuelve:
        // - token para autorización futura
        // - datos del usuario (id, nombre, email, rol)
        Response::json([
            'token' => $user['token'],
            'usuario' => [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'email' => $user['email'],
                'rol' => $user['rol']
            ]
        ]);
    }

    // -----------------------------------------------------
    // GET /users
    // Permite listar todos los usuarios, pero solo si el usuario autenticado es admin.
    // -----------------------------------------------------
    public function getAll($authUser) {

        // Verifica si el usuario autenticado existe y si su rol es "admin".
        if (!$authUser || $authUser['rol'] !== 'admin') {
            Response::json(['mensaje' => 'No autorizado'], 403);
            return;
        }

        // Si es administrador, llama al modelo y devuelve todos los usuarios.
        Response::json($this->model->getAll());
    }
}
?>
