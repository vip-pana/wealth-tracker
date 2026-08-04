<?php

declare(strict_types=1);

namespace App\Actions\Backup;

use App\Actions\Action;
use RuntimeException;
use SensitiveParameter;

/**
 * Encrypts a backup file before it leaves the machine, and decrypts it again on
 * restore. AES-256-CBC over fixed-size chunks with an HMAC-SHA256 over the
 * whole ciphertext, streamed so memory use stays flat as the database grows.
 *
 * File layout: MAGIC | IV (16B) | ciphertext… | HMAC (32B, trailing).
 *
 * The MAC covers the IV and every ciphertext byte, and is verified in a
 * separate pass *before* any plaintext is written, so a truncated or tampered
 * archive is rejected rather than half-restored.
 */
class EncryptBackup extends Action
{
    private const MAGIC = "WTBK1\0";

    private const CIPHER = 'aes-256-cbc';

    private const IV_BYTES = 16;

    private const MAC_BYTES = 32;

    private const CHUNK_BYTES = 1_048_576;

    public function encrypt(string $sourcePath, string $targetPath): void
    {
        [$encryptionKey, $macKey] = $this->keys();

        $in = $this->open($sourcePath, 'rb');
        $out = $this->open($targetPath, 'wb');

        $iv = random_bytes(self::IV_BYTES);
        $hmac = hash_init('sha256', HASH_HMAC, $macKey);

        $this->write($out, self::MAGIC, $targetPath);
        $this->write($out, $iv, $targetPath);
        hash_update($hmac, $iv);

        // CBC needs the previous block as the IV for the next chunk, so each
        // chunk is encrypted without padding and the running IV is the last
        // ciphertext block. Only the final chunk carries the padding.
        $runningIv = $iv;
        $buffer = '';

        while (! feof($in)) {
            $read = fread($in, self::CHUNK_BYTES);
            if ($read === false) {
                fclose($in);
                fclose($out);
                throw new RuntimeException("Unable to read backup: {$sourcePath}");
            }

            $buffer .= $read;

            // Hold back a partial block: everything but the trailing remainder
            // is encrypted now, the remainder waits for more input (or the
            // final padded chunk).
            $whole = intdiv(strlen($buffer), self::IV_BYTES) * self::IV_BYTES;
            if ($whole === 0) {
                continue;
            }

            $block = substr($buffer, 0, $whole);
            $buffer = substr($buffer, $whole);

            $cipherChunk = openssl_encrypt(
                $block,
                self::CIPHER,
                $encryptionKey,
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                $runningIv,
            );

            if ($cipherChunk === false) {
                fclose($in);
                fclose($out);
                throw new RuntimeException('Backup encryption failed.');
            }

            $this->write($out, $cipherChunk, $targetPath);
            hash_update($hmac, $cipherChunk);
            $runningIv = substr($cipherChunk, -self::IV_BYTES);
        }

        // Final chunk: PKCS#7 padding is applied here even when the remainder
        // is empty, so the archive always ends on a padded block.
        $final = openssl_encrypt(
            $buffer,
            self::CIPHER,
            $encryptionKey,
            OPENSSL_RAW_DATA,
            $runningIv,
        );

        if ($final === false) {
            fclose($in);
            fclose($out);
            throw new RuntimeException('Backup encryption failed.');
        }

        $this->write($out, $final, $targetPath);
        hash_update($hmac, $final);
        $this->write($out, hash_final($hmac, true), $targetPath);

        fclose($in);
        fclose($out);
    }

