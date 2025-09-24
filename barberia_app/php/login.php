<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json; charset=utf-8");
require_once "conexion.php";

$data = $_POST; // porque envías con FormData
$email = $data['email'];
$password = $data['password'];

$stmt = $conn->prepare("SELECT id, nombre, email, password_hash, rol_id FROM usuarios WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Aquí usas la contraseña hasheada (siempre 123456 en tu caso)
    if (password_verify($password, $row['password_hash'])) {
        echo json_encode([
            "status" => "OK",
            "usuario" => [
                "id" => $row['id'],
                "nombre" => $row['nombre'],
                "correo" => $row['email'],
                "rol" => $row['rol_id']
            ]
        ]);
    } else {
        echo json_encode(["status" => "ERROR", "message" => "Contraseña incorrecta"]);
    }
} else {
    echo json_encode(["status" => "ERROR", "message" => "Usuario no encontrado"]);
}
