<?php
declare(strict_types=1);

namespace ArkhamFiles;

/**
 * Validação, processamento e armazenamento de uploads de imagem.
 *
 * Pipeline de upload:
 *   1. Validar erro de upload (UPLOAD_ERR_OK)
 *   2. Validar tamanho (<= MAX_BYTES = 5 MB)
 *   3. Detectar MIME real via finfo (NÃO confia no $_FILES[type]
 *      enviado pelo cliente — pode ser forjado)
 *   4. Validar MIME está na whitelist (JPEG/PNG/WebP)
 *   5. Carregar com getimagesize (segunda camada de validação real)
 *   6. Gerar hash do conteúdo → nome do arquivo (anti-colisão)
 *   7. Mover original pra /uploads/originals/{hash}.{ext}
 *   8. Gerar thumbnail 300x300 (fit, mantém aspect ratio) → /uploads/thumbs/{hash}.jpg
 *
 * Por que MIME real?
 *   - $_FILES['type'] vem do navegador, fácil de adulterar via curl
 *   - finfo + getimagesize abrem o arquivo de fato e verificam magic bytes
 *   - Se um PHP shell renomeado pra .jpg subir, esses checks rejeitam
 *
 * Layout de pastas:
 *   /uploads/originals/{hash}.{ext}   — imagem original (servida pelo nginx)
 *   /uploads/thumbs/{hash}.jpg        — thumbnail 300x300 JPEG (admin grid)
 *
 * Hash: SHA-256 do conteúdo, primeiros 16 hex chars. Anti-colisão
 * suficiente pro volume (16⁶⁴ combinações) e curto o bastante pra URL.
 *
 * Permissões de arquivo: 0644 (legível por nginx/www-data).
 */
final class ImageUpload
{
    /** Limite hard de tamanho — bate com schema 0004 e nginx client_max_body_size */
    public const MAX_BYTES = 5_242_880; // 5 MB

    /** MIME types aceitos (validação por magic bytes, não Content-Type) */
    public const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** Dimensão alvo do thumbnail (caixa quadrada, fit dentro) */
    public const THUMB_SIZE = 300;

    /** Qualidade JPEG do thumbnail (0-100). 82 é o sweet spot tamanho/qualidade. */
    public const THUMB_QUALITY = 82;

    /**
     * Processa um $_FILES entry. Retorna os metadados pra inserir em
     * image_metadata, ou levanta DomainException se inválido.
     *
     * @param array{name?: string, type?: string, tmp_name?: string,
     *              error?: int, size?: int} $file  (entrada do $_FILES['campo'])
     * @param string $uploadsDir  Diretório base (espera ter subdirs originals/ e thumbs/)
     *
     * @return array{
     *   file_path: string,
     *   thumbnail_path: string,
     *   mime_type: string,
     *   file_size: int,
     *   width: int,
     *   height: int,
     *   original_filename: string,
     * }
     */
    public static function process(array $file, string $uploadsDir): array
    {
        // 1. Erro de upload
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new \DomainException(self::uploadErrorMessage((int) $error));
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            // is_uploaded_file: segurança extra — só aceita arquivos
            // vindos de upload HTTP real, não paths injetados
            throw new \DomainException('Upload inválido.');
        }

