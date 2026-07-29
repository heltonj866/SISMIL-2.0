<?php
/**
 * ARQUIVO: backend/toggle_homolog_veiculo.php
 * Endpoint para a S2 homologar ou rejeitar o acesso de um veículo (Requisito de Segurança Física).
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
    $data = json_decode(file_get_contents("php://input"), true);

    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    $status = filter_var($data['status'] ?? null, FILTER_VALIDATE_INT);
    $obs = $data['observacao'] ?? null;

    if (!$id) {
        Response::error('ID do veículo inválido.', 400);
    }

    $stmt = $pdo->prepare("UPDATE tb_veiculos SET homologado = ?, observacao_s2 = ? WHERE id = ?");
    
    if ($stmt->execute([$status, $obs, $id])) {
        $statusStr = $status === 1 ? 'HOMOLOGADO' : ($status === 2 ? 'REJEITADO' : 'PENDENTE');
        AuditLogger::log('HOMOLOG_VEICULO', "Avaliação da S2: Veículo ID {$id} alterado para {$statusStr}. Observação: {$obs}");
        
        Response::json(null, 'Status de homologação alterado com sucesso.');
    } else {
        throw new \Exception("Erro ao atualizar o banco de dados.");
    }
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao homologar veículo: ' . $e->getMessage());
    Response::error('Erro ao alterar homologação do veículo.', 500);
}