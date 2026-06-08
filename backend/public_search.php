<?php
require_once __DIR__ . '/security.php';
header('Content-Type: application/json; charset=utf-8');
apply_cors();
require 'db_connect.php';

$termo = trim($_GET['termo'] ?? '');

if (empty($termo)) {
    echo json_encode(['status' => 'erro', 'msg' => 'Termo de busca não informado.']);
    exit;
}

try {
    $busca = "%" . $termo . "%";

    // Busca militares pela nome de guerra, nome completo, posto, placa do veículo ou modelo
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
                WHEN 'Cel' THEN 4
                WHEN 'TC' THEN 5
                WHEN 'Maj' THEN 6
                WHEN 'Cap' THEN 7
                WHEN '1º Ten' THEN 8
                WHEN '2º Ten' THEN 9
                WHEN 'Asp' THEN 10
                WHEN 'S Ten' THEN 11
                WHEN '1º Sgt' THEN 12
                WHEN '2º Sgt' THEN 13
                WHEN '3º Sgt' THEN 14
                WHEN 'Alu' THEN 15
                WHEN 'Cb' THEN 16
                WHEN 'Sd EP' THEN 17
                WHEN 'Sd EV' THEN 18
                WHEN 'SC' THEN 99
                ELSE 100
            END ASC, m.nome_guerra ASC
            LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':t1' => $busca, ':t2' => $busca, ':t3' => $busca, ':t4' => $busca, ':t5' => $busca]);
    $militares = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Para cada militar, busca o primeiro veículo homologado (ou o mais recente)
    foreach ($militares as &$m) {
        $sv = $pdo->prepare("SELECT placa, modelo, cor, homologado FROM tb_veiculos WHERE militar_id = ? ORDER BY homologado DESC, id DESC LIMIT 1");
        $sv->execute([$m['id']]);
        $v = $sv->fetch(PDO::FETCH_ASSOC);
        $m['veiculo'] = $v ?: null;
    }
    unset($m);

    if (count($militares) > 0) {
        echo json_encode(['status' => 'sucesso', 'dados' => $militares]);
    } else {
        echo json_encode(['status' => 'sucesso', 'dados' => [], 'msg' => 'Nenhum militar encontrado.']);
    }

} catch (Exception $e) {
    send_error('Erro ao processar busca.', 'public_search.php: ' . $e->getMessage());
}
?>