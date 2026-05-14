<?php
declare(strict_types=1);

namespace ArkhamFiles;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Renderizador de QR codes — gera SVG e PNG pra URLs públicas
 * `/p/{public_id}` dos QRs do sistema.
 *
 * Implementação compatível com endroid/qr-code 4.x — usa a API fluent
 * (`Builder::create()->...->build()`) que existe em todas as versões
 * 4.x e nas primeiras 5.x.
 *
 * Configuração:
 *   - Error correction High (30%) — pra acomodar logo sem perder leitura
 *   - Preto sobre branco — máxima compatibilidade com scanners
 *   - Logo da categoria opcional no centro (PNG, 22% da área, com punchout)
 *   - Margem 2 módulos (quiet zone padrão pra impressão)
 */
final class QrRenderer
{
    public const SIZE_SMALL  = 300;
    public const SIZE_MEDIUM = 600;
    public const SIZE_LARGE  = 1200;

    private const MARGIN_PX = 8; // 2 módulos × 4 px

    /**
     * Gera o QR e retorna o conteúdo binário/textual.
     *
     * @param string $url        URL completa que o QR codifica
     * @param 'png'|'svg' $format
     * @param int $size          tamanho em pixels (use SIZE_* ou custom)
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
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size($size)
            ->margin(self::MARGIN_PX)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin());

        // Logo só pra PNG — SVG com logo embedado tem bugs de namespace
        if ($logoPath !== null && $format === 'png' && is_file($logoPath)) {
            $logoSize = (int) round($size * 0.22);
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
