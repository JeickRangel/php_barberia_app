<?php
// Habilitar CORS para permitir llamadas desde React (puerto 5173)
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Si es una petición preflight (OPTIONS), respondemos y salimos
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: text/plain; charset=utf-8");
require_once "conexion.php"; // Importa la conexión

// 1. Capturar los datos del formulario
$nombre       = trim($_POST["nombre"] ?? "");
$email        = trim($_POST["email"] ?? "");
$password     = $_POST["password"] ?? "";
$confirmar    = $_POST["confirmar"] ?? "";
$genero       = $_POST["genero"] ?? null;
$tipo_doc     = $_POST["tipo_doc"] ?? null;
$numero_doc   = trim($_POST["numero_doc"] ?? "");

// 2. Validaciones básicas
if ($nombre === "" || $email === "" || $password === "" || $confirmar === "") {
    http_response_code(400);
    exit("Faltan campos obligatorios.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit("Correo no válido.");
}

if ($password !== $confirmar) {
    http_response_code(400);
    exit("Las contraseñas no coinciden.");
}

// 3. Verificar duplicados (correo o documento)
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? OR numero_documento = ?");
$stmt->bind_param("ss", $email, $numero_doc);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    exit("El correo o el documento ya está registrado.");
}
$stmt->close();

// 4. Insertar en tabla usuarios
$hash = password_hash($password, PASSWORD_BCRYPT); // encripta la contraseña
$rol_id = 3; // 3 = Cliente
$estado = 1; // activo por defecto

$stmt = $conn->prepare("INSERT INTO usuarios 
    (nombre, email, password_hash, genero, tipo_documento, numero_documento, rol_id, estado) 
    VALUES (?,?,?,?,?,?,?,?)");

$stmt->bind_param("ssssssii", $nombre, $email, $hash, $genero, $tipo_doc, $numero_doc, $rol_id, $estado);

if (!$stmt->execute()) {
    http_response_code(500);
    exit("Error al guardar el usuario.");
}

$user_id = $stmt->insert_id; // ID del nuevo usuario
$stmt->close();

// 5. Insertar en clientes (opcional, ya lo dejamos listo)
$stmt = $conn->prepare("INSERT INTO clientes (user_id) VALUES (?)");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

// 6. Respuesta final al frontend
echo "¡OK!"; // Tu frontend busca este mensaje
?>
