<?php
declare(strict_types=1);

namespace ArkhamFiles;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Renderizador de QR codes — gera SVG e PNG pra URLs públicas
 * `/p/{public_id}` dos QRs do sistema.
 *
 * Implementação testada contra endroid/qr-code 5.1.0 — a versão usa:
 *   - Builder::create() fluent API (não constructor com args)
 *   - ErrorCorrectionLevel como enum (case ::High)
 *   - RoundBlockSizeMode como enum (case ::Margin)
 *
 * Configuração:
 *   - Error correction High (30%) — pra acomodar logo sem perder leitura
 *   - Preto sobre branco — máxima compatibilidade com scanners
 *   - Logo da categoria opcional no centro (PNG, 22% da área, com punchout)
 *   - Margem 8 px (= 2 módulos × 4 px, quiet zone padrão)
 */
final class QrRenderer
{
    public const SIZE_SMALL  = 300;
    public const SIZE_MEDIUM = 600;
    public const SIZE_LARGE  = 1200;

    private const MARGIN_PX = 8;

    /**
     * Gera o QR e retorna o conteúdo binário/textual.
     *
     * @param string $url       URL completa que o QR codifica
     * @param 'png'|'svg' $format
     * @param int $size         tamanho em pixels (use SIZE_* ou custom)
     * @param string|null $logoPath  caminho absoluto pra logo PNG ou null
     *
     * @return array{content: string, mime_type: string}
     */
    public static function render(
        string $url,
        string $format = 'svg',
        int $size = self::SIZE_MEDIUM,
        ?string $logoPath = null,
    ): array {
        $writer = match ($format) {
            'png'   => new PngWriter(),
            'svg'   => new SvgWriter(),
            default => throw new \InvalidArgumentException("Formato inválido: {$format}"),
        };

        $builder = Builder::create()
            ->writer($writer)
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size($size)
            ->margin(self::MARGIN_PX)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin);

        // Logo só pra PNG — SVG com logo embedado tem incompatibilidades
        // entre renderizadores (anchor/namespace). Em SVG queremos pureza.
        if ($logoPath !== null && $format === 'png' && is_file($logoPath)) {
            $logoSize = (int) round($size * 0.22); // 22% da área
            $builder = $builder
                ->logoPath($logoPath)
                ->logoResizeToWidth($logoSize)
                ->logoResizeToHeight($logoSize)
                ->logoPunchoutBackground(true);
        }

        $result = $builder->build();

        return [
            'content'   => $result->getString(),
            'mime_type' => $result->getMimeType(),
        ];
    }

    /**
     * Constrói o caminho absoluto pra o ícone da categoria (se houver) usado
     * no centro do QR. Retorna null se sem categoria ou ícone não existe.
     */
    public static function categoryIconPath(?Category $category, string $rootDir): ?string
    {
        if ($category === null || !$category->icon) {
            return null;
        }
        $iconName = preg_replace('/[^a-z0-9-]/', '', strtolower($category->icon));
        if ($iconName === '') return null;
        $path = $rootDir . '/public/assets/qr-icons/' . $iconName . '.png';
        return is_file($path) ? $path : null;
    }
}
