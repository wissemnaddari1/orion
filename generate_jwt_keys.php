<?php

declare(strict_types=1);

$projectDir = __DIR__;
$jwtDir = $projectDir . '/config/jwt';

if (!is_dir($jwtDir)) {
    if (!mkdir($jwtDir, 0700, true) && !is_dir($jwtDir)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $jwtDir));
    }
}

// Must match JWT_PASSPHRASE in .env
$passphrase = getenv('JWT_PASSPHRASE') ?: 'tryhackme';

// On Windows, OpenSSL needs config file (e.g. from Git for Windows)
$opensslConfig = getenv('OPENSSL_CONF');
if (empty($opensslConfig) && PHP_OS_FAMILY === 'Windows') {
    $candidates = [
        'C:\\Program Files\\Git\\mingw64\\etc\\ssl\\openssl.cnf',
        'C:\\Program Files\\Git\\usr\\ssl\\openssl.cnf',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            $opensslConfig = $path;
            putenv('OPENSSL_CONF=' . $path);
            break;
        }
    }
}

$config = [
    'private_key_bits' => 4096,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];
if (!empty($opensslConfig)) {
    $config['config'] = $opensslConfig;
}

$resource = openssl_pkey_new($config);
if ($resource === false) {
    echo "Error generating key:\n";
    while ($msg = openssl_error_string()) {
        echo " - $msg\n";
    }
    exit(1);
}

$exportOptions = [];
if (!empty($opensslConfig)) {
    $exportOptions['config'] = $opensslConfig;
}

if (!openssl_pkey_export($resource, $privateKey, $passphrase, $exportOptions)) {
    echo "Error exporting private key (with passphrase).\n";
    while ($msg = openssl_error_string()) {
        echo " - $msg\n";
    }
    // Fallback: export without passphrase (Windows OpenSSL often fails with passphrase)
    echo "Trying without passphrase (use JWT_PASSPHRASE= in .env)...\n";
    openssl_error_clear();
    if (!openssl_pkey_export($resource, $privateKey, null, $exportOptions)) {
        echo "Error exporting private key (without passphrase).\n";
        while ($msg = openssl_error_string()) {
            echo " - $msg\n";
        }
        exit(1);
    }
    $passphrase = '';
}

$details = openssl_pkey_get_details($resource);
if ($details === false || empty($details['key'])) {
    echo "Could not extract public key.\n";
    exit(1);
}

$publicKey = $details['key'];

if (file_put_contents($jwtDir . '/private.pem', $privateKey) === false) {
    echo "Failed to write private key file.\n";
    exit(1);
}

if (file_put_contents($jwtDir . '/public.pem', $publicKey) === false) {
    echo "Failed to write public key file.\n";
    exit(1);
}

echo "JWT keys generated successfully:\n";
echo " - " . $jwtDir . "/private.pem\n";
echo " - " . $jwtDir . "/public.pem\n";
if ($passphrase === '') {
    echo "\nNote: Keys are unencrypted (Windows fallback). Set JWT_PASSPHRASE= in .env\n";
}

