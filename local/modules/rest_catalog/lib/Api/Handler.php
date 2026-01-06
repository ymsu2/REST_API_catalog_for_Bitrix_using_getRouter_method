<?php
namespace RestCatalog\Api;

use RestCatalog\Controller;

/**
 * Обработчик API запросов
 */
class Handler
{
    /**
     * Обработка API запроса
     * @param string $path Путь API
     * @param string $method HTTP метод
     * @param array $query GET параметры
     * @return array
     */
    public function handle(string $path, string $method, array $query = []): array
    {
        // Убираем начальный и конечный слеши
        $path = trim($path, '/');
        
        // Для отладки
        error_log("API Handler called. Path: '$path', Method: '$method', Query: " . json_encode($query));
        
        // Маршрутизация для GET запросов
        if ($method === 'GET') {
            // Пустой путь или categories
            if ($path === '' || $path === 'categories') {
                // GET /api/catalog/categories
                return Controller\CategoryController::getCategories();
            }
            elseif ($path === 'products') {
                // GET /api/catalog/products?categoryId=ID
                $categoryId = (int)($query['categoryId'] ?? 0);
                if ($categoryId > 0) {
                    return Controller\ProductController::getCategoryProducts($categoryId);
                } else {
                    return [
                        'error' => 'Parameter categoryId is required',
                        'code' => 'INVALID_PARAMS',
                        'message' => 'Please provide categoryId parameter, e.g., ?categoryId=23'
                    ];
                }
            }
            elseif (preg_match('/^product\/(\d+)$/', $path, $matches)) {
                // GET /api/catalog/product/{id}
                $productId = (int)$matches[1];
                return Controller\ProductDetailController::getProductDetail($productId);
            }
        }
        
        // Если маршрут не найден
        return [
            'error' => 'Endpoint not found',
            'code' => 'NOT_FOUND',
            'requested_path' => $path,
            'available_endpoints' => [
                'GET /api/catalog/categories' => 'Get categories tree',
                'GET /api/catalog/products?categoryId=ID' => 'Get products in category',
                'GET /api/catalog/product/{id}' => 'Get product details'
            ]
        ];
    }
    
    /**
     * Альтернативный метод обработки - более надежный
     * @param string $requestUri Полный URI запроса
     * @param string $method HTTP метод
     * @return array
     */
    public function handleRequest(string $requestUri, string $method): array
    {
        // Определяем базовый путь API
        $apiBasePath = '/api/catalog/';
        
        // Извлекаем путь API из полного URI
        if (strpos($requestUri, $apiBasePath) === 0) {
            $apiPath = substr($requestUri, strlen($apiBasePath));
        } else {
            $apiPath = $requestUri;
        }
        
        // Разбираем параметры запроса
        $urlParts = parse_url($requestUri);
        $query = [];
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $query);
        }
        
        // Обрабатываем путь без параметров
        $path = isset($urlParts['path']) ? trim($urlParts['path'], '/') : '';
        
        // Убираем базовый путь из path
        if (strpos($path, 'api/catalog/') === 0) {
            $path = substr($path, strlen('api/catalog/'));
        }
        
        return $this->handle($path, $method, $query);
    }
    
    /**
     * Проверка, является ли запрос API запросом
     * @param string $uri URI запроса
     * @return bool
     */
    public static function isApiRequest(string $uri): bool
    {
        return strpos($uri, '/api/catalog/') === 0;
    }
}