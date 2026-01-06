<?php
use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;

class rest_catalog extends CModule
{
    var $MODULE_ID = "rest_catalog";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $PARTNER_NAME;
    var $PARTNER_URI;
    
    public function __construct()
    {
        $arModuleVersion = [];
        include(__DIR__ . "/version.php");
        
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        
        $this->MODULE_NAME = "REST API для каталога";
        $this->MODULE_DESCRIPTION = "Модуль для предоставления REST API каталога товаров";
        $this->PARTNER_NAME = "Юрий Сергеев, email: ysergeev@yandex.ru";
        $this->PARTNER_URI = "https://www.inet-press.com/";
    }
    
    public function DoInstall()
    {
        global $APPLICATION;
        
        ModuleManager::registerModule($this->MODULE_ID);
        
        // 1. Регистрация обработчика для стандартного REST API (/lib/rest/...)
        $this->registerEventHandlers();
        
        // 2. Установка настроек по умолчанию
        $this->installOptions();
        
        $APPLICATION->IncludeAdminFile(
            "Установка модуля REST API для каталога",
            __DIR__ . "/step.php"
        );
    }
    
    public function DoUninstall()
    {
        global $APPLICATION;
        
        // Удаление обработчика
        $this->unregisterEventHandlers();
        
        // Удаление настроек
        $this->uninstallOptions();
        
        // Удаление модуля
        ModuleManager::unRegisterModule($this->MODULE_ID);
        
        $APPLICATION->IncludeAdminFile(
            "Удаление модуля REST API для каталога",
            __DIR__ . "/unstep.php"
        );
    }
    
    public function InstallFiles()
    {
        return true;
    }
    
    public function UnInstallFiles()
    {
        return true;
    }
    
    private function registerEventHandlers()
    {
        //$eventManager = EventManager::getInstance();
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        
        // КРИТИЧЕСКИ ВАЖНО: Регистрируем обработчик REST API (/lib/rest/...)
        // Это необходимо для работы эндпоинтов типа /rest/12345/rest_catalog.categories.get
        
        $eventManager->registerEventHandler(
            'rest',
            'OnRestServiceBuildDescription',
            $this->MODULE_ID,
            'RestCatalog\Rest', // Убедитесь, что этот класс существует
            'onRestServiceBuildDescription'
        );
        
        return true;
    }
    
    private function unregisterEventHandlers()
    {
        $eventManager = EventManager::getInstance();
        
        $eventManager->unRegisterEventHandler(
            'rest',
            'OnRestServiceBuildDescription',
            $this->MODULE_ID,
            'RestCatalog\Rest',
            'onRestServiceBuildDescription'
        );
        
        return true;
    }
    
    /**
     * Регистрирует конфигурацию маршрутов в роутере
     */
    private function registerRouterConfig()
    {
        // Получаем роутер
        $router = Application::getInstance()->getRouter();
        
        if ($router) {
            // Регистрируем конфигурацию маршрутов
            $routesConfig = include __DIR__ . '/../lib/Config/routes.php';
            $router->addRoutes($routesConfig);
            
            // Сохраняем конфигурацию
            $this->saveRouterConfig();
        }
        
        // Очищаем кэш роутинга
        $this->clearRoutingCache();
        
        return true;
    }
    
    /**
     * Сохраняет конфигурацию маршрутов для автоматической загрузки
     */
    private function saveRouterConfig()
    {
        $configFile = Application::getDocumentRoot() . '/bitrix/configs/routes/rest_catalog.php';
        $configDir = dirname($configFile);
        
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        // Копируем конфигурацию маршрутов
        $sourceFile = __DIR__ . '/../lib/Config/routes.php';
        if (file_exists($sourceFile)) {
            copy($sourceFile, $configFile);
        }
        
        return true;
    }
    
    private function clearRoutingCache()
    {
        // Очищаем кэш роутинга
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        $cache->clean('routing', 'bitrix/routing');
        $cache->cleanDir('/routing/');
        
        // Очищаем opcache
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        return true;
    }
    
    private function installOptions()
    {
        Option::set($this->MODULE_ID, "api_key", "12345");
        Option::set($this->MODULE_ID, "iblock_catalog_id", 2);
        Option::set($this->MODULE_ID, "iblock_catalog_code", "clothes");
        Option::set($this->MODULE_ID, "iblock_offers_id", 3);
        Option::set($this->MODULE_ID, "iblock_offers_code", "clothes_offers");
        Option::set($this->MODULE_ID, "export_default_email", "admin@example.com");
    }
    
    private function uninstallOptions()
    {
        $connection = Bitrix\Main\Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        
        $tableName = $sqlHelper->forSql("b_option");
        $moduleId = $sqlHelper->forSql($this->MODULE_ID);
        
        $sql = "DELETE FROM {$tableName} WHERE MODULE_ID = '{$moduleId}'";
        $connection->query($sql);
    }
}