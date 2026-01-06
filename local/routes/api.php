<?php
use Bitrix\Main\Routing\RoutingConfigurator;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\Loader;

return static function (RoutingConfigurator $routes) {
    
    // === ГРУППА: Стандартный REST API путь /rest/{api_key}/ ===
    $routes->prefix('rest/{api_key}')
        ->where('api_key', '12345') // Проверяем API ключ в пути
        ->group(function (RoutingConfigurator $routes) {
            
            // 1. Получение дерева категорий
            $routes->get('rest_catalog.categories.get', 
                function ($api_key) {
                    // Загружаем модуль
                    if (!Loader::includeModule('rest_catalog')) {
                        return new Json([
                            'error' => 'Module not loaded',
                            'error_code' => 'MODULE_NOT_LOADED'
                        ], 500);
                    }
                    
                    try {
                        $result = \RestCatalog\Controller\CategoryController::getCategories();
                        return new Json($result);
                    } catch (\Exception $e) {
                        return new Json([
                            'error' => 'Internal server error',
                            'error_code' => 'INTERNAL_ERROR',
                            'message' => $e->getMessage()
                        ], 500);
                    }
                }
            )->name('rest.catalog.categories.get');
            
            // 2. Получение товаров в категории
            $routes->get('rest_catalog.category.products.get', 
                function ($api_key, \Bitrix\Main\HttpRequest $request) {
                    // Загружаем модуль
                    if (!Loader::includeModule('rest_catalog')) {
                        return new Json([
                            'error' => 'Module not loaded',
                            'error_code' => 'MODULE_NOT_LOADED'
                        ], 500);
                    }
                    
                    $categoryId = (int)$request->get('categoryId');
                    if ($categoryId <= 0) {
                        return new Json([
                            'error' => 'categoryId is required',
                            'error_code' => 'INVALID_PARAMS'
                        ], 400);
                    }
                    
                    try {
                        $result = \RestCatalog\Controller\ProductController::getCategoryProducts($categoryId);
                        return new Json($result);
                    } catch (\Exception $e) {
                        return new Json([
                            'error' => 'Internal server error',
                            'error_code' => 'INTERNAL_ERROR',
                            'message' => $e->getMessage()
                        ], 500);
                    }
                }
            )->name('rest.catalog.category.products.get');
            
            // 3. Получение детальной информации о товаре
            $routes->get('rest_catalog.product.get', 
                function ($api_key, \Bitrix\Main\HttpRequest $request) {
                    // Загружаем модуль
                    if (!Loader::includeModule('rest_catalog')) {
                        return new Json([
                            'error' => 'Module not loaded',
                            'error_code' => 'MODULE_NOT_LOADED'
                        ], 500);
                    }
                    
                    $productId = (int)$request->get('id');
                    if ($productId <= 0) {
                        return new Json([
                            'error' => 'id is required',
                            'error_code' => 'INVALID_PARAMS'
                        ], 400);
                    }
                    
                    try {
                        $result = \RestCatalog\Controller\ProductDetailController::getProductDetail($productId);
                        return new Json($result);
                    } catch (\Exception $e) {
                        return new Json([
                            'error' => 'Internal server error',
                            'error_code' => 'INTERNAL_ERROR',
                            'message' => $e->getMessage()
                        ], 500);
                    }
                }
            )->name('rest.catalog.product.get');
            
        });
};