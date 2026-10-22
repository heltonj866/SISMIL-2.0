<?php
// ARQUIVO: backend/index.php
// FrontController - Único Ponto de Entrada da API (Clean Architecture)

require_once __DIR__ . '/security.php';
session_start();
apply_cors();

// Autoload manual temporário (ou via security.php se já estiver lá)
require_once __DIR__ . '/src/Core/Request.php';
require_once __DIR__ . '/src/Core/Response.php';

require_once __DIR__ . '/src/Core/Router.php';
require_once __DIR__ . '/src/Core/Database.php';

require_once __DIR__ . '/src/Services/AuditLogger.php';
require_once __DIR__ . '/src/Services/SchemaService.php';
require_once __DIR__ . '/src/Services/NotificacaoService.php';
require_once __DIR__ . '/src/Services/UploadService.php';
require_once __DIR__ . '/src/Services/MilitarService.php';
require_once __DIR__ . '/src/Services/VeiculoService.php';
require_once __DIR__ . '/src/Services/ArranchamentoService.php';

require_once __DIR__ . '/src/Repositories/MilitarRepository.php';
require_once __DIR__ . '/src/Repositories/VeiculoRepository.php';
require_once __DIR__ . '/src/Repositories/ArranchamentoRepository.php';
require_once __DIR__ . '/src/Repositories/AlteracaoRepository.php';
require_once __DIR__ . '/src/Repositories/UsuarioRepository.php';

require_once __DIR__ . '/src/Controllers/AuthController.php';
require_once __DIR__ . '/src/Controllers/MilitarController.php';
require_once __DIR__ . '/src/Controllers/VeiculoController.php';
require_once __DIR__ . '/src/Controllers/ArranchamentoController.php';
require_once __DIR__ . '/src/Controllers/DashboardController.php';
require_once __DIR__ . '/src/Controllers/UserController.php';
require_once __DIR__ . '/src/Controllers/AuditController.php';
require_once __DIR__ . '/src/Controllers/NotificacaoController.php';
require_once __DIR__ . '/src/Controllers/S1PainelController.php';
require_once __DIR__ . '/src/Controllers/PortalMilitarController.php';

use Sismil\Core\Request;
use Sismil\Core\Router;
use Sismil\Core\Response;

use Sismil\Controllers\AuthController;
use Sismil\Controllers\MilitarController;
use Sismil\Controllers\VeiculoController;
use Sismil\Controllers\ArranchamentoController;
use Sismil\Controllers\DashboardController;
use Sismil\Controllers\UserController;
use Sismil\Controllers\AuditController;
use Sismil\Controllers\NotificacaoController;
use Sismil\Controllers\S1PainelController;
use Sismil\Controllers\PortalMilitarController;

$request = new Request();
$router = new Router();

// Garante migrações e tabelas essenciais (idempotente)
\Sismil\Services\SchemaService::ensureSchema();

// ==========================================
// REGISTRO DE ROTAS
// ==========================================

// --- AUTH ---
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/primeiro-acesso', [AuthController::class, 'primeiroAcesso']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->get('/api/auth/check', [AuthController::class, 'check']);

// --- DASHBOARD ---
$router->get('/api/dashboard/stats', [DashboardController::class, 'stats']);

// --- MILITAR ---
$router->get('/api/militar/search', [MilitarController::class, 'search']);
$router->get('/api/militar/get', [MilitarController::class, 'getById']);
$router->get('/api/militar/historico', [MilitarController::class, 'getHistorico']);
$router->get('/api/cep', [MilitarController::class, 'getCep']);
$router->post('/api/militar/save', [MilitarController::class, 'save']);
$router->post('/api/militar/desligar', [MilitarController::class, 'desligar']);
$router->post('/api/militar/reativar', [MilitarController::class, 'reativar']);
$router->post('/api/militar/delete', [MilitarController::class, 'delete']);
$router->post('/api/militar/alteracao/save', [MilitarController::class, 'saveAlteracao']);
$router->post('/api/militar/alteracao/delete', [MilitarController::class, 'deleteAlteracao']);

// --- VEÍCULO ---
$router->get('/api/veiculo/list', [VeiculoController::class, 'getByMilitar']);
$router->post('/api/veiculo/save', [VeiculoController::class, 'save']);
$router->post('/api/veiculo/homologar', [VeiculoController::class, 'homologar']);
$router->post('/api/veiculo/delete', [VeiculoController::class, 'delete']);

// --- ARRANCHAMENTO ---
$router->get('/api/arranchamento/list', [ArranchamentoController::class, 'getByData']);
$router->post('/api/arranchamento/save', [ArranchamentoController::class, 'save']);
$router->post('/api/arranchamento/saveExtra', [ArranchamentoController::class, 'saveExtra']);

// --- USER ---
$router->get('/api/user/list', [UserController::class, 'get']);
$router->post('/api/user/create', [UserController::class, 'create']);
$router->post('/api/user/update', [UserController::class, 'update']);
$router->post('/api/user/delete', [UserController::class, 'delete']);

// --- AUDITORIA (somente admin) ---
$router->get('/api/auditoria/list', [AuditController::class, 'list']);
$router->get('/api/auditoria/acoes', [AuditController::class, 'acoes']);

// --- NOTIFICAÇÕES (admin / sargenteação) ---
$router->get('/api/notificacoes/list', [NotificacaoController::class, 'list']);
$router->post('/api/notificacoes/ler', [NotificacaoController::class, 'marcarLida']);
$router->post('/api/notificacoes/efetivar-precadastro', [NotificacaoController::class, 'efetivarPreCadastro']);

// --- PAINEL S1 / PLANILHA GERAL (admin / sargenteação) ---
$router->get('/api/s1/resumo', [S1PainelController::class, 'resumo']);
$router->post('/api/s1/situacao', [S1PainelController::class, 'registrarSituacaoS1']);
$router->post('/api/s1/planilha/sync', [S1PainelController::class, 'sincronizarPlanilha']);

// --- PORTAL INDIVIDUAL DO MILITAR ---
$router->get('/api/portal/me', [PortalMilitarController::class, 'meuPerfil']);
$router->post('/api/portal/dados-basicos', [PortalMilitarController::class, 'salvarDadosBasicos']);
$router->post('/api/portal/apresentacao', [PortalMilitarController::class, 'registrarApresentacao']);

// ==========================================
// DISPATCH
// ==========================================
try {
    $router->dispatch($request);
} catch (\Exception $e) {
    error_log("[SISMIL_ROUTER] Ocorreu uma exceção: " . $e->getMessage());
    Response::error("Erro Interno do Servidor", 500);
}
