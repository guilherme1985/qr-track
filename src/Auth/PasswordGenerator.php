<?php
declare(strict_types=1);

namespace ArkhamFiles\Auth;

/**
 * Gera senhas temporárias aleatórias.
 *
 * Usadas quando o admin reseta a senha de outro usuário. A senha é
 * exibida em texto plano UMA ÚNICA VEZ e o user é forçado a trocá-la
 * no próximo login (via `must_change_password`).
 *
 * O alfabeto exclui caracteres visualmente ambíguos (0/O/o, 1/l/I)
 * pra que o admin possa ditar pelo telefone sem erro. Mesmo assim, a
 * entropia é alta o suficiente: ~5.95 bits/char × 16 chars ≈ 95 bits.
 */
final class PasswordGenerator
{
    /** Sem 0, O, o, 1, l, I */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ' . 'abcdefghjkmnpqrstuvwxyz' . '23456789';

    /** Símbolos legíveis (sem ambiguidade visual e sem precisar de escape em CLI) */
    private const SYMBOLS  = '!@#$%&*+-=?';

    /**
     * Senha aleatória de comprimento $length que satisfaz a política
     * (sempre tem ao menos 1 maiúscula, 1 minúscula, 1 dígito, 1 símbolo).
     */
    public static function generate(int $length = 16): string
    {
        if ($length < 8) {
            throw new \InvalidArgumentException('Length must be at least 8');
        }

        // Garante pelo menos 1 de cada classe
        $required = [
            self::pickFrom('ABCDEFGHJKLMNPQRSTUVWXYZ'),  // upper
            self::pickFrom('abcdefghjkmnpqrstuvwxyz'),   // lower
            self::pickFrom('23456789'),                  // digit
            self::pickFrom(self::SYMBOLS),               // symbol
        ];

        // Preenche o resto com chars aleatórios do alfabeto + símbolos
        $pool = self::ALPHABET . self::SYMBOLS;
        $remaining = $length - count($required);
        $extras = [];
        for ($i = 0; $i < $remaining; $i++) {
            $extras[] = self::pickFrom($pool);
        }

        // Embaralha pra que os "obrigatórios" não fiquem sempre nas
        // primeiras 4 posições
        $chars = array_merge($required, $extras);
        shuffle($chars);

        return implode('', $chars);
    }

    private static function pickFrom(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
