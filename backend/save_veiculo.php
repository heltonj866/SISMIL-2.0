<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
require_login(['admin', 'sargenteacao']);
validate_csrf();
require 'db_connect.php';

try {
    $id         = $_POST['veiculo_id'] ?? null;
    $militar_id = $_POST['v_militar_id'] ?? null;
    $placa      = strtoupper(trim($_POST['v_placa'] ?? ''));

    if (!$militar_id || !$placa) throw new Exception("Identificação do militar e placa são obrigatórios.");

    // Upload CRLV PDF - HIGH-02: valida MIME real
    $pdf_path = null; $sqlPdf = "";
    if (isset($_FILES['v_pdf']) && $_FILES['v_pdf']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/../uploads/documentos/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['v_pdf']['name'], PATHINFO_EXTENSION));
        $mime_real = mime_content_type($_FILES['v_pdf']['tmp_name']);
        if ($ext !== 'pdf' || $mime_real !== 'application/pdf') {
            throw new Exception("O documento deve ser um PDF válido.");
        }
        $pdf_path = 'crlv_' . bin2hex(random_bytes(8)) . '.pdf';
        if (move_uploaded_file($_FILES['v_pdf']['tmp_name'], $dir . $pdf_path)) {
            $sqlPdf = ", pdf_veiculo = :pdf";
        } else { $pdf_path = null; }
    }

    // Emissão CRLV (data de emissão; validade = emissao + 1 ano calculado no print_selo.php)
    $emissao = (!empty($_POST['v_emissao'])) ? $_POST['v_emissao'] : null;

    $dados = [
        ':militar_id' => $militar_id,
        ':tipo'       => $_POST['v_tipo'] ?? 'Carro',
        ':placa'      => $placa,
        ':marca'      => $_POST['v_marca'] ?? '',
        ':modelo'     => $_POST['v_modelo'] ?? '',
        ':cor'        => $_POST['v_cor'] ?? '',
        ':emissao'    => $emissao,
    ];

    if ($id && $id !== "") {
        $dados[':id'] = $id;
        unset($dados[':militar_id']); // Evita o erro de parâmetro não definido no UPDATE
        if ($pdf_path) $dados[':pdf'] = $pdf_path;
        // Quando S2 edita, resetar homologado apenas se não for a avaliação S2
        $sql = "UPDATE tb_veiculos SET tipo_veiculo=:tipo, placa=:placa, marca=:marca,
                modelo=:modelo, cor=:cor, emissao_crlv=:emissao, homologado=0 $sqlPdf WHERE id=:id";
    } else {
        $dados[':pdf'] = $pdf_path;
        $sql = "INSERT INTO tb_veiculos (militar_id, tipo_veiculo, placa, marca, modelo, cor, emissao_crlv, pdf_veiculo, homologado)
                VALUES (:militar_id, :tipo, :placa, :marca, :modelo, :cor, :emissao, :pdf, 0)";
    }

    $pdo->prepare($sql)->execute($dados);
    echo json_encode(['status' => 'sucesso']);
} catch (Exception $e) {
    send_error($e->getMessage(), 'save_veiculo.php: ' . $e->getMessage());
}
?>