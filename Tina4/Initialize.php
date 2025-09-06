<?php

namespace Tina4;

function bc_divmod(string $a, string $b): array {
    return [bcdiv($a, $b, 0), bcmod($a, $b)];
}

function bc_lshift(string $a, int $n): string {
    return bcmul($a, bcpow('2', (string)$n, 0), 0);
}

function bc_or(string $a, string $b): string {
    // Since no bit overlap in usage, use bcadd; otherwise implement full bitwise
    return bcadd($a, $b, 0);
}

function bc_hex(string $num): string {
    if (bccomp($num, '0') === 0) {
        return '0';
    }
    $hex = '';
    while (bccomp($num, '0') > 0) {
        $rem = (int) bcmod($num, '16');
        $hex = dechex($rem) . $hex;
        $num = bcdiv($num, '16', 0);
    }
    return $hex;
}

function uuid7(): string {
    static $last = ['0', '0', '0', '0'];
    static $last_as_of = ['0', '0', '0', '0']; // Not used in provided Python, but included

    $ns = hrtime(true);
    $ns_str = is_float($ns) ? number_format($ns, 0, '.', '') : (string) $ns;

    if ($ns_str === '0') {
        return '00000000-0000-0000-0000-000000000000';
    }

    $sixteen_secs = '16000000000';

    [$t1, $rest1] = bc_divmod($ns_str, $sixteen_secs);
    $shifted_rest1 = bc_lshift($rest1, 16);
    [$t2, $rest2] = bc_divmod($shifted_rest1, $sixteen_secs);
    $shifted_rest2 = bc_lshift($rest2, 12);
    [$t3, $_] = bc_divmod($shifted_rest2, $sixteen_secs);

    // t3 |= 7 << 12
    $ver_shift = bc_lshift('7', 12);
    $t3 = bc_or($t3, $ver_shift); // or bcadd since no overlap

    // Sequence handling
    $seq = '0';
    if ($t1 === $last[0] && $t2 === $last[1] && $t3 === $last[2]) {
        if (bccomp($last[3], '16383') < 0) { // 0x3FFF = 16383
            $last[3] = bcadd($last[3], '1');
        }
        $seq = $last[3];
    } else {
        $last = [$t1, $t2, $t3, '0'];
    }

    // t4 = (2 << 14) | seq
    $var_shift = bc_lshift('2', 14);
    $t4 = bc_or($var_shift, $seq);

    // Random 6 bytes
    $rand = bin2hex(random_bytes(6));

    // Format with padding
    $t1_hex = str_pad(bc_hex($t1), 8, '0', STR_PAD_LEFT);
    $t2_hex = str_pad(bc_hex($t2), 4, '0', STR_PAD_LEFT);
    $t3_hex = str_pad(bc_hex($t3), 4, '0', STR_PAD_LEFT);
    $t4_hex = str_pad(bc_hex($t4), 4, '0', STR_PAD_LEFT);

    return "{$t1_hex}-{$t2_hex}-{$t3_hex}-{$t4_hex}-{$rand}";
}