        // 2. Tamanho
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new \DomainException('Arquivo vazio.');
        }
        if ($size > self::MAX_BYTES) {
            $mb = number_format(self::MAX_BYTES / 1024 / 1024, 1);
            throw new \DomainException("Arquivo excede o limite de {$mb} MB.");
        }

        // 3. MIME real via finfo (NÃO confia em $file['type'])
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) $finfo->file($tmpName);
        if (!isset(self::ALLOWED_MIMES[$detectedMime])) {
            throw new \DomainException(
                'Formato não permitido. Aceitos: JPEG, PNG, WebP.'
            );
        }
        $extension = self::ALLOWED_MIMES[$detectedMime];

        // 4. getimagesize — segunda camada (rejeita arquivos
        // com magic bytes válidos mas estrutura corrompida)
        $info = @getimagesize($tmpName);
        if ($info === false) {
            throw new \DomainException('Arquivo de imagem inválido ou corrompido.');
        }
        $width  = (int) $info[0];
        $height = (int) $info[1];
        if ($width < 1 || $height < 1) {
            throw new \DomainException('Dimensões inválidas.');
        }

        // 5. Hash do conteúdo → nome único
        $hash = substr(hash_file('sha256', $tmpName), 0, 16);

        // 6. Garante que os diretórios existem
        $originalsDir = $uploadsDir . '/originals';
        $thumbsDir    = $uploadsDir . '/thumbs';
        foreach ([$originalsDir, $thumbsDir] as $d) {
            if (!is_dir($d)) {
                if (!mkdir($d, 0755, true) && !is_dir($d)) {
                    throw new \RuntimeException("Falha ao criar diretório: {$d}");
                }
            }
        }

        // 7. Move original
        $originalRelative  = "originals/{$hash}.{$extension}";
        $originalAbsolute  = $uploadsDir . '/' . $originalRelative;
        if (!move_uploaded_file($tmpName, $originalAbsolute)) {
            throw new \RuntimeException('Falha ao salvar imagem.');
        }
        @chmod($originalAbsolute, 0644);

        // 8. Gera thumbnail
        $thumbRelative = "thumbs/{$hash}.jpg";
        $thumbAbsolute = $uploadsDir . '/' . $thumbRelative;
        try {
            self::generateThumbnail($originalAbsolute, $thumbAbsolute, $detectedMime);
        } catch (\Throwable $e) {
            // Se thumb falhar, limpa o original pra não deixar lixo
            @unlink($originalAbsolute);
            throw new \RuntimeException('Falha ao gerar thumbnail: ' . $e->getMessage());
        }
        @chmod($thumbAbsolute, 0644);

        return [
            'file_path'         => $originalRelative,
            'thumbnail_path'    => $thumbRelative,
            'mime_type'         => $detectedMime,
            'file_size'         => $size,
            'width'             => $width,
            'height'            => $height,
            'original_filename' => self::sanitizeFilename((string) ($file['name'] ?? '')),
        ];
    }

    /**
     * Apaga arquivos do disco — chamado por ImageQr::deleteFiles() em
     * hard-delete.
     */
    public static function deleteFiles(string $uploadsDir, string $filePath, string $thumbnailPath): void
    {
        $original = $uploadsDir . '/' . ltrim($filePath, '/');
        $thumb    = $uploadsDir . '/' . ltrim($thumbnailPath, '/');
        if (is_file($original)) @unlink($original);
        if (is_file($thumb))    @unlink($thumb);
    }

    /**
     * Gera thumbnail 300x300 usando GD. Mantém aspect ratio (fit dentro
     * da caixa), centraliza em fundo branco se necessário. Salva como JPEG.
     */
    private static function generateThumbnail(string $sourceFile, string $destFile, string $mime): void
    {
        // Carrega o source de acordo com o tipo
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourceFile),
            'image/png'  => @imagecreatefrompng($sourceFile),
            'image/webp' => @imagecreatefromwebp($sourceFile),
            default      => false,
        };
        if ($src === false) {
            throw new \RuntimeException('GD não conseguiu abrir a imagem.');
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $boxSize = self::THUMB_SIZE;

        // Calcula proporção mantendo aspect ratio (fit dentro da caixa)
        $ratio = min($boxSize / $srcW, $boxSize / $srcH);
        $newW  = (int) round($srcW * $ratio);
        $newH  = (int) round($srcH * $ratio);

        // Cria canvas quadrado com fundo (preto bate com tema escuro do app)
        $thumb = imagecreatetruecolor($boxSize, $boxSize);
        $bg = imagecolorallocate($thumb, 16, 16, 16); // var(--af-bg) ≈ #101010
        imagefilledrectangle($thumb, 0, 0, $boxSize, $boxSize, $bg);

        // Centraliza
        $offsetX = (int) (($boxSize - $newW) / 2);
        $offsetY = (int) (($boxSize - $newH) / 2);

        imagecopyresampled(
            $thumb, $src,
            $offsetX, $offsetY, 0, 0,
            $newW, $newH, $srcW, $srcH
        );

        if (!imagejpeg($thumb, $destFile, self::THUMB_QUALITY)) {
            imagedestroy($src);
            imagedestroy($thumb);
            throw new \RuntimeException('imagejpeg falhou ao escrever.');
        }

        imagedestroy($src);
        imagedestroy($thumb);
    }

    /**
     * Sanitiza nome de arquivo original pra exibir no admin/viewer sem
     * abrir vetor de XSS. Aceita até 100 chars.
     */
    private static function sanitizeFilename(string $name): string
    {
        $name = mb_substr($name, 0, 100, 'UTF-8');
        // Remove path traversal e caracteres de controle
        $name = preg_replace('/[\x00-\x1f\x7f]/', '', $name) ?? '';
        $name = str_replace(['/', '\\', "\0"], '', $name);
        $name = trim($name);
        return $name !== '' ? $name : 'imagem';
    }

    /**
     * Traduz códigos UPLOAD_ERR_* em mensagens legíveis em PT-BR.
     */
    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o limite permitido.',
            UPLOAD_ERR_PARTIAL   => 'Upload incompleto — tente novamente.',
            UPLOAD_ERR_NO_FILE   => 'Nenhum arquivo enviado.',
            UPLOAD_ERR_NO_TMP_DIR=> 'Erro no servidor: diretório temporário ausente.',
            UPLOAD_ERR_CANT_WRITE=> 'Erro no servidor: não foi possível gravar o arquivo.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão PHP.',
            default              => 'Erro no upload (código ' . $code . ').',
        };
    }

    /**
     * Formata bytes em string legível (1.5 MB, 250 KB...).
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 1) . ' KB';
        return number_format($bytes / 1024 / 1024, 1) . ' MB';
    }
}
