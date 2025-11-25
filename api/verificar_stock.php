<?php
// =====================================================================
// API/VERIFICAR_STOCK.PHP - Verificar disponibilidad de productos
// =====================================================================
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include_once 'config.php';  // CORREGIDO

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error de conexión a la base de datos"
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->productos)) {
    try {
        $disponibilidad = [];
        
        foreach ($data->productos as $item) {
            $query = "SELECT id_producto, nombre, stock, precio FROM productos WHERE id_producto = :id AND activo = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $item->id);
            $stmt->execute();
            
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($producto) {
                $disponibilidad[] = [
                    'id' => $item->id,
                    'nombre' => $producto['nombre'],
                    'precio' => $producto['precio'],
                    'cantidad_solicitada' => $item->cantidad,
                    'stock_disponible' => $producto['stock'],
                    'disponible' => ($producto['stock'] >= $item->cantidad)
                ];
            } else {
                $disponibilidad[] = [
                    'id' => $item->id,
                    'nombre' => 'Producto no encontrado',
                    'disponible' => false
                ];
            }
        }
        
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "data" => $disponibilidad
        ]);
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Error al verificar stock: " . $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Se requiere una lista de productos"
    ]);
}
?>