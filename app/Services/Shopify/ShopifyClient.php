<?php

namespace App\Services\Shopify;

use Illuminate\Support\Facades\Http;

class ShopifyClient
{
    public function graphql(string $query, array $variables = []): array
    {
        $shop = config('shopify.shop_domain');
        $version = config('shopify.api_version');
        $token = config('shopify.access_token');

        $url = "https://{$shop}/admin/api/{$version}/graphql.json";

        $attempts = 0;
        $maxAttempts = 6;

        // 👇 IMPORTANT: variables debe ser objeto JSON, no lista
        $varsPayload = empty($variables) ? (object)[] : $variables;

        while (true) {
            $attempts++;

            $res = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Accept' => 'application/json',
            ])->post($url, [
                'query' => $query,
                'variables' => $varsPayload,
            ]);

            if ($res->status() === 429 && $attempts < $maxAttempts) {
                $retryAfter = (int) ($res->header('Retry-After') ?? 1);
                sleep(max(1, min($retryAfter, 5)));
                continue;
            }

            if (!$res->successful()) {
                throw new \RuntimeException("Shopify error HTTP {$res->status()}: ".$res->body());
            }

            $json = $res->json();

            if (!empty($json['errors'])) {
                $msg = $json['errors'][0]['message'] ?? 'GraphQL error';
                throw new \RuntimeException($msg);
            }

            return $json;
        }
    }
}
