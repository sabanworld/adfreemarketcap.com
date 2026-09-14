<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Minimal Bech32 decode for Nostr npub → hex pubkey (BIP-173).
 */
final class Bech32
{
    private const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    public static function npubToHex(string $npub): string
    {
        $decoded = self::decode($npub);
        throw_unless($decoded['hrp'] === 'npub', new InvalidArgumentException('Expected an npub bech32 string.'));

        $bytes = self::convertBits($decoded['data'], 5, 8, false);
        throw_unless($bytes !== null && count($bytes) === 32, new InvalidArgumentException('Invalid npub payload length.'));

        return bin2hex(implode('', array_map(chr(...), $bytes)));
    }

    /**
     * @return array{hrp: string, data: list<int>}
     */
    private static function decode(string $bech): array
    {
        $bech = strtolower($bech);
        $pos = strrpos($bech, '1');
        throw_unless($pos !== false && $pos >= 1, new InvalidArgumentException('Invalid bech32 string.'));

        $hrp = substr($bech, 0, $pos);
        $data = [];
        $length = strlen($bech);

        for ($i = $pos + 1; $i < $length; $i++) {
            $value = strpos(self::CHARSET, $bech[$i]);
            throw_unless($value !== false, new InvalidArgumentException('Invalid bech32 character.'));
            $data[] = $value;
        }

        throw_unless(count($data) >= 6, new InvalidArgumentException('Bech32 data too short.'));
        throw_unless(self::verifyChecksum($hrp, $data), new InvalidArgumentException('Invalid bech32 checksum.'));

        return [
            'hrp' => $hrp,
            'data' => array_slice($data, 0, -6),
        ];
    }

    /**
     * @param  list<int>  $data
     * @return list<int>|null
     */
    private static function convertBits(array $data, int $fromBits, int $toBits, bool $pad): ?array
    {
        $acc = 0;
        $bits = 0;
        $ret = [];
        $maxv = (1 << $toBits) - 1;

        foreach ($data as $value) {
            if ($value < 0 || ($value >> $fromBits) !== 0) {
                return null;
            }

            $acc = ($acc << $fromBits) | $value;
            $bits += $fromBits;

            while ($bits >= $toBits) {
                $bits -= $toBits;
                $ret[] = ($acc >> $bits) & $maxv;
            }
        }

        if ($pad) {
            if ($bits > 0) {
                $ret[] = ($acc << ($toBits - $bits)) & $maxv;
            }
        } elseif ($bits >= $fromBits || ((($acc << ($toBits - $bits)) & $maxv) !== 0)) {
            return null;
        }

        return $ret;
    }

    /**
     * @param  list<int>  $data
     */
    private static function verifyChecksum(string $hrp, array $data): bool
    {
        return self::polymod(array_merge(self::hrpExpand($hrp), $data)) === 1;
    }

    /**
     * @return list<int>
     */
    private static function hrpExpand(string $hrp): array
    {
        $ret = [];
        $length = strlen($hrp);

        for ($i = 0; $i < $length; $i++) {
            $ret[] = ord($hrp[$i]) >> 5;
        }

        $ret[] = 0;

        for ($i = 0; $i < $length; $i++) {
            $ret[] = ord($hrp[$i]) & 31;
        }

        return $ret;
    }

    /**
     * @param  list<int>  $values
     */
    private static function polymod(array $values): int
    {
        $generator = [0x3B6A57B2, 0x26508E6D, 0x1EA119FA, 0x3D4233DD, 0x2A1462B3];
        $chk = 1;

        foreach ($values as $value) {
            $top = $chk >> 25;
            $chk = (($chk & 0x1FFFFFF) << 5) ^ $value;

            for ($i = 0; $i < 5; $i++) {
                if ((($top >> $i) & 1) !== 0) {
                    $chk ^= $generator[$i];
                }
            }
        }

        return $chk;
    }
}
