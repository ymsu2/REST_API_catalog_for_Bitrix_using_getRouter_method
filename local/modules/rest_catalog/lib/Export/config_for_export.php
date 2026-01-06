<?
define("HOSTNAME",'business-01.local');
define("URL_PROTOCOL",'http');
define("BITRIX_SITE_ID",'s1');
define("BITRIX_SITE_LANG",'ru');

// Определяем корень сайта
define("DOCUMENTROOT",'D:/project/business-01');

define("CATALOG_FOLDER",'/catalog/clothes/');
define("PRODUCT_FOLDER",'product');

// Устанавливаем константы для отключения сессии и и обхода проверок со стороны Битрикс
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);
define('STOP_STATISTICS', true);
define('BX_SESSION_ID', 'cli');
define('NO_AGENT_CHECK', true);

// Явно определяем сайт и язык
define('SITE_ID', 's1');
define('LANGUAGE_ID', 'ru');

// Путь для сохранения файла
define('UPLOAD_DIR', '/upload/');
//define('UPLOAD_DIR', '/tmp/');
?>