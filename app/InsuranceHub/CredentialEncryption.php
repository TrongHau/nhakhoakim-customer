<?php

namespace App\InsuranceHub;

use RuntimeException;

/**
 * Mã hóa / giải mã password trong bảng ProviderCredentials.
 *
 * Thuật toán: AES-256-CBC
 *   key = str_pad('NhaKhoaKim_' . {CredentialId}, 32, '0')
 *   iv  = str_pad('NhaKhoaKim_' . {CredentialId}, 16, '0')
 *
 * Dùng chung cho mọi provider (BaoMinh, BaoViet, PTI, ...).
 * Không đặt logic này trong từng Driver.
 */
class CredentialEncryption
{
    private const ALGO   = 'AES-256-CBC';
    private const PREFIX = 'NhaKhoaKim_';

    public static function encrypt(string $plain, int $credentialId): string
    {
        [$key, $iv] = self::buildKeyIv($credentialId);
        $encrypted  = openssl_encrypt($plain, self::ALGO, $key, 0, $iv);

        if ($encrypted === false) {
            throw new RuntimeException("CredentialEncryption: encrypt failed for credential #{$credentialId}");
        }

        return $encrypted;
    }

    public static function decrypt(string $encrypted, int $credentialId): string
    {
        [$key, $iv] = self::buildKeyIv($credentialId);
        $plain      = openssl_decrypt($encrypted, self::ALGO, $key, 0, $iv);

        if ($plain === false) {
            throw new RuntimeException("CredentialEncryption: decrypt failed for credential #{$credentialId}");
        }

        return $plain;
    }

    private static function buildKeyIv(int $credentialId): array
    {
        $base = self::PREFIX . $credentialId;
        return [
            str_pad($base, 32, '0'),
            str_pad($base, 16, '0'),
        ];
    }
}
