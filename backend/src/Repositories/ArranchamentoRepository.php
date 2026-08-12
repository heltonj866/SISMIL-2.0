<?php
namespace Sismil\Repositories;

use Sismil\Core\Database;
use PDO;

class ArranchamentoRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getByDataAndSubunidade(string $data, string $subunidade = ''): array {
        if (!empty($subunidade)) {
            $stmt = $this->db->prepare("SELECT * FROM tb_arranchamento WHERE data_refeicao = ? AND subunidade = ? ORDER BY id ASC");
            $stmt->execute([$data, $subunidade]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM tb_arranchamento WHERE data_refeicao = ? ORDER BY subunidade ASC, id ASC");
            $stmt->execute([$data]);
        }
        return $stmt->fetchAll();
    }

    public function getRelatorioImpressao(string $data, string $subunidade = ''): array {
        $sqlFilter = "";
        $params = [$data];

        if (!empty($subunidade)) {
            $sqlFilter = " AND subunidade = ?";
            $params[] = $subunidade;
        }

        $sqlOfSgt = "SELECT * FROM tb_arranchamento 
                     WHERE data_refeicao = ?" . $sqlFilter . " 
                     AND posto_grad IN ('Cel', 'Ten Cel', 'Maj', 'Cap', '1º Ten', '2º Ten', 'Asp', 'Subten', 'Sub Ten', '1º Sgt', '2º Sgt', '3º Sgt')
                     ORDER BY FIELD(posto_grad, 'Cel', 'Ten Cel', 'Maj', 'Cap', '1º Ten', '2º Ten', 'Asp', 'Subten', 'Sub Ten', '1º Sgt', '2º Sgt', '3º Sgt'), nome_guerra ASC";

        $sqlCbSd = "SELECT * FROM tb_arranchamento 
                    WHERE data_refeicao = ?" . $sqlFilter . " 
                    AND posto_grad IN ('Cb', 'Sd EP', 'Sd EV', 'SC', 'Sd')
                    ORDER BY FIELD(posto_grad, 'Cb', 'Sd EP', 'Sd EV', 'SC', 'Sd'), CAST(numero AS UNSIGNED) ASC, nome_guerra ASC";

        $stmt1 = $this->db->prepare($sqlOfSgt);
        $stmt1->execute($params);
        $ofSgt = $stmt1->fetchAll();

        $stmt2 = $this->db->prepare($sqlCbSd);
        $stmt2->execute($params);
        $cbSd = $stmt2->fetchAll();

        return ['ofSgt' => $ofSgt, 'cbSd' => $cbSd];
    }

    public function salvarLote(string $subunidade, string $posto_grad, string $numero, string $nome_guerra, array $refeicoes): array {
        $stmtCheck = $this->db->prepare("SELECT id FROM tb_arranchamento WHERE data_refeicao = ? AND subunidade = ? AND posto_grad = ? AND nome_guerra = ?");
        $stmtUpdate = $this->db->prepare("UPDATE tb_arranchamento SET cafe = ?, almoco = ?, numero = ? WHERE id = ?");
        $stmtInsert = $this->db->prepare("INSERT INTO tb_arranchamento (data_refeicao, subunidade, posto_grad, numero, nome_guerra, cafe, almoco) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $datasArranchadas = [];
        
        try {
            $this->db->beginTransaction();
            
            foreach ($refeicoes as $ref) {
                $data   = $ref['data'];
                $cafe   = (int)$ref['cafe'];
                $almoco = (int)$ref['almoco'];
                
                $datasArranchadas[] = $data;

                $stmtCheck->execute([$data, $subunidade, $posto_grad, $nome_guerra]);
                $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                
                if ($row) {
                    $stmtUpdate->execute([$cafe, $almoco, $numero, $row['id']]);
                } else {
                    $stmtInsert->execute([$data, $subunidade, $posto_grad, $numero, $nome_guerra, $cafe, $almoco]);
                }
            }
            
            $this->db->commit();
            return $datasArranchadas;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function salvarExtra(array $dados): void {
        // Para o Furriel salvar "pessoas por fora" ou atualizar a refeição
        $id = !empty($dados['id']) ? (int)$dados['id'] : null;
        $data = $dados['data'];
        $subunidade = $dados['subunidade'];
        $posto_grad = $dados['posto_grad'];
        $nome_guerra = $dados['nome_guerra'];
        $cafe = (int)($dados['cafe'] ?? 0);
        $almoco = (int)($dados['almoco'] ?? 0);
        $jantar = (int)($dados['jantar'] ?? 0);
        $quantidade = (int)($dados['quantidade'] ?? 1);
        if ($quantidade < 1) $quantidade = 1;
        
        if ($id) {
            $stmt = $this->db->prepare("UPDATE tb_arranchamento SET cafe = ?, almoco = ?, jantar = ?, quantidade = ? WHERE id = ?");
            $stmt->execute([$cafe, $almoco, $jantar, $quantidade, $id]);
        } else {
            // Verifica se já existe (não mesclamos se for extra com quantidade variável repetida, mas mantemos o check para segurança)
            $stmtCheck = $this->db->prepare("SELECT id FROM tb_arranchamento WHERE data_refeicao = ? AND subunidade = ? AND posto_grad = ? AND nome_guerra = ?");
            $stmtCheck->execute([$data, $subunidade, $posto_grad, $nome_guerra]);
            $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $stmt = $this->db->prepare("UPDATE tb_arranchamento SET cafe = ?, almoco = ?, jantar = ?, quantidade = ? WHERE id = ?");
                $stmt->execute([$cafe, $almoco, $jantar, $quantidade, $row['id']]);
            } else {
                $stmt = $this->db->prepare("INSERT INTO tb_arranchamento (data_refeicao, subunidade, posto_grad, numero, nome_guerra, cafe, almoco, jantar, is_extra, quantidade) VALUES (?, ?, ?, '', ?, ?, ?, ?, 1, ?)");
                $stmt->execute([$data, $subunidade, $posto_grad, $nome_guerra, $cafe, $almoco, $jantar, $quantidade]);
            }
        }
    }
}
