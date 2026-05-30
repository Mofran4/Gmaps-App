<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

$db_file = __DIR__ . '/../database.sqlite';
$db = new PDO("sqlite:$db_file");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// GET: fetch records
if ($method === 'GET' && $action === 'list') {
    $cat = $_GET['category'] ?? 'all';
    $bounds = $_GET['bounds'] ?? null; // sw_lat,sw_lng,ne_lat,ne_lng

    $where = [];
    $params = [];

    if ($cat !== 'all') {
        $where[] = "category = ?";
        $params[] = $cat;
    }

    if ($bounds) {
        [$sw_lat, $sw_lng, $ne_lat, $ne_lng] = explode(',', $bounds);
        $where[] = "latitude BETWEEN ? AND ?";
        $params[] = $sw_lat;
        $params[] = $ne_lat;
        $where[] = "longitude BETWEEN ? AND ?";
        $params[] = $sw_lng;
        $params[] = $ne_lng;
    }

    $sql = "SELECT * FROM records";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    respond(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// GET: categories list
if ($method === 'GET' && $action === 'categories') {
    $stmt = $db->query("SELECT DISTINCT category, COUNT(*) as total FROM records GROUP BY category ORDER BY total DESC");
    respond(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// POST: create record
if ($method === 'POST' && $action === 'create') {
    $required = ['title', 'address', 'detail', 'category', 'latitude', 'longitude'];
    foreach ($required as $f) {
        if (empty($input[$f])) respond(['success' => false, 'message' => "Field '$f' wajib diisi."], 400);
    }
    $stmt = $db->prepare("INSERT INTO records (title, address, detail, category, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$input['title'], $input['address'], $input['detail'], $input['category'], $input['latitude'], $input['longitude']]);
    $id = $db->lastInsertId();
    $new = $db->query("SELECT * FROM records WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    respond(['success' => true, 'message' => 'Record berhasil ditambahkan.', 'data' => $new]);
}

// PUT: update record
if ($method === 'POST' && $action === 'update') {
    $id = intval($input['id'] ?? 0);
    if (!$id) respond(['success' => false, 'message' => 'ID tidak valid.'], 400);
    $stmt = $db->prepare("UPDATE records SET title=?, address=?, detail=?, category=?, latitude=?, longitude=? WHERE id=?");
    $stmt->execute([$input['title'], $input['address'], $input['detail'], $input['category'], $input['latitude'], $input['longitude'], $id]);
    $updated = $db->query("SELECT * FROM records WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    respond(['success' => true, 'message' => 'Record berhasil diperbarui.', 'data' => $updated]);
}

// DELETE: remove record
if ($method === 'POST' && $action === 'delete') {
    $id = intval($input['id'] ?? 0);
    if (!$id) respond(['success' => false, 'message' => 'ID tidak valid.'], 400);
    $db->exec("DELETE FROM records WHERE id = $id");
    respond(['success' => true, 'message' => 'Record berhasil dihapus.']);
}

respond(['success' => false, 'message' => 'Endpoint tidak ditemukan.'], 404);
