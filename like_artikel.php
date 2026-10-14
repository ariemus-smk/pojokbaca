<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
$id = 0;
if (is_array($data) && isset($data['id'])) {
    $id = (int)$data['id'];
} elseif (isset($_POST['id'])) {
    $id = (int)$_POST['id'];
}

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID artikel tidak valid.']);
    exit;
}

try {
    // Cek apakah artikel ada
    $stmt = $pdo->prepare("SELECT id, likes FROM artikel WHERE id = ?");
    $stmt->execute([$id]);
    $art = $stmt->fetch();

    if (!$art) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Artikel tidak ditemukan.']);
        exit;
    }

    if (!isset($_SESSION['liked_articles']) || !is_array($_SESSION['liked_articles'])) {
        $_SESSION['liked_articles'] = [];
    }

    $isAlreadyLiked = in_array($id, $_SESSION['liked_articles'], true);
    $action = $data['action'] ?? ($_POST['action'] ?? 'toggle');

    if ($action === 'like' || ($action === 'toggle' && !$isAlreadyLiked)) {
        $stmtUp = $pdo->prepare("UPDATE artikel SET likes = likes + 1 WHERE id = ?");
        $stmtUp->execute([$id]);
        if (!$isAlreadyLiked) {
            $_SESSION['liked_articles'][] = $id;
        }
        $liked = true;
        $msg = 'Terima kasih telah menyukai artikel ini!';
    } else {
        $stmtUp = $pdo->prepare("UPDATE artikel SET likes = GREATEST(0, likes - 1) WHERE id = ?");
        $stmtUp->execute([$id]);
        $_SESSION['liked_articles'] = array_values(array_diff($_SESSION['liked_articles'], [$id]));
        $liked = false;
        $msg = 'Batal menyukai artikel.';
    }

    $stmtLikes = $pdo->prepare("SELECT likes FROM artikel WHERE id = ?");
    $stmtLikes->execute([$id]);
    $currentLikes = (int)$stmtLikes->fetchColumn();

    echo json_encode([
        'success' => true,
        'likes' => $currentLikes,
        'liked' => $liked,
        'message' => $msg
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem saat memproses suka.']);
}
exit;
