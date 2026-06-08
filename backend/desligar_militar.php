<?php
// ARQUIVO: backend/desligar_militar.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
require_login(['admin', 'sargenteacao']);
validate_csrf();
require 'db_connect.php';

$id = $_POST['militar_id'] ?? null;
$arquivo = $_FILES['nada_consta'] ?? null;

// Verifica se o ID chegou e se o ficheiro foi anexado
if (!$id || !$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'erro', 'msg' => 'ID inválido ou ficheiro PDF não anexado.']);
    exit;
}

// Bloqueia tentativas de subir arquivos maliciosos (só aceita PDF com MIME real)
$extensao  = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
$mime_real = mime_content_type($arquivo['tmp_name']);
if ($extensao !== 'pdf' || $mime_real !== 'application/pdf') {
    echo json_encode(['status' => 'erro', 'msg' => 'O arquivo de "Nada Consta" deve ser um PDF válido.']);
    exit;
}

// Nome seguro com bytes aleatórios
$novoNome        = 'nada_consta_' . $id . '_' . bin2hex(random_bytes(6)) . '.pdf';
$caminhoDestino  = __DIR__ . '/../uploads/documentos/' . $novoNome;

// Cria a pasta caso ainda não exista
if (!is_dir(__DIR__ . '/../uploads/documentos/')) {
    mkdir(__DIR__ . '/../uploads/documentos/', 0755, true);
}

// Tenta mover o PDF para a pasta e atualizar a base de dados
if (move_uploaded_file($arquivo['tmp_name'], $caminhoDestino)) {
    try {
        $sql = "UPDATE tb_militares SET status_ativo = 0, pdf_nada_consta = ?, data_desligamento = CURDATE() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$novoNome, $id]);
        
        echo json_encode(['status' => 'sucesso', 'msg' => 'Militar desligado com sucesso. Histórico mantido.']);
    } catch (PDOException $e) {
        send_error('Erro ao atualizar banco de dados.', 'desligar_militar.php: ' . $e->getMessage());
    }
} else {
    echo json_encode(['status' => 'erro', 'msg' => 'Falha ao guardar o ficheiro no servidor.']);
}
?>