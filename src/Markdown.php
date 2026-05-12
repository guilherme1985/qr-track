<?php
declare(strict_types=1);

namespace ArkhamFiles;

/**
 * Renderizador de Markdown com sanitização HTML.
 *
 * Usa Parsedown Extra (declarado no composer.json desde PR 01) para
 * processar Markdown rico (tabelas, footnotes, fenced code, definition
 * lists, etc.) e aplica uma camada de sanitização adicional sobre o
 * HTML resultante.
 *
 * Decisão de design (decidida no escopo do PR 07):
 *   - Modo: "Opção A" — Parsedown Extra completo + sanitização customizada
 *   - Raw HTML inline NÃO é permitido (setMarkupEscaped(true))
 *   - Imagens permitidas apenas com URLs https://
 *   - Tags perigosas bloqueadas: script, iframe, object, embed, form, input
 *   - Atributos perigosos removidos: on* (onclick, onmouseover, etc.),
 *     style (evita CSS exploits), javascript: em hrefs
 *
 * Por que sanitizar EM CIMA do Parsedown se ele já tem setSafeMode?
 *   - SafeMode escapa todo HTML inline (`<b>` vira `&lt;b&gt;`), o que é
 *     muito conservador. Queremos permitir os elementos que o Parsedown
 *     GERA mas não os que o usuário ESCREVE em raw.
 *   - setMarkupEscaped(true) já faz isso, mas vamos garantir com defesa em
 *     profundidade (regex pós-processamento).
 *
 * Performance: para notas de até 64 KB, render típico fica < 50ms.
 * Cache não é necessário no escopo atual.
 */
final class Markdown
{
    /** Limite hard de tamanho (em bytes) — bate com schema comment */
    public const MAX_LENGTH_BYTES = 65536;

    /**
     * Renderiza um Markdown como HTML sanitizado, pronto pra injetar em
     * template via `<?= ?>` (sem htmlspecialchars).
     *
     * @param string $markdown  Conteúdo bruto fornecido pelo usuário
     * @return string           HTML sanitizado
     */
    public static function render(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        // Parsedown Extra renderiza tabelas, footnotes, etc.
        $parser = new \ParsedownExtra();

        // Escapa raw HTML inline do usuário — só queremos o HTML que o
        // próprio Parsedown gera a partir do markdown.
        $parser->setMarkupEscaped(true);

        // Não trata URLs como links automaticamente (evita parsing
        // surpresa de coisas tipo "http://" em meio a texto).
        $parser->setUrlsLinked(false);

        $html = $parser->text($markdown);

        return self::sanitize($html);
    }

    /**
     * Sanitização defensiva pós-Parsedown.
     *
     * O Parsedown com setMarkupEscaped(true) já bloqueia raw HTML do
     * usuário, mas como defesa em profundidade aplicamos regras
     * adicionais sobre o HTML que ele GERA — protegendo contra eventuais
     * bugs futuros do parser.
     */
    public static function sanitize(string $html): string
    {
        // 1. Remove blocos inteiros de tags claramente perigosas, caso
        //    apareçam (não deveriam, dado setMarkupEscaped(true), mas...).
        $dangerousTags = ['script', 'iframe', 'object', 'embed', 'form',
                          'input', 'textarea', 'select', 'button', 'style',
                          'link', 'meta', 'base'];
        foreach ($dangerousTags as $tag) {
            $html = preg_replace(
                '#<' . $tag . '\b[^>]*>.*?</' . $tag . '>#is',
                '',
                $html
            ) ?? $html;
            // Self-closing version too
            $html = preg_replace(
                '#<' . $tag . '\b[^>]*/?>#is',
                '',
                $html
            ) ?? $html;
        }

        // 2. Remove atributos on* (onclick, onmouseover, onload, etc.)
        $html = preg_replace(
            '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i',
            '',
            $html
        ) ?? $html;

        // 3. Remove atributo style (CSS exploits, position:fixed clickjacking)
        $html = preg_replace(
            '/\sstyle\s*=\s*("[^"]*"|\'[^\']*\')/i',
            '',
            $html
        ) ?? $html;

        // 4. Neutraliza javascript: e data: em hrefs
        $html = preg_replace_callback(
            '/\shref\s*=\s*("([^"]*)"|\'([^\']*)\')/i',
            function ($m) {
                $url = $m[2] ?? $m[3] ?? '';
                $lower = strtolower(trim($url));
                if (str_starts_with($lower, 'javascript:') ||
                    str_starts_with($lower, 'vbscript:') ||
                    str_starts_with($lower, 'data:text/html')) {
                    return ' href="#blocked"';
                }
                return $m[0];
            },
            $html
        ) ?? $html;

        // 5. Imagens só com https:// (bloqueia http e protocolos custom)
        $html = preg_replace_callback(
            '/<img\b[^>]*\ssrc\s*=\s*("([^"]*)"|\'([^\']*)\')[^>]*>/i',
            function ($m) {
                $src = $m[2] ?? $m[3] ?? '';
                if (!str_starts_with(strtolower(trim($src)), 'https://')) {
                    return ''; // remove a imagem inteira
                }
                return $m[0];
            },
            $html
        ) ?? $html;

        return $html;
    }

    /**
     * Conta caracteres (não bytes) — útil pra mostrar feedback no form.
     * Multibyte-safe.
     */
    public static function charCount(string $markdown): int
    {
        return mb_strlen($markdown, 'UTF-8');
    }

    /**
     * Trunca preview de markdown pra listagens (corta antes do limite,
     * remove markdown markup mais óbvio, deixa string corrida).
     */
    public static function preview(string $markdown, int $maxLength = 120): string
    {
        // Remove headers, listas, links, ênfase — fica texto cru
        $text = preg_replace('/^#{1,6}\s+/m', '', $markdown) ?? $markdown;
        $text = preg_replace('/^[*\-+]\s+/m', '', $text) ?? $text;
        $text = preg_replace('/^\d+\.\s+/m', '', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;
        $text = preg_replace('/[*_`~]/', '', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            return $text;
        }
        return mb_substr($text, 0, $maxLength - 1, 'UTF-8') . '…';
    }
}
