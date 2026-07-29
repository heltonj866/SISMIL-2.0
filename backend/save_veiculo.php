<?php
// ARQUIVO: backend/save_veiculo.php
require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(['admin', 's2', 'sargenteacao']); 

require_once __DIR__ . '/src/Core/Response.php';
require_once __DIR__ . '/src/Services/UploadService.php';
require_once __DIR__ . '/src/Repositories/VeiculoRepository.php';
require_once __DIR__ . '/src/Repositories/AlteracaoRepository.php';
require_once __DIR__ . '/src/Services/VeiculoService.php';

use Sismil\Core\Response;
use Sismil\Services\VeiculoService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::json(405, 'Método não permitido');
}

validate_csrf();

try {
    $service = new VeiculoService();
    $service->salvarVeiculo($_POST);
    
    Response::json(200, "Veículo salvo com sucesso");
} catch (\Exception $e) {
    error_log("[SISMIL] Erro no save_veiculo: " . $e->getMessage());
    Response::json(500, "Erro ao salvar veículo: " . $e->getMessage());
}