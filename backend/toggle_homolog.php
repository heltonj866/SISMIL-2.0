<?php
/**
 * ARQUIVO: backend/toggle_homolog.php
 * Endpoint para a S2 inverter a homologação do perfil de um militar (Toggle rápido).
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
    $input = json_decode(file_get_contents("php://input"), true);
    
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id) { 
        Response::error('ID inválido.', 400);
    }

    $sql = "UPDATE tb_militares SET homologado = IF(homologado = 1, 0, 1) WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$id])) {
        AuditLogger::log('TOGGLE_HOMOLOG_MILITAR', "Homologação do perfil do Militar ID {$id} foi invertida via toggle.");
        Response::json(null, 'Status atualizado com sucesso.');
    } else {
        throw new \Exception("Erro ao atualizar o banco de dados.");
    }
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao alterar homologacao de militar: ' . $e->getMessage());
    Response::error('Erro ao alterar homologação do militar.', 500);
}