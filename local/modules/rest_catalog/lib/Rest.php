<?php
namespace RestCatalog;

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;

class Rest
{
    public static function onRestServiceBuildDescription()
    {
        return [
            'rest_catalog' => [
                'rest_catalog.categories.get' => [
                    'callback' => [__CLASS__, 'getCategories'],
                    'options' => []
                ],
                'rest_catalog.category.products.get' => [
                    'callback' => [__CLASS__, 'getCategoryProducts'],
                    'options' => []
                ],
                'rest_catalog.product.get' => [
                    'callback' => [__CLASS__, 'getProductDetail'],
                    'options' => []
                ],
            ]
        ];
    }
    
    public static function getCategories($query, $n, \CRestServer $server)
    {
        // Проверяем API ключ
        if (!self::checkApiKey($server)) {
            throw new \Bitrix\Rest\RestException(
                'Invalid API key',
                'WRONG_API_KEY',
                \CRestServer::STATUS_FORBIDDEN
            );
        }
        
        if (!Loader::includeModule('rest_catalog')) {
            throw new \Bitrix\Rest\RestException(
                'Module rest_catalog not loaded',
                'MODULE_NOT_LOADED',
                \CRestServer::STATUS_INTERNAL
            );
        }
        
        try {
            return \RestCatalog\Controller\CategoryController::getCategories();
        } catch (\Exception $e) {
            throw new \Bitrix\Rest\RestException(
                'Internal server error',
                'INTERNAL_ERROR',
                \CRestServer::STATUS_INTERNAL
            );
        }
    }
    
    public static function getCategoryProducts($query, $n, \CRestServer $server)
    {
        // Проверяем API ключ
        if (!self::checkApiKey($server)) {
            throw new \Bitrix\Rest\RestException(
                'Invalid API key',
                'WRONG_API_KEY',
                \CRestServer::STATUS_FORBIDDEN
            );
        }
        
        if (!Loader::includeModule('rest_catalog')) {
            throw new \Bitrix\Rest\RestException(
                'Module rest_catalog not loaded',
                'MODULE_NOT_LOADED',
                \CRestServer::STATUS_INTERNAL
            );
        }
        
        $categoryId = (int)($query['categoryId'] ?? 0);
        
        if ($categoryId <= 0) {
            throw new \Bitrix\Rest\RestException(
                'categoryId is required',
                'INVALID_PARAMS',
                \CRestServer::STATUS_WRONG_REQUEST
            );
        }
        
        try {
            return \RestCatalog\Controller\ProductController::getCategoryProducts($categoryId);
        } catch (\Exception $e) {
            throw new \Bitrix\Rest\RestException(
                'Internal server error',
                'INTERNAL_ERROR',
                \CRestServer::STATUS_INTERNAL
            );
        }
    }
    
    public static function getProductDetail($query, $n, \CRestServer $server)
    {
        // Проверяем API ключ
        if (!self::checkApiKey($server)) {
            throw new \Bitrix\Rest\RestException(
                'Invalid API key',
                'WRONG_API_KEY',
                \CRestServer::STATUS_FORBIDDEN
            );
        }
        
        if (!Loader::includeModule('rest_catalog')) {
            throw new \Bitrix\Rest\RestException(
                'Module rest_catalog not loaded',
                'MODULE_NOT_LOADED',
                \CRestServer::STATUS_INTERNAL
            );
        }
        
        $productId = (int)($query['id'] ?? 0);
        
        if ($productId <= 0) {
            throw new \Bitrix\Rest\RestException(
                'id is required',
                'INVALID_PARAMS',
                \CRestServer::STATUS_WRONG_REQUEST
            );
        }
        
        try {
            return \RestCatalog\Controller\ProductDetailController::getProductDetail($productId);
        } catch (\Exception $e) {
            throw new \Bitrix\Rest\RestException(
                'Internal server error',
                'INTERNAL_ERROR',
                \CRestServer::STATUS_INTERNAL
            );
        }
    }
    
    /**
     * Проверка API ключа
     */
    private static function checkApiKey(\CRestServer $server): bool
    {
        // Проверяем ключ из пути URL
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        $requestUri = $request->getRequestUri();
        
        // Проверяем паттерн /rest/{api_key}/...
        if (preg_match('#^/rest/([^/]+)/#', $requestUri, $matches)) {
            return $matches[1] === '12345';
        }
        
        // Проверяем в параметрах запроса
        $query = $server->getQuery();
        if (isset($query['api_key']) && $query['api_key'] === '12345') {
            return true;
        }
        
        // Проверяем в заголовке
        $headers = $server->getHeaders();
        if (isset($headers['Authorization']) && $headers['Authorization'] === 'Bearer 12345') {
            return true;
        }
        
        // Проверяем в данных авторизации сервера
        $auth = $server->getAuth();
        if ($auth && isset($auth['api_key']) && $auth['api_key'] === '12345') {
            return true;
        }
        
        return false;
    }
}