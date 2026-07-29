<?php
/**
 * ARQUIVO: backend/save_alteracao.php
 * Endpoint para cadastrar alterações disciplinares, recompensas e dispensas.
 *
 * @package Sismil\Controllers
 */

require_once __DIR__ . '/src/Core/Response.php';
require_once __DIR__ . '/src/Core/Database.php';
require_once __DIR__ . '/src/Services/AuditLogger.php';
require_once __DIR__ . '/security.php';

use Sismil\Core\Database;
use Sismil\Core\Response;
use Sismil\Services\AuditLogger;

session_start();
apply_cors();
require_login(['admin', 'sargenteacao']);
validate_csrf();

try {
    $pdo = Database::getInstance();
    
    $id_militar = filter_var($_POST['s1_militar_id'] ?? '', FILTER_VALIDATE_INT);
    $categoria  = trim($_POST['s1_cat'] ?? '');
    $tipo       = trim($_POST['s1_tipo'] ?? '');
    $data_fato  = trim($_POST['s1_data'] ?? '');
    $desc       = trim($_POST['s1_desc'] ?? '');
    $doc        = trim($_POST['s1_doc'] ?? '');
    $dias       = filter_var($_POST['s1_dias'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    
    $autor      = $_SESSION['usuario_login'] ?? 'Sistema';

    if (empty($id_militar) || empty($categoria)) {
        Response::error('Dados obrigatórios faltando. ID e Categoria são exigidos.', 400);
    }

    $arquivo_path = null;
    if (isset($_FILES['s1_file']) && $_FILES['s1_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['s1_file']['name'], PATHINFO_EXTENSION));
        $mime_real = mime_content_type($_FILES['s1_file']['tmp_name']);
        
        $extensoes_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
        $mimes_permitidos = ['application/pdf', 'image/jpeg', 'image/png'];

        if (!in_array($ext, $extensoes_permitidas, true) || !in_array($mime_real, $mimes_permitidos, true)) {
            Response::error('Formato de arquivo inválido. Use PDF, JPG ou PNG.', 400);
        }

        $novoNome = "doc_" . $id_militar . "_" . bin2hex(random_bytes(6)) . "." . $ext;
        $diretorioDestino = __DIR__ . "/../uploads/docs";
        
        if (!is_dir($diretorioDestino)) {
            mkdir($diretorioDestino, 0755, true);
        }
        
        if (move_uploaded_file($_FILES['s1_file']['tmp_name'], $diretorioDestino . "/" . $novoNome)) {
            $arquivo_path = $novoNome;
        } else {
            Response::error('Falha ao processar o upload do documento comprobatório.', 500);
        }
    }

    $sql = "INSERT INTO tb_alteracoes 
            (militar_id, categoria, tipo_detalhe, data_fato, descricao, documento_ref, qtd_dias, arquivo_path, registrado_por) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$id_militar, $categoria, $tipo, $data_fato, $desc, $doc, $dias, $arquivo_path, $autor])) {
        $insertId = $pdo->lastInsertId();
        AuditLogger::log('CREATE_ALTERACAO', "Sargenteação: Nova alteração (ID {$insertId}, {$categoria}) registrada para o Militar ID {$id_militar}.");
        
        Response::json(null, 'Alteração salva com sucesso!');
    } else {
        throw new \Exception("Falha ao salvar no banco de dados.");
    }
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao salvar alteração: ' . $e->getMessage());
    Response::error('Erro interno ao salvar alteração.', 500);
}