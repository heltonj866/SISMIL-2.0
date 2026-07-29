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
}
