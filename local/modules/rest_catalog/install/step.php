<?php
/**
 * Шаг установки модуля
 */

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Localization\Loc;

defined('B_PROLOG_INCLUDED') || die();

// Подключаем языковые файлы
Loc::loadMessages(__FILE__);

$success = true;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Установка модуля REST API для каталога</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
            color: #333;
        }
        
        .install-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #3f8ed8;
            border-bottom: 2px solid #3f8ed8;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        h2 {
            color: #555;
            margin-top: 25px;
            margin-bottom: 10px;
        }
        
        ul, ol {
            margin-left: 20px;
            margin-bottom: 15px;
        }
        
        li {
            margin-bottom: 5px;
        }
        
        .success-message {
            background-color: #e8f5e8;
            border: 1px solid #c3e6c3;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        .warning-message {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        table th {
            background-color: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #ddd;
        }
        
        table td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        
        table tr:hover {
            background-color: #f9f9f9;
        }
        
        code {
            background-color: #f4f4f4;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: Consolas, Monaco, monospace;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3f8ed8;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin-top: 20px;
        }
        
        .btn:hover {
            background-color: #357abd;
        }
        
        .note {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <h1>Установка модуля REST API для каталога</h1>
        
        <p>Модуль предоставляет REST API для работы с каталогом товаров, экспорт в Excel и другие функции.</p>
        
        <div class="success-message">
            <strong>Установка завершена успешно!</strong>
            <p>Модуль готов к использованию.</p>
        </div>
        
        <h2>Возможности модуля:</h2>
        <ul>
            <li>REST API для получения списка категорий</li>
            <li>REST API для получения товаров в категории</li>
            <li>REST API для получения детальной информации о товаре</li>
            <li>Экспорт товаров в Excel</li>
            <li>Отправка экспорта по email</li>
        </ul>
        
        <h2>Доступные REST API эндпоинты:</h2>
        <table>
            <thead>
                <tr>
                    <th>Метод</th>
                    <th>URL</th>
                    <th>Описание</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>GET</td>
                    <td><code>/rest/12345/rest_catalog.categories.get</code></td>
                    <td>Получение дерева категорий</td>
                </tr>
                <tr>
                    <td>GET</td>
                    <td><code>/rest/12345/rest_catalog.category.products.get?categoryId=ID</code></td>
                    <td>Получение товаров в указанной категории</td>
                </tr>
                <tr>
                    <td>GET</td>
                    <td><code>/rest/12345/rest_catalog.product.get?id=ID</code></td>
                    <td>Получение детальной информации о товаре</td>
                </tr>
            </tbody>
        </table>
        
        <h2>Консольные команды:</h2>
        <table>
            <thead>
                <tr>
                    <th>Команда</th>
                    <th>Описание</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>php /local/modules/rest_catalog/lib/Export/console_export.php</code></td>
                    <td>Экспорт товаров в Excel</td>
                </tr>
                <tr>
                    <td><code>php /local/modules/rest_catalog/lib/Export/console_export_email.php email@example.com</code></td>
                    <td>Экспорт товаров в Excel с отправкой на email</td>
                </tr>
            </tbody>
        </table>
        
        <h2>Инструкции по настройке:</h2>
        <ol>
            <li>
                <strong>Настройка инфоблоков:</strong><br>
                Убедитесь, что инфоблоки созданы и настроены:
                <ul>
                    <li>Каталог "Одежда" (ID=2, код=clothes)</li>
                    <li>Торговые предложения (ID=3, код=clothes_offers)</li>
                </ul>
            </li>
            <li>
                <strong>Настройка REST доступа:</strong><br>
                Включите доступ через REST API для обоих инфоблоков в их настройках
            </li>
            <li>
                <strong>Настройка роутинга:</strong><br>
                В файле .htaccess добавьте правила для обработки REST запросов
            </li>
        </ol>
        
        <div class="warning-message">
            <p><strong>Важно!</strong> Для работы REST API через стандартный битриксовый роутинг (/rest/) необходимо:</p>
            <ol>
                <li>Включить REST API в настройках модуля "REST API"</li>
                <li>Убедиться, что инфоблоки имеют включенный доступ через REST</li>
            </ol>
        </div>
        <hr>
        <p>
             Copyright &copy Юрий Сергеев</br>email: <a href="mailto:ysergeev@yandex.ru">ysergeev@yandex.ru</a></br> Telegramm 
            <a href="https://t.me/yms_sevastopol">@yms_sevastopol</a>
        </p>
        <hr>
        <p>
            <a href="/bitrix/admin/module_admin.php" class="btn">
                Вернуться к списку модулей
            </a>
        </p>
    </div>
</body>
</html>