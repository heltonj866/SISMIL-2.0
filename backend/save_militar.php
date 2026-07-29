<?php
// ARQUIVO: backend/save_militar.php
require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(['admin', 'sargenteacao']); // Apenas admin ou sargenteacao podem editar

require_once __DIR__ . '/src/Core/Response.php';
require_once __DIR__ . '/src/Services/UploadService.php';
require_once __DIR__ . '/src/Repositories/MilitarRepository.php';
require_once __DIR__ . '/src/Repositories/AlteracaoRepository.php';
require_once __DIR__ . '/src/Services/MilitarService.php';

use Sismil\Core\Response;
use Sismil\Services\MilitarService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::json(405, 'Método não permitido');
}

validate_csrf();

try {
    $service = new MilitarService();
    $id = $service->salvarMilitar($_POST);
    
    Response::json(200, "Militar salvo com sucesso", ['id' => $id]);
} catch (\Exception $e) {
    error_log("[SISMIL] Erro no save_militar: " . $e->getMessage());
    Response::json(500, "Erro ao salvar militar: " . $e->getMessage());
}