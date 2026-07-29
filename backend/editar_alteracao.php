<?php
/**
 * ARQUIVO: backend/editar_alteracao.php
 * Endpoint para alterar dados de um registro disciplinar/recompensa.
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
    
    $id    = filter_var($_POST['s1_edit_id'] ?? 0, FILTER_VALIDATE_INT);
    $cat   = trim($_POST['s1_cat'] ?? '');
    $tipo  = trim($_POST['s1_tipo'] ?? '');
    $data  = trim($_POST['s1_data'] ?? '');
    $desc  = trim($_POST['s1_desc'] ?? '');
    $doc   = trim($_POST['s1_doc'] ?? '');
    $dias  = filter_var($_POST['s1_dias'] ?? 0, FILTER_VALIDATE_INT) ?: 0;

    if (!$id) {
        Response::error('ID inválido para edição.', 400);
    }

    $sql = "UPDATE tb_alteracoes SET categoria=?, tipo_detalhe=?, data_fato=?, descricao=?, documento_ref=?, qtd_dias=? WHERE id=?";
    $params = [$cat, $tipo, $data, $desc, $doc, $dias, $id];
    
    if (isset($_FILES['s1_file']) && $_FILES['s1_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['s1_file']['name'], PATHINFO_EXTENSION));
        $mime_real = mime_content_type($_FILES['s1_file']['tmp_name']);
        
        $extensoes_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
        $mimes_permitidos = ['application/pdf', 'image/jpeg', 'image/png'];

        if (!in_array($ext, $extensoes_permitidas, true) || !in_array($mime_real, $mimes_permitidos, true)) {
            Response::error('Formato de arquivo inválido. Use PDF, JPG ou PNG.', 400);
        }

        $novoNome = "doc_" . $id . "_" . bin2hex(random_bytes(6)) . "." . $ext;
        $diretorioDestino = __DIR__ . "/../uploads/docs";
        
        if (!is_dir($diretorioDestino)) {
            mkdir($diretorioDestino, 0755, true);
        }
        
        if (move_uploaded_file($_FILES['s1_file']['tmp_name'], $diretorioDestino . "/" . $novoNome)) {
            $sql = "UPDATE tb_alteracoes SET categoria=?, tipo_detalhe=?, data_fato=?, descricao=?, documento_ref=?, qtd_dias=?, arquivo_path=? WHERE id=?";
            $params = [$cat, $tipo, $data, $desc, $doc, $dias, $novoNome, $id];
        } else {
            Response::error('Falha ao mover o arquivo enviado.', 500);
        }
    }

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        AuditLogger::log('UPDATE_ALTERACAO', "Sargenteação: Registro histórico ID {$id} foi alterado. Categoria: {$cat}.");
        Response::json(null, 'Edição salva com sucesso!');
    } else {
        throw new \Exception("Erro ao atualizar o banco de dados.");
    }
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao editar alteração: ' . $e->getMessage());
    Response::error('Erro ao salvar edição da alteração.', 500);
}