<?php
declare(strict_types=1);

/** Error whose message is safe to show to the visitor. */
final class UserError extends RuntimeException
{
}

function appSecret(): string
{
    $secret = getenv('APP_SECRET') ?: '';
    if (strlen($secret) < 16) {
        throw new RuntimeException('APP_SECRET is missing or too short.');
    }
    return $secret;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function isHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}

/**
 * Anonymous visitor identity: a random token in an HttpOnly cookie. There are no accounts;
 * the token is the only thing that lets a browser see its own uploads. The database stores
 * only its hash, so a database leak cannot be replayed as cookies.
 */
function ownerToken(): string
{
    $token = $_COOKIE['ic_owner'] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
        $token = bin2hex(random_bytes(16));
        setcookie('ic_owner', $token, [
            'expires'  => time() + 60 * 60 * 24 * 30,
            'path'     => '/',
            'secure'   => isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['ic_owner'] = $token;
    }
    return $token;
}

function ownerHash(string $token): string
{
    return hash('sha256', $token);
}

function clientHash(): string
{
    return hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), appSecret());
}

/** Stateless CSRF token bound to the visitor's cookie. */
function csrfToken(string $ownerToken): string
{
    return hash_hmac('sha256', 'csrf|' . $ownerToken, appSecret());
}

function csrfValid(string $ownerToken, mixed $submitted): bool
{
    return is_string($submitted) && hash_equals(csrfToken($ownerToken), $submitted);
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}
