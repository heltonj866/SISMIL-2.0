<?php
// ARQUIVO: backend/save_alteracao.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(['admin', 'sargenteacao']);
validate_csrf();
require 'db_connect.php'; 

// Recebe os dados
$id_militar = $_POST['s1_militar_id'] ?? '';
$categoria = $_POST['s1_cat'] ?? '';
$tipo = $_POST['s1_tipo'] ?? '';
$data = $_POST['s1_data'] ?? '';
$desc = $_POST['s1_desc'] ?? '';
$doc = $_POST['s1_doc'] ?? '';
$dias = $_POST['s1_dias'] ?? 0;

$autor = $_SESSION['usuario_idt'] ?? 'Sistema';

if(empty($id_militar) || empty($categoria)) {
    echo json_encode(['status' => 'erro', 'msg' => 'Dados obrigatórios faltando.']); 
    exit;
}

$arquivo_path = null;
if (isset($_FILES['s1_file']) && $_FILES['s1_file']['error'] == 0) {
    $ext = strtolower(pathinfo($_FILES['s1_file']['name'], PATHINFO_EXTENSION));
    $mime_real = mime_content_type($_FILES['s1_file']['tmp_name']);
    
    $extensoes_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
    $mimes_permitidos = ['application/pdf', 'image/jpeg', 'image/png'];

    if (!in_array($ext, $extensoes_permitidas) || !in_array($mime_real, $mimes_permitidos)) {
        echo json_encode(['status' => 'erro', 'msg' => 'Formato de arquivo inválido. Use PDF, JPG ou PNG.']);
        exit;
    }

    // Nome seguro com bytes aleatórios
    $novoNome = "doc_" . $id_militar . "_" . bin2hex(random_bytes(6)) . "." . $ext;
    $diretorioDestino = "../uploads/docs";
    
    if (!is_dir($diretorioDestino)) {
        mkdir($diretorioDestino, 0755, true);
    }
    
    if (move_uploaded_file($_FILES['s1_file']['tmp_name'], $diretorioDestino . "/" . $novoNome)) {
        $arquivo_path = $novoNome;
    } else {
        echo json_encode(['status' => 'erro', 'msg' => 'Falha ao fazer upload do arquivo.']);
        exit;
    }
}

try {
    $sql = "INSERT INTO tb_alteracoes (militar_id, categoria, tipo_detalhe, data_fato, descricao, documento_ref, qtd_dias, arquivo_path, registrado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_militar, $categoria, $tipo, $data, $desc, $doc, $dias, $arquivo_path, $autor]);
    
    echo json_encode(['status' => 'sucesso']);
} catch (PDOException $e) {
    send_error("Erro ao salvar alteração.", $e->getMessage());
}
?>