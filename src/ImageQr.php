<?php
declare(strict_types=1);

namespace ArkhamFiles;

use PDO;

/**
 * Helper para QRs do tipo 'image'.
 *
 * Cada imagem QR tem:
 *   - Um registro em qrcodes (type='image') — gerenciado por QrCode
 *   - Um registro em image_metadata — gerenciado aqui
 *   - 2 arquivos físicos em /uploads/originals/ e /uploads/thumbs/
 *
 * Operações de create/update são transacionais (DB), mas o **arquivo no
 * disco é processado ANTES** da transação. Se a transação rollar, o
 * arquivo fica órfão até a próxima limpeza manual. Aceitável porque:
 *   1. Falha de DB é raríssima (SQLite local em LXC)
 *   2. Arquivo órfão não polui DB nem viewer público
 *   3. Audit log permite identificar o que foi órfão pra limpeza
 */
final class ImageQr
{
    public function __construct(
        public readonly int $qrId,
        public readonly string $filePath,
        public readonly string $thumbnailPath,
        public readonly string $mimeType,
        public readonly int $fileSize,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?string $originalFilename,
        public readonly string $uploadedAt,
    ) {}

    public static function findByQrId(int $qrId): ?self
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM image_metadata WHERE qr_id = :q'
        );
        $stmt->execute([':q' => $qrId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return new self(
            qrId:             (int) $row['qr_id'],
            filePath:         (string) $row['file_path'],
            thumbnailPath:    (string) $row['thumbnail_path'],
            mimeType:         (string) $row['mime_type'],
            fileSize:         (int) $row['file_size'],
            width:            isset($row['width'])  ? (int) $row['width']  : null,
            height:           isset($row['height']) ? (int) $row['height'] : null,
            originalFilename: $row['original_filename'] ?: null,
            uploadedAt:       (string) $row['uploaded_at'],
        );
    }

    /**
     * Cria QR do tipo 'image' + metadata. O caller é responsável por
     * processar o upload (via ImageUpload::process) ANTES e passar os
     * metadados aqui.
     *
     * @param array{file_path: string, thumbnail_path: string,
     *              mime_type: string, file_size: int,
     *              width: int, height: int, original_filename: string} $imageData
     *
     * @return array{id: int, public_id: string}
     */
    public static function create(
        string $title,
        array $imageData,
        ?int $categoryId,
        ?int $createdBy,
        ?string $expiresAt = null,
    ): array {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $qr = QrCode::create(
                type:        'image',
                title:       $title,
                categoryId:  $categoryId,
                createdBy:   $createdBy,
                expiresAt:   $expiresAt,
                payload:     null,
            );

            $pdo->prepare('
                INSERT INTO image_metadata
                  (qr_id, file_path, thumbnail_path, mime_type,
                   file_size, width, height, original_filename)
                VALUES (:q, :fp, :tp, :mt, :sz, :w, :h, :fn)
            ')->execute([
                ':q'  => $qr['id'],
                ':fp' => $imageData['file_path'],
                ':tp' => $imageData['thumbnail_path'],
                ':mt' => $imageData['mime_type'],
                ':sz' => $imageData['file_size'],
                ':w'  => $imageData['width'],
                ':h'  => $imageData['height'],
                ':fn' => $imageData['original_filename'],
            ]);

            $pdo->commit();
            return $qr;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Atualiza só os campos editáveis do QR (título, categoria, expiração).
     * A imagem em si não pode ser trocada — pra mudar, exclui e cria nova.
     * Decisão de produto: simplifica fluxo + preserva integridade (URLs
     * estáveis quando o conteúdo é o mesmo).
     */
    public static function updateMetadata(
        int $qrId,
        string $title,
        ?int $categoryId,
        ?string $expiresAt,
        ?bool $isDisabled = null,
    ): void {
        QrCode::update(
            id:         $qrId,
            title:      $title,
            categoryId: $categoryId,
            expiresAt:  $expiresAt,
            isDisabled: $isDisabled,
        );
    }

    /**
     * Hard-delete do QR + arquivos do disco. Chamado pelo handler após
     * confirmação por digitação. Cascade FK apaga image_metadata
     * automaticamente; este método só limpa os arquivos físicos.
     */
    public static function hardDeleteWithFiles(int $qrId, string $uploadsDir): void
    {
        $img = self::findByQrId($qrId);
        QrCode::hardDelete($qrId);
        if ($img !== null) {
            ImageUpload::deleteFiles($uploadsDir, $img->filePath, $img->thumbnailPath);
        }
    }

    /**
     * Constrói a URL pública pra acessar o original (servido pelo nginx
     * direto de /uploads/originals/). Estática — relativa ao webroot.
     */
    public function originalUrl(): string
    {
        return '/uploads/' . ltrim($this->filePath, '/');
    }

    public function thumbnailUrl(): string
    {
        return '/uploads/' . ltrim($this->thumbnailPath, '/');
    }

    /**
     * Aspect ratio textual ("16:9", "3:2"...) pra exibir no metadata.
     * Retorna null se as dimensões não estiverem disponíveis.
     */
    public function aspectRatio(): ?string
    {
        if (!$this->width || !$this->height) return null;
        $g = self::gcd($this->width, $this->height);
        $w = (int) ($this->width / $g);
        $h = (int) ($this->height / $g);
        // Reduz razões muito específicas (ex: 1920:1080 vira 16:9)
        if ($w > 50 || $h > 50) {
            return $this->width . '×' . $this->height;
        }
        return "{$w}:{$h}";
    }

    public function dimensionsLabel(): string
    {
        if (!$this->width || !$this->height) return '—';
        return $this->width . ' × ' . $this->height . ' px';
    }

    public function fileSizeLabel(): string
    {
        return ImageUpload::formatBytes($this->fileSize);
    }

    public function formatLabel(): string
    {
        return match ($this->mimeType) {
            'image/jpeg' => 'JPEG',
            'image/png'  => 'PNG',
            'image/webp' => 'WebP',
            default      => $this->mimeType,
        };
    }

    private static function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : self::gcd($b, $a % $b);
    }
}
