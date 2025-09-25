<?php
// disponibilidad.php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json; charset=utf-8");

require_once "conexion.php";

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET': // 🔹 Listar disponibilidad por usuario
        if (!isset($_GET['id_usuario'])) {
            echo json_encode(["status" => "ERROR", "message" => "id_usuario requerido"]);
            exit();
        }

        $id_usuario = intval($_GET['id_usuario']);
        $result = $conn->query("SELECT id_disponibilidad, id_usuario, dia_semana, hora_inicio, hora_fin 
                                FROM disponibilidad 
                                WHERE id_usuario = $id_usuario");

        if (!$result) {
            echo json_encode(["status" => "ERROR", "message" => $conn->error]);
            exit();
        }

        $horarios = [];
        while ($row = $result->fetch_assoc()) {
            $horarios[] = $row;
        }
        echo json_encode($horarios);
        break;

    case 'POST': // 🔹 Crear disponibilidad (puede ser varios días)
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['id_usuario'], $data['hora_inicio'], $data['hora_fin'], $data['dias'])) {
            echo json_encode(["status" => "ERROR", "message" => "Datos incompletos"]);
            exit();
        }

        $id_usuario  = intval($data['id_usuario']);
        $hora_inicio = $data['hora_inicio'];
        $hora_fin    = $data['hora_fin'];
        $dias        = $data['dias'];

        if (!is_array($dias) || empty($dias)) {
            echo json_encode(["status" => "ERROR", "message" => "No se enviaron días válidos"]);
            exit();
        }

        foreach ($dias as $dia) {
            $stmt = $conn->prepare("INSERT INTO disponibilidad (id_usuario, dia_semana, hora_inicio, hora_fin) 
                                    VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $id_usuario, $dia, $hora_inicio, $hora_fin);

            if (!$stmt->execute()) {
                echo json_encode(["status" => "ERROR", "message" => $stmt->error]);
                exit();
            }
        }

        echo json_encode(["status" => "OK", "message" => "Disponibilidad agregada"]);
        break;

    case 'DELETE': // 🔹 Eliminar disponibilidad
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id_disponibilidad'] ?? null;

    if (!$id) {
        echo json_encode(["status" => "ERROR", "message" => "id_disponibilidad requerido"]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM disponibilidad WHERE id_disponibilidad=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "OK", "message" => "Disponibilidad eliminada"]);
    } else {
        echo json_encode(["status" => "ERROR", "message" => $stmt->error]);
    }
    break;


    default:
        http_response_code(405);
        echo json_encode(["status" => "ERROR", "message" => "Método no permitido"]);
}
