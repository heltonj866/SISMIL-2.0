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
        $sql = "
            SELECT 
                u.id, 
                u.identidade, 
                u.role, 
                u.ativo, 
                u.militar_id,
                CASE WHEN u.senha_hash IS NOT NULL AND u.senha_hash != '' THEN 1 ELSE 0 END as possui_senha,
                COALESCE(m.subunidade, u.subunidade) as subunidade, 
                COALESCE(m.nome_guerra, u.nome) as nome, 
                COALESCE(m.posto_grad, u.posto_grad) as posto_grad
            FROM tb_usuarios u
            LEFT JOIN tb_militares m ON u.militar_id = m.id
            ORDER BY u.id DESC
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
