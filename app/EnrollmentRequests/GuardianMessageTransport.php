<?php

namespace App\EnrollmentRequests;

use Illuminate\Support\Facades\Http;

class GuardianMessageTransport
{
    public function send(string $channel, string $recipient, array $payload, string $key): void
    {
        $options = config('bc-messages.channels.'.$channel);
        if (!$options || !filter_var($options['url'] ?? null, FILTER_VALIDATE_URL)
            || !in_array(parse_url($options['url'], PHP_URL_SCHEME), ['http', 'https'], true)
            || (!app()->environment(['local', 'testing']) && parse_url($options['url'], PHP_URL_SCHEME) !== 'https')) {
            throw new \RuntimeException('Canal sem endpoint válido; HTTPS é obrigatório fora do ambiente local.');
        }
        $request = Http::timeout(10)->connectTimeout(3)->withoutRedirecting()
            ->withHeaders(['Idempotency-Key' => $key]);
        if ($options['token'] ?? null) {
            $request = $request->withToken($options['token']);
        }
        $response = $request->post($options['url'], $payload + [
            'channel' => $channel, 'recipient' => $recipient, 'idempotency_key' => $key,
        ]);
        if (!$response->successful()) {
            throw new \RuntimeException('Endpoint recusou a mensagem: HTTP '.$response->status());
        }
    }

    public function recipient(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        $number = preg_replace('/[^0-9]/', '', $value);
        if (!str_starts_with(trim($value), '+')) {
            if (in_array(strlen($number), [10, 11])) {
                $number = config('bc-messages.country_code').$number;
            } elseif (!str_starts_with($number, (string) config('bc-messages.country_code'))) {
                return null;
            }
        }
        $number = '+'.$number;

        return preg_match('/^\\+[1-9][0-9]{7,14}$/', $number) ? $number : null;
    }
}
