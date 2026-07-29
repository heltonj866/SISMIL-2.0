<?php
// ARQUIVO: backend/desligar_militar.php
require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(['admin', 'sargenteacao']); 

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

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$motivo = $_POST['motivo_desligamento'] ?? 'Não informado';
$data_desligamento = $_POST['data_desligamento'] ?? date('Y-m-d');

try {
    $service = new MilitarService();
    $service->desligarMilitar($id, $motivo, $data_desligamento);
    
    Response::json(200, "Militar desligado com sucesso. Status alterado para inativo.");
} catch (\Exception $e) {
    error_log("[SISMIL] Erro ao desligar militar: " . $e->getMessage());
    Response::json(500, "Erro ao desligar militar: " . $e->getMessage());
}