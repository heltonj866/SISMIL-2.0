<?php
namespace Sismil\Services;

use Sismil\Core\Database;
use PDO;

/**
 * Garante de forma idempotente que as novas colunas e tabelas do SISMIL 2.0 existam no banco.
 */
class SchemaService {
    private static bool $verificado = false;

    public const SITUACOES_PADRAO = [
        'Em Forma',
        'ApoioFormatura',
        'Assistência',
        'CRA',
        'Missão OM',
        '(2) Ch Vtr',
        '(2) Missão Ext',
        '(2) Viagem SV',
        'Arejamento',
        'Convalescença',
        'Curso/Estágio/Concurso',
        'CS',
        'Disp ADM',
        'Disp Med',
        'Disp Recomp',
        'Férias',
        'Instalação',
        'Lic Pat/Mat',
        'LTSP/LTIP',
        'Luto',
        'Núpcias',
        'Op Esperantina',
        'Op Jacinto',
        'OpCajari',
        'Serviço',
        'ESV',
        'SSV'
    ];

    public static function ensureSchema(): void {
        if (self::$verificado) {
            return;
        }
        self::$verificado = true;

        try {
            $pdo = Database::getInstance();

            // Garante que o CPF permita NULL para que novos pré-cadastros sem CPF não gerem conflito na chave UNIQUE
            try {
                $pdo->exec("ALTER TABLE tb_militares MODIFY COLUMN cpf VARCHAR(20) NULL DEFAULT NULL");
                $pdo->exec("UPDATE tb_militares SET cpf = NULL WHERE cpf = '' OR TRIM(cpf) = ''");
            } catch (\Exception $e) {}

            // 1. Novas colunas em tb_militares
            $colsStmt = $pdo->query("SHOW COLUMNS FROM tb_militares");
            $existingCols = [];
            if ($colsStmt) {
                foreach ($colsStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                    $existingCols[strtolower($col['Field'])] = true;
                }
            }

            $alterParts = [];
            if (!isset($existingCols['grupamento'])) {
                $alterParts[] = "ADD COLUMN grupamento VARCHAR(50) DEFAULT NULL";
            }
            if (!isset($existingCols['situacao_atual'])) {
                $alterParts[] = "ADD COLUMN situacao_atual VARCHAR(60) DEFAULT 'Em Forma'";
            }
            if (!isset($existingCols['situacao_desde'])) {
                $alterParts[] = "ADD COLUMN situacao_desde DATETIME DEFAULT NULL";
            }
            if (!isset($existingCols['situacao_obs'])) {
                $alterParts[] = "ADD COLUMN situacao_obs VARCHAR(255) DEFAULT NULL";
            }
            if (!isset($existingCols['funcao'])) {
                $alterParts[] = "ADD COLUMN funcao VARCHAR(100) DEFAULT NULL";
            }

            if (!empty($alterParts)) {
                $pdo->exec("ALTER TABLE tb_militares " . implode(", ", $alterParts));
            }

            // 2. Tabela de Notificações Internas (Sargenteação / Admin)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tb_notificacoes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tipo VARCHAR(40) NOT NULL,
                    titulo VARCHAR(150) NOT NULL,
                    mensagem TEXT NOT NULL,
                    militar_id INT DEFAULT NULL,
                    referencia_chave VARCHAR(120) DEFAULT NULL,
                    dados_json TEXT DEFAULT NULL,
                    lida TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_notif_ref (referencia_chave),
                    INDEX idx_lida_created (lida, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // 3. Tabela do Livro de Apresentação Digital
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tb_livro_apresentacao (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    militar_id INT NOT NULL,
                    situacao_anterior VARCHAR(60) DEFAULT NULL,
                    situacao_nova VARCHAR(60) NOT NULL,
                    data_inicio DATE DEFAULT NULL,
                    data_termino DATE DEFAULT NULL,
                    observacao VARCHAR(255) DEFAULT NULL,
                    registrado_por VARCHAR(100) DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_militar_livro (militar_id, created_at),
                    INDEX idx_created_livro (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // 4. Tabela de Configurações do Sistema (URL da Planilha, Situações extras, etc.)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tb_config_sistema (
                    chave VARCHAR(80) PRIMARY KEY,
                    valor TEXT DEFAULT NULL,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // 5. Provisionamento automático de contas 'militar' (com senha vazia para primeiro acesso) para todos os militares ativos com CPF
            $pdo->exec("
                INSERT IGNORE INTO tb_usuarios (identidade, senha_hash, role, ativo, nome, posto_grad, subunidade, militar_id)
                SELECT m.cpf, '', 'militar', 1, m.nome_guerra, m.posto_grad, m.subunidade, m.id
                FROM tb_militares m
                LEFT JOIN tb_usuarios u ON (u.militar_id = m.id OR u.identidade = m.cpf)
                WHERE m.status_ativo = 1
                  AND m.cpf IS NOT NULL AND TRIM(m.cpf) != ''
                  AND u.id IS NULL
            ");

        } catch (\Exception $e) {
            error_log("[SISMIL_SCHEMA] Aviso na verificação de schema: " . $e->getMessage());
        }
    }

    public static function getSituacoesDisponiveis(): array {
        self::ensureSchema();
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("SELECT valor FROM tb_config_sistema WHERE chave = 'situacoes_custom'");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val) {
                $decoded = json_decode($val, true);
                if (is_array($decoded) && !empty($decoded)) {
                    return array_values(array_unique(array_merge(self::SITUACOES_PADRAO, $decoded)));
                }
            }
        } catch (\Exception $e) {}
        return self::SITUACOES_PADRAO;
    }
}
