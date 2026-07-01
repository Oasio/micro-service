<?php

namespace App\Tests\Fake;

/**
 * Paire de clés RSA de TEST (uniquement pour les tests : l'Auth ne tourne pas en PHPUnit).
 * On signe un JWT avec la clé privée, et le double de provider renvoie la clé publique
 * correspondante → on peut tester la vérification (200) et les rejets (401).
 *
 * Clé de test sans valeur de sécurité (générée pour la suite de tests).
 */
final class TestKeys
{
    public const KID = 'test-kid';

    public const PUBLIC_PEM = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvqwGpSSjkgFrgQF2sG6o
iD4f/316f2sFLapzrOkl6bVyCqJ+IiVj5FxT59dLn2FFduTg1CurV6eEdEgTzJL2
6N+6UIpUtHCq51uygb5Uu1k7HH46qEOr+8ZNQd8jXUO3/JzV3mLOiP0NQe9/91FI
X7SgevRmUBAY/YH/V2lBw/fbr0x+YJn20F52TrnilAdP1ZuPrM/df8Tz7b6JHVvq
yQfPFGugy7zp3INmsjp2QY98QEQKrFa88qL6o9g3ol3+45dwhSNJKaP2WPWEzg8/
oBFe+C2TAUD4L/ZxKr0ik/SrY3ob3urCn8lW/5dDVolvZ60JoRa+8yvj5NLWgiAV
DQIDAQAB
-----END PUBLIC KEY-----
PEM;

    public const PRIVATE_PEM = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC+rAalJKOSAWuB
AXawbqiIPh//fXp/awUtqnOs6SXptXIKon4iJWPkXFPn10ufYUV25ODUK6tXp4R0
SBPMkvbo37pQilS0cKrnW7KBvlS7WTscfjqoQ6v7xk1B3yNdQ7f8nNXeYs6I/Q1B
73/3UUhftKB69GZQEBj9gf9XaUHD99uvTH5gmfbQXnZOueKUB0/Vm4+sz91/xPPt
vokdW+rJB88Ua6DLvOncg2ayOnZBj3xARAqsVrzyovqj2DeiXf7jl3CFI0kpo/ZY
9YTODz+gEV74LZMBQPgv9nEqvSKT9Ktjehve6sKfyVb/l0NWiW9nrQmhFr7zK+Pk
0taCIBUNAgMBAAECggEAH0bcBtuTZ/rgUJbAimvMgh92uOkzXpKxPxlrxNxbLL1n
ye4iX1A01PEpjlPsWBDpB89ple7dOhFaPTIplUWHnRGTYBd2POxntxsn6qJ3Bw25
uuXLPMNvrcA3TME2mDDojavduo9eA8NGw8nD+voUCNGkqtFLtdcTfQTbBtKsSAx8
7XdVXExcBIQx9ATWZ72GOtDz6xRmFbBMH+OqVFw41q3vFTkc4RruuGr6cQQ73T+5
bkj2ot4zUKgOhz0H9QfsvEe8/O8C0ML3+tE6b47CsXnWnqi6N6lTU0Ym3FFJIhM/
707phyQbxH7CZEBBRXSymHvbRyG8UbsYq3CfEMR1nQKBgQD8Mvl7ADbbOMF9YNR9
Cd2/wpSYt5tgldawI54n/PCd2S0lSVzaxg8dGmLASEiHxbwpMahgRldrjPhz1/hG
s2v52bLTSYcW8o0z4iMDv37T8/utnZhL4iOjk3Fp+DVqwbEFu7Htf6YRpw1m9t/u
T/v8uJpI1GwKrbGhPJ1ldDtIZwKBgQDBi6tth1ormQFRagHyT0YhpPNC+AX4CLI8
c10zeeFUeOF8yFCE9ZPlLKSuis6cifLWQnzYYsEzZ2E3liuroqyqUDfRsXg+ghsN
Wpc6HU7LGBS0y39C7+FKLs0BGxw/CDlLyXMVpRLSFuCGfl6pwICy4UbQgi/LHvg7
DkoBE0VeawKBgQDdnxHbRAHwvTxTKF8yDhR+qcgZN+fjhWjm2jXmYAE2RR3GEWT2
n2uyoHg9DogmP94suQWErOuviG7gpd34iz0Mj4D06T08LiNlf4hTh9k0+sek9sNZ
k7zLxwq4G7UdPl6IcjtWQIE4K/u8CqAX9NO0bqit7XWQILjivrh16iEaawKBgHVF
CFi0AzmZWogW4BkOWWL3TAHOv+cadcxU5irTdWk2WQG/abI2Did01k9/gVKt7vpN
jNGbfI6F3AzPK6SyS0Ziln+ytTXCpVuBAVJQAbbi32DwUCqhp/LHyqUZ5RJ4DCdU
Zyu9OlsbS22SUg8uuYwpHTwnNYgwOp2lucdPAuADAoGBAPYeak7COsnEm8ImtPBH
U1mS16SUTMKNb8XjZDgOrp554mxv8GDd+MtX5npX1w83wIayOuv7P9gJt6YJ7Ykm
Qe4M4On9Vq0tiKWRUQ9Ml5z2DaJ9mQc9E5tCNpwtVkfTe4BtP69g+pa7/WLE4VnD
HIzAaR2tmpkjGfyOfG5naKcE
-----END PRIVATE KEY-----
PEM;

    /**
     * Forge un JWT RS256 signé avec la clé privée de test.
     *
     * @param array<string, mixed> $claims
     */
    public static function mintToken(array $claims = []): string
    {
        $claims = array_merge([
            'sub' => '1',
            'username' => 'alice',
            'roles' => ['ROLE_USER'],
            'iat' => time(),
            'exp' => time() + 3600,
        ], $claims);

        $b64 = static fn (string $d): string => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

        $header = $b64(json_encode(['typ' => 'JWT', 'alg' => 'RS256', 'kid' => self::KID]));
        $payload = $b64(json_encode($claims));

        openssl_sign($header.'.'.$payload, $signature, self::PRIVATE_PEM, OPENSSL_ALGO_SHA256);

        return $header.'.'.$payload.'.'.$b64($signature);
    }
}
