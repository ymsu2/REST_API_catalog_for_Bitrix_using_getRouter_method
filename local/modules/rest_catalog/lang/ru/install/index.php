<?php
/**
 * Русские языковые файлы для установки модуля
 */

$MESS['REST_CATALOG_MODULE_NAME'] = 'REST API для каталога';
$MESS['REST_CATALOG_MODULE_DESCRIPTION'] = 'Модуль для предоставления REST API каталога товаров';
$MESS['REST_CATALOG_PARTNER_NAME'] = 'Разработчик';
$MESS['REST_CATALOG_PARTNER_URI'] = 'https://example.com';

// Сообщения установки
$MESS['REST_CATALOG_INSTALL_TITLE'] = 'Установка модуля REST API для каталога';
$MESS['REST_CATALOG_INSTALL_DESCRIPTION'] = 'Мастер установки модуля REST API для работы с каталогом товаров';
$MESS['REST_CATALOG_INSTALL_FEATURES'] = 'Возможности модуля';
$MESS['REST_CATALOG_INSTALL_FEATURE_1'] = 'REST API для получения списка категорий';
$MESS['REST_CATALOG_INSTALL_FEATURE_2'] = 'REST API для получения товаров в категории';
$MESS['REST_CATALOG_INSTALL_FEATURE_3'] = 'REST API для получения детальной информации о товаре';
$MESS['REST_CATALOG_INSTALL_FEATURE_4'] = 'Экспорт товаров в Excel';
$MESS['REST_CATALOG_INSTALL_FEATURE_5'] = 'Отправка экспорта по email';
$MESS['REST_CATALOG_INSTALL_INSTRUCTIONS'] = 'Инструкции по настройке';
$MESS['REST_CATALOG_INSTALL_STEP_1'] = 'Шаг 1: Настройка инфоблоков';
$MESS['REST_CATALOG_INSTALL_STEP_1_DESC'] = 'Убедитесь, что инфоблоки созданы и настроены: каталог "Одежда" (ID=2, код=clothes) и торговые предложения (ID=3, код=clothes_offers)';
$MESS['REST_CATALOG_INSTALL_STEP_2'] = 'Шаг 2: Настройка REST доступа';
$MESS['REST_CATALOG_INSTALL_STEP_2_DESC'] = 'Включите доступ через REST API для обоих инфоблоков в их настройках';
$MESS['REST_CATALOG_INSTALL_STEP_3'] = 'Шаг 3: Настройка роутинга';
$MESS['REST_CATALOG_INSTALL_STEP_3_DESC'] = 'Настройте ваш веб-сервер для обработки REST запросов (см. документацию модуля)';
$MESS['REST_CATALOG_INSTALL_COMPLETE'] = 'Установка завершена успешно!';
$MESS['REST_CATALOG_INSTALL_COMPLETE_DESC'] = 'Модуль готов к работе. Вы можете начать использовать REST API.';
$MESS['REST_CATALOG_INSTALL_API_ENDPOINTS'] = 'Доступные эндпоинты API';
$MESS['REST_CATALOG_INSTALL_API_METHOD'] = 'Метод';
$MESS['REST_CATALOG_INSTALL_API_URL'] = 'URL';
$MESS['REST_CATALOG_INSTALL_API_DESCRIPTION'] = 'Описание';
$MESS['REST_CATALOG_INSTALL_API_CATEGORIES_DESC'] = 'Получение дерева категорий';
$MESS['REST_CATALOG_INSTALL_API_PRODUCTS_DESC'] = 'Получение товаров в указанной категории';
$MESS['REST_CATALOG_INSTALL_API_PRODUCT_DETAIL_DESC'] = 'Получение детальной информации о товаре';
$MESS['REST_CATALOG_INSTALL_BACK_TO_LIST'] = 'Вернуться к списку модулей';

// Сообщения удаления
$MESS['REST_CATALOG_UNINSTALL_TITLE'] = 'Удаление модуля REST API для каталога';
$MESS['REST_CATALOG_UNINSTALL_WARNING'] = 'Внимание!';
$MESS['REST_CATALOG_UNINSTALL_WARNING_DESC'] = 'Модуль будет полностью удален из системы. Все его файлы будут удалены.';
$MESS['REST_CATALOG_UNINSTALL_WHAT_WILL_BE_REMOVED'] = 'Что будет удалено:';
$MESS['REST_CATALOG_UNINSTALL_REMOVE_1'] = 'Файлы модуля из директории /local/modules/rest_catalog/';
$MESS['REST_CATALOG_UNINSTALL_REMOVE_2'] = 'Обработчики REST API';
$MESS['REST_CATALOG_UNINSTALL_REMOVE_3'] = 'Регистрация модуля в системе';
$MESS['REST_CATALOG_UNINSTALL_REMOVE_4'] = 'Консольные команды экспорта';
$MESS['REST_CATALOG_UNINSTALL_REMOVE_5'] = 'Конфигурационные файлы модуля';
$MESS['REST_CATALOG_UNINSTALL_WHAT_WILL_REMAIN'] = 'Что останется:';
$MESS['REST_CATALOG_UNINSTALL_REMAIN_1'] = 'Данные инфоблоков (товары и категории)';
$MESS['REST_CATALOG_UNINSTALL_REMAIN_2'] = 'Настройки REST доступа инфоблоков';
$MESS['REST_CATALOG_UNINSTALL_REMAIN_3'] = 'Настроенные роуты (если были изменены вручную)';
$MESS['REST_CATALOG_UNINSTALL_COMPLETE'] = 'Удаление завершено';
$MESS['REST_CATALOG_UNINSTALL_COMPLETE_DESC'] = 'Модуль успешно удален из системы.';
$MESS['REST_CATALOG_UNINSTALL_BACK_TO_LIST'] = 'Вернуться к списку модулей';

// Ошибки
$MESS['REST_CATALOG_INSTALL_ERROR_VERSION'] = 'Требуется версия 1С-Битрикс не ниже 14.0.0 (D7)';
$MESS['REST_CATALOG_INSTALL_ERROR_MODULE'] = 'Не удалось установить модуль';
$MESS['REST_CATALOG_INSTALL_ERROR_IBLOCK'] = 'Не найден инфоблок "Одежда" (ID=2). Создайте его перед установкой модуля.';