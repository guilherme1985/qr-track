<?php
declare(strict_types=1);

/**
 * Extrai símbolos do sprite Tabler em /public/assets/icons/sprite.svg
 * e gera PNGs individuais em /public/assets/qr-icons/{nome}.png pra
 * serem usados como logo no centro dos QR codes.
 *
 * Uso:
 *   php bin/build-qr-icons.php
 *
 * Idempotente. Sobrescreve PNGs existentes.
 *
 * Conversor (em ordem de preferência):
 *   1. rsvg-convert (librsvg2-bin)
 *   2. ImageMagick CLI (convert)
 *   3. Imagick extension PHP
 */

require __DIR__ . '/../src/Bootstrap.php';
\ArkhamFiles\Bootstrap::init(dirname(__DIR__));

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$rootDir   = dirname(__DIR__);
$spriteFile = $rootDir . '/public/assets/icons/sprite.svg';
$outDir     = $rootDir . '/public/assets/qr-icons';

if (!is_file($spriteFile)) {
    fwrite(STDERR, "✗ Sprite não encontrado: {$spriteFile}\n");
    exit(1);
}

if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

// Detecta conversor
$converter = null;
exec('which rsvg-convert 2>/dev/null', $out, $rc);
if ($rc === 0 && $out !== []) {
    $converter = 'rsvg-convert';
} else {
    exec('which convert 2>/dev/null', $out2, $rc2);
    if ($rc2 === 0 && $out2 !== []) {
        $converter = 'imagemagick';
    } elseif (extension_loaded('imagick')) {
        $converter = 'imagick-php';
    }
}

if ($converter === null) {
    fwrite(STDERR, "✗ Nenhum conversor SVG→PNG disponível.\n");
    fwrite(STDERR, "  Instale: sudo apt-get install librsvg2-bin\n");
    fwrite(STDERR, "       ou: sudo apt-get install imagemagick\n");
    fwrite(STDERR, "       ou: sudo apt-get install php8.3-imagick\n");
    exit(1);
}

fwrite(STDOUT, "Usando conversor: {$converter}\n\n");

$sprite = file_get_contents($spriteFile);
preg_match_all(
    '#<symbol\s+id="i-([a-z0-9-]+)"\s+viewBox="([^"]+)"[^>]*>(.*?)</symbol>#is',
    $sprite,
    $matches,
    PREG_SET_ORDER
);

$count = 0;
$failures = [];

foreach ($matches as $m) {
    $name = $m[1];
    $viewBox = $m[2];
    $content = trim($m[3]);

    $standalone = <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="{$viewBox}" width="200" height="200">
<rect width="100%" height="100%" fill="white"/>
<g fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
{$content}
</g>
</svg>
SVG;

    $svgTmp = tempnam(sys_get_temp_dir(), 'qricon_');
    file_put_contents($svgTmp, $standalone);
    $pngOut = "{$outDir}/{$name}.png";

    $success = false;
    if ($converter === 'rsvg-convert') {
        exec("rsvg-convert -w 200 -h 200 -b white -o " . escapeshellarg($pngOut) . " " . escapeshellarg($svgTmp) . " 2>&1", $cmdOut, $cmdRc);
        $success = ($cmdRc === 0 && is_file($pngOut));
    } elseif ($converter === 'imagemagick') {
        exec("convert -background white " . escapeshellarg($svgTmp) . " " . escapeshellarg($pngOut) . " 2>&1", $cmdOut, $cmdRc);
        $success = ($cmdRc === 0 && is_file($pngOut));
    } elseif ($converter === 'imagick-php') {
        try {
            $im = new Imagick();
            $im->setBackgroundColor('white');
            $im->readImageBlob($standalone);
            $im->setImageFormat('png32');
            $im->writeImage($pngOut);
            $im->clear();
            $success = is_file($pngOut);
        } catch (\Throwable $e) {
            $failures[] = "{$name}: {$e->getMessage()}";
        }
    }

    @unlink($svgTmp);

    if ($success) {
        $count++;
        fwrite(STDOUT, "  ✓ {$name}.png\n");
    } else {
        $failures[] = $name;
        fwrite(STDOUT, "  ✗ {$name} FALHOU\n");
    }
}

fwrite(STDOUT, "\n✓ Gerados {$count} ícones em {$outDir}/\n");
if ($failures !== []) {
    exit(1);
}
