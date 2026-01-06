<?php
/**
 * Шаг удаления модуля
 */

use Bitrix\Main\Localization\Loc;

defined('B_PROLOG_INCLUDED') || die();

// Подключаем языковые файлы
Loc::loadMessages(__FILE__);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Удаление модуля REST API для каталога</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
            color: #333;
        }
        
        .uninstall-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #e74c3c;
            border-bottom: 2px solid #e74c3c;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        h2 {
            color: #555;
            margin-top: 25px;
            margin-bottom: 10px;
        }
        
        ul {
            margin-left: 20px;
            margin-bottom: 15px;
        }
        
        li {
            margin-bottom: 5px;
        }
        
        .warning-message {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        .info-message {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
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
        
        .btn-danger {
            background-color: #e74c3c;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn:hover {
            background-color: #357abd;
        }
    </style>
</head>
<body>
    <div class="uninstall-container">
        <h1>Удаление модуля REST API для каталога</h1>
        
        <div class="warning-message">
            <p><strong>Внимание!</strong> Модуль будет полностью удален из системы.</p>
            <p>Все файлы модуля будут удалены, а его функции станут недоступны.</p>
        </div>
        
        <h2>Что будет удалено:</h2>
        <ul>
            <li>Файлы модуля из директории /local/modules/rest_catalog/</li>
            <li>Обработчики REST API</li>
            <li>Регистрация модуля в системе</li>
            <li>Консольные команды экспорта</li>
            <li>Конфигурационные файлы модуля</li>
            <li>Настройки модуля из базы данных</li>
        </ul>
        
        <h2>Что останется:</h2>
        <ul>
            <li>Данные инфоблоков (товары и категории)</li>
            <li>Настройки REST доступа инфоблоков</li>
            <li>Настроенные роуты (если были изменены вручную)</li>
            <li>Созданные почтовые шаблоны (если были созданы)</li>
        </ul>
        
        <div class="info-message">
            <p><strong>Удаление завершено</strong></p>
            <p>Модуль успешно удален из системы. Все связанные файлы и настройки удалены.</p>
        </div>
        
        <h2>После удаления рекомендуется:</h2>
        <ul>
            <li>Проверить .htaccess файл и удалить правила роутинга для модуля</li>
            <li>Удалить почтовое событие "REST_CATALOG_EXPORT" если оно больше не нужно</li>
            <li>Удалить созданные файлы экспорта из папки /upload/</li>
        </ul>
        
        <p>
            <a href="/bitrix/admin/module_admin.php" class="btn">
                Вернуться к списку модулей
            </a>
        </p>
    </div>
</body>
</html>