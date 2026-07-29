<?php
/**
 * ARQUIVO: backend/excluir_veiculo.php
 * Endpoint para exclusão de um veículo da frota.
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
    $data = json_decode(file_get_contents("php://input"), true);
    
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    
    if (!$id) {
        Response::error('ID do veículo inválido ou não informado.', 400);
    }
    
    $stmtSelect = $pdo->prepare("SELECT placa FROM tb_veiculos WHERE id = ?");
    $stmtSelect->execute([$id]);
    $veiculo = $stmtSelect->fetch(PDO::FETCH_ASSOC);

    if (!$veiculo) {
        Response::error('Veículo não encontrado.', 404);
    }

    $stmt = $pdo->prepare("DELETE FROM tb_veiculos WHERE id = ?");
    if ($stmt->execute([$id])) {
        AuditLogger::log('DELETE_VEICULO', "Veículo excluído do sistema. Placa: {$veiculo['placa']} (ID: {$id})");
        Response::json(null, 'Excluído com sucesso.');
    } else {
        throw new \Exception("Falha na exclusão.");
    }
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao excluir veículo: ' . $e->getMessage());
    Response::error('Erro ao tentar excluir veículo.', 500);
}