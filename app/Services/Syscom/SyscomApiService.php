<?php

namespace App\Services\Syscom;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyscomApiService
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $this->baseUrl = config('services.syscom.base_url');
        $this->clientId = config('services.syscom.client_id');
        $this->clientSecret = config('services.syscom.client_secret');
    }

    /**
     * Obtiene el token de acceso usando Client Credentials.
     * Cachea el token para evitar llamadas innecesarias.
     */
    public function getAccessToken(): ?string
    {
        return Cache::remember('syscom_access_token', 3500, function () {
            try {
                // Usamos la URL exacta que proporcionaste para el auth
                $authUrl = 'https://developers.syscom.mx/oauth/token';
                
                $response = Http::asForm()
                    ->acceptJson()
                    ->post($authUrl, [
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'grant_type' => 'client_credentials',
                    ]);

                if ($response->failed()) {
                    Log::error('Syscom Auth Failed', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    return null;
                }

                return $response->json('access_token');
            } catch (\Exception $e) {
                Log::error('Syscom Auth Exception: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Helper para realizar peticiones autenticadas.
     */
    protected function request()
    {
        $token = $this->getAccessToken();

        if (!$token) {
            throw new \Exception('No se pudo obtener el token de acceso de Syscom.');
        }

        return Http::withToken($token)
            ->baseUrl($this->baseUrl)
            ->timeout(30);
    }

    /**
     * Obtiene el listado de categorías.
     */
    public function getCategories()
    {
        try {
            $response = $this->request()->get('/categorias');
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Syscom GetCategories Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el detalle de una categoría.
     */
    public function getCategoryDetail(string $id)
    {
        try {
            $response = $this->request()->get("/categorias/{$id}");
            return $response->json();
        } catch (\Exception $e) {
            Log::error("Syscom GetCategoryDetail Error ({$id}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene productos con filtros opcionales.
     */
    public function getProducts(array $filters = [])
    {
        try {
            // Syscom usa parámetros como: categoria, pagina, busqueda
            $response = $this->request()->get('/productos', $filters);
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Syscom GetProducts Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene el detalle de un producto por ID o Modelo.
     */
    public function getProductDetail(string $id)
    {
        try {
            $response = $this->request()->get("/productos/{$id}");
            return $response->json();
        } catch (\Exception $e) {
            Log::error("Syscom GetProductDetail Error ({$id}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene el stock y precio de un producto específico.
     */
    public function getProductInventory(string $id)
    {
        try {
            // Algunos endpoints de Syscom permiten consultar solo stock/precio
            // para ser más rápidos
            $response = $this->request()->get("/productos/{$id}/existencia");
            return $response->json();
        } catch (\Exception $e) {
            Log::error("Syscom GetProductInventory Error ({$id}): " . $e->getMessage());
            return null;
        }
    }
}
