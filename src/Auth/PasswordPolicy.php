<?php
declare(strict_types=1);

namespace ArkhamFiles\Auth;

/**
 * Validação da política de senha.
 *
 * Regras:
 *   - mínimo 8 caracteres
 *   - pelo menos 1 letra maiúscula  (A-Z)
 *   - pelo menos 1 letra minúscula  (a-z)
 *   - pelo menos 1 dígito           (0-9)
 *   - pelo menos 1 símbolo          (qualquer não-alfanumérico)
 *   - não pode ser igual ao username (case-insensitive)
 *
 * Devolve uma lista de erros (chaves de I18n). Lista vazia = senha OK.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    /**
     * @return list<string> Chaves de erro (vazias se válida)
     */
    public static function validate(string $password, string $username = ''): array
    {
        $errors = [];

        if (mb_strlen($password) < self::MIN_LENGTH) {
            $errors[] = 'errors.password.too_short';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'errors.password.no_uppercase';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'errors.password.no_lowercase';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'errors.password.no_digit';
        }
        // Símbolo: qualquer caractere não-alfanumérico (inclui espaço, mas
        // espaço sozinho cobre o requisito — não é problema).
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'errors.password.no_symbol';
        }
        if ($username !== '' && mb_strtolower($password) === mb_strtolower($username)) {
            $errors[] = 'errors.password.equals_username';
        }

        return $errors;
    }

    /**
     * Resumo legível dos requisitos pra exibir na UI próxima ao input.
     * @return list<string>
     */
    public static function requirements(): array
    {
        return [
            'errors.password.req_length',     // "Mínimo 8 caracteres"
            'errors.password.req_uppercase',
            'errors.password.req_lowercase',
            'errors.password.req_digit',
            'errors.password.req_symbol',
        ];
    }
}
