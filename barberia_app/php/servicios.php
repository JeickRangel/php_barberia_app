<?php
header("Access-Control-Allow-Origin: *"); // Permitir llamadas desde React
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Manejo de preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = "localhost";
$user = "root"; // tu usuario MySQL
$pass = "";     // tu contraseña MySQL
$db = "barberia_app";

// Conexión
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode(["error" => "Conexión fallida: " . $conn->connect_error]));
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case "GET": // Listar servicios
        $result = $conn->query("SELECT * FROM servicios");
        $servicios = [];
        while ($row = $result->fetch_assoc()) {
            $servicios[] = $row;
        }
        echo json_encode($servicios);
        break;

    case "POST": // Crear servicio
        $data = json_decode(file_get_contents("php://input"), true);
        $stmt = $conn->prepare("INSERT INTO servicios (nombre, descripcion, precio, duracion) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssdi", $data['nombre'], $data['descripcion'], $data['precio'], $data['duracion']);
        $stmt->execute();
        echo json_encode(["success" => true, "id" => $conn->insert_id]);
        break;

    case "PUT": // Editar servicio
        $data = json_decode(file_get_contents("php://input"), true);
        $stmt = $conn->prepare("UPDATE servicios SET nombre=?, descripcion=?, precio=?, duracion=? WHERE id_servicio=?");
        $stmt->bind_param("ssdii", $data['nombre'], $data['descripcion'], $data['precio'], $data['duracion'], $data['id_servicio']);
        $stmt->execute();
        echo json_encode(["success" => true]);
        break;

    case "DELETE": // Eliminar servicio
        parse_str(file_get_contents("php://input"), $data);
        $id = $data['id_servicio'];
        $stmt = $conn->prepare("DELETE FROM servicios WHERE id_servicio=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(["success" => true]);
        break;
}
$conn->close();
