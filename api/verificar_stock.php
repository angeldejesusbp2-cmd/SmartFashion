// =============================================================================
// api/verificar_stock.php - Verificar disponibilidad de stock
// =============================================================================
<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../config.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->productos)) {
    try {
        $disponibilidad = [];
        
        foreach ($data->productos as $item) {
            $query = "SELECT id_producto, nombre, stock FROM productos WHERE id_producto = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $item->id);
            $stmt->execute();
            
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $disponibilidad[] = [
                'id' => $item->id,
                'nombre' => $producto['nombre'],
                'cantidad_solicitada' => $item->cantidad,
                'stock_disponible' => $producto['stock'],
                'disponible' => ($producto['stock'] >= $item->cantidad)
            ];
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
            "message" => "Error: " . $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Datos incompletos"
    ]);
}
?>