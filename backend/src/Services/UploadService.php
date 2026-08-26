<?php
namespace Sismil\Services;

use Exception;

/**
 * Serviço de Upload de Arquivos.
 * Concentra as regras de segurança e validação de MIME type para o SISMIL.
 */
class UploadService {

    /**
     * Realiza a validação rigorosa de upload de arquivo analisando o tipo MIME real.
     *
     * @param string $file_key Chave do arquivo na superglobal $_FILES.
     * @param array<string> $extensoes_permitidas Extensões de arquivo autorizadas.
     * @param array<string> $mimes_permitidos Tipos MIME validados via magic bytes.
     * @param string $dir Diretório de destino no servidor.
     * @param string $prefixo Prefixo para o nome seguro gerado.
     * @return string|null Nome do arquivo salvo ou null caso nenhum arquivo tenha sido enviado.
     * @throws Exception Se o arquivo não atender aos requisitos de segurança ou falhar ao mover.
     */
    public function validarEProcessarUpload(string $file_key, array $extensoes_permitidas, array $mimes_permitidos, string $dir, string $prefixo): ?string {
        if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($_FILES[$file_key]['error'] === UPLOAD_ERR_INI_SIZE || $_FILES[$file_key]['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new Exception("O arquivo enviado para '{$file_key}' é muito grande (excede o limite do servidor). Envie uma imagem de até 2MB.");
        }

        if ($_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erro durante o envio do arquivo '{$file_key}' (Código {$_FILES[$file_key]['error']}).");
        }
        
        $file = $_FILES[$file_key];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $extensoes_permitidas, true)) {
            throw new Exception("Tipo de arquivo inválido para '{$file_key}'. Permitido: " . implode(', ', $extensoes_permitidas));
        }

        // Validação profunda com Magic Bytes
        $mime_real = mime_content_type($file['tmp_name']);
        // Aceita variações conhecidas de mime-types de imagem
        $mimes_expandidos = array_merge($mimes_permitidos, [
            'image/pjpeg', 'image/x-png', 'image/webp', 'image/jpeg', 'image/png'
        ]);
        if (!in_array($mime_real, $mimes_expandidos, true)) {
            throw new Exception("Conteúdo de arquivo suspeito para '{$file_key}'. MIME detectado: {$mime_real}");
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext_segura = ($ext === 'pdf') ? 'pdf' : 'jpg'; // Normaliza extensões de imagem para jpg
        $novo_nome  = $prefixo . bin2hex(random_bytes(8)) . '.' . $ext_segura;

        if (!move_uploaded_file($file['tmp_name'], $dir . $novo_nome)) {
            throw new Exception("Falha de IO ao mover arquivo '{$file_key}'.");
        }
        
        return $novo_nome;
    }
}
