<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Tests\Unit\Fixtures;

use Brix\CelumFal\Utility\LicenseKey;
use ReflectionClassConstant;

/**
 * Builds license keys for tests.
 *
 * Inverse of LicenseKey::decrypt(): Vigenere cipher (mod 256, addition form)
 * keyed by the secret, encoded as base64.
 */
final class LicenseKeyFixture
{
    public static function encode(string $host, int $expiryTimestamp): string
    {
        return self::encrypt($host . '_' . $expiryTimestamp);
    }

    public static function encrypt(string $plain): string
    {
        $secret = self::secret();
        $secretLength = strlen($secret);

        $encrypted = '';
        for ($i = 0, $length = strlen($plain); $i < $length; $i++) {
            $secretChar = substr($secret, ($i % $secretLength) - 1, 1);
            $encrypted .= chr((ord($plain[$i]) + ord($secretChar)) % 256);
        }

        return base64_encode($encrypted);
    }

    private static function secret(): string
    {
        return (string)(new ReflectionClassConstant(LicenseKey::class, 'SECRET'))->getValue();
    }
}
