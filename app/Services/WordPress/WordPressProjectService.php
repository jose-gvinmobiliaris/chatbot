<?php

namespace App\Services\WordPress;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WordPressProjectService
{
    private function client()
    {
        $url = rtrim(
            (string) config('services.wordpress.url'),
            '/'
        );

        if ($url === '') {
            throw new RuntimeException(
                'No se ha configurado WORDPRESS_API_URL.'
            );
        }

        return Http::baseUrl($url)
            ->acceptJson()
            ->timeout(config('services.wordpress.timeout', 10));
    }

    public function getProjects(array $filters = []): array
    {
        $params = array_filter([
            'page' => $filters['page'] ?? 1,
            'per_page' => $filters['per_page'] ?? 50,
            'district' => $filters['district'] ?? null,
            'province' => $filters['province'] ?? null,
            'property_type' => $filters['property_type'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->client()->get('/projects', $params);

        if ($response->failed()) {
            throw new RuntimeException(
                'Error consultando proyectos WordPress. HTTP: ' .
                $response->status()
            );
        }

        return $response->json();
    }

    public function getProject(int $wordpressId): ?array
    {
        $response = $this->client()->get("projects/{$wordpressId}");

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new RuntimeException(
                'Error consultando proyecto WordPress.'
            );
        }

        return $response->json();
    }

    public function getLocations(): array
    {
        $response = $this->client()->get('locations');

        if ($response->failed()) {
            throw new RuntimeException(
                'Error consultando ubicaciones WordPress.'
            );
        }

        return $response->json();
    }

    public function recommend(array $filters = []): array
    {
        $response = $this->getProjects([
            'district' => $filters['district'] ?? null,
            'province' => $filters['province'] ?? null,
            'property_type' => $filters['property_type'] ?? null,
            'per_page' => 50,
        ]);

        return $response['data'] ?? [];
    }
}