<?php
declare(strict_types=1);

namespace ArkhamFiles\Auth;

use ArkhamFiles\Database;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Facade do 2FA TOTP.
 *
 * Fluxo de ativação:
 *   1. generateSecret()              → string base32 (160 bits)
 *   2. provisioningUri()             → otpauth://... (vira QR code)
 *   3. qrCodeSvg()                   → SVG inline pro template
 *   4. user escaneia no autenticador, digita código de 6 dígitos
 *   5. verifyCode($secret, $code)    → bool
 *   6. activate($userId, $secret)    → grava secret encriptado, totp_enabled=1
 *   7. generateRecoveryCodes()       → 10 códigos plain, mostra uma vez
 *   8. saveRecoveryCodes($userId, $codes) → grava hashes
 *
 * Fluxo de login com 2FA ativo:
 *   1. Auth::login() passa senha OK
 *   2. Se totp_enabled, NÃO seta user_id; seta pending_2fa_user_id
 *   3. Redireciona pra /admin/2fa/verify
 *   4. User digita código (ou recovery code)
 *   5. verifyForLogin($userId, $code) → consumir e migrar a sessão
 *
 * Janela TOTP: window=1 (aceita código atual ± 30s). Defensivo contra
 * clock skew sem expor uma janela grande demais.
 */
final class TwoFactor
{
    private const TOTP_WINDOW          = 1;
    private const RECOVERY_CODE_COUNT  = 10;
    private const RECOVERY_CODE_LENGTH = 10;

    private static function lib(): Google2FA
    {
        $g = new Google2FA();
        $g->setWindow(self::TOTP_WINDOW);
        return $g;
    }

    public static function generateSecret(): string
    {
        // Default da lib: 16 chars base32 = 80 bits. Aumentamos pra 160 bits
        // (32 chars), padrão recomendado pra TOTP.
        return self::lib()->generateSecretKey(32);
    }

    public static function provisioningUri(string $username, string $secret): string
    {
        // "Arkham Files" aparece no app autenticador como nome da conta
        return self::lib()->getQRCodeUrl(
            'Arkham Files',
            $username,
            $secret
        );
    }

    /**
     * Devolve um SVG inline (string) renderizando o QR code da URI.
     * Tamanho padrão: 240×240 px. Cores neutras pra aceitar
     * fundo claro ou escuro.
     */
    public static function qrCodeSvg(string $provisioningUri, int $size = 240): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        return $writer->writeString($provisioningUri);
    }

    /**
     * Verifica código de 6 dígitos contra um segredo base32.
     * Tolerância: window=1 (cobre clock skew de até ±30s).
     */
    public static function verifyCode(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }
        try {
            return (bool) self::lib()->verifyKey($secret, $code);
        } catch (\Throwable) {
            return false;
        }
    }

    // -------------------------------------------------------------
    // Recovery codes
    // -------------------------------------------------------------

    /**
     * Gera os 10 códigos de recuperação em texto plano. Cada código tem
     * 10 chars do alfabeto sem ambiguidade (sem 0/O, 1/l/I).
     * Formato exibido: XXXXX-XXXXX (com hífen no meio pra leitura).
     *
     * @return list<string>
     */
    public static function generateRecoveryCodes(): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $code = '';
            for ($j = 0; $j < self::RECOVERY_CODE_LENGTH; $j++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = substr($code, 0, 5) . '-' . substr($code, 5, 5);
        }
        return $codes;
    }

    /**
     * Hash bcrypt de cada código (mesmo algoritmo das senhas — Argon2id
     * seria overkill aqui, bcrypt é suficiente porque o input já tem
     * alta entropia: ~51 bits por código).
     *
     * @param  list<string> $plainCodes
     * @return list<string>
     */
    public static function hashRecoveryCodes(array $plainCodes): array
    {
        return array_map(
            static fn(string $c): string => password_hash($c, PASSWORD_BCRYPT),
            $plainCodes
        );
    }

    // -------------------------------------------------------------
    // Persistência
    // -------------------------------------------------------------

    /**
     * Ativa 2FA pro usuário. Grava o segredo encriptado e marca o flag.
     * NÃO grava recovery codes — esses são salvos separadamente após
     * o user confirmar que viu eles.
     */
    public static function activate(int $userId, string $secret): void
    {
        $encrypted = Crypto::encrypt($secret);
        Database::pdo()->prepare('
            UPDATE users
               SET totp_secret  = :s,
                   totp_enabled = 1
             WHERE id = :id
        ')->execute([':s' => $encrypted, ':id' => $userId]);
    }

    /**
     * Desativa 2FA. Remove o segredo e os recovery codes — não faz
     * sentido manter qualquer um dos dois sem o flag ativo.
     */
    public static function deactivate(int $userId): void
    {
        Database::pdo()->prepare('
            UPDATE users
               SET totp_secret    = NULL,
                   totp_enabled   = 0,
                   recovery_codes = NULL
             WHERE id = :id
        ')->execute([':id' => $userId]);
    }

    /**
     * @param list<string> $plainCodes
     */
    public static function saveRecoveryCodes(int $userId, array $plainCodes): void
    {
        $hashed = self::hashRecoveryCodes($plainCodes);
        Database::pdo()->prepare(
            'UPDATE users SET recovery_codes = :rc WHERE id = :id'
        )->execute([
            ':rc' => json_encode($hashed, JSON_UNESCAPED_SLASHES),
            ':id' => $userId,
        ]);
    }

    /**
     * Devolve o segredo decriptado, ou null se o user não tem 2FA
     * ativo ou se a decriptação falhou.
     */
    public static function getSecret(User $user): ?string
    {
        if (!$user->totpEnabled || $user->totpSecret === null) {
            return null;
        }
        return Crypto::decrypt($user->totpSecret);
    }

    /**
     * Tenta consumir um recovery code. Se válido, remove do banco
     * (rewrite do JSON sem aquele entry) e devolve true.
     * Comparação é por bcrypt — testa cada hash até achar match.
     */
    public static function consumeRecoveryCode(int $userId, string $providedCode): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT recovery_codes FROM users WHERE id = :id'
        );
        $stmt->execute([':id' => $userId]);
        $rawJson = (string) ($stmt->fetchColumn() ?: '');
        if ($rawJson === '') {
            return false;
        }

        $hashes = json_decode($rawJson, true);
        if (!is_array($hashes)) {
            return false;
        }

        $providedNormalized = strtoupper(trim($providedCode));
        // Aceita tanto "ABCDE-FGHIJ" quanto "ABCDEFGHIJ"
        if (strlen($providedNormalized) === self::RECOVERY_CODE_LENGTH) {
            $providedNormalized = substr($providedNormalized, 0, 5)
                . '-' . substr($providedNormalized, 5, 5);
        }

        foreach ($hashes as $idx => $hash) {
            if (!is_string($hash)) continue;
            if (password_verify($providedNormalized, $hash)) {
                // Consumo: remove esse hash do array
                array_splice($hashes, $idx, 1);
                Database::pdo()->prepare(
                    'UPDATE users SET recovery_codes = :rc WHERE id = :id'
                )->execute([
                    ':rc' => json_encode(array_values($hashes), JSON_UNESCAPED_SLASHES),
                    ':id' => $userId,
                ]);
                return true;
            }
        }
        return false;
    }

    public static function remainingRecoveryCodes(User $user): int
    {
        if ($user->recoveryCodes === null) {
            return 0;
        }
        $arr = json_decode($user->recoveryCodes, true);
        return is_array($arr) ? count($arr) : 0;
    }
}
