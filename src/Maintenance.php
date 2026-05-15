<?php
declare(strict_types=1);

namespace ArkhamFiles;

/**
 * Gerencia o "modo manutenção" via arquivo flag em data/maintenance.flag.
 *
 * Modelo:
 *   - Arquivo existe → modo ATIVO. Conteúdo do arquivo = mensagem.
 *   - Arquivo ausente → modo INATIVO. Funcionamento normal.
 *
 * Bypass: admins logados sempre acessam tudo, mas vêem um banner de alerta
 * no admin pra lembrar que o público está vendo a tela de manutenção.
 *
 * Fail-safe: se houver erro de I/O, assume modo INATIVO (não trava o site).
 */
final class Maintenance
{
    private const FLAG_FILENAME = 'maintenance.flag';
    private const DEFAULT_MESSAGE = 'Manutenção em curso · volte em breve.';

    public static function flagPath(): string
    {
        return rtrim(Config::get('DATA_DIR') ?? (Bootstrap::rootDir() . '/data'), '/')
             . '/' . self::FLAG_FILENAME;
    }

    /**
     * Modo manutenção está ativo?
     */
    public static function isActive(): bool
    {
        $path = self::flagPath();
        return is_file($path);
    }

    /**
     * Lê a mensagem customizada do arquivo (ou retorna a default).
     */
    public static function message(): string
    {
        $path = self::flagPath();
        if (!is_file($path)) {
            return self::DEFAULT_MESSAGE;
        }
        $content = @file_get_contents($path);
        if ($content === false || trim($content) === '') {
            return self::DEFAULT_MESSAGE;
        }
        return trim($content);
    }

    /**
     * Liga o modo manutenção. Cria/sobrescreve o arquivo flag.
     *
     * @return bool true se conseguiu escrever
     */
    public static function enable(string $message = ''): bool
    {
        $path = self::flagPath();
        $msg = trim($message) !== '' ? trim($message) : self::DEFAULT_MESSAGE;
        $ok = @file_put_contents($path, $msg . "\n") !== false;
        if ($ok) @chmod($path, 0644);
        return $ok;
    }

    /**
     * Desliga o modo manutenção. Apaga o arquivo flag.
     *
     * @return bool true se conseguiu apagar (ou já não existia)
     */
    public static function disable(): bool
    {
        $path = self::flagPath();
        if (!is_file($path)) return true;
        return @unlink($path);
    }

    /**
     * Verifica se uma rota deve ser interceptada pelo middleware.
     * Rotas exceção: /healthz (pra monitoramento poder verificar),
     * todas as rotas de autenticação (admin tem que conseguir entrar pra
     * desligar a manutenção) e os assets estáticos.
     *
     * @param string $uri  caminho da URL (sem query string)
     */
    public static function shouldBypass(string $uri): bool
    {
        // Rotas de exceção — sempre passam mesmo em manutenção
        $exempt = [
            '/healthz',
            '/admin/login',
            '/admin/2fa/verify',
            '/admin/2fa/setup',
            '/admin/logout',
            '/admin/forgot-password',
        ];
        foreach ($exempt as $path) {
            if ($uri === $path) return true;
        }

        // Assets estáticos (CSS, JS, imagens, fonts) — sempre passam
        if (str_starts_with($uri, '/assets/')) return true;
        if (str_starts_with($uri, '/uploads/')) return true;

        return false;
    }

    /**
     * Renderiza a tela de manutenção e encerra a request.
     * Retorna HTTP 503 (Service Unavailable) — apropriado pro caso e
     * sinaliza pros search engines não indexar essa página.
     */
    public static function render(string $rootDir): void
    {
        http_response_code(503);
        header('Retry-After: 3600'); // sugere ao crawler tentar de novo em 1h
        $maintenanceMessage = self::message();
        require $rootDir . '/templates/maintenance.php';
        exit;
    }
}
