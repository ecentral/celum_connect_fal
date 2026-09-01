<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Utility;

use Brix\CelumFal\Exceptions\InvalidConfigurationException;
use DateTimeImmutable;

/**
 * A decoded CELUM license key.
 *
 * The key is the only source for the CELUM base URL and carries the date the
 * license runs out. Every rejection names its reason through the exception
 * code, so callers can tell an unreadable key from an expired one.
 */
final class LicenseKey
{
    public const REASON_EMPTY = 1756713601;
    public const REASON_UNDECODABLE = 1756713602;
    public const REASON_MALFORMED = 1756713603;

    private const SECRET = 'ZbMchtd9DivzjPDi5QIio1iVERFnNZiSE33QKY3Gw9rYfCNLFiKloJQt3zi4';

    private function __construct(
        private readonly string $host,
        private readonly DateTimeImmutable $expiresAt
    ) {
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function fromEncoded(string $encoded): self
    {
        $encoded = trim($encoded);
        if ($encoded === '') {
            throw new InvalidConfigurationException('License key is empty.', self::REASON_EMPTY);
        }

        $decrypted = self::decrypt($encoded);

        // The host may itself contain underscores, so split at the last one.
        if (preg_match('/^(.*)_([^_]+)$/', $decrypted, $matches) !== 1) {
            throw new InvalidConfigurationException('License key does not carry a host and an expiry date.', self::REASON_MALFORMED);
        }
        if (ctype_digit($matches[2]) === false) {
            throw new InvalidConfigurationException('License key does not carry a valid expiry date.', self::REASON_MALFORMED);
        }

        return new self(rtrim($matches[1]), (new DateTimeImmutable())->setTimestamp((int)$matches[2]));
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now > $this->expiresAt;
    }

    /**
     * True only while the license is still valid - an expired license is
     * reported as expired, never as "about to expire".
     */
    public function expiresWithinDays(int $days, DateTimeImmutable $now): bool
    {
        if ($this->isExpired($now)) {
            return false;
        }

        return $this->expiresAt <= $now->modify('+' . $days . ' days');
    }

    /**
     * Decrypts a license key with a Vigenere cipher (mod 256, subtraction
     * form) keyed by SECRET. See
     * https://de.wikipedia.org/wiki/Vigen%C3%A8re-Chiffre. Not real
     * cryptography - this only mirrors the format Brix's licensing defines
     * (see CLAUDE.md "Bekannte Besonderheiten").
     *
     * @throws InvalidConfigurationException
     */
    private static function decrypt(string $encoded): string
    {
        $data = self::decodeBase64($encoded);
        $keyLength = strlen(self::SECRET);

        $result = '';
        for ($i = 0, $length = strlen($data); $i < $length; $i++) {
            $keyChar = substr(self::SECRET, ($i % $keyLength) - 1, 1);
            $result .= chr((ord($data[$i]) - ord($keyChar) + 256) % 256);
        }

        return $result;
    }

    /**
     * Decodes URL-safe base64 (RFC 4648 5): swaps back the "-_" alphabet
     * to "+/" and restores the "=" padding that URL-safe encoders strip.
     *
     * @throws InvalidConfigurationException
     */
    private static function decodeBase64(string $data): string
    {
        $base64 = strtr($data, '-_', '+/');
        $remainder = strlen($base64) % 4;
        if ($remainder > 0) {
            $base64 .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            throw new InvalidConfigurationException('License key is not valid base64.', self::REASON_UNDECODABLE);
        }

        return $decoded;
    }
}
