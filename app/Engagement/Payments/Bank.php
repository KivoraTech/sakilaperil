<?php

declare(strict_types=1);

namespace App\Engagement\Payments;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/** Bank transfer helpers (port of lib/payments/bank.ts; always available, ADR-008). */
final class Bank
{
    public static function normalizeIban(string $iban): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $iban));
    }

    /** Groups of 4 for display ("IT60 X054 …"). */
    public static function formatIban(string $iban): string
    {
        return trim((string) preg_replace('/(.{4})/', '$1 ', self::normalizeIban($iban)));
    }

    /** ISO 13616 mod-97 check, so a typo never produces a scannable-but-wrong QR. */
    public static function isValidIban(string $iban): bool
    {
        $s = self::normalizeIban($iban);
        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $s) !== 1) {
            return false;
        }
        $rearranged = substr($s, 4).substr($s, 0, 4);
        $remainder = 0;
        foreach (str_split($rearranged) as $ch) {
            $digits = ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
            foreach (str_split($digits) as $d) {
                $remainder = ($remainder * 10 + (int) $d) % 97;
            }
        }

        return $remainder === 1;
    }

    /** EPC069-12 "SEPA Credit Transfer" QR payload (version 002, UTF-8). */
    public static function epcPayload(string $name, string $iban, ?string $bic = null, ?float $amountEur = null, ?string $remittance = null): string
    {
        $amount = $amountEur !== null && $amountEur > 0
            ? 'EUR'.(string) preg_replace('/\.00$/', '', number_format($amountEur, 2, '.', ''))
            : '';

        return implode("\n", [
            'BCD', '002', '1', 'SCT',
            strtoupper((string) preg_replace('/\s+/', '', $bic ?? '')),
            mb_substr($name, 0, 70),
            self::normalizeIban($iban),
            $amount, '', '',
            mb_substr($remittance ?? '', 0, 140),
        ]);
    }

    /**
     * SVG data URI of a QR code (server-side, so no QR library ships to the browser). Same SVG shape
     * as the `qrcode` npm package: margin 2, error correction M, one <path> of dark modules.
     */
    public static function qrDataUri(string $payload): string
    {
        $qr = Encoder::encode($payload, ErrorCorrectionLevel::M(), 'UTF-8');
        $matrix = $qr->getMatrix();
        $n = $matrix->getWidth();
        $margin = 2;
        $size = $n + $margin * 2;
        $d = '';
        for ($y = 0; $y < $n; $y++) {
            $x = 0;
            while ($x < $n) {
                if ($matrix->get($x, $y) !== 1) {
                    $x++;

                    continue;
                }
                $start = $x;
                while ($x < $n && $matrix->get($x, $y) === 1) {
                    $x++;
                }
                $d .= 'M'.($start + $margin).' '.($y + $margin + 0.5).'h'.($x - $start);
            }
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.$size.'" shape-rendering="crispEdges">'
            .'<path fill="#ffffff" d="M0 0h'.$size.'v'.$size.'H0z"/>'
            .'<path stroke="#000000" d="'.$d.'"/></svg>'."\n";

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
