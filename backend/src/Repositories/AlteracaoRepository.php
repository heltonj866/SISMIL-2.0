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

    public function save(array $data, ?string $arquivoPath = null): void {
        $id = (int)($data['s1_id'] ?? $data['id'] ?? 0);
        $militarId   = (int)($data['s1_militar_id'] ?? 0);
        $categoria   = $data['s1_cat'] ?? '';
        $tipoDetalhe = $data['s1_tipo'] ?? '';
        $descricao   = $data['s1_desc'] ?? '';
        $dataFato    = $data['s1_data'] ?? date('Y-m-d');
        $documentoRef = $data['s1_doc'] ?? '';
        $qtdDias     = (int)($data['s1_dias'] ?? 0);
        $registradoPor = $_SESSION['usuario_nome'] ?? 'Sistema';

        if ($id > 0) {
            // Se houver arquivo novo, atualiza. Se não, mantém o anterior.
            if ($arquivoPath) {
                $stmt = $this->db->prepare("UPDATE tb_alteracoes SET categoria=?, tipo_detalhe=?, descricao=?, data_fato=?, documento_ref=?, qtd_dias=?, arquivo_path=? WHERE id=?");
                $stmt->execute([$categoria, $tipoDetalhe, $descricao, $dataFato, $documentoRef, $qtdDias, $arquivoPath, $id]);
            } else {
                $stmt = $this->db->prepare("UPDATE tb_alteracoes SET categoria=?, tipo_detalhe=?, descricao=?, data_fato=?, documento_ref=?, qtd_dias=? WHERE id=?");
                $stmt->execute([$categoria, $tipoDetalhe, $descricao, $dataFato, $documentoRef, $qtdDias, $id]);
            }
        } else {
            $stmt = $this->db->prepare("INSERT INTO tb_alteracoes (militar_id, categoria, tipo_detalhe, descricao, data_fato, documento_ref, qtd_dias, arquivo_path, registrado_por) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$militarId, $categoria, $tipoDetalhe, $descricao, $dataFato, $documentoRef, $qtdDias, $arquivoPath, $registradoPor]);
        }
    }

    public function delete(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM tb_alteracoes WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function registrar(int $militarId, ?int $operadorId, string $dataFato, string $secao, string $tipoAlteracao, string $descricao): void {
        $operadorNome = $_SESSION['usuario_nome'] ?? null;
        try {
            $stmt = $this->db->prepare("INSERT INTO tb_alteracoes (militar_id, tipo_alteracao, descricao, data_fato, operador_id, operador_nome) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$militarId, $tipoAlteracao, $descricao, $dataFato, $operadorId, $operadorNome]);
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao registrar histórico de alteração: " . $e->getMessage());
        }
    }
}
