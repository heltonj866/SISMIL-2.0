<?php
// ARQUIVO: backend/save_arranchamento.php
require_once __DIR__ . '/security.php';
header('Content-Type: application/json; charset=utf-8');
apply_cors();
require 'db_connect.php';

// MED-02: Rate limiting para o arranchamento público
// Máximo 20 envios por IP a cada hora
$rate_id = 'arranchamento_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!check_rate_limit($rate_id, 20, 3600)) {
    send_error('Muitas solicitações. Tente novamente em 1 hora.');
}

$input = json_decode(file_get_contents('php://input'), true);

$subunidade = $input['subunidade'] ?? '';
$posto_grad = $input['posto_grad'] ?? '';
$numero = $input['numero'] ?? '';
$nome_guerra = trim($input['nome_guerra'] ?? '');
$refeicoes = $input['refeicoes'] ?? [];

if (empty($subunidade) || empty($posto_grad) || empty($nome_guerra) || empty($refeicoes)) {
    echo json_encode(['status' => 'erro', 'msg' => 'Dados incompletos. Preencha todos os campos e selecione pelo menos uma refeição.']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Preparar queries
    // Verifica se já existe um lançamento para este militar neste dia
    $stmtCheck = $pdo->prepare("SELECT id FROM tb_arranchamento WHERE data_refeicao = ? AND subunidade = ? AND posto_grad = ? AND nome_guerra = ?");
    
    $stmtUpdate = $pdo->prepare("UPDATE tb_arranchamento SET cafe = ?, almoco = ?, numero = ? WHERE id = ?");
    
    $stmtInsert = $pdo->prepare("INSERT INTO tb_arranchamento (data_refeicao, subunidade, posto_grad, numero, nome_guerra, cafe, almoco) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($refeicoes as $ref) {
        $data = $ref['data'];
        $cafe = $ref['cafe'];
        $almoco = $ref['almoco'];
        
        $stmtCheck->execute([$data, $subunidade, $posto_grad, $nome_guerra]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Atualiza
            $stmtUpdate->execute([$cafe, $almoco, $numero, $row['id']]);
        } else {
            // Insere
            $stmtInsert->execute([$data, $subunidade, $posto_grad, $numero, $nome_guerra, $cafe, $almoco]);
        }
    }
    
    $pdo->commit();
    echo json_encode(['status' => 'sucesso']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    send_error('Erro ao salvar arranchamento. Tente novamente.', 'save_arranchamento.php: ' . $e->getMessage());
}
?>
