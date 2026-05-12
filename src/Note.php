<?php
declare(strict_types=1);

namespace ArkhamFiles;

use PDO;

/**
 * Helper para QRs do tipo 'note'.
 *
 * Cada nota tem:
 *   - Um registro em qrcodes (tipo='note') — gerenciado por QrCode
 *   - Um registro em note_metadata (markdown_content) — gerenciado aqui
 *
 * As operações create/update são em transação (atomic): se uma falhar,
 * desfaz tudo.
 *
 * Soft-delete em qrcodes não toca note_metadata. Hard-delete em qrcodes
 * cascateia pra note_metadata via FK ON DELETE CASCADE no schema.
 */
final class Note
{
    /**
     * Cria um novo QR do tipo 'note' com markdown_content. Retorna
     * o ID do QR e o public_id.
     *
     * @return array{id: int, public_id: string}
     */
    public static function create(
        string $title,
        string $markdownContent,
        ?int $categoryId,
        ?int $createdBy,
        ?string $expiresAt = null,
    ): array {
        self::validateMarkdownSize($markdownContent);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $qr = QrCode::create(
                type:        'note',
                title:       $title,
                categoryId:  $categoryId,
                createdBy:   $createdBy,
                expiresAt:   $expiresAt,
                payload:     null,  // notas usam note_metadata, não payload
            );

            $pdo->prepare('
                INSERT INTO note_metadata (qr_id, markdown_content)
                VALUES (:q, :m)
            ')->execute([
                ':q' => $qr['id'],
                ':m' => $markdownContent,
            ]);

            $pdo->commit();
            return $qr;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Atualiza título / categoria / expiração / markdown de uma nota
     * existente, em transação.
     */
    public static function update(
        int $qrId,
        string $title,
        string $markdownContent,
        ?int $categoryId,
        ?string $expiresAt,
        ?bool $isDisabled = null,
    ): void {
        self::validateMarkdownSize($markdownContent);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            QrCode::update(
                id:         $qrId,
                title:      $title,
                categoryId: $categoryId,
                expiresAt:  $expiresAt,
                isDisabled: $isDisabled,
            );

            $pdo->prepare('
                UPDATE note_metadata
                   SET markdown_content = :m, updated_at = CURRENT_TIMESTAMP
                 WHERE qr_id = :q
            ')->execute([
                ':m' => $markdownContent,
                ':q' => $qrId,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Carrega o markdown_content. Retorna string vazia se a nota não
     * existir (defensive default — caller já valida QR antes).
     */
    public static function getMarkdown(int $qrId): string
    {
        $stmt = Database::pdo()->prepare(
            'SELECT markdown_content FROM note_metadata WHERE qr_id = :q'
        );
        $stmt->execute([':q' => $qrId]);
        $value = $stmt->fetchColumn();
        return $value === false ? '' : (string) $value;
    }

    /**
     * Valida o tamanho do markdown. Lança DomainException se exceder.
     */
    private static function validateMarkdownSize(string $markdown): void
    {
        $bytes = strlen($markdown);
        if ($bytes > Markdown::MAX_LENGTH_BYTES) {
            $kb = number_format(Markdown::MAX_LENGTH_BYTES / 1024, 0);
            throw new \DomainException(
                "Conteúdo Markdown excede o limite de {$kb} KB"
            );
        }
    }
}
