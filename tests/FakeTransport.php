<?php

declare(strict_types=1);

namespace Tests;

use App\Paystack\HttpTransport;

/** Records requests and returns queued responses instead of calling Paystack. */
final class FakeTransport implements HttpTransport
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];
    /** @var list<array{status:int, body:string}> */
    private array $responses = [];

    /** @param array<string,mixed> $json */
    public function queue(int $status, array $json): self
    {
        $this->responses[] = ['status' => $status, 'body' => json_encode($json)];
        return $this;
    }

    public function request(string $method, string $url, array $headers, ?string $body = null): array
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        return array_shift($this->responses) ?? ['status' => 500, 'body' => 'no response queued'];
    }
}
