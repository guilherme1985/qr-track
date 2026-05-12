<?php
declare(strict_types=1);

namespace ArkhamFiles;

use PDO;

/**
 * Helper para QRs do tipo 'strain' (dossier botânico de cultivo).
 *
 * Cada strain tem:
 *   - Um registro em qrcodes (type='strain') — gerenciado por QrCode
 *   - Um registro em strain_metadata (dados específicos) — gerenciado aqui
 *
 * Esquema do strain_metadata (já existe desde PR 01):
 *   strain_name     TEXT      — nome popular (ex: "Northern Lights #5")
 *   source          enum      — 'semente' | 'clone'
 *   genetics        enum      — 'indica' | 'sativa' | 'hibrida'
 *   seed_type       enum NULL — 'regular' | 'feminizada' | 'automatica'
 *                                (só faz sentido se source='semente')
 *   planting_date   DATE NULL — plantio
 *   flowering_date  DATE NULL — início da floração
 *   harvest_date    DATE NULL — colheita
 *
 * Cálculo derivado (no model, não no banco):
 *   - Dias em vegetação = (flowering_date - planting_date) ou (today - planting_date)
 *   - Dias em floração  = (harvest_date - flowering_date) ou (today - flowering_date)
 *   - Ciclo total       = planting_date até harvest_date (ou hoje)
 */
final class Strain
{
    public const SOURCES   = ['semente', 'clone'];
    public const GENETICS  = ['indica', 'sativa', 'hibrida'];
    public const SEED_TYPES = ['regular', 'feminizada', 'automatica'];

    public function __construct(
        public readonly int $qrId,
        public readonly string $strainName,
        public readonly string $source,
        public readonly string $genetics,
        public readonly ?string $seedType,
        public readonly ?string $plantingDate,
        public readonly ?string $floweringDate,
        public readonly ?string $harvestDate,
        public readonly string $updatedAt,
    ) {}

