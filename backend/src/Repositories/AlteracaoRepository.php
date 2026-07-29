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
}
