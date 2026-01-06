<?php
// /local/modules/rest_catalog/include.php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

// Регистрируем автозагрузку классов модуля
\Bitrix\Main\Loader::registerAutoLoadClasses('rest_catalog', [
    'RestCatalog\Api\Handler' => 'lib/Api/Handler.php',
    'RestCatalog\Controller\CategoryController' => 'lib/Controller/CategoryController.php',
    'RestCatalog\Controller\ProductController' => 'lib/Controller/ProductController.php',
    'RestCatalog\Controller\ProductDetailController' => 'lib/Controller/ProductDetailController.php',
    'RestCatalog\Export\ExcelGenerator' => 'lib/Export/ExcelGenerator.php',
    'RestCatalog\Rest' => 'lib/Rest.php',
]);

// Регистрируем обработчик REST API для стандартного /rest/ API
\Bitrix\Main\EventManager::getInstance()->registerEventHandler(
    'rest',
    'OnRestServiceBuildDescription',
    'rest_catalog',
    'RestCatalog\Rest',
    'onRestServiceBuildDescription'
);

// Логируем для отладки
if (defined('BX_DEBUG') && BX_DEBUG) {
    error_log("RestCatalog module loaded at " . date('Y-m-d H:i:s'));
}