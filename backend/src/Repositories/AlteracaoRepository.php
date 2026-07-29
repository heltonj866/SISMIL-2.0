<?php
namespace Sismil\Repositories;

use Sismil\Core\Database;
use PDO;

class AlteracaoRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getByMilitarId(int $militarId): array {
        $stmt = $this->db->prepare("SELECT * FROM tb_alteracoes WHERE militar_id = ? ORDER BY data_fato DESC, id DESC");
        $stmt->execute([$militarId]);
        return $stmt->fetchAll();
    }

    public function save(array $data): void {
        $id = (int)($data['id'] ?? 0);
        $militarId   = (int)($data['militar_id'] ?? 0);
        $tipo        = $data['tipo_alteracao'] ?? '';
        $descricao   = $data['descricao'] ?? '';
        $dataFato    = $data['data_fato'] ?? date('Y-m-d');
        $operadorId  = $_SESSION['usuario_id'] ?? null;
        $operadorNome= $_SESSION['usuario_nome'] ?? null;

        if ($id > 0) {
            $stmt = $this->db->prepare("UPDATE tb_alteracoes SET tipo_alteracao=?, descricao=?, data_fato=? WHERE id=?");
            $stmt->execute([$tipo, $descricao, $dataFato, $id]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO tb_alteracoes (militar_id, tipo_alteracao, descricao, data_fato, operador_id, operador_nome) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$militarId, $tipo, $descricao, $dataFato, $operadorId, $operadorNome]);
        }
    }

    public function delete(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM tb_alteracoes WHERE id = ?");
        $stmt->execute([$id]);
    }
}
