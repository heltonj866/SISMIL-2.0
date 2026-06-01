<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json; charset=utf-8');
require 'db_connect.php';

try {
    // Contagens básicas
    $militares  = $pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo = 1")->fetchColumn();
    $inativos   = $pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo = 0")->fetchColumn();
    $veiculos   = $pdo->query("SELECT COUNT(*) FROM tb_veiculos")->fetchColumn();
    $pendentes  = $pdo->query("SELECT COUNT(*) FROM tb_veiculos WHERE homologado = 0")->fetchColumn();
    $homologados= $pdo->query("SELECT COUNT(*) FROM tb_veiculos WHERE homologado = 1")->fetchColumn();
    $com_cnh    = $pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo=1 AND cat_cnh IS NOT NULL AND cat_cnh != ''")->fetchColumn();

    // Efetivo por Subunidade e Posto
    $sqlEf = "SELECT subunidade, posto_grad, COUNT(*) as qtd
              FROM tb_militares WHERE status_ativo = 1
              GROUP BY subunidade, posto_grad
              ORDER BY subunidade ASC,
                CASE posto_grad
                    WHEN 'Cel' THEN 4 WHEN 'Ten Cel' THEN 5 WHEN 'Maj' THEN 6
                    WHEN 'Cap' THEN 7 WHEN '1º Ten' THEN 8 WHEN '2º Ten' THEN 9
                    WHEN 'Asp' THEN 10 WHEN 'Subten' THEN 11 WHEN 'Sub Ten' THEN 11
                    WHEN '1º Sgt' THEN 12 WHEN '2º Sgt' THEN 13 WHEN '3º Sgt' THEN 14
                    WHEN 'Cb' THEN 15 WHEN 'Sd EP' THEN 16 WHEN 'Sd EV' THEN 17
                    WHEN 'Sd' THEN 18 WHEN 'SC' THEN 99 ELSE 100 END ASC";
    $efetivo_raw = $pdo->query($sqlEf)->fetchAll(PDO::FETCH_ASSOC);

    $efetivo_su = [];
    foreach ($efetivo_raw as $row) {
        $su = $row['subunidade'] ?: 'Sem SU';
        if (!isset($efetivo_su[$su])) $efetivo_su[$su] = ['total' => 0, 'detalhes' => []];
        $efetivo_su[$su]['total'] += $row['qtd'];
        $efetivo_su[$su]['detalhes'][] = ['posto' => $row['posto_grad'], 'qtd' => $row['qtd']];
    }

    // Veículos pendentes com dados do militar
    $sqlPend = "SELECT v.id, v.placa, v.modelo, v.cor, v.observacao_s2,
                       m.posto_grad, m.nome_guerra
                FROM tb_veiculos v
                JOIN tb_militares m ON v.militar_id = m.id
                WHERE v.homologado = 0
                ORDER BY v.id DESC
                LIMIT 20";
    $veiculos_pendentes = $pdo->query($sqlPend)->fetchAll(PDO::FETCH_ASSOC);

    // CNH por Categoria
    $sqlCnh = "SELECT cat_cnh as cat, COUNT(*) as qtd
               FROM tb_militares
               WHERE status_ativo=1 AND cat_cnh IS NOT NULL AND cat_cnh != ''
               GROUP BY cat_cnh ORDER BY qtd DESC";
    $cnh_cats = $pdo->query($sqlCnh)->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status'            => 'sucesso',
        'militares'         => (int)$militares,
        'inativos'          => (int)$inativos,
        'veiculos'          => (int)$veiculos,
        'pendentes'         => (int)$pendentes,
        'homologados'       => (int)$homologados,
        'com_cnh'           => (int)$com_cnh,
        'efetivo_su'        => $efetivo_su,
        'veiculos_pendentes'=> $veiculos_pendentes,
        'cnh_cats'          => $cnh_cats,
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'erro', 'msg' => $e->getMessage()]);
}
?>