<?php

declare(strict_types=1);

use App\Orders\OrderRepository;
use App\PaymentService;
use App\Paystack\PaystackClient;
use App\Support\Env;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

function app_config(): array
{
    return [
        'secret' => Env::get('PAYSTACK_SECRET_KEY'),
        'public' => Env::get('PAYSTACK_PUBLIC_KEY'),
        'app_url' => rtrim(Env::get('APP_URL', 'http://localhost:8000'), '/'),
        'db' => Env::get('DB_FILE', dirname(__DIR__) . '/storage/orders.sqlite'),
    ];
}

function orders(): OrderRepository
{
    static $repo;
    return $repo ??= OrderRepository::sqlite(app_config()['db']);
}

function payments(): ?PaymentService
{
    $secret = app_config()['secret'];
    return $secret ? new PaymentService(new PaystackClient($secret), orders()) : null;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Demo catalogue. Prices are in kobo and only ever read on the server, never from the form. */
const PRODUCTS = [
    'starter' => ['name' => 'Starter Website', 'desc' => '5-page responsive business website', 'price' => 15000000],
    'mobile' => ['name' => 'Flutter Mobile App', 'desc' => 'Android & iOS app with backend API', 'price' => 45000000],
    'seo' => ['name' => 'SEO Audit', 'desc' => 'Full on-page audit with action plan', 'price' => 2500000],
];

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
    }
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}
