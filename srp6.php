<?php
// ============================================================
//  srp6.php — SRP6 compatible TrinityCore 3.3.5a
//
//  Source de référence : TrinityCore GruntSRP6 (authserver)
//  N = 894B645E89E1535BBDAD5B8B290650530801B18EBFBF5E8FAB3C82872A3E9BB7
//  g = 7, H = SHA-1
//  Toutes les big numbers sont stockées en LITTLE-ENDIAN dans la DB
// ============================================================

class SRP6
{
    // !! Prime N CORRECTE pour TrinityCore 3.3.5a !!
    // ATTENTION : votre code précédent avait "B79B3399..." qui est FAUX
    // La valeur correcte commence par "894B645E..."
    // Source : TrinityCore/TrinityCore GruntSRP6.cpp (branche 3.3.5)
    const N_HEX = '894B645E89E1535BBDAD5B8B290650530801B18EBFBF5E8FAB3C82872A3E9BB7';

    const G = 7;

    /**
     * Génère un salt aléatoire (32 octets binaires)
     */
    public static function generateSalt(): string
    {
        return random_bytes(32);
    }

    /**
     * Calcule le verifier SRP6 compatible TrinityCore 3.3.5a
     *
     * Algorithme :
     *   h1  = SHA1(UPPER(username) . ':' . UPPER(password))   [raw bytes]
     *   h2  = SHA1(salt_bytes . h1)                           [raw bytes]
     *   x   = BigNumber(h2) en little-endian
     *   v   = g^x mod N                                       [little-endian, 32 bytes]
     */
    public static function calcVerifier(
        string $username,
        string $password,
        string $saltBytes
    ): array {
        $N = gmp_init(self::N_HEX, 16);
        $g = gmp_init(self::G);

        $U = strtoupper($username);
        $P = strtoupper($password);

        $h1 = sha1($U . ':' . $P, true);
        $h2 = sha1($saltBytes . $h1, true);

        // LITTLE-ENDIAN : convention du protocole d'auth WoW
        $x = gmp_import($h2, 1, GMP_LSW_FIRST | GMP_LITTLE_ENDIAN);
        $v = gmp_powm($g, $x, $N);

        $vBytes = gmp_export($v, 1, GMP_LSW_FIRST | GMP_LITTLE_ENDIAN);
        $vBytes = str_pad($vBytes, 32, "\x00", STR_PAD_RIGHT);

        return [
            'salt'     => $saltBytes,
            'verifier' => substr($vBytes, 0, 32),
        ];
    }

    /**
     * Vérifie un mot de passe côté CMS
     */
    public static function verifyPassword(
        string $username,
        string $password,
        string $saltBytes,
        string $storedVerifier
    ): bool {
        if (strlen($saltBytes) !== 32 || strlen($storedVerifier) !== 32) {
            error_log('[SRP6] Longueur invalide : salt=' . strlen($saltBytes) . ' verifier=' . strlen($storedVerifier));
            return false;
        }
        $computed = self::calcVerifier($username, $password, $saltBytes);
        return hash_equals($storedVerifier, $computed['verifier']);
    }

    /**
     * Debug — à utiliser temporairement pour diagnostiquer, puis supprimer
     */
    public static function debugVerifier(
        string $username,
        string $password,
        string $saltBytes,
        string $storedVerifier
    ): array {
        $computed = self::calcVerifier($username, $password, $saltBytes);
        return [
            'username'     => $username,
            'salt_len'     => strlen($saltBytes),
            'verifier_len' => strlen($storedVerifier),
            'salt_hex'     => bin2hex($saltBytes),
            'stored_hex'   => bin2hex($storedVerifier),
            'computed_hex' => bin2hex($computed['verifier']),
            'match'        => hash_equals($storedVerifier, $computed['verifier']),
            'N_used'       => self::N_HEX,
        ];
    }
}
