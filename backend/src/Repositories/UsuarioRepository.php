<?php
namespace Sismil\Repositories;

use Sismil\Core\Database;
use PDO;

/**
 * Camada de Persistência (Repository) para a entidade Usuário.
 */
class UsuarioRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Lista todos os usuários do sistema.
     * Não retorna o hash da senha por segurança.
     *
     * @return array
     */
    public function findAllSecure(): array {
        // Segurança: omitimos o campo 'senha_hash' da listagem base
        $stmt = $this->db->query("SELECT id, identidade, role, ativo, subunidade, nome, posto_grad FROM tb_usuarios ORDER BY id DESC");
        return $stmt->fetchAll();
    }
}
