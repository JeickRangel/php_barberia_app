<?php
// usuarios.php
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

    case 'GET': // 🔹 Listar usuarios
        if (isset($_GET['rol'])) {
            $rol = intval($_GET['rol']);
            $result = $conn->query("SELECT u.id, u.nombre, u.email as correo, u.genero, 
                                           u.tipo_documento, u.numero_documento, 
                                           u.rol_id as rol, r.nombre as rol_nombre
                                    FROM usuarios u
                                    INNER JOIN roles r ON u.rol_id = r.id
                                    WHERE u.rol_id = $rol");
        } else {
            $result = $conn->query("SELECT u.id, u.nombre, u.email as correo, u.genero, 
                                           u.tipo_documento, u.numero_documento, 
                                           u.rol_id as rol, r.nombre as rol_nombre
                                    FROM usuarios u
                                    INNER JOIN roles r ON u.rol_id = r.id");
        }

        if (!$result) {
            echo json_encode(["status" => "ERROR", "message" => $conn->error]);
            exit();
        }

        $usuarios = [];
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
        echo json_encode($usuarios);
        break;

    case 'POST': // 🔹 Crear usuario
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['nombre'], $data['correo'], $data['rol'])) {
            echo json_encode(["status" => "ERROR", "message" => "Datos incompletos"]);
            exit();
        }

        $nombre   = $data['nombre'];
        $correo   = $data['correo'];
        $genero   = $data['genero'] ?? null;
        $tipo_doc = $data['tipo_documento'] ?? null;
        $num_doc  = $data['numero_documento'] ?? null;
        $rol      = $data['rol'];
        $password = password_hash("123456", PASSWORD_BCRYPT); // por defecto

        // Verificar si el correo o documento ya existe
        $check = $conn->prepare("SELECT id FROM usuarios WHERE email=? OR numero_documento=?");
        $check->bind_param("ss", $correo, $num_doc);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            echo json_encode(["status" => "ERROR", "message" => "El correo o documento ya está registrado"]);
            exit();
        }
        $check->close();

        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password_hash, genero, tipo_documento, numero_documento, rol_id, estado) 
                                VALUES (?,?,?,?,?,?,?,1)");
        $stmt->bind_param("ssssssi", $nombre, $correo, $password, $genero, $tipo_doc, $num_doc, $rol);

        if ($stmt->execute()) {
            echo json_encode(["status" => "OK", "message" => "Usuario creado", "id" => $stmt->insert_id]);
        } else {
            echo json_encode(["status" => "ERROR", "message" => $stmt->error]);
        }
        break;

    case 'PUT': // 🔹 Editar usuario
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['id'])) {
            echo json_encode(["status" => "ERROR", "message" => "ID no proporcionado"]);
            exit();
        }

        // 🔸 Reset de contraseña
        if (isset($data['resetPassword']) && $data['resetPassword'] === true) {
            $id = intval($data['id']);
            $newPass = password_hash("123456", PASSWORD_BCRYPT);

            $stmt = $conn->prepare("UPDATE usuarios SET password_hash=? WHERE id=?");
            $stmt->bind_param("si", $newPass, $id);

            if ($stmt->execute()) {
                echo json_encode(["status" => "OK", "message" => "Contraseña reiniciada a 123456"]);
            } else {
                echo json_encode(["status" => "ERROR", "message" => $stmt->error]);
            }
            return; // 👈 importante
        }

        // 🔸 Actualización normal
        $id       = intval($data['id']);
        $nombre   = $data['nombre'];
        $correo   = $data['correo'];
        $genero   = $data['genero'];
        $tipo_doc = $data['tipo_documento'];
        $num_doc  = $data['numero_documento'];
        $rol      = $data['rol'];

        // Validar duplicados (excluyendo el mismo ID)
        $check = $conn->prepare("SELECT id FROM usuarios WHERE (email=? OR numero_documento=?) AND id<>?");
        $check->bind_param("ssi", $correo, $num_doc, $id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            echo json_encode(["status" => "ERROR", "message" => "El correo o documento ya está en uso por otro usuario"]);
            exit();
        }
        $check->close();

        $stmt = $conn->prepare("UPDATE usuarios 
                                SET nombre=?, email=?, genero=?, tipo_documento=?, numero_documento=?, rol_id=? 
                                WHERE id=?");
        $stmt->bind_param("ssssssi", $nombre, $correo, $genero, $tipo_doc, $num_doc, $rol, $id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "OK", "message" => "Usuario actualizado"]);
        } else {
            echo json_encode(["status" => "ERROR", "message" => $stmt->error]);
        }
        break;

    case 'DELETE': // 🔹 Eliminar usuario
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'] ?? null;

        if (!$id) {
            echo json_encode(["status" => "ERROR", "message" => "ID no proporcionado"]);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "OK", "message" => "Usuario eliminado"]);
        } else {
            echo json_encode(["status" => "ERROR", "message" => $stmt->error]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "ERROR", "message" => "Método no permitido"]);
}
