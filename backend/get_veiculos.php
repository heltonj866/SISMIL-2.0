<?php
/**
 * ARQUIVO: backend/get_veiculos.php
 * Controller de leitura dos veículos de um militar.
 */

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/src/Core/Response.php';

use Sismil\Core\Response;
use Sismil\Repositories\VeiculoRepository;

session_start();
apply_cors();
require_login(); 

$militar_id = filter_var($_GET['militar_id'] ?? null, FILTER_VALIDATE_INT);

if (!$militar_id) {
    Response::error('Militar ID não informado ou inválido.', 400);
}

try {
    $repo = new VeiculoRepository();
    $veiculos = $repo->getByMilitarId($militar_id);

    // Regra de Privacidade (S2)
    if ($_SESSION['usuario_role'] === 'user') {
        foreach ($veiculos as &$v) {
            unset($v['observacao_s2']);
        }
        unset($v);
    }

    Response::json(['dados' => $veiculos]);
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao carregar veiculos: ' . $e->getMessage());
    Response::error('Erro ao carregar veículos.', 500);
}