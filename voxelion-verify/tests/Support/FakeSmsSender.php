<?php

namespace Tests\Support;

use App\Services\Sms\SmsSender;

/** Records outbound SMS so tests can extract the OTP and assert delivery. */
class FakeSmsSender implements SmsSender
{
    /** @var array<int,array{to:string,message:string}> */
    public array $sent = [];

    public bool $throws = false;

    public function send(string $to, string $message): void
    {
        if ($this->throws) {
            throw new \RuntimeException('sms provider error');
        }

        $this->sent[] = ['to' => $to, 'message' => $message];
    }

    /** Extract the 6-digit OTP from the last sent message. */
    public function lastCode(): string
    {
        preg_match('/(\d{6})/', end($this->sent)['message'], $m);

        return $m[1];
    }
}
