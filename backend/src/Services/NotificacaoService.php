<?php
namespace Sismil\Services;

use Sismil\Core\Database;
use PDO;

/**
 * Serviço de gerenciamento e geração de notificações internas para a Sargenteação/Admin.
 */
class NotificacaoService {
    private PDO $db;

    public function __construct() {
        SchemaService::ensureSchema();
        $this->db = Database::getInstance();
    }

    /**
     * Sincroniza automaticamente notificações baseadas no estado atual do banco
     * (CNH vencida, CRLV vencido, Veículos reprovados pela S2 e Meta de 5 FO+).
     */
    public function sincronizarAlertasAutomaticos(): void {
        try {
            // 1. Limpa alertas de CNH vencida de militares que já renovaram a CNH
            $this->db->exec("
                UPDATE tb_notificacoes n
                JOIN tb_militares m ON n.militar_id = m.id
                SET n.lida = 1
                WHERE n.tipo = 'CNH_VENCIDA'
                  AND n.lida = 0
                  AND (m.status_ativo = 0 OR m.cat_cnh IS NULL OR m.cat_cnh = '' OR m.validade_cnh >= CURDATE())
            ");

            // 2. Gera alertas para militares ativos com CNH vencida
            $stmtCnh = $this->db->query("
                SELECT id, posto_grad, numero, nome_guerra, subunidade, cat_cnh, validade_cnh
                FROM tb_militares
                WHERE status_ativo = 1
                  AND cat_cnh IS NOT NULL AND cat_cnh != ''
                  AND validade_cnh IS NOT NULL AND validade_cnh != '0000-00-00'
                  AND validade_cnh < CURDATE()
            ");
            if ($stmtCnh) {
                $ins = $this->db->prepare("
                    INSERT IGNORE INTO tb_notificacoes (tipo, titulo, mensagem, militar_id, referencia_chave)
                    VALUES ('CNH_VENCIDA', :titulo, :msg, :mil_id, :ref)
                ");
                foreach ($stmtCnh->fetchAll(PDO::FETCH_ASSOC) as $m) {
                    $ref = 'cnh_venc_' . $m['id'] . '_' . $m['validade_cnh'];
                    $dtBr = date('d/m/Y', strtotime($m['validade_cnh']));
                    $ident = trim($m['posto_grad'] . ' ' . ($m['numero'] ? $m['numero'] . ' ' : '') . $m['nome_guerra']);
                    $su = $m['subunidade'] ? " ({$m['subunidade']})" : '';
                    $ins->execute([
                        ':titulo' => "CNH Vencida: {$ident}",
                        ':msg'    => "O militar {$ident}{$su} está com a CNH (Cat. {$m['cat_cnh']}) vencida desde {$dtBr}.",
                        ':mil_id' => (int)$m['id'],
                        ':ref'    => $ref
                    ]);
                }
            }

            // 3. Limpa alertas de CRLV vencido que já foram renovados ou excluídos
            $this->db->exec("
                UPDATE tb_notificacoes n
                LEFT JOIN tb_veiculos v ON n.referencia_chave = CONCAT('crlv_venc_', v.id, '_', v.validade_crlv)
                SET n.lida = 1
                WHERE n.tipo = 'CRLV_VENCIDO'
                  AND n.lida = 0
                  AND (v.id IS NULL OR v.validade_crlv >= CURDATE())
            ");

            // 4. Gera alertas para veículos de militares ativos com CRLV vencido
            $stmtCrlv = $this->db->query("
                SELECT v.id as veiculo_id, v.placa, v.modelo, v.validade_crlv,
                       m.id as militar_id, m.posto_grad, m.numero, m.nome_guerra, m.subunidade
                FROM tb_veiculos v
                JOIN tb_militares m ON v.militar_id = m.id
                WHERE m.status_ativo = 1
                  AND v.validade_crlv IS NOT NULL AND v.validade_crlv != '0000-00-00'
                  AND v.validade_crlv < CURDATE()
            ");
            if ($stmtCrlv) {
                $insCrlv = $this->db->prepare("
                    INSERT IGNORE INTO tb_notificacoes (tipo, titulo, mensagem, militar_id, referencia_chave)
                    VALUES ('CRLV_VENCIDO', :titulo, :msg, :mil_id, :ref)
                ");
                foreach ($stmtCrlv->fetchAll(PDO::FETCH_ASSOC) as $v) {
                    $ref = 'crlv_venc_' . $v['veiculo_id'] . '_' . $v['validade_crlv'];
                    $dtBr = date('d/m/Y', strtotime($v['validade_crlv']));
                    $ident = trim($v['posto_grad'] . ' ' . ($v['numero'] ? $v['numero'] . ' ' : '') . $v['nome_guerra']);
                    $insCrlv->execute([
                        ':titulo' => "CRLV Vencido: Placa " . strtoupper($v['placa']),
                        ':msg'    => "O veículo {$v['modelo']} (Placa " . strtoupper($v['placa']) . ") do militar {$ident} está com CRLV vencido desde {$dtBr}.",
                        ':mil_id' => (int)$v['militar_id'],
                        ':ref'    => $ref
                    ]);
                }
            }

            // 5. Gera alertas para veículos reprovados/pendentes pela S2 com observação
            $stmtS2 = $this->db->query("
                SELECT v.id as veiculo_id, v.placa, v.modelo, v.observacao_s2,
                       m.id as militar_id, m.posto_grad, m.numero, m.nome_guerra
                FROM tb_veiculos v
                JOIN tb_militares m ON v.militar_id = m.id
                WHERE m.status_ativo = 1
                  AND (v.homologado = 0 OR v.homologado IS NULL)
                  AND v.observacao_s2 IS NOT NULL AND TRIM(v.observacao_s2) != ''
            ");
            if ($stmtS2) {
                $insS2 = $this->db->prepare("
                    INSERT IGNORE INTO tb_notificacoes (tipo, titulo, mensagem, militar_id, referencia_chave)
                    VALUES ('VEICULO_S2', :titulo, :msg, :mil_id, :ref)
                ");
                foreach ($stmtS2->fetchAll(PDO::FETCH_ASSOC) as $v) {
                    $obsHash = substr(md5(trim($v['observacao_s2'])), 0, 8);
                    $ref = 'veic_s2_' . $v['veiculo_id'] . '_' . $obsHash;
                    $ident = trim($v['posto_grad'] . ' ' . ($v['numero'] ? $v['numero'] . ' ' : '') . $v['nome_guerra']);
                    $insS2->execute([
                        ':titulo' => "Veículo Não Aprovado S2: " . strtoupper($v['placa']),
                        ':msg'    => "Veículo {$v['modelo']} (" . strtoupper($v['placa']) . ") de {$ident} possui pendência S2: \"{$v['observacao_s2']}\".",
                        ':mil_id' => (int)$v['militar_id'],
                        ':ref'    => $ref
                    ]);
                }
            }

            // 6. Gera alertas para militares ativos que atingiram múltiplos de 5 FO+
            $stmtFo = $this->db->query("
                SELECT m.id, m.posto_grad, m.numero, m.nome_guerra, m.subunidade, COUNT(a.id) as total_fo
                FROM tb_militares m
                JOIN tb_alteracoes a ON a.militar_id = m.id
                WHERE m.status_ativo = 1
                  AND UPPER(TRIM(a.tipo_detalhe)) = 'FO+'
                GROUP BY m.id
                HAVING total_fo >= 5
            ");
            if ($stmtFo) {
                $insFo = $this->db->prepare("
                    INSERT IGNORE INTO tb_notificacoes (tipo, titulo, mensagem, militar_id, referencia_chave)
                    VALUES ('META_FO_POSITIVO', :titulo, :msg, :mil_id, :ref)
                ");
                foreach ($stmtFo->fetchAll(PDO::FETCH_ASSOC) as $f) {
                    $marco = (int)(floor(((int)$f['total_fo']) / 5) * 5);
                    if ($marco >= 5) {
                        $ref = 'fo_pos_' . $f['id'] . '_m' . $marco;
                        $ident = trim($f['posto_grad'] . ' ' . ($f['numero'] ? $f['numero'] . ' ' : '') . $f['nome_guerra']);
                        $su = $f['subunidade'] ? " ({$f['subunidade']})" : '';
                        $insFo->execute([
                            ':titulo' => "Meta de {$marco} FO+ Atingida: {$ident}",
                            ':msg'    => "O militar {$ident}{$su} atingiu {$f['total_fo']} registro(s) de FO+ no Histórico S1.",
                            ':mil_id' => (int)$f['id'],
                            ':ref'    => $ref
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("[SISMIL_NOTIF] Erro na sincronização automática: " . $e->getMessage());
        }
    }

    public function notificarVeiculoAvaliado(int $veiculoId, int $homologado, string $obs): void {
        try {
            if ($homologado === 1) {
                // Se foi aprovado, marca notificações anteriores desse veículo como lidas
                $stmt = $this->db->prepare("UPDATE tb_notificacoes SET lida = 1 WHERE tipo = 'VEICULO_S2' AND referencia_chave LIKE ?");
                $stmt->execute(['veic_s2_' . $veiculoId . '_%']);
                return;
            }

            $stmtV = $this->db->prepare("
                SELECT v.id, v.placa, v.modelo, m.id as militar_id, m.posto_grad, m.numero, m.nome_guerra
                FROM tb_veiculos v
                JOIN tb_militares m ON v.militar_id = m.id
                WHERE v.id = ?
            ");
            $stmtV->execute([$veiculoId]);
            $v = $stmtV->fetch(PDO::FETCH_ASSOC);
            if (!$v) return;

            $obsTexto = trim($obs) !== '' ? trim($obs) : 'Reprovado / Pendente na inspeção S2';
            $ref = 'veic_s2_' . $veiculoId . '_' . substr(md5($obsTexto), 0, 8);
            $ident = trim($v['posto_grad'] . ' ' . ($v['numero'] ? $v['numero'] . ' ' : '') . $v['nome_guerra']);

            $ins = $this->db->prepare("
                INSERT IGNORE INTO tb_notificacoes (tipo, titulo, mensagem, militar_id, referencia_chave)
                VALUES ('VEICULO_S2', :titulo, :msg, :mil_id, :ref)
            ");
            $ins->execute([
                ':titulo' => "Veículo Não Aprovado S2: " . strtoupper($v['placa']),
                ':msg'    => "Veículo {$v['modelo']} (" . strtoupper($v['placa']) . ") de {$ident} foi marcado como não aprovado pela S2. Motivo: \"{$obsTexto}\".",
                ':mil_id' => (int)$v['militar_id'],
                ':ref'    => $ref
            ]);
        } catch (\Exception $e) {
            error_log("[SISMIL_NOTIF] Erro ao notificar veículo S2: " . $e->getMessage());
        }
    }

    public function verificarMetaFoPositivoMilitar(int $militarId): void {
        if ($militarId <= 0) return;
        try {
            $stmt = $this->db->prepare("
                SELECT m.id, m.posto_grad, m.numero, m.nome_guerra, m.subunidade, COUNT(a.id) as total_fo
                FROM tb_militares m
                JOIN tb_alteracoes a ON a.militar_id = m.id
                WHERE m.id = ? AND UPPER(TRIM(a.tipo_detalhe)) = 'FO+'
                GROUP BY m.id
            ");
            $stmt->execute([$militarId]);
            $f = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($f && (int)$f['total_fo'] >= 5) {
                $marco = (int)(floor(((int)$f['total_fo']) / 5) * 5);
                $ref = 'fo_pos_' . $f['id'] . '_m' . $marco;
                $ident = trim($f['posto_grad'] . ' ' . ($f['numero'] ? $f['numero'] . ' ' : '') . $f['nome_guerra']);
                $su = $f['subunidade'] ? " ({$f['subunidade']})" : '';
                $ins = $this->db->prepare("
                    INSERT IGNORE INTO tb_notificacoes (tipo, titulo, mensagem, militar_id, referencia_chave)
                    VALUES ('META_FO_POSITIVO', :titulo, :msg, :mil_id, :ref)
                ");
                $ins->execute([
                    ':titulo' => "Meta de {$marco} FO+ Atingida: {$ident}",
                    ':msg'    => "O militar {$ident}{$su} atingiu {$f['total_fo']} registro(s) de FO+ no Histórico S1.",
                    ':mil_id' => (int)$f['id'],
                    ':ref'    => $ref
                ]);
            }
        } catch (\Exception $e) {
            error_log("[SISMIL_NOTIF] Erro ao verificar meta FO+: " . $e->getMessage());
        }
    }

    public function criarNotificacaoPreCadastro(array $dadosPlanilha): void {
        try {
            $nomeCompleto = mb_strtoupper(trim($dadosPlanilha['nome_completo'] ?? ''), 'UTF-8');
            if ($nomeCompleto === '') return;

            $ref = 'precad_' . md5($nomeCompleto);
            $posto = trim($dadosPlanilha['posto_grad'] ?? '');
            $guerra = mb_strtoupper(trim($dadosPlanilha['nome_guerra'] ?? ''), 'UTF-8');
            $su = trim($dadosPlanilha['subunidade'] ?? '');

            $stmt = $this->db->prepare("
                INSERT INTO tb_notificacoes (tipo, titulo, mensagem, militar_id, referencia_chave, dados_json, lida)
                VALUES ('PRE_CADASTRO_PLANILHA', :titulo, :msg, NULL, :ref, :json, 0)
                ON DUPLICATE KEY UPDATE
                    titulo = VALUES(titulo),
                    mensagem = VALUES(mensagem),
                    dados_json = VALUES(dados_json)
            ");
            $stmt->execute([
                ':titulo' => "Novo Militar na Planilha: {$posto} {$guerra}",
                ':msg'    => "Militar {$nomeCompleto} ({$su}) identificado na Planilha Geral aguardando efetivação de pré-cadastro.",
                ':ref'    => $ref,
                ':json'   => json_encode($dadosPlanilha, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (\Exception $e) {
            error_log("[SISMIL_NOTIF] Erro ao criar notificação de pré-cadastro: " . $e->getMessage());
        }
    }

    public function notificarLivroApresentacao(int $militarId, string $identMilitar, string $sitAnterior, string $sitNova, string $obs = ''): void {
        try {
            $ref = 'livro_' . $militarId . '_' . time();
            $detalheObs = $obs !== '' ? " Observação: \"{$obs}\"." : '';
            $stmt = $this->db->prepare("
                INSERT INTO tb_notificacoes (tipo, titulo, mensagem, militar_id, referencia_chave)
                VALUES ('LIVRO_APRESENTACAO', :titulo, :msg, :mil_id, :ref)
            ");
            $stmt->execute([
                ':titulo' => "Apresentação Digital: {$identMilitar}",
                ':msg'    => "Militar alterou situação de \"{$sitAnterior}\" para \"{$sitNova}\".{$detalheObs}",
                ':mil_id' => $militarId,
                ':ref'    => $ref
            ]);
        } catch (\Exception $e) {
            error_log("[SISMIL_NOTIF] Erro ao notificar livro de apresentação: " . $e->getMessage());
        }
    }

    public function listarNotificacoes(bool $apenasNaoLidas = false, int $limite = 300): array {
        $this->sincronizarAlertasAutomaticos();

        $where = $apenasNaoLidas ? "WHERE lida = 0" : "";
        $stmt = $this->db->prepare("
            SELECT id, tipo, titulo, mensagem, militar_id, referencia_chave, dados_json, lida, created_at
            FROM tb_notificacoes
            {$where}
            ORDER BY lida ASC, created_at DESC, id DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($lista as &$item) {
            $item['dados_extras'] = !empty($item['dados_json']) ? json_decode($item['dados_json'], true) : null;
            unset($item['dados_json']);
        }

        $naoLidas = (int)$this->db->query("SELECT COUNT(*) FROM tb_notificacoes WHERE lida = 0")->fetchColumn();

        return [
            'nao_lidas'    => $naoLidas,
            'notificacoes' => $lista,
            'itens'        => $lista
        ];
    }

    public function marcarComoLida(?int $id = null, bool $todas = false): void {
        if ($todas) {
            $this->db->exec("UPDATE tb_notificacoes SET lida = 1 WHERE lida = 0");
        } elseif ($id && $id > 0) {
            $stmt = $this->db->prepare("UPDATE tb_notificacoes SET lida = 1 WHERE id = ?");
            $stmt->execute([$id]);
        }
    }
}
