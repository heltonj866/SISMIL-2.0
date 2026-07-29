<?php
/**
 * ARQUIVO: backend/get_users.php
 * Controller de leitura da lista de operadores.
 */

require_once __DIR__ . '/src/Core/Response.php';
require_once __DIR__ . '/src/Repositories/UsuarioRepository.php';
require_once __DIR__ . '/security.php';

use Sismil\Core\Response;
use Sismil\Repositories\UsuarioRepository;

session_start();
apply_cors();
require_login(['admin']);

try {
    $repo = new UsuarioRepository();
    $users = $repo->findAllSecure();

    $data = [];
    foreach ($users as $u) {
        $data[] = [
            'id' => $u['id'],
            'identidade' => $u['identidade'],
            'role' => $u['role'],
            'ativo' => $u['ativo'] ?? 1,
            'posto_grad' => $u['posto_grad'] ?? '',
            'nome_guerra' => $u['nome'] ?? 'Usuário',
            'subunidade' => $u['subunidade'] ?? '---'
        ];
    }

    Response::json(['data' => $data]);
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao buscar lista de usuários: ' . $e->getMessage());
    Response::error('Erro interno ao buscar lista de usuários.', 500);
}