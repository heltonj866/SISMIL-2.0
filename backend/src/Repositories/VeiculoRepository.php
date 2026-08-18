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

    public function findById(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM tb_veiculos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function insert(array $dados): int {
        $sql = "INSERT INTO tb_veiculos (
            militar_id, tipo_veiculo, marca, modelo, cor, proprietario_nome, proprietario_parentesco, placa, emissao_crlv, pdf_veiculo, pdf_comprovante_vinculo
        ) VALUES (
            :militar_id, :tipo_veiculo, :marca, :modelo, :cor, :proprietario_nome, :proprietario_parentesco, :placa, :emissao_crlv, :pdf_veiculo, :pdf_comprovante_vinculo
        )";
        $this->db->prepare($sql)->execute($dados);
        return (int)$this->db->lastInsertId();
    }

    public function update(array $dados): void {
        $setClauses = [];
        $params = [':id' => $dados[':id']];
        foreach ($dados as $key => $val) {
            if ($key === ':id' || $key === ':militar_id') continue; // militar_id não muda
            $colName = ltrim($key, ':');
            $setClauses[] = "$colName = $key";
            $params[$key] = $val;
        }
        
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
