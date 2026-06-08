<?php
// ARQUIVO: backend/save_militar.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
require_login(['admin', 'sargenteacao']);
validate_csrf();
require 'db_connect.php';

try {
    function getPost($key) {
        $val = $_POST[$key] ?? null;
        return ($val === '' || $val === 'null') ? null : trim($val);
    }

    // --- HIGH-02: Função para validar upload de arquivo com MIME real ---
    function validar_upload($file_key, $extensoes_permitidas, $mimes_permitidos, $dir, $prefixo) {
        if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $file = $_FILES[$file_key];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Valida extensão
        if (!in_array($ext, $extensoes_permitidas)) {
            throw new Exception("Tipo de arquivo inválido para '$file_key'. Permitido: " . implode(', ', $extensoes_permitidas));
        }

        // Valida MIME real do conteúdo do arquivo
        $mime_real = mime_content_type($file['tmp_name']);
        if (!in_array($mime_real, $mimes_permitidos)) {
            throw new Exception("Conteúdo de arquivo inválido para '$file_key'. MIME detectado: $mime_real");
        }

        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Nome seguro com extensão controlada (nunca usa a extensão original)
        $ext_segura = ($ext === 'pdf') ? 'pdf' : 'jpg';
        $novo_nome  = $prefixo . bin2hex(random_bytes(8)) . '.' . $ext_segura;

        if (!move_uploaded_file($file['tmp_name'], $dir . $novo_nome)) {
            throw new Exception("Falha ao mover arquivo '$file_key'.");
        }
        return $novo_nome;
    }

    $id  = getPost('id_militar') ?? getPost('militarId');
    $cpf = getPost('cpf');
    if (!$cpf) throw new Exception("O CPF é obrigatório.");

    $dir_uploads = __DIR__ . '/../uploads/';
    $dir_docs    = __DIR__ . '/../uploads/documentos/';

    // Upload de Foto — HIGH-02: valida MIME real (somente imagens)
    $foto_path = validar_upload('foto', ['jpg','jpeg','png','webp'],
        ['image/jpeg','image/png','image/webp'], $dir_uploads, 'foto_');
    $sqlFoto = $foto_path ? ", foto_path = :foto" : "";

    // Upload de CNH — apenas PDF
    $pdf_cnh = validar_upload('pdf_habilitacao', ['pdf'],
        ['application/pdf'], $dir_docs, 'cnh_');
    $sqlCnh = $pdf_cnh ? ", pdf_habilitacao = :pdf_cnh" : "";

    // Upload de Nada Consta — apenas PDF
    $pdf_nada_consta = validar_upload('pdf_nada_consta', ['pdf'],
        ['application/pdf'], $dir_docs, 'nc_');
    $sqlPdf = $pdf_nada_consta ? ", pdf_nada_consta = :pdf_nada_consta" : "";

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
        if ($foto_path)       $dados[':foto']           = $foto_path;
        if ($pdf_nada_consta) $dados[':pdf_nada_consta'] = $pdf_nada_consta;
        if ($pdf_cnh)         $dados[':pdf_cnh']         = $pdf_cnh;

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
        $dados[':foto']           = $foto_path;
        $dados[':pdf_nada_consta'] = $pdf_nada_consta;
        $dados[':pdf_cnh']         = $pdf_cnh;

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
    send_error($e->getMessage(), 'save_militar.php: ' . $e->getMessage());
}
?>