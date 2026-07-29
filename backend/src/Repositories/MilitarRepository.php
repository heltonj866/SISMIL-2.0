<?php
namespace Sismil\Repositories;

use Sismil\Core\Database;
use PDO;

/**
 * Camada de Persistência (Repository) para a entidade Militar.
 * Centraliza as consultas SQL, separando o acesso aos dados da regra de negócio.
 */
class MilitarRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Busca um militar específico pelo ID.
     *
     * @param int $id ID do militar
     * @return array|null Retorna os dados do militar ou null se não encontrado
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM tb_militares WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Busca pública segura por termo (usada na portaria/S2).
     *
     * @param string $query Termo buscado
     * @return array
     */
    public function searchPublic(string $query): array {
        $sql = "SELECT DISTINCT m.id, m.posto_grad, m.nome_guerra, m.nome_completo,
                    m.subunidade, m.secao, m.pelotao, m.celular_princ,
                    m.dt_nascimento, m.dt_praca, m.foto_path, m.cat_cnh,
                    m.status_ativo
                FROM tb_militares m
                LEFT JOIN tb_veiculos v ON v.militar_id = m.id
                WHERE m.status_ativo = 1
                  AND (
                    m.nome_guerra LIKE :t1
                    OR m.nome_completo LIKE :t2
                    OR m.posto_grad LIKE :t3
                    OR v.placa LIKE :t4
                    OR v.modelo LIKE :t5
                  )
                ORDER BY 
                CASE m.posto_grad
                    WHEN 'Gen Ex' THEN 1 WHEN 'Gen Div' THEN 2 WHEN 'Gen Bda' THEN 3 WHEN 'Cel' THEN 4
                    WHEN 'TC' THEN 5 WHEN 'Maj' THEN 6 WHEN 'Cap' THEN 7 WHEN '1º Ten' THEN 8
                    WHEN '2º Ten' THEN 9 WHEN 'Asp' THEN 10 WHEN 'S Ten' THEN 11 WHEN '1º Sgt' THEN 12
                    WHEN '2º Sgt' THEN 13 WHEN '3º Sgt' THEN 14 WHEN 'Alu' THEN 15 WHEN 'Cb' THEN 16
                    WHEN 'Sd EP' THEN 17 WHEN 'Sd EV' THEN 18 WHEN 'SC' THEN 99 ELSE 100
                END ASC, m.nome_guerra ASC LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $like = "%{$query}%";
        $stmt->execute([':t1' => $like, ':t2' => $like, ':t3' => $like, ':t4' => $like, ':t5' => $like]);
        $militares = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($militares as &$m) {
            $sv = $this->db->prepare("SELECT placa, modelo, cor, homologado FROM tb_veiculos WHERE militar_id = ? ORDER BY homologado DESC, id DESC LIMIT 1");
            $sv->execute([$m['id']]);
            $v = $sv->fetch(PDO::FETCH_ASSOC);
            $m['veiculo'] = $v ?: null;
        }
        
        return $militares;
    }

    /**
     * Realiza a busca complexa (usada no Dashboard Interno).
     * 
     * @param array $filtros Array contendo os parâmetros de filtro.
     * @return array
     */
    public function searchComplex(array $filtros): array {
        $statusFilter = !empty($filtros['inativos']) ? "status_ativo = 0" : "status_ativo = 1";
        $sql = "SELECT * FROM tb_militares WHERE $statusFilter";
        $params = [];

        $tipo_busca = $filtros['tipo_busca'] ?? 'geral';

        if ($tipo_busca === 'geral') {
            $termo = $filtros['termo'] ?? '';
            if (!empty($termo)) {
                if (is_numeric($termo)) {
                    $sql .= " AND numero = ?";
                    $params[] = $termo;
                } else {
                    $sql .= " AND (nome_guerra LIKE ? OR nome_completo LIKE ? OR cpf LIKE ? OR idt_militar LIKE ?)";
                    $params = array_merge($params, ["%$termo%", "%$termo%", "%$termo%", "%$termo%"]);
                }
            }

            $posto = $filtros['posto'] ?? '';
            if (!empty($posto) && $posto !== 'Todos') {
                $sql .= " AND posto_grad = ?";
                $params[] = $posto;
            }

            $qmg = $filtros['qmg'] ?? '';
            if (!empty($qmg) && $qmg !== 'Todas') {
                $sql .= " AND qmg = ?";
                $params[] = $qmg;
            }

            $subunidade = $filtros['subunidade'] ?? '';
            if (!empty($subunidade) && $subunidade !== 'Todas') {
                $sql .= " AND subunidade = ?";
                $params[] = $subunidade;
            }

            if (!empty($filtros['sem_foto'])) {
                $sql .= " AND (foto_path IS NULL OR foto_path = '' OR foto_path = 'sem_foto.png' OR foto_path = 'sem_foto.PNG')";
            }

            $mes = $filtros['mes_aniversario'] ?? '';
            if (!empty($mes) && is_numeric($mes)) {
                $sql .= " AND MONTH(dt_nascimento) = ?";
                $params[] = (int)$mes;
            }
        } else if ($tipo_busca === 'cnh') {
            $filtro_cnh = $filtros['filtro_cnh'] ?? 'TODAS';
            if ($filtro_cnh === 'PRO') {
                $sql .= " AND (cat_cnh LIKE '%C%' OR cat_cnh LIKE '%D%' OR cat_cnh LIKE '%E%')";
            } elseif ($filtro_cnh === 'PENDENTES') {
                $sql .= " AND EXISTS (SELECT 1 FROM tb_veiculos WHERE tb_veiculos.militar_id = tb_militares.id AND tb_veiculos.homologado = 0)";
            } elseif ($filtro_cnh !== 'TODAS' && !empty($filtro_cnh)) {
                $sql .= " AND cat_cnh = ?";
                $params[] = $filtro_cnh;
            } else {
                $sql .= " AND cat_cnh IS NOT NULL AND cat_cnh != ''";
            }
        }

        $sql .= " ORDER BY 
            CASE posto_grad
                WHEN 'Gen Ex' THEN 1 WHEN 'Gen Div' THEN 2 WHEN 'Gen Bda' THEN 3 WHEN 'Cel' THEN 4
                WHEN 'TC' THEN 5 WHEN 'Maj' THEN 6 WHEN 'Cap' THEN 7 WHEN '1º Ten' THEN 8
                WHEN '2º Ten' THEN 9 WHEN 'Asp' THEN 10 WHEN 'S Ten' THEN 11 WHEN '1º Sgt' THEN 12
                WHEN '2º Sgt' THEN 13 WHEN '3º Sgt' THEN 14 WHEN 'Alu' THEN 15 WHEN 'Cb' THEN 16
                WHEN 'Sd EP' THEN 17 WHEN 'Sd EV' THEN 18 WHEN 'SC' THEN 99 ELSE 100
            END ASC, dt_praca ASC, CAST(numero AS UNSIGNED) ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExportRelatorio(array $filtros, string $roleSessao): array {
        $tipo = $filtros['tipo_busca'] ?? 'geral';
        $inativos = isset($filtros['inativos']) && $filtros['inativos'] == '1';
        $params = [];
        $sql = "SELECT * FROM tb_militares WHERE 1=1";
        
        if (!$inativos) {
            $sql .= " AND status_ativo = 1";
        }

        if ($tipo === 'geral') {
            $termo = $filtros['termo'] ?? '';
            $posto = $filtros['posto'] ?? '';
            $su    = $filtros['su'] ?? '';
            $qmg   = $filtros['qmg'] ?? '';
            $sem_foto = isset($filtros['sem_foto']) && $filtros['sem_foto'] == '1';
            $mes_aniversario = $filtros['mes_aniversario'] ?? '';

            if (!empty($termo)) {
                $t = "%" . trim($termo) . "%";
                $sql .= " AND (nome_guerra LIKE :t1 OR nome_completo LIKE :t2 OR numero LIKE :t3)";
                $params[':t1'] = $t; $params[':t2'] = $t; $params[':t3'] = $t;
            }
            if (!empty($posto)) { $sql .= " AND posto_grad = :posto"; $params[':posto'] = $posto; }
            if (!empty($su)) { $sql .= " AND subunidade = :su"; $params[':su'] = $su; }
            if (!empty($qmg)) { $sql .= " AND qmg = :qmg"; $params[':qmg'] = $qmg; }

            if ($sem_foto) {
                if (in_array(strtolower(trim($roleSessao)), ['admin', 'sargenteacao'])) {
                    $sql .= " AND (foto_path IS NULL OR foto_path = '' OR foto_path = 'sem_foto.png' OR foto_path = 'sem_foto.PNG')";
                }
            }
            if (!empty($mes_aniversario) && is_numeric($mes_aniversario) && $mes_aniversario >= 1 && $mes_aniversario <= 12) {
                $sql .= " AND MONTH(dt_nascimento) = :mes_aniversario";
                $params[':mes_aniversario'] = (int)$mes_aniversario;
            }
        } 
        else if ($tipo === 'cnh') {
            $filtro = $filtros['filtro_cnh'] ?? 'TODAS';
            $sql .= " AND cat_cnh IS NOT NULL AND cat_cnh != ''";
            
            if ($filtro === 'PENDENTE') $sql .= " AND (homologado IS NULL OR homologado = 0)";
            elseif ($filtro === 'VEICULOS') $sql .= " AND placa IS NOT NULL AND placa != ''";
            elseif ($filtro === 'A') $sql .= " AND cat_cnh LIKE '%A%'";
            elseif ($filtro === 'B') $sql .= " AND cat_cnh LIKE '%B%'";
            elseif ($filtro === 'PRO') $sql .= " AND (cat_cnh LIKE '%C%' OR cat_cnh LIKE '%D%' OR cat_cnh LIKE '%E%' OR cat_cnh LIKE '%AD%' OR cat_cnh LIKE '%AE%')";
        }

        $sql .= " ORDER BY nome_guerra ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $dados): int {
        $sql = "INSERT INTO tb_militares (
            cpf, posto_grad, numero, nome_guerra, subunidade, pelotao, secao, nome_completo,
            nome_pai, nome_mae, qmg, dt_nascimento, tipo_sanguineo, dt_praca, idt_militar,
            email, celular_princ, celular_sec, nome_resp, tel_resp, tel_emergencia,
            cep, endereco, num_residencia, bairro, cidade, estado,
            cat_cnh, validade_cnh, pdf_habilitacao, foto_path, pdf_nada_consta, status_ativo
        ) VALUES (
            :cpf, :posto_grad, :numero, :nome_guerra, :subunidade, :pelotao, :secao, :nome_completo,
            :nome_pai, :nome_mae, :qmg, :dt_nascimento, :tipo_sanguineo, :dt_praca, :idt_militar,
            :email, :celular_princ, :celular_sec, :nome_resp, :tel_resp, :tel_emergencia,
            :cep, :endereco, :num_residencia, :bairro, :cidade, :estado,
            :cat_cnh, :validade_cnh, :pdf_cnh, :foto, :pdf_nada_consta, 1
        )";
        $this->db->prepare($sql)->execute($dados);
        return (int)$this->db->lastInsertId();
    }

    public function update(array $dados): void {
        $setClauses = [];
        $params = [':id' => $dados[':id']];
        foreach ($dados as $key => $val) {
            if ($key === ':id') continue;
            $colName = ltrim($key, ':');
            // Mapeamentos específicos do banco vs nome do parâmetro
            if ($colName === 'foto') $colName = 'foto_path';
            if ($colName === 'pdf_cnh') $colName = 'pdf_habilitacao';

            $setClauses[] = "$colName = $key";
            $params[$key] = $val;
        }
        
        $sql = "UPDATE tb_militares SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $this->db->prepare($sql)->execute($params);
    }

    public function updateStatus(int $id, int $statusAtivo, ?string $pdfNadaConsta = null): void {
        $sql = "UPDATE tb_militares SET status_ativo = ?";
        $params = [$statusAtivo];
        
        if ($pdfNadaConsta !== null) {
            $sql .= ", pdf_nada_consta = ?";
            $params[] = $pdfNadaConsta;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        
        $this->db->prepare($sql)->execute($params);
    }
}
