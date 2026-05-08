<?php
declare(strict_types=1);

namespace ArkhamFiles;

/**
 * HTTP helpers — IP real do cliente atravessando Cloudflare + nginx reverse proxy.
 */
final class Http
{
    /**
     * Returns the real client IP, respecting Cloudflare and trusted proxies.
     *
     * Order of precedence:
     *   1. CF-Connecting-IP header (when USE_CLOUDFLARE_TUNNEL=true and request
     *      came from a trusted proxy)
     *   2. First IP in X-Forwarded-For (chain trimmed by trusted proxies)
     *   3. REMOTE_ADDR
     */
    public static function clientIp(): ?string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($remote === null) {
            return null;
        }

        $trustedProxies = Config::getList('TRUSTED_PROXIES');
        $useCloudflare = Config::getBool('USE_CLOUDFLARE_TUNNEL', false);
        $remoteIsTrusted = in_array($remote, $trustedProxies, true);

        if ($useCloudflare && $remoteIsTrusted && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $cfIp = trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
            if (filter_var($cfIp, FILTER_VALIDATE_IP)) {
                return $cfIp;
            }
        }

        if ($remoteIsTrusted && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $chain = array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']));
            // Pega o primeiro que NÃO é proxy confiável (cliente real)
            foreach ($chain as $hop) {
                if ($hop !== '' && !in_array($hop, $trustedProxies, true)) {
                    if (filter_var($hop, FILTER_VALIDATE_IP)) {
                        return $hop;
                    }
                }
            }
            // Se a chain inteira é de proxies confiáveis, retorna o primeiro válido
            foreach ($chain as $hop) {
                if (filter_var($hop, FILTER_VALIDATE_IP)) {
                    return $hop;
                }
            }
        }

        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : null;
    }

    public static function userAgent(): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if ($ua === null) {
            return null;
        }
        // Limita tamanho pra não inflar logs
        return mb_substr((string) $ua, 0, 512);
    }
}
