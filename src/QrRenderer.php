<?php
declare(strict_types=1);

namespace ArkhamFiles;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;

/**
 * Renderizador de QR codes — gera SVG e PNG pra URLs públicas
 * `/p/{public_id}` dos QRs do sistema.
 *
 * Configuração:
 *
 *   - Error correction High (30%) — necessário pra acomodar logo no
 *     centro sem perder leitura. URLs do sistema são curtas, sem custo
 *     visual relevante.
 *
 *   - Preto sobre branco — máxima compatibilidade com scanners. Custom
 *     colors sacrificam taxa de leitura em scanners velhos.
 *
 *   - Logo opcional no centro: hoje é o ícone da categoria, redimensionado
 *     pra 22-25% da área (limite seguro pra Reed-Solomon nível H).
 *
 *   - Margem (quiet zone) 2 módulos — padrão recomendado pra impressão.
 *
 * URLs geradas têm formato `https://qrtrack.arkhamcloud.net/p/xxxx-xx`
 * (~46 chars), bem dentro do limite que o nível H suporta.
 */
final class QrRenderer
{
    /**
     * Tamanhos pré-definidos:
     *   - small   → 300px (PNG inline em emails/documentos)
     *   - medium  → 600px (default da página de visualização admin)
     *   - large   → 1200px (impressão alta resolução)
     */
    public const SIZE_SMALL  = 300;
    public const SIZE_MEDIUM = 600;
    public const SIZE_LARGE  = 1200;

    /**
     * Margem em "módulos" — cada módulo é um quadrado do QR. 2 é o mínimo
     * recomendado pelo padrão pra leitura confiável.
     */
    private const MARGIN_MODULES = 2;

    /**
     * Gera o QR e retorna o conteúdo binário/textual.
     *
     * @param string $url        URL completa que o QR codifica
     * @param 'png'|'svg' $format
     * @param int $size          tamanho em pixels (use SIZE_* ou custom)
     * @param string|null $logoPath  caminho absoluto pra logo (PNG/SVG) ou null
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

        $builderArgs = [
            'writer'               => $writer,
            'data'                 => $url,
            'encoding'             => new Encoding('UTF-8'),
            'errorCorrectionLevel' => ErrorCorrectionLevel::High,
            'size'                 => $size,
            'margin'               => self::MARGIN_MODULES * 4, // endroid usa px na margem
            'roundBlockSizeMode'   => RoundBlockSizeMode::Margin,
        ];

        // Logo só pra PNG — SVG com logo embedado tem problemas de
        // compatibilidade entre renderizadores (anchor positioning,
        // namespace, etc). Em SVG queremos pureza máxima.
        if ($logoPath !== null && $format === 'png' && is_file($logoPath)) {
            $logoSize = (int) round($size * 0.22); // 22% da área
            $builderArgs['logoPath']             = $logoPath;
            $builderArgs['logoResizeToWidth']    = $logoSize;
            $builderArgs['logoResizeToHeight']   = $logoSize;  // necessário pra SVG logos
            $builderArgs['logoPunchoutBackground'] = true; // recorta o QR atrás do logo
        }

        $builder = new Builder(...$builderArgs);
        $result = $builder->build();

        return [
            'content'   => $result->getString(),
            'mime_type' => $result->getMimeType(),
        ];
    }

    /**
     * Constrói o caminho absoluto pra o ícone da categoria (se houver) usado
     * no centro do QR. Retorna null se a categoria não tem ícone, ou se
     * o arquivo do ícone não existe.
     *
     * Usa os SVGs do sprite Tabler que foram extraídos como PNG na fase
     * de "install assets" do app (caminho convencionado em
     * /public/assets/qr-icons/{nome}.png — pasta criada pelo PR 9.5).
     */
    public static function categoryIconPath(?Category $category, string $rootDir): ?string
    {
        if ($category === null || !$category->icon) {
            return null;
        }
        // Sanitize: aceita só [a-z0-9-]
        $iconName = preg_replace('/[^a-z0-9-]/', '', strtolower($category->icon));
        if ($iconName === '') return null;
        $path = $rootDir . '/public/assets/qr-icons/' . $iconName . '.png';
        return is_file($path) ? $path : null;
    }
}
