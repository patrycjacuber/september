<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); // ewentualnie ogranicz do domeny
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE"); // >>> CHANGED (dodałem PUT)
header("Access-Control-Allow-Headers: Content-Type");

require_once(realpath($_SERVER["DOCUMENT_ROOT"]) .'/outlet/backend/config/database.php');
require_once(realpath($_SERVER["DOCUMENT_ROOT"]) .'/outlet/backend/config/auth.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(["error" => "Błąd połączenia z bazą"]);
    exit;
}

$user = $_SESSION['user'] ?? 'guest'; 

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $stmt = $db->prepare("
            SELECT c.id AS cart_id, p.id, p.name, p.price, p.image, c.quantity
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_login = :user
        ");
        $stmt->execute(['user' => $user]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = array_sum(array_column($items, 'quantity'));

        echo json_encode(
            [
                "items" => $items,
                "total_count" => $total
            ]
        );
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        $productId = $data['product_id'] ?? null;
        $quantity = $data['quantity'] ?? 1;

        if (!$productId) {
            http_response_code(400);
            echo json_encode(["error" => "Brak ID produktu"]);
            exit;
        }

        // sprawdź stock
        $stmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product || $product['stock'] <= 0) {
            http_response_code(400);
            echo json_encode(["error" => "Brak na stanie."]);
            exit;
        }

        // >>> NEW: sprawdź łączny limit (max 2 szt. na usera)
        $stmt = $db->prepare("SELECT COALESCE(SUM(quantity),0) as total FROM cart WHERE user_login = ?");
        $stmt->execute([$user]);
        $currentQty = (int)$stmt->fetchColumn();

        if ($currentQty + $quantity > 2) {
            http_response_code(400);
            echo json_encode([
                "error" => "Limit 2 szt. na użytkownika",
                "already" => $currentQty,
                "requested" => $quantity,
                "remaining" => max(0, 2 - $currentQty)
            ]);
            exit;
        }

        // >>> poprawiony insert/update
        $check = $db->prepare("SELECT id FROM cart WHERE user_login = :user AND product_id = :pid");
        $check->execute(['user' => $user, 'pid' => $productId]);

        if ($check->rowCount() > 0) {
            // zwiększ ilość
            $update = $db->prepare("
                UPDATE cart SET quantity = quantity + :qty
                WHERE user_login = :user AND product_id = :pid
            ");
            $update->execute(['qty' => $quantity, 'user' => $user, 'pid' => $productId]);
        } else {
            // dodaj nowy
            $insert = $db->prepare("
                INSERT INTO cart (user_login, product_id, quantity)
                VALUES (:user, :pid, :qty)
            ");
            $insert->execute(['user' => $user, 'pid' => $productId, 'qty' => $quantity]);
        }

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = array_sum(array_column($items, 'quantity'));

        echo json_encode(["success" => true, "message" => "Dodano do koszyka", "new_total_count" => $total]);
        break;

    case 'PUT': // >>> NEW (aktualizacja ilości)
        $data = json_decode(file_get_contents("php://input"), true);
        $productId = $data['product_id'] ?? null;
        $newQuantity = $data['quantity'] ?? 1;

        if (!$productId) {
            http_response_code(400);
            echo json_encode(["error" => "Brak ID produktu"]);
            exit;
        }

        // sprawdź limit 2 szt.

        $stmt = $db->prepare("SELECT COALESCE(SUM(quantity),0) as total FROM cart WHERE user_login = ?");
        $stmt->execute([$user]);
        $otherQty = (int)$stmt->fetchColumn();

        if ($otherQty + $newQuantity > 2) {
            http_response_code(400);
            echo json_encode(["error" => "Limit 2 szt. na użytkownika"]);
            exit;
        }

        // $update = $db->prepare("UPDATE cart SET quantity = :qty WHERE user_login = :user AND product_id = :pid");
        // $update->execute(['qty' => $newQuantity, 'user' => $user, 'pid' => $productId]);
        $check = $db->prepare("SELECT id FROM cart WHERE user_login = :user AND product_id = :pid");
        $check->execute(['user' => $user, 'pid' => $productId]);

        if ($check->rowCount() > 0) {
            // zwiększ ilość
            $update = $db->prepare("
                UPDATE cart SET quantity = quantity + :qty
                WHERE user_login = :user AND product_id = :pid
            ");
            $update->execute(['qty' => $newQuantity, 'user' => $user, 'pid' => $productId]);
        } else {
            // dodaj nowy
            $insert = $db->prepare("
                INSERT INTO cart (user_login, product_id, quantity)
                VALUES (:user, :pid, :qty)
            ");
            $insert->execute(['user' => $user, 'pid' => $productId, 'qty' => $newQuantity]);
        }

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = array_sum(array_column($items, 'quantity'));

        echo json_encode(["success" => true, "message" => "Zaktualizowano ilość", "new_total_count" => $total]);
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"), true);
        $productId = $data['product_id'] ?? null;

        if (!$productId) {
            http_response_code(400);
            echo json_encode(["error" => "Brak ID produktu do usunięcia"]);
            exit;
        }

        $delete = $db->prepare("
            DELETE FROM cart WHERE user_login = :user AND product_id = :pid
        ");
        $delete->execute(['user' => $user, 'pid' => $productId]);

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = array_sum(array_column($items, 'quantity'));

        echo json_encode(["success" => true, "message" => "Usunięto z koszyka", "new_total_count" => $total]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Metoda niedozwolona"]);
        break;
}

// bin 

        // if ($newQuantity < 1) {
        //     // jeśli 0 lub mniej → usuń z koszyka
        //     // $delete = $db->prepare("DELETE FROM cart WHERE user_login = :user AND product_id = :pid");
        //     // $delete->execute(['user' => $user, 'pid' => $productId]);
        //     // echo json_encode(["success" => true, "message" => "Usunięto produkt"]);
        //     exit;
        // }

                // $stmt = $db->prepare("SELECT COALESCE(SUM(quantity),0) as total FROM cart WHERE user_login = ? AND product_id != ?");
        // $stmt->execute([$user, $productId]);
        // $otherQty = (int)$stmt->fetchColumn();

?>