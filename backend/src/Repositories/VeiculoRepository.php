<?php
namespace Sismil\Repositories;

use Sismil\Core\Database;
use PDO;

class VeiculoRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getByMilitarId(int $militarId): array {
        $stmt = $this->db->prepare("SELECT * FROM tb_veiculos WHERE militar_id = ? ORDER BY id DESC");
        $stmt->execute([$militarId]);
        return $stmt->fetchAll();
    }

    public function getEstatistica(string $tipo): int {
        switch ($tipo) {
            case 'total':
                return (int)$this->db->query("SELECT COUNT(*) FROM tb_veiculos")->fetchColumn();
            case 'pendentes':
                return (int)$this->db->query("SELECT COUNT(*) FROM tb_veiculos WHERE homologado = 0")->fetchColumn();
            case 'homologados':
                return (int)$this->db->query("SELECT COUNT(*) FROM tb_veiculos WHERE homologado = 1")->fetchColumn();
            default:
                return 0;
        }
    }

    public function getPendentes(int $limit = 20): array {
        $sql = "SELECT v.id, v.placa, v.modelo, v.cor, v.observacao_s2,
                       m.posto_grad, m.nome_guerra
                FROM tb_veiculos v
                JOIN tb_militares m ON v.militar_id = m.id
                WHERE v.homologado = 0
                ORDER BY v.id DESC LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findByIdWithMilitar(int $veiculoId) {
        $sql = "SELECT v.*, m.posto_grad, m.nome_guerra, m.numero, m.celular_princ, m.subunidade 
                FROM tb_veiculos v 
                JOIN tb_militares m ON v.militar_id = m.id 
                WHERE v.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$veiculoId]);
        return $stmt->fetch();
    }

    public function getAllWithMilitar(): array {
        $sql = "SELECT v.*, m.posto_grad, m.nome_guerra, m.numero, m.subunidade, m.celular_princ 
                FROM tb_veiculos v
                JOIN tb_militares m ON v.militar_id = m.id
                ORDER BY m.posto_grad DESC, m.nome_guerra ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    private function getExistingColumns(): array {
        static $cols = null;
        if ($cols === null) {
            try {
                $stmt = $this->db->query("SHOW COLUMNS FROM tb_veiculos");
                $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (\Exception $e) {
                $cols = ['id', 'militar_id', 'tipo_veiculo', 'marca', 'modelo', 'cor', 'placa', 'emissao_crlv', 'validade_crlv', 'pdf_veiculo', 'homologado', 'observacao_s2'];
            }
        }
        return $cols;
    }

    public function findById(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM tb_veiculos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function insert(array $dados): int {
        $existingCols = $this->getExistingColumns();
        $fields = [];
        $placeholders = [];
        $params = [];

        foreach ($dados as $key => $val) {
            $colName = ltrim($key, ':');
            if (in_array($colName, $existingCols, true)) {
                $fields[] = $colName;
                $placeholders[] = $key;
                $params[$key] = $val;
            }
        }

        $sql = "INSERT INTO tb_veiculos (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $this->db->prepare($sql)->execute($params);
        return (int)$this->db->lastInsertId();
    }

    public function update(array $dados): void {
        $existingCols = $this->getExistingColumns();
        $setClauses = [];
        $params = [':id' => $dados[':id']];

        foreach ($dados as $key => $val) {
            if ($key === ':id' || $key === ':militar_id') continue; // militar_id não muda
            $colName = ltrim($key, ':');
            if (in_array($colName, $existingCols, true)) {
                $setClauses[] = "$colName = $key";
                $params[$key] = $val;
            }
        }

        if (empty($setClauses)) return;

        $sql = "UPDATE tb_veiculos SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $this->db->prepare($sql)->execute($params);
    }

    public function updateHomologacao(int $id, int $homologado, string $obs): void {
        $stmt = $this->db->prepare("UPDATE tb_veiculos SET homologado = ?, observacao_s2 = ? WHERE id = ?");
        $stmt->execute([$homologado, $obs, $id]);
    }

    public function delete(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM tb_veiculos WHERE id = ?");
        $stmt->execute([$id]);
    }
}
