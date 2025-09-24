<?php
// reservas.php
header("Access-Control-Allow-Origin: *");
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
    case 'GET': // 🔹 Listar reservas con JOINs
        $sql = "SELECT r.id_reserva, r.fecha, r.hora, r.estado,
                       c.nombre AS cliente, e.nombre AS empleado,
                       s.nombre AS servicio, s.precio
                FROM reservas r
                JOIN usuarios c ON r.cliente_id = c.id
                JOIN usuarios e ON r.empleado_id = e.id
                JOIN servicios s ON r.servicio_id = s.id_servicio
                ORDER BY r.fecha, r.hora";
        $result = $conn->query($sql);
        $reservas = [];
        while ($row = $result->fetch_assoc()) {
            $reservas[] = $row;
        }
        echo json_encode($reservas);
        break;

    case 'POST': // 🔹 Crear reserva
        $data = json_decode(file_get_contents("php://input"), true);
        $cliente = $data['cliente_id'];
        $empleado = $data['empleado_id'];
        $servicio = $data['servicio_id'];
        $fecha = $data['fecha'];
        $hora = $data['hora'];

        $stmt = $conn->prepare("INSERT INTO reservas 
            (cliente_id, empleado_id, servicio_id, fecha, hora, estado) 
            VALUES (?, ?, ?, ?, ?, 'pendiente')");
        $stmt->bind_param("iiiss", $cliente, $empleado, $servicio, $fecha, $hora);
        $stmt->execute();

        echo json_encode(["status" => "OK", "message" => "Reserva creada", "id" => $stmt->insert_id]);
        break;

    case 'PUT': // 🔹 Actualizar estado de la reserva
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id_reserva'];
        $estado = $data['estado'];

        $stmt = $conn->prepare("UPDATE reservas SET estado=? WHERE id_reserva=?");
        $stmt->bind_param("si", $estado, $id);
        $stmt->execute();

        echo json_encode(["status" => "OK", "message" => "Estado actualizado"]);
        break;

    case 'DELETE': // 🔹 Eliminar reserva
        parse_str(file_get_contents("php://input"), $data);
        $id = $data['id'];

        $stmt = $conn->prepare("DELETE FROM reservas WHERE id_reserva=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        echo json_encode(["status" => "OK", "message" => "Reserva eliminada"]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "ERROR", "message" => "Método no permitido"]);
}
