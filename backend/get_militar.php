<?php
// ARQUIVO: backend/get_militar.php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(); // Qualquer usuário autenticado
require 'db_connect.php';

$id = $_GET['id'] ?? '';

if(empty($id)) {
    echo json_encode(['status' => 'erro', 'msg' => 'ID não informado']);
    exit;
}

try {
    $sql = "SELECT * FROM tb_militares WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    if (ob_get_length()) ob_clean(); // Limpa sujeira do buffer

    if ($dados) {
        // Converte NULL em VAZIO para não quebrar o Javascript
        foreach ($dados as $key => $value) {
            if (is_null($value)) $dados[$key] = "";
        }
        
        // Remove campos sensíveis para usuários comuns
        if (isset($_SESSION['usuario_role']) && $_SESSION['usuario_role'] === 'user') {
            unset($dados['cpf']);
            unset($dados['nome_pai']);
            unset($dados['nome_mae']);
            unset($dados['celular_sec']);
            unset($dados['tel_emergencia']);
        }
        
        echo json_encode(['status' => 'sucesso', 'dados' => $dados]);
    } else {
        echo json_encode(['status' => 'erro', 'msg' => 'Militar não encontrado no banco.']);
    }

} catch (PDOException $e) {
    send_error("Erro ao buscar dados do militar.", $e->getMessage());
}
?>