<?php

/**
 * Generate JWT key pair for Lexik (RSA 2048).
 * Use when: php bin/console lexik:jwt:generate-keypair fails on Windows (e.g. OpenSSL "No such process").
 *
 * Run from project root: php scripts/generate-jwt-keys.php
 * Requires: JWT_PASSPHRASE in .env (can be empty for dev).
 *
 * Tries OpenSSL first; if that fails (e.g. on Windows), falls back to phpseclib (pure PHP).
 */

$projectDir = dirname(__DIR__);
$jwtDir = $projectDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'jwt';
$privatePath = $jwtDir . DIRECTORY_SEPARATOR . 'private.pem';
$publicPath = $jwtDir . DIRECTORY_SEPARATOR . 'public.pem';

if (!is_dir($jwtDir)) {
    mkdir($jwtDir, 0755, true);
}

// Optional: load passphrase from .env (empty string if not set)
$passphrase = '';
if (is_file($projectDir . DIRECTORY_SEPARATOR . '.env')) {
    $env = file_get_contents($projectDir . DIRECTORY_SEPARATOR . '.env');
    if (preg_match('/JWT_PASSPHRASE=(.+)$/m', $env, $m)) {
        $passphrase = trim($m[1], " \t\n\r\"'");
    }
}

$written = false;

// ----- Try OpenSSL first (works on Linux/macOS) -----
$resource = @openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);

if ($resource !== false) {
    $exported = @openssl_pkey_export($resource, $privateKey, $passphrase ?: null);
    if ($exported) {
        $details = openssl_pkey_get_details($resource);
        if ($details !== false && isset($details['key'])) {
            file_put_contents($privatePath, $privateKey);
            file_put_contents($publicPath, $details['key']);
            $written = true;
        }
    }
}

// ----- Fallback: phpseclib (pure PHP, works on Windows without OpenSSL) -----
if (!$written) {
    $autoload = $projectDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!is_file($autoload)) {
        fwrite(STDERR, "OpenSSL failed and vendor/autoload.php not found. Run: composer install\n");
        exit(1);
    }
    require_once $autoload;

    if (!class_exists(\phpseclib3\Crypt\RSA::class)) {
        fwrite(STDERR, "OpenSSL failed and phpseclib not installed. Run: composer require --dev phpseclib/phpseclib\n");
        exit(1);
    }

    $private = \phpseclib3\Crypt\RSA::createKey(2048);
    if ($passphrase !== '') {
        $private = $private->withPassword($passphrase);
    }
    $public = $private->getPublicKey();

    file_put_contents($privatePath, $private->toString('PKCS8'));
    file_put_contents($publicPath, $public->toString('PKCS8'));
    $written = true;
}

if (!$written) {
    $err = openssl_error_string();
    fwrite(STDERR, "OpenSSL error: " . ($err ?: 'unknown') . "\n");
    exit(1);
}

echo "JWT keys written to:\n  " . $privatePath . "\n  " . $publicPath . "\n";
