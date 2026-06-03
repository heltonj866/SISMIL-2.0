<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['usuario_role']) || !in_array(strtolower($_SESSION['usuario_role']), ['admin', 'sargenteacao'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'erro', 'msg' => 'Acesso negado.']);
    exit;
}

require 'db_connect.php';

try {
    function getPost($key) {
        $val = $_POST[$key] ?? null;
        return ($val === '' || $val === 'null') ? null : trim($val);
    }

    $id  = getPost('id_militar') ?? getPost('militarId');
    $cpf = getPost('cpf');

    if (!$cpf) throw new Exception("O CPF é obrigatório.");

    // Upload de Foto
    $foto_path = null; $sqlFoto = "";
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/../uploads/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $novo_nome = uniqid('foto_') . '.' . $ext;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $novo_nome)) {
            $foto_path = $novo_nome; $sqlFoto = ", foto_path = :foto";
        }
    }

    // Upload de CNH (pdf_habilitacao)
    $pdf_cnh = null; $sqlCnh = "";
    if (isset($_FILES['pdf_habilitacao']) && $_FILES['pdf_habilitacao']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/../uploads/documentos/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['pdf_habilitacao']['name'], PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $novo_nome_cnh = uniqid('cnh_') . '.pdf';
            if (move_uploaded_file($_FILES['pdf_habilitacao']['tmp_name'], $dir . $novo_nome_cnh)) {
                $pdf_cnh = $novo_nome_cnh; $sqlCnh = ", pdf_habilitacao = :pdf_cnh";
            }
        }
    }

    // Upload de Nada Consta
    $pdf_nada_consta = null; $sqlPdf = "";
    if (isset($_FILES['pdf_nada_consta']) && $_FILES['pdf_nada_consta']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/../uploads/documentos/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['pdf_nada_consta']['name'], PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $novo_nome_pdf = uniqid('nc_') . '.pdf';
            if (move_uploaded_file($_FILES['pdf_nada_consta']['tmp_name'], $dir . $novo_nome_pdf)) {
                $pdf_nada_consta = $novo_nome_pdf; $sqlPdf = ", pdf_nada_consta = :pdf_nada_consta";
            }
        }
    }

    $dados = [
        ':cpf'            => $cpf,
        ':posto_grad'     => getPost('posto_grad'),
        ':nome_guerra'    => getPost('nome_guerra'),
        ':numero'         => getPost('numero'),
        ':subunidade'     => getPost('subunidade'),
        ':pelotao'        => getPost('pelotao'),
        ':secao'          => getPost('secao'),
        ':nome_completo'  => getPost('nome_completo'),
        ':nome_pai'       => getPost('nome_pai'),
        ':nome_mae'       => getPost('nome_mae'),
        ':qmg'            => getPost('qmg'),
        ':dt_nascimento'  => getPost('dt_nascimento'),
        ':tipo_sanguineo' => getPost('tipo_sanguineo'),
        ':dt_praca'       => getPost('dt_praca'),
        ':idt_militar'    => getPost('idt_militar'),
        ':email'          => getPost('email'),
        ':celular_princ'  => getPost('celular_princ'),
        ':celular_sec'    => getPost('celular_sec'),
        ':nome_resp'      => getPost('nome_resp'),
        ':tel_resp'       => getPost('tel_resp'),
        ':tel_emergencia' => getPost('tel_emergencia'),
        ':cep'            => getPost('cep'),
        ':endereco'       => getPost('endereco'),
        ':num_residencia' => getPost('num_residencia'),
        ':bairro'         => getPost('bairro'),
        ':cidade'         => getPost('cidade'),
        ':estado'         => getPost('estado'),
        ':cat_cnh'        => getPost('cat_cnh'),
        ':validade_cnh'   => getPost('validade_cnh'),
    ];

    if ($id) {
        $dados[':id'] = $id;
        if ($foto_path) $dados[':foto'] = $foto_path;
        if ($pdf_nada_consta) $dados[':pdf_nada_consta'] = $pdf_nada_consta;
        if ($pdf_cnh) $dados[':pdf_cnh'] = $pdf_cnh;

        $sql = "UPDATE tb_militares SET
            cpf=:cpf, posto_grad=:posto_grad, numero=:numero, nome_guerra=:nome_guerra,
            subunidade=:subunidade, pelotao=:pelotao, secao=:secao, nome_completo=:nome_completo,
            nome_pai=:nome_pai, nome_mae=:nome_mae,
            qmg=:qmg, dt_nascimento=:dt_nascimento, tipo_sanguineo=:tipo_sanguineo,
            dt_praca=:dt_praca, idt_militar=:idt_militar, email=:email, celular_princ=:celular_princ,
            celular_sec=:celular_sec, nome_resp=:nome_resp, tel_resp=:tel_resp, tel_emergencia=:tel_emergencia,
            cep=:cep, endereco=:endereco, num_residencia=:num_residencia, bairro=:bairro,
            cidade=:cidade, estado=:estado, cat_cnh=:cat_cnh, validade_cnh=:validade_cnh
            $sqlFoto $sqlPdf $sqlCnh
            WHERE id=:id";
    } else {
        $dados[':foto'] = $foto_path;
        $dados[':pdf_nada_consta'] = $pdf_nada_consta;
        $dados[':pdf_cnh'] = $pdf_cnh;

        $sql = "INSERT INTO tb_militares (
            cpf, posto_grad, numero, nome_guerra, subunidade, pelotao, secao, nome_completo,
            nome_pai, nome_mae, qmg, dt_nascimento, tipo_sanguineo, dt_praca, idt_militar,
            email, celular_princ, celular_sec, nome_resp, tel_resp, tel_emergencia,
            cep, endereco, num_residencia, bairro, cidade, estado,
            cat_cnh, validade_cnh, pdf_habilitacao, foto_path, pdf_nada_consta, status_ativo
        ) VALUES (
            :cpf, :posto_grad, :numero, :nome_guerra, :subunidade, :pelotao, :secao, :nome_completo,
            :nome_pai, :nome_mae, :qmg, :dt_nascimento, :tipo_sanguineo, :dt_praca, :idt_militar,
            :email, :celular_princ, :celular_sec, :nome_resp, :tel_resp, :tel_emergencia,
            :cep, :endereco, :num_residencia, :bairro, :cidade, :estado,
            :cat_cnh, :validade_cnh, :pdf_cnh, :foto, :pdf_nada_consta, 1
        )";
    }

    $pdo->prepare($sql)->execute($dados);
    echo json_encode(['status' => 'sucesso', 'msg' => 'Salvo com sucesso!']);
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'msg' => $e->getMessage()]);
}
?>