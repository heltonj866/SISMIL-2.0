<?php
require 'db_connect.php';
try {
    $pdo->exec("ALTER TABLE tb_usuarios ADD COLUMN nome VARCHAR(100) DEFAULT NULL, ADD COLUMN posto_grad VARCHAR(50) DEFAULT NULL");
    echo "Colunas nome e posto_grad adicionadas com sucesso!";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>
