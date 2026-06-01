<?php
require 'db_connect.php';
try {
    $pdo->exec("ALTER TABLE tb_usuarios ADD COLUMN subunidade VARCHAR(50) DEFAULT NULL");
    echo "Coluna subunidade adicionada!";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
