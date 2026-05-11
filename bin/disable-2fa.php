<?php
declare(strict_types=1);

/**
 * CLI · Desativar 2FA de um usuário (uso de emergência).
 *
 * Quando usar:
 *   - O curador (ou admin) perdeu acesso ao app autenticador
 *   - E também usou todos os 10 códigos de recuperação
 *   - Você precisa restaurar acesso sem depender do segundo fator
 *
 * Uso:
 *   php bin/disable-2fa.php                    # interativo (lista users com 2FA)
 *   php bin/disable-2fa.php --username=admin   # direto
 *
 * Efeitos:
 *   - Apaga totp_secret e recovery_codes do user
 *   - Marca totp_enabled=0
 *   - Audit log: 2fa_disabled (by=cli)
 *
 * Próximo login: usuário entra só com senha. Se for admin, no primeiro
 * acesso será forçado a configurar 2FA novamente (via enforceTwoFactorSetup).
 */

require __DIR__ . '/../src/Bootstrap.php';

use ArkhamFiles\Bootstrap;
use ArkhamFiles\Auth\User;
use ArkhamFiles\Auth\TwoFactor;
use ArkhamFiles\Auth\Audit;

$rootDir = dirname(__DIR__);
Bootstrap::init($rootDir);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Esse script é apenas pra linha de comando.\n");
    exit(1);
}

function ask(string $prompt, ?string $default = null): string
{
    $hint = $default !== null ? " [{$default}]" : '';
    fwrite(STDOUT, $prompt . $hint . ': ');
    $line = fgets(STDIN);
    if ($line === false) return $default ?? '';
    $line = trim($line);
    return $line === '' ? ($default ?? '') : $line;
}

function fail(string $msg): never
{
    fwrite(STDERR, "✗ {$msg}\n");
    exit(1);
}

function ok(string $msg): void
{
    fwrite(STDOUT, "✓ {$msg}\n");
}

// ---------- parse args ---------------------------------------------------

$opts = getopt('', ['username:']);
$username = $opts['username'] ?? null;

if ($username === null) {
    fwrite(STDOUT, "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
    fwrite(STDOUT, "  ARKHAM FILES · Desativar 2FA (emergência)\n");
    fwrite(STDOUT, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n");

    $users = User::listAll();
    $with2fa = array_values(array_filter($users, fn(User $u) => $u->totpEnabled));
    if ($with2fa === []) {
        fwrite(STDOUT, "Nenhum usuário tem 2FA ativo no momento.\n");
        exit(0);
    }
    fwrite(STDOUT, "Usuários com 2FA ativo:\n");
    foreach ($with2fa as $i => $u) {
        $remaining = TwoFactor::remainingRecoveryCodes($u);
        fwrite(STDOUT, sprintf(
            "  [%d] %s (role=%s, recovery_codes=%d, last_login=%s)\n",
            $i + 1, $u->username, $u->role, $remaining,
            $u->lastLoginAt ?? '—'
        ));
    }
    $choice = ask("\nEscolha (1-" . count($with2fa) . ") ou digite o username diretamente", null);
    if ($choice === '') {
        fail('Cancelado.');
    }
    if (ctype_digit($choice)) {
        $idx = (int) $choice - 1;
        if (!isset($with2fa[$idx])) {
            fail("Índice inválido.");
        }
        $username = $with2fa[$idx]->username;
    } else {
        $username = $choice;
    }
}

// ---------- valida e confirma -------------------------------------------

$user = User::findByUsername((string) $username);
if ($user === null) {
    fail("Usuário '{$username}' não encontrado.");
}
if (!$user->totpEnabled) {
    fail("Usuário '{$username}' não tem 2FA ativo.");
}

fwrite(STDOUT, "\n⚠ Você vai DESATIVAR o 2FA de: {$user->username} (role={$user->role})\n");
fwrite(STDOUT, "  Próximo login será só com senha. Se for admin, será forçado a reconfigurar.\n\n");

$confirm = ask("Digite 'sim' para confirmar", null);
if (strtolower($confirm) !== 'sim') {
    fail('Cancelado.');
}

// ---------- desativa ----------------------------------------------------

TwoFactor::deactivate($user->id);
Audit::log('2fa_disabled', null, 'user', $user->id,
    ['target_username' => $user->username, 'by' => 'cli'],
    'cli', 'bin/disable-2fa.php');

ok("2FA desativado para {$user->username}.");
fwrite(STDOUT, "\n");
fwrite(STDOUT, "Próximos passos para o usuário:\n");
fwrite(STDOUT, "  1. Fazer login com username + senha\n");
if ($user->isAdmin()) {
    fwrite(STDOUT, "  2. Sistema vai forçar configuração de 2FA antes de qualquer ação (admin obrigatório)\n");
} else {
    fwrite(STDOUT, "  2. (Opcional) Reconfigurar 2FA em /admin/profile\n");
}
