<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  exit;
}

require_once(realpath($_SERVER["DOCUMENT_ROOT"]) . '/outlet/backend/config/database.php');
require_once(realpath($_SERVER["DOCUMENT_ROOT"]) . '/outlet/backend/config/auth.php'); 

$database = new Database();
$db = $database->getConnection();

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}


$username = $_SESSION['username'] ?? ($_SERVER['AUTH_USER'] ?? 'guest');

try {
  $db = (new Database())->getConnection();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents("php://input"), true);
    $items = $payload['items'] ?? [];

    if (!$items || !is_array($items)) {
      http_response_code(400);
      echo json_encode(["error" => "Brak produktów w zamówieniu"]);
      exit;
    }

    // 1) pobierz aktywny drop
    $dropId = (int) $db->query("SELECT id FROM drops WHERE active = 3 LIMIT 1")->fetchColumn();
    if (!$dropId) {
      http_response_code(400);
      echo json_encode(["error" => "Brak aktywnego dropu"]);
      exit;
    }

    // 2) policz ile user już kupił w tym dropie
    $stmt = $db->prepare("
      SELECT COALESCE(SUM(oi.quantity),0) AS total
      FROM orders o
      JOIN order_items oi ON oi.order_id = o.id
      WHERE o.user_login = :u AND o.drop_id = :d
      FOR UPDATE
    ");
    $stmt->execute([':u' => $username, ':d' => $dropId]);
    $already = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $requested = array_reduce($items, fn($s, $it) => $s + (int) $it['quantity'], 0);
    $limit = 2; 

    if ($already + $requested > $limit) {
      http_response_code(400);
      echo json_encode([
        "error" => "Limit 2 szt. na drop przekroczony",
        "limit" => $limit,
        "already" => $already,
        "requested" => $requested,
        "remaining" => max(0, $limit - $already)
      ]);
      exit;
    }

    // 3) transakcja
    $db->beginTransaction();

    // (a) odejmujemy stany atomowo – zawsze w stałej kolejności po id
    usort($items, fn($a, $b) => $a['id'] <=> $b['id']);

    $dec = $db->prepare("UPDATE products SET stock = stock - :q WHERE id = :pid AND stock >= :q");
    $check = $db->prepare("SELECT name, stock FROM products WHERE id = :pid");

    $outOfStock = [];
    foreach ($items as $it) {
      $pid = (int) $it['id'];
      $qty = max(1, (int) $it['quantity']);

      $dec->execute([':q' => $qty, ':pid' => $pid]);

      if ($dec->rowCount() === 0) {
        // nie udało się odjąć — brak stanu
        $check->execute([':pid' => $pid]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        $outOfStock[] = [
          "product_id" => $pid,
          "name" => $row['name'] ?? 'Produkt',
          "available" => isset($row['stock']) ? (int) $row['stock'] : 0,
          "requested" => $qty
        ];
      }
    }

    if (!empty($outOfStock)) {
      $db->rollBack();
      http_response_code(409);
      echo json_encode([
        "error" => "Brak wystarczającego stanu dla części pozycji",
        "items" => $outOfStock
      ]);
      exit;
    }

    // (b) tworzymy zamówienie
    $insOrder = $db->prepare(
      "INSERT INTO orders (user_login, drop_id, created_at, status_id) VALUES (:u, :d, NOW(), :status)");
    $insOrder->execute([':u' => $username, ':d' => $dropId, ':status' => 1]);
    $orderId = (int) $db->lastInsertId();

    // (c) zapis pozycji zamówienia
    $insItem = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (:o, :p, :q)");
    foreach ($items as $it) {
      $insItem->execute([
        ':o' => $orderId,
        ':p' => (int) $it['id'],
        ':q' => (int) $it['quantity']
      ]);
    }

    error_log("Usunięto z koszyka: " . $username);

    try {
      $clearCart = $db->prepare("DELETE FROM `cart` WHERE `user_login` = :u");
      $clearCart->execute([':u' => $username]);

      error_log("Usunięto z koszyka: " . $clearCart->rowCount() . " pozycji dla usera: $username");
    } catch (PDOException $e) {
      error_log("Błąd w usuwaniu koszyka: " . $e->getMessage());
    }


    error_log("Usunięto z koszyka: " . $clearCart->rowCount() . " pozycji");

    $db->commit();

    echo json_encode([
      "success" => true,
      "order_id" => $orderId
    ]);
    exit;
  }

  // GET: lista zamówień
  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
      SELECT o.id AS order_id, o.user_login, o.drop_id, o.created_at,
             p.id AS product_id, p.name, p.price, p.image, oi.quantity,
             s.label AS status
      FROM orders o
      JOIN order_items oi ON oi.order_id = o.id
      JOIN products p ON p.id = oi.product_id
      JOIN order_statuses s ON o.status_id = s.id
      WHERE o.user_login = :u
      ORDER BY o.created_at DESC, o.id DESC
    ");
    $stmt->execute([':u' => $username]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
  }



  http_response_code(405);
  echo json_encode(["error" => "Method not allowed"]);

} catch (Throwable $e) {
  if ($db && $db->inTransaction()) {
    $db->rollBack();
  }
  http_response_code(500);
  echo json_encode(["error" => "Błąd serwera", "details" => $e->getMessage()]);
}

?>