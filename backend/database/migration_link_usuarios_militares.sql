-- Script de migração: Vincular Usuários aos Militares
-- Adiciona a coluna militar_id e cria a restrição de chave estrangeira (FK)

ALTER TABLE tb_usuarios 
ADD COLUMN militar_id INT NULL DEFAULT NULL AFTER identidade;

ALTER TABLE tb_usuarios 
ADD CONSTRAINT fk_usuario_militar 
FOREIGN KEY (militar_id) REFERENCES tb_militares(id) 
ON DELETE SET NULL;