    public function decrypt(string $sourcePath, string $targetPath): void
    {
        [$encryptionKey, $macKey] = $this->keys();

        $size = filesize($sourcePath);
        if ($size === false) {
            throw new RuntimeException("Unable to read backup: {$sourcePath}");
        }

        $headerBytes = strlen(self::MAGIC) + self::IV_BYTES;
        $cipherBytes = $size - $headerBytes - self::MAC_BYTES;

        if ($cipherBytes <= 0 || $cipherBytes % self::IV_BYTES !== 0) {
            throw new RuntimeException("Not a valid backup archive: {$sourcePath}");
        }

        $in = $this->open($sourcePath, 'rb');

        $magic = (string) fread($in, strlen(self::MAGIC));
        if (! hash_equals(self::MAGIC, $magic)) {
            fclose($in);
            throw new RuntimeException("Not a valid backup archive: {$sourcePath}");
        }

        $iv = (string) fread($in, self::IV_BYTES);

        // Verify before decrypting: a tampered or truncated archive must never
        // produce a partially written plaintext database.
        $this->verifyMac($in, $iv, $macKey, $cipherBytes, $sourcePath);

        fseek($in, $headerBytes);
        $out = $this->open($targetPath, 'wb');

        $runningIv = $iv;
        $remaining = $cipherBytes;

        while ($remaining > 0) {
            $want = (int) min(self::CHUNK_BYTES, $remaining);
            $chunk = (string) fread($in, $want);
            $remaining -= $want;

            $isFinal = $remaining === 0;

            $plain = openssl_decrypt(
                $chunk,
                self::CIPHER,
                $encryptionKey,
                $isFinal
                    ? OPENSSL_RAW_DATA
                    : OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                $runningIv,
            );

            if ($plain === false) {
                fclose($in);
                fclose($out);
                throw new RuntimeException('Backup decryption failed: wrong key?');
            }

            $this->write($out, $plain, $targetPath);
            $runningIv = substr($chunk, -self::IV_BYTES);
        }

        fclose($in);
        fclose($out);
    }

    /**
     * @param  resource  $handle
     */
    private function verifyMac(
        $handle,
        string $iv,
        #[SensitiveParameter] string $macKey,
        int $cipherBytes,
        string $sourcePath,
    ): void {
        $hmac = hash_init('sha256', HASH_HMAC, $macKey);
        hash_update($hmac, $iv);

        $remaining = $cipherBytes;
        while ($remaining > 0) {
            $want = (int) min(self::CHUNK_BYTES, $remaining);
            hash_update($hmac, (string) fread($handle, $want));
            $remaining -= $want;
        }

        $expected = (string) fread($handle, self::MAC_BYTES);

        if (! hash_equals(hash_final($hmac, true), $expected)) {
            fclose($handle);
            throw new RuntimeException("Backup archive failed integrity check: {$sourcePath}");
        }
    }

    /**
     * Separate keys for encryption and authentication, both derived from the
     * configured secret so a single env var stays the whole story.
     *
     * @return array{string, string}
     */
    private function keys(): array
    {
        /** @var string|null $configured */
        $configured = config('backup.encryption_key');

        if ($configured === null || $configured === '') {
            throw new RuntimeException(
                'BACKUP_ENCRYPTION_KEY is not set. Generate one with `php artisan backup:keygen`.'
            );
        }

        $secret = base64_decode($configured, true);

        if ($secret === false || strlen($secret) !== 32) {
            throw new RuntimeException(
                'BACKUP_ENCRYPTION_KEY must be 32 base64-encoded bytes. Regenerate it with `php artisan backup:keygen`.'
            );
        }

        return [
            hash_hkdf('sha256', $secret, 32, 'wealth-tracker/backup/enc'),
            hash_hkdf('sha256', $secret, 32, 'wealth-tracker/backup/mac'),
        ];
    }

    /**
     * @return resource
     */
    private function open(string $path, string $mode)
    {
        $handle = fopen($path, $mode);

        if ($handle === false) {
            throw new RuntimeException("Unable to open {$path}");
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     */
    private function write($handle, string $bytes, string $path): void
    {
        if (fwrite($handle, $bytes) === false) {
            throw new RuntimeException("Unable to write {$path}");
        }
    }
}
