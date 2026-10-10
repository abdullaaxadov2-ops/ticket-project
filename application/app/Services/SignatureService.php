<?php

namespace App\Services;

use App\Contracts\SignatureContract;

class SignatureService implements SignatureContract
{
    public function sign(array $data, string $key): string
    {
        ksort($data);
        $string = implode('|', array_map(
            fn ($dataKey, $value) => "{$dataKey}|{$value}",
            array_keys($data),
            array_values($data),
        ));

        return hash_hmac('sha256', $string, $key);
    }

    public function checkSignature(array $data, string $key, string $controlSignature): bool
    {
        return hash_equals($this->sign($data, $key), $controlSignature);
    }
}
