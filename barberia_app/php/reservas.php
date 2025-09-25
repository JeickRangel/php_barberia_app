<?php
// reservas.php
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
    case 'GET': // 🔹 Listar reservas con filtros opcionales
        $where = [];
        if (isset($_GET['empleado_id'])) {
            $empleado_id = intval($_GET['empleado_id']);
            $where[] = "r.empleado_id = $empleado_id";
        }
        if (isset($_GET['cliente_id'])) {
            $cliente_id = intval($_GET['cliente_id']);
            $where[] = "r.cliente_id = $cliente_id";
        }
        if (isset($_GET['estado'])) {
            $estado = $conn->real_escape_string($_GET['estado']);
            $where[] = "r.estado = '$estado'";
        }
        if (isset($_GET['fecha_inicio']) && isset($_GET['fecha_fin'])) {
            $fi = $conn->real_escape_string($_GET['fecha_inicio']);
            $ff = $conn->real_escape_string($_GET['fecha_fin']);
            $where[] = "r.fecha BETWEEN '$fi' AND '$ff'";
        }

        $whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT r.id_reserva, r.fecha, r.hora, r.estado,
                       c.nombre AS cliente, e.nombre AS empleado,
                       s.nombre AS servicio, s.precio
                FROM reservas r
                JOIN usuarios c ON r.cliente_id = c.id
                JOIN usuarios e ON r.empleado_id = e.id
                JOIN servicios s ON r.servicio_id = s.id_servicio
                $whereSql
                ORDER BY r.fecha, r.hora";
        $result = $conn->query($sql);

        if (!$result) {
            echo json_encode(["status" => "ERROR", "message" => $conn->error]);
            exit();
        }

        $reservas = [];
        while ($row = $result->fetch_assoc()) {
            $reservas[] = $row;
        }
        echo json_encode($reservas);
        break;

    case 'POST': // 🔹 Crear reserva con validaciones
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['cliente_id'], $data['empleado_id'], $data['servicio_id'], $data['fecha'], $data['hora'])) {
            echo json_encode(["status" => "ERROR", "message" => "Datos incompletos"]);
            exit();
        }

        $cliente   = intval($data['cliente_id']);
        $empleado  = intval($data['empleado_id']);
        $servicio  = intval($data['servicio_id']);
        $fecha     = $data['fecha'];
        $hora      = $data['hora'];

        // Verificar disponibilidad del barbero
        $diaSemana = date("l", strtotime($fecha)); // Ej: Monday, Tuesday
        $diasMap = [
            "Monday" => "Lunes", "Tuesday" => "Martes", "Wednesday" => "Miércoles",
            "Thursday" => "Jueves", "Friday" => "Viernes", "Saturday" => "Sábado", "Sunday" => "Domingo"
        ];
        $diaSemanaEsp = $diasMap[$diaSemana] ?? $diaSemana;

        $stmt = $conn->prepare("SELECT * FROM disponibilidad 
                                WHERE id_usuario=? 
                                  AND dia_semana=? 
                                  AND ? BETWEEN hora_inicio AND hora_fin");
        $stmt->bind_param("iss", $empleado, $diaSemanaEsp, $hora);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            echo json_encode(["status" => "ERROR", "message" => "El barbero no trabaja en ese horario"]);
            exit();
        }

        // Verificar si ya tiene otra reserva en ese horario
        $stmt = $conn->prepare("SELECT * FROM reservas 
                                WHERE empleado_id=? AND fecha=? AND hora=? 
                                  AND estado IN ('pendiente','confirmada')");
        $stmt->bind_param("iss", $empleado, $fecha, $hora);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            echo json_encode(["status" => "ERROR", "message" => "El barbero ya tiene una cita en ese horario"]);
            exit();
        }

        // Insertar reserva
        $stmt = $conn->prepare("INSERT INTO reservas 
            (cliente_id, empleado_id, servicio_id, fecha, hora, estado) 
            VALUES (?, ?, ?, ?, ?, 'pendiente')");
        $stmt->bind_param("iiiss", $cliente, $empleado, $servicio, $fecha, $hora);

        if ($stmt->execute()) {
            echo json_encode(["status" => "OK", "message" => "Reserva creada", "id" => $stmt->insert_id]);
        } else {
            echo json_encode(["status" => "ERROR", "message" => $stmt->error]);
        }
        break;

    case 'PUT': // 🔹 Cambiar estado de la reserva
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id_reserva'] ?? null;
        $estado = $data['estado'] ?? null;

        if (!$id || !$estado) {
            echo json_encode(["status" => "ERROR", "message" => "Datos incompletos"]);
            exit();
        }

        $stmt = $conn->prepare("UPDATE reservas SET estado=? WHERE id_reserva=?");
        $stmt->bind_param("si", $estado, $id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "OK", "message" => "Estado actualizado"]);
        } else {
            echo json_encode(["status" => "ERROR", "message" => $stmt->error]);
        }
        break;

    case 'DELETE': // 🔹 Eliminar reserva
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'] ?? null;

        if (!$id) {
            echo json_encode(["status" => "ERROR", "message" => "ID no proporcionado"]);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM reservas WHERE id_reserva=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "OK", "message" => "Reserva eliminada"]);
        } else {
            echo json_encode(["status" => "ERROR", "message" => $stmt->error]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "ERROR", "message" => "Método no permitido"]);
}
