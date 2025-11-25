<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config.php';

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? $_GET['id'] : die();

try {
    $query = "SELECT 
                p.*,
                c.nombre as categoria,
                AVG(r.calificacion) as calificacion_promedio,
                COUNT(r.id_resena) as total_resenas
              FROM productos p
              LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
              LEFT JOIN resenas r ON p.id_producto = r.id_producto AND r.aprobada = 1
              WHERE p.id_producto = :id AND p.activo = 1
              GROUP BY p.id_producto";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $id);
    $stmt->execute();
    
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($producto) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "data" => $producto
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Producto no encontrado"
        ]);
    }
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>