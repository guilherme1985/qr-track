<?php
declare(strict_types=1);

namespace ArkhamFiles\Auth;

use ArkhamFiles\Config;

/**
 * Encriptação simétrica autenticada para segredos sensíveis em repouso
 * (TOTP secret, principalmente).
 *
 * Algoritmo: AES-256-GCM via OpenSSL nativo do PHP. GCM fornece
 * confidencialidade + autenticação numa única passada — qualquer
 * modificação do ciphertext invalida a tag de autenticação.
 *
 * Formato do output (string):
 *   base64( IV[12 bytes] | CIPHERTEXT[var] | TAG[16 bytes] )
 *
 * A chave AES é derivada da TOTP_ENCRYPTION_KEY do .env via SHA-256
 * (devolve 32 bytes, que é o que AES-256 espera). Isso permite que a
 * chave no .env seja qualquer string de comprimento arbitrário.
 *
 * Importante: se a TOTP_ENCRYPTION_KEY mudar, TODOS os segredos
 * encriptados ficam ilegíveis. Mantenha em local seguro e backup.
 */
final class Crypto
{
    private const CIPHER  = 'aes-256-gcm';
    private const IV_LEN  = 12;
    private const TAG_LEN = 16;

    /**
     * Encripta uma string em texto plano. Devolve string base64-encoded
     * pronta pra ser guardada em coluna TEXT do banco.
     */
    public static function encrypt(string $plaintext): string
    {
        $key = self::deriveKey();
        $iv  = random_bytes(self::IV_LEN);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',          // additional authenticated data
            self::TAG_LEN
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('openssl_encrypt failed');
        }

        return base64_encode($iv . $ciphertext . $tag);
    }

    /**
     * Decripta uma string previamente encriptada por encrypt().
     * Retorna null se a tag de autenticação não bater (ciphertext
     * adulterado, chave errada, ou formato inválido).
     */
    public static function decrypt(string $envelope): ?string
    {
        $raw = base64_decode($envelope, true);
        if ($raw === false || strlen($raw) < self::IV_LEN + self::TAG_LEN + 1) {
            return null;
        }

        $iv         = substr($raw, 0, self::IV_LEN);
        $tag        = substr($raw, -self::TAG_LEN);
        $ciphertext = substr($raw, self::IV_LEN, -self::TAG_LEN);

        $key = self::deriveKey();

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            ''
        );

        return $plaintext === false ? null : $plaintext;
    }

    /**
     * Deriva uma chave AES-256 (32 bytes) da TOTP_ENCRYPTION_KEY do .env.
     * Se a env var estiver vazia, falha cedo — não há segurança em
     * encriptar com chave conhecida.
     */
    private static function deriveKey(): string
    {
        $raw = (string) Config::get('TOTP_ENCRYPTION_KEY', '');
        if ($raw === '') {
            throw new \RuntimeException(
                'TOTP_ENCRYPTION_KEY ausente no .env. Gere uma chave forte '
                . '(ex: openssl rand -hex 32) e configure antes de usar 2FA.'
            );
        }
        // SHA-256 binário = 32 bytes, exatamente o tamanho que AES-256 espera
        return hash('sha256', $raw, true);
    }
}
