-- ARQUIVO: backend/database/schema_auditoria.sql
-- =======================================================================================
-- TRILHA DE AUDITORIA (POSIN-EB)
-- Requisito: Registro imutável de Quem, O Quê, Quando e Onde para ações críticas no sistema.
-- =======================================================================================

CREATE TABLE IF NOT EXISTS `tb_logs_auditoria` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NULL,                         -- Referência ao tb_usuarios (opcional para casos de login falho de usuário inexistente)
  `usuario_nome` VARCHAR(100) NULL,              -- Nome ou Identificação no momento da ação (histórico imutável)
  `acao` VARCHAR(50) NOT NULL,                   -- Tipo de ação (ex: LOGIN_SUCCESS, CREATE_MILITAR, UPDATE_FROTA)
  `detalhes` TEXT NULL,                          -- Payload JSON ou texto detalhando a ação (ex: ID afetado, campos alterados)
  `ip_address` VARCHAR(45) NOT NULL,             -- IP de origem da requisição (suporta IPv4 e IPv6)
  `user_agent` VARCHAR(255) NULL,                -- Navegador e Sistema Operacional utilizado
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Timestamp exato da ocorrência
  CONSTRAINT `fk_log_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `tb_usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice para acelerar consultas de auditoria por período, usuário ou tipo de ação
CREATE INDEX `idx_auditoria_data` ON `tb_logs_auditoria` (`created_at`);
CREATE INDEX `idx_auditoria_usuario` ON `tb_logs_auditoria` (`usuario_id`);
CREATE INDEX `idx_auditoria_acao` ON `tb_logs_auditoria` (`acao`);
