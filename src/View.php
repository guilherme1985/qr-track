<?php
declare(strict_types=1);

namespace ArkhamFiles;

/**
 * Template renderer leve, sem deps externas.
 *
 * Convenção:
 *   - templates ficam em /templates relativo à raiz do projeto
 *   - partials são chamados via View::partial('components/badge', [...])
 *   - layouts envolvem o conteúdo via View::renderWithLayout()
 *
 * Use a função global `e($var)` (definida em src/helpers.php) dentro
 * dos templates para escapar HTML. Variáveis nuas não são escapadas
 * automaticamente — decisão consciente para permitir partials/HTML.
 */
final class View
{
    private static ?string $templatesDir = null;

    public static function setTemplatesDir(string $dir): void
    {
        self::$templatesDir = rtrim($dir, '/');
    }

    private static function dir(): string
    {
        if (self::$templatesDir === null) {
            self::$templatesDir = dirname(__DIR__) . '/templates';
        }
        return self::$templatesDir;
    }

    /**
     * Renderiza um template e retorna o HTML como string.
     *
     * @param string $template Caminho relativo, sem .php (ex: 'admin/dashboard')
     * @param array<string,mixed> $data Variáveis disponíveis no escopo do template
     */
    public static function render(string $template, array $data = []): string
    {
        $file = self::dir() . '/' . $template . '.php';
        if (!is_readable($file)) {
            throw new \RuntimeException("Template not found: {$template}");
        }

        $renderer = static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            require $__file;
            return ob_get_clean() ?: '';
        };

        return $renderer($file, $data);
    }

    public static function renderWithLayout(
        string $template,
        string $layout,
        array $data = []
    ): string {
        $body = self::render($template, $data);
        return self::render($layout, ['bodyContent' => $body] + $data);
    }

    /**
     * Inclui um partial inline (uso dentro de templates).
     */
    public static function partial(string $template, array $data = []): void
    {
        echo self::render($template, $data);
    }

    public static function display(string $template, string $layout, array $data = []): void
    {
        echo self::renderWithLayout($template, $layout, $data);
    }

    /**
     * Escape HTML. Em templates, use a função global `e()`.
     */
    public static function escape(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
