<?php
require 'db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS tb_arranchamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_refeicao DATE NOT NULL,
    subunidade VARCHAR(50) NOT NULL,
    posto_grad VARCHAR(50) NOT NULL,
    numero VARCHAR(20),
    nome_guerra VARCHAR(100) NOT NULL,
    cafe TINYINT(1) DEFAULT 0,
    almoco TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

try {
    $pdo->exec($sql);
    echo "Tabela tb_arranchamento criada com sucesso!";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>
