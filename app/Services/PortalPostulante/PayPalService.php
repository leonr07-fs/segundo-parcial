<?php

namespace App\Services\PortalPostulante;

use Illuminate\Support\Facades\Http;
use DomainException;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $webhookId;

    public function __construct()
    {
        $mode = config('services.paypal.mode', 'sandbox');
        $this->baseUrl = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
        $this->clientId = config('services.paypal.client_id', '');
        $this->clientSecret = config('services.paypal.client_secret', '');
        $this->webhookId = config('services.paypal.webhook_id', '');
    }

    /**
     * Obtener el Access Token de PayPal
     */
    private function getAccessToken(): string
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            Log::error('PayPal getAccessToken Failed', ['response' => $response->json()]);
            throw new DomainException('No se pudo autenticar con PayPal.');
        }

        return $response->json('access_token');
    }

    /**
     * Crear una nueva orden de pago (Intent: CAPTURE)
     */
    public function createOrder(float $amount, string $currency = 'BOB', string $referenceId = ''): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/v2/checkout/orders", [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => $referenceId,
                        'custom_id' => $referenceId,
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => number_format($amount, 2, '.', ''),
                        ]
                    ]
                ]
            ]);

        if ($response->failed()) {
            Log::error('PayPal createOrder Failed', ['response' => $response->json()]);
            throw new DomainException('No se pudo crear la orden en PayPal.');
        }

        return $response->json(); // Retorna la estructura de la orden, incluyendo el ID
    }

    /**
     * Capturar los fondos de una orden aprobada por el cliente.
     */
    public function captureOrder(string $orderId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withBody('{}', 'application/json')
            ->post("{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture");

        if ($response->failed()) {
            Log::error('PayPal captureOrder Failed', ['response' => $response->json()]);
            throw new DomainException('No se pudo capturar el pago en PayPal.');
        }

        return $response->json(); // Retorna información de la captura
    }

    /**
     * Verifica la firma criptográfica del Webhook recibido.
     */
    public function verifyWebhookSignature(array $headers, array $payload): bool
    {
        if (empty($this->webhookId)) {
            Log::warning('PayPal Webhook ID no configurado, omitiendo validación estricta.');
            // Dependiendo de la seguridad deseada, si no hay webhook ID podríamos rechazar, 
            // pero para desarrollo con ngrok sin webhook ID configurado podríamos retornar true o false.
            // Para producción, siempre debe estar configurado.
            throw new DomainException('PayPal Webhook ID no configurado.');
        }

        $token = $this->getAccessToken();

        $verificationPayload = [
            'auth_algo' => $headers['PAYPAL-AUTH-ALGO'][0] ?? '',
            'cert_url' => $headers['PAYPAL-CERT-URL'][0] ?? '',
            'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'][0] ?? '',
            'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'][0] ?? '',
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'][0] ?? '',
            'webhook_id' => $this->webhookId,
            'webhook_event' => $payload,
        ];

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/v1/notifications/verify-webhook-signature", $verificationPayload);

        if ($response->failed()) {
            Log::error('PayPal verifyWebhookSignature Failed', ['response' => $response->json()]);
            return false;
        }

        $status = $response->json('verification_status');
        return $status === 'SUCCESS';
    }
}
