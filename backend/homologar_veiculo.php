<?php
/**
 * ARQUIVO: backend/homologar_veiculo.php (Nota: afeta a tabela de militares)
 * Endpoint para a S2 homologar o cadastro completo do militar.
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
require_login(['admin', 's2']);
validate_csrf();

try {
    $pdo = Database::getInstance();
    $id = filter_var($_POST['id_militar'] ?? null, FILTER_VALIDATE_INT);
    $homologado = filter_var($_POST['homologado'] ?? null, FILTER_VALIDATE_INT);
    
    if (!$id) {
        Response::error('ID do militar inválido.', 400);
    }
    
    $sql = "UPDATE tb_militares SET homologado = :homologado WHERE id = :id";
    
    if ($pdo->prepare($sql)->execute([':homologado' => $homologado, ':id' => $id])) {
        $statusStr = $homologado === 1 ? 'HOMOLOGADO' : 'NÃO HOMOLOGADO';
        AuditLogger::log('HOMOLOG_MILITAR', "Avaliação da S2: Cadastro do Militar ID {$id} alterado para {$statusStr}.");
        
        Response::json(null, 'Homologação do militar atualizada com sucesso.');
    } else {
        throw new \Exception("Falha ao atualizar o banco de dados.");
    }
} catch (\Exception $e) { 
    error_log('[SISMIL] Erro ao homologar militar: ' . $e->getMessage());
    Response::error('Erro interno ao tentar homologar.', 500);
}
