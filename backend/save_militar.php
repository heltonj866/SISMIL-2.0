<?php
/**
 * ARQUIVO: backend/save_militar.php
 * Endpoint para salvar ou atualizar registros de militares.
 *
 * @package Sismil\Controllers
 */

require_once __DIR__ . '/src/Core/Response.php';
require_once __DIR__ . '/src/Core/Database.php';
require_once __DIR__ . '/src/Services/AuditLogger.php';
require_once __DIR__ . '/security.php';

use Sismil\Core\Database;
use Sismil\Core\Response;
use Sismil\Services\AuditLogger;

session_start();
require_login(['admin', 'sargenteacao']);
validate_csrf();

try {
    $pdo = Database::getInstance();

    /**
     * Obtém valores do $_POST de forma segura, retornando null se vazio.
     */
    function getPost(string $key): ?string {
        $val = $_POST[$key] ?? null;
        return ($val === '' || $val === 'null') ? null : trim($val);
    }

    /**
     * Realiza a validação rigorosa de upload de arquivo analisando o tipo MIME real.
     *
     * @param string $file_key Chave do arquivo na superglobal $_FILES.
     * @param array<string> $extensoes_permitidas Extensões de arquivo autorizadas.
     * @param array<string> $mimes_permitidos Tipos MIME validados via magic bytes.
     * @param string $dir Diretório de destino no servidor.
     * @param string $prefixo Prefixo para o nome seguro gerado.
     * @return string|null Nome do arquivo salvo ou null caso nenhum arquivo tenha sido enviado.
     * @throws Exception Se o arquivo não atender aos requisitos de segurança.
     */
    function validar_upload(string $file_key, array $extensoes_permitidas, array $mimes_permitidos, string $dir, string $prefixo): ?string {
        if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $file = $_FILES[$file_key];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $extensoes_permitidas, true)) {
            throw new Exception("Tipo de arquivo inválido para '$file_key'. Permitido: " . implode(', ', $extensoes_permitidas));
        }

        $mime_real = mime_content_type($file['tmp_name']);
        if (!in_array($mime_real, $mimes_permitidos, true)) {
            throw new Exception("Conteúdo de arquivo inválido para '$file_key'. MIME detectado: $mime_real");
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext_segura = ($ext === 'pdf') ? 'pdf' : 'jpg';
        $novo_nome  = $prefixo . bin2hex(random_bytes(8)) . '.' . $ext_segura;

        if (!move_uploaded_file($file['tmp_name'], $dir . $novo_nome)) {
            throw new Exception("Falha ao mover arquivo '$file_key'.");
        }
        return $novo_nome;
    }

    $id  = getPost('id_militar') ?? getPost('militarId');
    $cpf = getPost('cpf');
    
    if (!$cpf) {
        Response::error('O CPF é obrigatório.', 400);
    }

    $dir_uploads = __DIR__ . '/../uploads/';
    $dir_docs    = __DIR__ . '/../uploads/documentos/';

    // Upload de Foto (MIME: Imagens)
    $foto_path = validar_upload('foto', ['jpg','jpeg','png','webp'], ['image/jpeg','image/png','image/webp'], $dir_uploads, 'foto_');
    $sqlFoto = $foto_path ? ", foto_path = :foto" : "";

    // Upload de CNH (MIME: PDF)
    $pdf_cnh = validar_upload('pdf_habilitacao', ['pdf'], ['application/pdf'], $dir_docs, 'cnh_');
    $sqlCnh = $pdf_cnh ? ", pdf_habilitacao = :pdf_cnh" : "";

    // Upload de Nada Consta (MIME: PDF)
    $pdf_nada_consta = validar_upload('pdf_nada_consta', ['pdf'], ['application/pdf'], $dir_docs, 'nc_');
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

    $acaoAuditoria = 'CREATE_MILITAR';
    $identificador = $cpf;

    if ($id) {
        $acaoAuditoria = 'UPDATE_MILITAR';
        $dados[':id'] = $id;
        if ($foto_path)       $dados[':foto']            = $foto_path;
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
        $dados[':foto']            = $foto_path;
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
    
    // Registro na Trilha de Auditoria
    $acaoDetalhes = json_encode([
        'cpf' => $cpf,
        'nome_guerra' => getPost('nome_guerra'),
        'posto_grad' => getPost('posto_grad')
    ]);
    AuditLogger::log($acaoAuditoria, "Registro de militar processado. Dados: $acaoDetalhes");

    Response::json(null, 'Salvo com sucesso!');

} catch (Exception $e) {
    error_log('[SISMIL] Erro ao salvar militar: ' . $e->getMessage());
    Response::error('Falha ao processar o formulário. ' . $e->getMessage(), 400);
}