    public static function findByQrId(int $qrId): ?self
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM strain_metadata WHERE qr_id = :q'
        );
        $stmt->execute([':q' => $qrId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return new self(
            qrId:          (int) $row['qr_id'],
            strainName:    (string) $row['strain_name'],
            source:        (string) $row['source'],
            genetics:      (string) $row['genetics'],
            seedType:      $row['seed_type']      ?: null,
            plantingDate:  $row['planting_date']  ?: null,
            floweringDate: $row['flowering_date'] ?: null,
            harvestDate:   $row['harvest_date']   ?: null,
            updatedAt:     (string) $row['updated_at'],
        );
    }

    /**
     * Cria um novo QR do tipo 'strain' + registro em strain_metadata.
     * Operação transacional: se uma falhar, desfaz tudo.
     *
     * @param array{
     *   strain_name: string,
     *   source: 'semente'|'clone',
     *   genetics: 'indica'|'sativa'|'hibrida',
     *   seed_type?: 'regular'|'feminizada'|'automatica'|null,
     *   planting_date?: string|null,
     *   flowering_date?: string|null,
     *   harvest_date?: string|null,
     * } $strainData
     *
     * @return array{id: int, public_id: string}
     */
    public static function create(
        string $title,
        array $strainData,
        ?int $categoryId,
        ?int $createdBy,
        ?string $expiresAt = null,
    ): array {
        self::validate($strainData);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $qr = QrCode::create(
                type:        'strain',
                title:       $title,
                categoryId:  $categoryId,
                createdBy:   $createdBy,
                expiresAt:   $expiresAt,
                payload:     null,
            );

            $pdo->prepare('
                INSERT INTO strain_metadata
                  (qr_id, strain_name, source, genetics, seed_type,
                   planting_date, flowering_date, harvest_date)
                VALUES (:q, :n, :s, :g, :st, :pd, :fd, :hd)
            ')->execute([
                ':q'  => $qr['id'],
                ':n'  => $strainData['strain_name'],
                ':s'  => $strainData['source'],
                ':g'  => $strainData['genetics'],
                ':st' => self::resolveSeedType($strainData),
                ':pd' => $strainData['planting_date']  ?? null,
                ':fd' => $strainData['flowering_date'] ?? null,
                ':hd' => $strainData['harvest_date']   ?? null,
            ]);

            $pdo->commit();
            return $qr;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Atualiza campos editáveis do strain.
     */
    public static function update(
        int $qrId,
        string $title,
        array $strainData,
        ?int $categoryId,
        ?string $expiresAt,
        ?bool $isDisabled = null,
    ): void {
        self::validate($strainData);

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
                UPDATE strain_metadata
                   SET strain_name = :n, source = :s, genetics = :g,
                       seed_type = :st, planting_date = :pd,
                       flowering_date = :fd, harvest_date = :hd,
                       updated_at = CURRENT_TIMESTAMP
                 WHERE qr_id = :q
            ')->execute([
                ':n'  => $strainData['strain_name'],
                ':s'  => $strainData['source'],
                ':g'  => $strainData['genetics'],
                ':st' => self::resolveSeedType($strainData),
                ':pd' => $strainData['planting_date']  ?? null,
                ':fd' => $strainData['flowering_date'] ?? null,
                ':hd' => $strainData['harvest_date']   ?? null,
                ':q'  => $qrId,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Valida regras de negócio (campos enum + datas plausíveis +
     * seed_type só com source=semente).
     *
     * @throws \DomainException
     */
    private static function validate(array $data): void
    {
        $name = trim((string) ($data['strain_name'] ?? ''));
        if ($name === '') {
            throw new \DomainException('Nome da strain é obrigatório.');
        }
        if (mb_strlen($name) > 100) {
            throw new \DomainException('Nome da strain excede 100 caracteres.');
        }

        if (!in_array($data['source'] ?? '', self::SOURCES, true)) {
            throw new \DomainException('Origem inválida (use semente ou clone).');
        }
        if (!in_array($data['genetics'] ?? '', self::GENETICS, true)) {
            throw new \DomainException('Genética inválida.');
        }

        $seedType = $data['seed_type'] ?? null;
        if ($seedType !== null && $seedType !== '' && !in_array($seedType, self::SEED_TYPES, true)) {
            throw new \DomainException('Tipo de semente inválido.');
        }

        // Datas: formato YYYY-MM-DD se presente
        foreach (['planting_date', 'flowering_date', 'harvest_date'] as $key) {
            $val = $data[$key] ?? null;
            if ($val !== null && $val !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                throw new \DomainException("Data {$key} inválida (use YYYY-MM-DD).");
            }
        }

        // Ordem cronológica: plantio ≤ floração ≤ colheita
        $p = $data['planting_date'] ?? null;
        $f = $data['flowering_date'] ?? null;
        $h = $data['harvest_date'] ?? null;
        if ($p && $f && $f < $p) {
            throw new \DomainException('Data de floração não pode ser anterior ao plantio.');
        }
        if ($f && $h && $h < $f) {
            throw new \DomainException('Data de colheita não pode ser anterior à floração.');
        }
        if ($p && $h && $h < $p) {
            throw new \DomainException('Data de colheita não pode ser anterior ao plantio.');
        }
    }

    /**
     * seed_type só faz sentido se source = 'semente'. Quando vira clone,
     * forçamos NULL pra manter integridade dos dados.
     */
    private static function resolveSeedType(array $data): ?string
    {
        if (($data['source'] ?? '') !== 'semente') {
            return null;
        }
        $st = $data['seed_type'] ?? null;
        return ($st !== null && $st !== '') ? $st : null;
    }

    // ------------------------------------------------------------------
    // Cálculos derivados (timeline)
    // ------------------------------------------------------------------

    /**
     * Dias em vegetação. Retorna null se sem plantio.
     * Se já floresceu, conta até floração. Senão, até hoje (ou até
     * colheita se foi colhido antes da floração — caso raro).
     */
    public function daysInVeg(): ?int
    {
        if (!$this->plantingDate) return null;
        $endDate = $this->floweringDate ?? $this->harvestDate ?? gmdate('Y-m-d');
        return self::daysBetween($this->plantingDate, $endDate);
    }

    /**
     * Dias em floração. Retorna null se ainda não floresceu.
     */
    public function daysInFlower(): ?int
    {
        if (!$this->floweringDate) return null;
        $endDate = $this->harvestDate ?? gmdate('Y-m-d');
        return self::daysBetween($this->floweringDate, $endDate);
    }

    /**
     * Ciclo total. Retorna null se sem plantio.
     */
    public function totalCycleDays(): ?int
    {
        if (!$this->plantingDate) return null;
        $endDate = $this->harvestDate ?? gmdate('Y-m-d');
        return self::daysBetween($this->plantingDate, $endDate);
    }

    /**
     * Estado atual no ciclo: 'planted' (plantado, aguardando floração),
     * 'flowering' (em floração), 'harvested' (colhido), 'unknown' (sem datas).
     */
    public function lifecyclePhase(): string
    {
        if ($this->harvestDate)   return 'harvested';
        if ($this->floweringDate) return 'flowering';
        if ($this->plantingDate)  return 'planted';
        return 'unknown';
    }

    private static function daysBetween(string $start, string $end): int
    {
        $s = strtotime($start);
        $e = strtotime($end);
        if ($s === false || $e === false) return 0;
        return (int) round(($e - $s) / 86400);
    }
}
