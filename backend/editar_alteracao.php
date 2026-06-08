<?php
// ARQUIVO: backend/editar_alteracao.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(['admin', 'sargenteacao']);
validate_csrf();
require 'db_connect.php';

// Recebe os dados
$id = $_POST['s1_edit_id'] ?? 0; // ID do registro para editar
$cat = $_POST['s1_cat'] ?? '';
$tipo = $_POST['s1_tipo'] ?? '';
$data = $_POST['s1_data'] ?? '';
$desc = $_POST['s1_desc'] ?? '';
$doc = $_POST['s1_doc'] ?? '';
$dias = $_POST['s1_dias'] ?? 0;

try {
    if (!$id) {
        throw new Exception("ID inválido para edição.");
    }

    // Atualiza os dados padrão
    $sql = "UPDATE tb_alteracoes SET categoria=?, tipo_detalhe=?, data_fato=?, descricao=?, documento_ref=?, qtd_dias=? WHERE id=?";
    $params = [$cat, $tipo, $data, $desc, $doc, $dias, $id];
    
    // Se enviou arquivo novo, valida e atualiza o caminho também
    if (isset($_FILES['s1_file']) && $_FILES['s1_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['s1_file']['name'], PATHINFO_EXTENSION));
        $mime_real = mime_content_type($_FILES['s1_file']['tmp_name']);
        
        $extensoes_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
        $mimes_permitidos = ['application/pdf', 'image/jpeg', 'image/png'];

        if (!in_array($ext, $extensoes_permitidas) || !in_array($mime_real, $mimes_permitidos)) {
            throw new Exception("Formato de arquivo inválido. Use PDF, JPG ou PNG.");
        }

        // Nome seguro com bytes aleatórios
        $novoNome = "doc_" . $id . "_" . bin2hex(random_bytes(6)) . "." . $ext;
        $diretorioDestino = "../uploads/docs";
        
        if (!is_dir($diretorioDestino)) {
            mkdir($diretorioDestino, 0755, true);
        }
        
        if (move_uploaded_file($_FILES['s1_file']['tmp_name'], $diretorioDestino . "/" . $novoNome)) {
            $sql = "UPDATE tb_alteracoes SET categoria=?, tipo_detalhe=?, data_fato=?, descricao=?, documento_ref=?, qtd_dias=?, arquivo_path=? WHERE id=?";
            $params = [$cat, $tipo, $data, $desc, $doc, $dias, $novoNome, $id];
        } else {
            throw new Exception("Falha ao mover o arquivo enviado.");
        }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['status' => 'sucesso']);
} catch (Exception $e) {
    send_error("Erro ao salvar edição da alteração.", $e->getMessage());
}
?>