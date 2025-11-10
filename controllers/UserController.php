<?php
/**
 * Controlador de usuarios
 */
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';

class UserController {
    private $model;

    public function __construct() {
        $this->model = new User();
    }

    // POST /users/register
    public function register($data) {
        if (empty($data['nombre']) || empty($data['email']) || empty($data['password'])) {
            Response::json(['mensaje' => 'Faltan datos obligatorios'], 400);
            return;
        }
        $rol = $data['rol'] ?? 'cliente';
        $res = $this->model->register($data['nombre'], $data['email'], $data['password'], $rol);
        if (is_array($res) && isset($res['error']) && $res['error'] === 'email_exists') {
            Response::json(['mensaje' => 'Email ya registrado'], 409);
            return;
        }
        Response::json(['success' => (bool)$res], $res ? 201 : 500);
    }

    // POST /users/login
    public function login($data) {
        if (empty($data['email']) || empty($data['password'])) {
            Response::json(['mensaje' => 'Faltan credenciales'], 400);
            return;
        }
        $user = $this->model->login($data['email'], $data['password']);
        if (!$user) {
            Response::json(['mensaje' => 'Credenciales incorrectas'], 401);
            return;
        }
        Response::json($user);
    }

    // GET /users (solo admin)
    public function getAll($authUser) {
        if (!$authUser || $authUser['rol'] !== 'admin') {
            Response::json(['mensaje' => 'No autorizado'], 403);
            return;
        }
        Response::json($this->model->getAll());
    }
}
?>
