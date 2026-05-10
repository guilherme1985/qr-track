<?php
declare(strict_types=1);

/**
 * CLI · Criar usuário (use pra fazer seed do primeiro admin).
 *
 * Modo interativo:
 *   php bin/create-user.php
 *
 * Modo non-interactive (CI/automação):
 *   php bin/create-user.php --username=admin --email=foo@bar.com --role=admin --password='Senha@123'
 *
 * Se já existir usuário com mesmo username, falha com exit 1.
 */

require __DIR__ . '/../src/Bootstrap.php';

use ArkhamFiles\Bootstrap;
use ArkhamFiles\Auth\User;
use ArkhamFiles\Auth\PasswordPolicy;

$rootDir = dirname(__DIR__);
Bootstrap::init($rootDir);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Esse script é apenas pra linha de comando.\n");
    exit(1);
}

// ---------- helpers de I/O -----------------------------------------------

function ask(string $prompt, ?string $default = null): string
{
    $hint = $default !== null ? " [{$default}]" : '';
    fwrite(STDOUT, $prompt . $hint . ': ');
    $line = fgets(STDIN);
    if ($line === false) {
        return $default ?? '';
    }
    $line = trim($line);
    return $line === '' ? ($default ?? '') : $line;
}

function askPassword(string $prompt): string
{
    fwrite(STDOUT, $prompt . ': ');
    // Tenta esconder com stty (Linux/Mac); fallback: input visível com aviso
    $sttyExists = trim((string) shell_exec('command -v stty')) !== '';
    if ($sttyExists) {
        $oldStyle = trim((string) shell_exec('stty -g'));
        shell_exec('stty -echo');
        $line = fgets(STDIN);
        shell_exec('stty ' . $oldStyle);
        fwrite(STDOUT, "\n");
    } else {
        fwrite(STDOUT, "\n[aviso: stty não disponível, senha será visível]\n");
        $line = fgets(STDIN);
    }
    return $line === false ? '' : trim($line);
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

$opts = getopt('', ['username:', 'email:', 'role:', 'password:']);
$nonInteractive = !empty($opts['username']) && !empty($opts['password']);

if ($nonInteractive) {
    $username = (string) $opts['username'];
    $email    = (string) ($opts['email'] ?? '');
    $role     = (string) ($opts['role']  ?? User::ROLE_ADMIN);
    $password = (string) $opts['password'];
} else {
    fwrite(STDOUT, "\n");
    fwrite(STDOUT, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
    fwrite(STDOUT, "  ARKHAM FILES · Seed user\n");
    fwrite(STDOUT, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n");

    $username = ask('Username', 'admin');
    $email    = ask('E-mail (opcional)', '');
    $role     = ask('Role (admin|curator)', User::ROLE_ADMIN);
    $password = askPassword('Senha');
    $confirm  = askPassword('Confirmar senha');
    if ($password !== $confirm) {
        fail('Senhas não coincidem.');
    }
}

// ---------- validações ---------------------------------------------------

if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
    fail("Username inválido: '{$username}' (use letras, números, ponto, hífen, underline).");
}
if (User::usernameExists($username)) {
    fail("Username '{$username}' já existe.");
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail("E-mail inválido: '{$email}'.");
}
if (!in_array($role, [User::ROLE_ADMIN, User::ROLE_CURATOR], true)) {
    fail("Role inválido: '{$role}' (use 'admin' ou 'curator').");
}
$pwdErrors = PasswordPolicy::validate($password, $username);
if ($pwdErrors !== []) {
    fwrite(STDERR, "✗ Senha não atende à política:\n");
    foreach ($pwdErrors as $key) {
        fwrite(STDERR, "  · " . $key . "\n");
    }
    exit(1);
}

// ---------- cria ---------------------------------------------------------

$userId = User::create(
    username: $username,
    email:    $email !== '' ? $email : null,
    plainPassword: $password,
    role:     $role,
    mustChangePassword: false,  // o admin que digitou já sabe a senha
);

ok("Usuário #{$userId} criado: {$username} (role={$role}).");
