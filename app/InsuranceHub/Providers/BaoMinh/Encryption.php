<?php

namespace App\InsuranceHub\Providers\BaoMinh;

use RuntimeException;

/**
 * PGP encryption/decryption cho BaoMinh API.
 * Key content truyền vào từ DB — không đọc từ file (K8s-safe).
 * GnuPG keyring tạo trong /tmp/, ephemeral theo pod.
 */
class Encryption
{
    private $encryptFingerprint;
    private $decryptFingerprint;
    private $gpgHome;

    public function __construct(string $baominhPublicKey, string $nkkPrivateKey)
    {
        if (!extension_loaded('gnupg')) {
            throw new RuntimeException('PHP gnupg extension is required. Install: apt install php-gnupg && phpenmod gnupg');
        }

        $this->gpgHome = sys_get_temp_dir() . '/nkk_gpg_' . substr(md5($baominhPublicKey), 0, 8);

        if (!is_dir($this->gpgHome)) {
            mkdir($this->gpgHome, 0700, true);
        }

        putenv('GNUPGHOME=' . $this->gpgHome);
        $gpg = new \gnupg();
        $gpg->seterrormode(\gnupg::ERROR_EXCEPTION);

        $enc = $gpg->import($baominhPublicKey);
        if (empty($enc['fingerprint'])) {
            throw new RuntimeException('Failed to import BaoMinh public key');
        }
        $this->encryptFingerprint = $enc['fingerprint'];

        $dec = $gpg->import($nkkPrivateKey);
        if (empty($dec['fingerprint'])) {
            throw new RuntimeException('Failed to import NKK private key');
        }
        $this->decryptFingerprint = $dec['fingerprint'];
    }

    public function encrypt(string $plaintext): string
    {
        putenv('GNUPGHOME=' . $this->gpgHome);
        $gpg = new \gnupg();
        $gpg->seterrormode(\gnupg::ERROR_EXCEPTION);
        $gpg->addencryptkey($this->encryptFingerprint);
        $result = $gpg->encrypt($plaintext);
        $gpg->clearencryptkeys();

        if ($result === false) {
            throw new RuntimeException('PGP encryption failed: ' . $gpg->geterror());
        }

        return $result;
    }

    public function decrypt(string $ciphertext): string
    {
        putenv('GNUPGHOME=' . $this->gpgHome);
        $gpg = new \gnupg();
        $gpg->seterrormode(\gnupg::ERROR_EXCEPTION);
        $gpg->adddecryptkey($this->decryptFingerprint, '');
        $result = $gpg->decrypt($ciphertext);
        $gpg->cleardecryptkeys();

        if ($result === false) {
            throw new RuntimeException('PGP decryption failed: ' . $gpg->geterror());
        }

        return $result;
    }
}
