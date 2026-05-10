<?php
declare(strict_types=1);

namespace ArkhamFiles;

/**
 * Helper pra renderizar ícones do sprite SVG.
 *
 * O sprite vive em /assets/icons/sprite.svg e é incluído inline no
 * <body> uma vez por request (via Icon::sprite()), assim cada uso
 * via <use href="#i-name"> não dispara request HTTP separada.
 *
 * Em templates, prefira a função global `icon()` definida em helpers.php.
 */
final class Icon
{
    private static ?string $spriteContent = null;

    /**
     * Lê o sprite do disco e retorna seu HTML (a tag <svg style="display:none">).
     * Inclua isso uma vez no <body>, idealmente logo após <body>.
     */
    public static function sprite(): string
    {
        if (self::$spriteContent === null) {
            $path = dirname(__DIR__) . '/public/assets/icons/sprite.svg';
            if (!is_readable($path)) {
                self::$spriteContent = '';
                return '';
            }
            $content = (string) file_get_contents($path);
            // Remove a declaração XML — não pode aparecer no meio de um documento HTML
            $content = preg_replace('/<\?xml[^?]*\?>/', '', $content);
            self::$spriteContent = $content ?? '';
        }
        return self::$spriteContent;
    }

    /**
     * Renderiza um ícone do sprite via <svg><use/></svg>.
     *
     * @param string $name Nome sem o prefixo "i-" (ex: 'leaf', 'folder')
     * @param string $extraClass Classes CSS adicionais (ex: 'af-icon--lg af-phosphor')
     * @param array<string,string> $attrs Atributos HTML extras
     */
    public static function render(string $name, string $extraClass = '', array $attrs = []): string
    {
        $name = preg_replace('/[^a-z0-9-]/', '', strtolower($name)) ?? '';
        if ($name === '') {
            return '';
        }

        $classes = trim('af-icon ' . $extraClass);
        $attrParts = ['class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '"'];

        $hasAriaLabel = false;
        foreach ($attrs as $k => $v) {
            $attrParts[] = htmlspecialchars($k, ENT_QUOTES, 'UTF-8')
                . '="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '"';
            if (strtolower($k) === 'aria-label') {
                $hasAriaLabel = true;
            }
        }
        if (!$hasAriaLabel) {
            $attrParts[] = 'aria-hidden="true"';
        }

        return sprintf(
            '<svg %s><use href="#i-%s"/></svg>',
            implode(' ', $attrParts),
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
        );
    }
}
