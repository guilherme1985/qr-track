<?php
declare(strict_types=1);

namespace ArkhamFiles;

/**
 * Constantes de UX para o formulário de categoria.
 *
 * Mantém em código (e não em DB) porque:
 *   - Lista curada pela equipe de design, não por usuário
 *   - Mudanças aqui = deploy explícito (intencional)
 *   - Validação server-side simples: o valor submetido tem que estar
 *     na lista. Qualquer coisa fora vira "sem ícone" / "sem cor".
 */
final class CategoryAttributes
{
    /**
     * Ícones disponíveis para categorias.
     * Subset curado do sprite Tabler — só os que fazem sentido como
     * "pasta", "tópico", "domínio" (sem chevrons, setas, controls de UI).
     *
     * Cada entrada: chave = nome do símbolo no sprite (sem prefixo "i-"),
     * valor = label legível em PT-BR (mostrado em tooltip).
     *
     * @return array<string,string>
     */
    public static function icons(): array
    {
        return [
            'folder'          => 'Pasta',
            'folder-open'     => 'Pasta aberta',
            'leaf'            => 'Folha (botânico)',
            'seedling'        => 'Muda (cultivo)',
            'notes'           => 'Notas',
            'photo'           => 'Fotografia',
            'link'            => 'Link / URL',
            'wifi'            => 'Wi-Fi',
            'qrcode'          => 'QR Code',
            'user'            => 'Pessoa',
            'phone'           => 'Telefone',
            'mail'            => 'E-mail',
            'message-circle'  => 'Mensagem',
            'map-pin'         => 'Localização',
            'shield-lock'     => 'Segurança',
            'lock'            => 'Privado',
            'settings'        => 'Configuração',
            'edit'            => 'Edição',
            'filter'          => 'Filtro',
            'help-circle'     => 'Ajuda',
            'folder-question' => 'Diversos',
            'bell'            => 'Notificação',
            'alert-triangle'  => 'Atenção',
        ];
    }

    /**
     * Paleta de cores curada do tema Arkham (mais alguns neutros úteis).
     *
     * Cada entrada: chave = valor armazenado no banco (hex), valor = label PT-BR.
     * O label aparece em tooltip e é usado por screen readers.
     *
     * Cores derivadas da palette principal do app:
     *   - phosphor: verde do tema (#7DDB4F)
     *   - gold:     ouro (#A88B4C)
     *   - blood:    sangue (#A32D2D)
     *   - bg:       fundo / neutro
     *
     * @return array<string,string>  hex => label
     */
    public static function colors(): array
    {
        return [
            '#7DDB4F' => 'Phosphor (verde)',
            '#A88B4C' => 'Gold (ouro)',
            '#A32D2D' => 'Blood (sangue)',
            '#5C1A1B' => 'Blood escuro',
            '#3A7CA5' => 'Azul cobalto',
            '#8B5A3C' => 'Bronze',
            '#6B5B95' => 'Roxo institucional',
            '#9CAF88' => 'Verde acinzentado',
            '#E8DDC9' => 'Pergaminho',
            '#7A7A7A' => 'Cinza grafite',
        ];
    }

    /**
     * Valida que um ícone submetido pelo form está na lista oficial.
     * Se inválido (incluindo string vazia/null), devolve null.
     */
    public static function normalizeIcon(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        return array_key_exists($value, self::icons()) ? $value : null;
    }

    /**
     * Valida e normaliza uma cor submetida. Aceita apenas hex da
     * paleta oficial (case-insensitive em comparação, mas armazena
     * exatamente como na lista).
     */
    public static function normalizeColor(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $upper = strtoupper(trim($value));
        foreach (self::colors() as $hex => $_label) {
            if (strtoupper($hex) === $upper) {
                return $hex;
            }
        }
        return null;
    }
}
