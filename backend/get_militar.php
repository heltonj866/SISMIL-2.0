<?php
/**
 * ARQUIVO: backend/get_militar.php
 * Controller de leitura de um militar específico.
 */

require_once __DIR__ . '/src/Core/Response.php';
require_once __DIR__ . '/src/Repositories/MilitarRepository.php';
require_once __DIR__ . '/security.php';

use Sismil\Core\Response;
use Sismil\Repositories\MilitarRepository;

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
apply_cors();
require_login(); 

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    Response::error('ID inválido ou não informado.', 400);
}

try {
    $repo = new MilitarRepository();
    $dados = $repo->findById($id);

    if ($dados) {
        // Converte NULL em VAZIO para não quebrar componentes Frontend
        foreach ($dados as $key => $value) {
            if (is_null($value)) {
                $dados[$key] = "";
            }
        }
        
        // Regra de Negócio: Usuários sem privilégios não veem dados sensíveis (Privacidade)
        if (isset($_SESSION['usuario_role']) && $_SESSION['usuario_role'] === 'user') {
            unset($dados['cpf'], $dados['nome_pai'], $dados['nome_mae'], $dados['celular_sec'], $dados['tel_emergencia']);
        }
        
        Response::json(['dados' => $dados]);
    } else {
        Response::error('Militar não encontrado no banco de dados.', 404);
    }
} catch (\Exception $e) {
    error_log('[SISMIL] Erro no get_militar: ' . $e->getMessage());
    Response::error('Erro ao buscar dados do militar.', 500);
}