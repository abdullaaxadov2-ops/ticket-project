<?php

namespace Tests\Unit;

use App\Services\SignatureService;
use PHPUnit\Framework\TestCase;

class SignatureServiceTest extends TestCase
{
    private const KEY = 'secret';

    public function testSignsSortedKeyValuePairsWithHmacSha256(): void
    {
        $service = new SignatureService();

        $signature = $service->sign(['status' => 1, 'order_id' => '42', 'amount' => 45000000], self::KEY);

        $this->assertSame(
            hash_hmac('sha256', 'amount|45000000|order_id|42|status|1', self::KEY),
            $signature,
        );
    }

    public function testKeyOrderDoesNotChangeSignature(): void
    {
        $service = new SignatureService();

        $this->assertSame(
            $service->sign(['a' => 1, 'b' => 2], self::KEY),
            $service->sign(['b' => 2, 'a' => 1], self::KEY),
        );
    }

    public function testCheckSignatureAcceptsValidSignature(): void
    {
        $service = new SignatureService();
        $data = ['order_id' => '42', 'amount' => 45000000, 'status' => 1];

        $this->assertTrue($service->checkSignature($data, self::KEY, $service->sign($data, self::KEY)));
    }

    public function testCheckSignatureRejectsWrongKeyOrChangedData(): void
    {
        $service = new SignatureService();
        $data = ['order_id' => '42', 'amount' => 45000000, 'status' => 1];
        $signature = $service->sign($data, self::KEY);

        $this->assertFalse($service->checkSignature($data, 'other-key', $signature));
        $this->assertFalse($service->checkSignature([...$data, 'amount' => 1], self::KEY, $signature));
    }
}
