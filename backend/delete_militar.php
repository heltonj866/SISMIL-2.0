<?php
// ARQUIVO: backend/delete_militar.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
require_login(['admin']);
validate_csrf();
require 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$id    = $input['id'] ?? '';

if (empty($id)) {
    send_error('ID não informado.');
}

try {
    // Recuperar a foto para apagar do disco
    $stmt = $pdo->prepare("SELECT foto_path FROM tb_militares WHERE id = ?");
    $stmt->execute([$id]);
    $militar = $stmt->fetch(PDO::FETCH_ASSOC);

    // Apagar o registro
    $stmtDel = $pdo->prepare("DELETE FROM tb_militares WHERE id = ?");
    if ($stmtDel->execute([$id])) {
        // Apagar foto do disco se existir
        if ($militar && !empty($militar['foto_path'])) {
            $arquivo = __DIR__ . '/../uploads/' . basename($militar['foto_path']);
            if (file_exists($arquivo)) unlink($arquivo);
        }
        echo json_encode(['status' => 'sucesso', 'msg' => 'Excluído com sucesso.']);
    } else {
        throw new Exception("Erro ao excluir do banco.");
    }
} catch (Exception $e) {
    send_error('Erro ao excluir militar.', 'delete_militar.php: ' . $e->getMessage());
}
?>