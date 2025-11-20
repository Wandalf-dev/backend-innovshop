<?php
$config = [
    'private_key_bits' => 4096,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'config' => 'C:/xampp/apache/bin/openssl.cnf'
];

$res = openssl_pkey_new($config);

if ($res === false) {
    echo "Error generating key: " . openssl_error_string() . "\n";
    exit(1);
}

openssl_pkey_export($res, $privKey, null, $config);
file_put_contents('config/jwt/private.pem', $privKey);

$pubKey = openssl_pkey_get_details($res);
file_put_contents('config/jwt/public.pem', $pubKey['key']);

echo "JWT keys generated successfully!\n";
