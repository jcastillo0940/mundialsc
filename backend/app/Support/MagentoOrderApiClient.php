<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MagentoOrderApiClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function ordersForEmail(string $email): array
    {
        return $this->ordersWhere('customer_email', strtolower(trim($email)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function ordersForIncrementId(string $incrementId): array
    {
        return $this->ordersWhere('increment_id', trim($incrementId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ordersWhere(string $field, string $value): array
    {
        $baseUrl = rtrim((string) config('services.magento.base_url'), '/');
        $token = (string) config('services.magento.access_token');

        if ($baseUrl === '' || $token === '') {
            throw ValidationException::withMessages([
                'magento' => 'La conexion con la tienda en linea no esta configurada.',
            ]);
        }

        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout((int) config('services.magento.timeout', 20))
            ->get($this->ordersEndpoint($baseUrl), [
                'searchCriteria[filter_groups][0][filters][0][field]' => $field,
                'searchCriteria[filter_groups][0][filters][0][value]' => $value,
                'searchCriteria[filter_groups][0][filters][0][condition_type]' => 'eq',
                'searchCriteria[sortOrders][0][field]' => 'created_at',
                'searchCriteria[sortOrders][0][direction]' => 'DESC',
                'searchCriteria[pageSize]' => (int) config('services.magento.order_lookup_page_size', 25),
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'magento' => 'No fue posible consultar la tienda en linea en este momento.',
            ]);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $items = $payload['items'] ?? [];

        return is_array($items) ? $items : [];
    }

    private function ordersEndpoint(string $baseUrl): string
    {
        $storeCode = trim((string) config('services.magento.store_code', ''));
        $restPath = $storeCode !== '' ? "rest/{$storeCode}/V1/orders" : 'rest/V1/orders';

        return $baseUrl.'/'.$restPath;
    }
}
