<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$APPLICATION->SetTitle('Экспорт товаров');

// Проверяем API ключ для публичного доступа
$validApiKey = '12345';
$providedApiKey = $_GET['api_key'] ?? '';

if ($providedApiKey !== $validApiKey) {
    header('HTTP/1.0 403 Forbidden');
    die('Invalid API key');
}

// Устанавливаем локаль для правильной сортировки
setlocale(LC_COLLATE, 'ru_RU.UTF-8');
setlocale(LC_CTYPE, 'ru_RU.UTF-8');

// Вспомогательные функции

/**
 * Получаем все отсортированные товары
 */
function getSortedProducts(): array
{
    \Bitrix\Main\Loader::includeModule('iblock');
    
    $products = [];
    
    // Сначала получаем все категории для кэширования
    $allCategories = [];
    $dbSections = \CIBlockSection::GetList(
        ['SORT' => 'ASC'],
        ['IBLOCK_ID' => 2, 'ACTIVE' => 'Y'],
        false,
        ['ID', 'NAME', 'IBLOCK_SECTION_ID']
    );
    
    while ($section = $dbSections->GetNext()) {
        $allCategories[(int)$section['ID']] = [
            'name' => $section['NAME'],
            'parent_id' => (int)$section['IBLOCK_SECTION_ID']
        ];
    }
    
    // Функция для получения полного пути категории
    $getCategoryPath = function($sectionId) use ($allCategories) {
        if ($sectionId <= 0 || !isset($allCategories[$sectionId])) {
            return [
                'display' => 'Без категории',
                'sort_key' => 'zzzzzzzzzz'
            ];
        }
        
        $pathParts = [];
        $sortParts = [];
        $currentId = $sectionId;
        
        while (isset($allCategories[$currentId])) {
            $categoryName = $allCategories[$currentId]['name'];
            $pathParts[] = $categoryName;
            $sortParts[] = $categoryName;
            
            $currentId = $allCategories[$currentId]['parent_id'];
            if ($currentId <= 0) break;
        }
        
        $pathParts = array_reverse($pathParts);
        $sortParts = array_reverse($sortParts);
        
        return [
            'display' => implode(' / ', $pathParts),
            'sort_key' => implode(' / ', $sortParts)
        ];
    };
    
    // Получаем все товары
    $dbRes = \CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => 2, 'ACTIVE' => 'Y'],
        false,
        false,
        ['ID', 'NAME', 'CODE', 'IBLOCK_SECTION_ID']
    );
    
    while ($element = $dbRes->GetNext()) {
        $productId = (int)$element['ID'];
        $sectionId = (int)$element['IBLOCK_SECTION_ID'];
        
        $categoryInfo = $getCategoryPath($sectionId);
        
        $products[] = [
            'id' => $productId,
            'name' => $element['NAME'],
            'code' => $element['CODE'] ?: 'product' . $productId,
            'section_id' => $sectionId,
            'category_display' => $categoryInfo['display'],
            'category_sort_key' => $categoryInfo['sort_key']
        ];
    }
    
    // СОРТИРОВКА: сначала по категории, затем по названию товара
    usort($products, function($a, $b) {
        $categoryCompare = strcoll($a['category_sort_key'], $b['category_sort_key']);
        if ($categoryCompare !== 0) {
            return $categoryCompare;
        }
        return strcoll($a['name'], $b['name']);
    });
    
    return $products;
}

/**
 * Получение количества предложений
 */
function getOffersCount(int $productId): int
{
    \Bitrix\Main\Loader::includeModule('iblock');
    
    $dbRes = \CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID' => 3,
            'ACTIVE' => 'Y',
            'PROPERTY_CML2_LINK' => $productId
        ],
        false,
        false,
        ['ID']
    );
    
    $count = 0;
    while ($dbRes->Fetch()) {
        $count++;
    }
    
    return $count;
}

/**
 * Получение минимальной цены
 */
function getMinPrice(int $productId): float
{
    \Bitrix\Main\Loader::includeModule('catalog');
    \Bitrix\Main\Loader::includeModule('iblock');
    
    $minPrice = 0.0;
    
    // Цена основного товара
    $dbPrice = \CPrice::GetList(
        [],
        ['PRODUCT_ID' => $productId, 'CATALOG_GROUP_ID' => 1]
    );
    
    if ($price = $dbPrice->Fetch()) {
        $minPrice = (float)$price['PRICE'];
    }
    
    // Цены предложений
    $dbOffers = \CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID' => 3,
            'ACTIVE' => 'Y',
            'PROPERTY_CML2_LINK' => $productId
        ],
        false,
        false,
        ['ID']
    );
    
    while ($offer = $dbOffers->Fetch()) {
        $dbOfferPrice = \CPrice::GetList(
            [],
            ['PRODUCT_ID' => $offer['ID'], 'CATALOG_GROUP_ID' => 1]
        );
        
        if ($offerPrice = $dbOfferPrice->Fetch()) {
            $priceValue = (float)$offerPrice['PRICE'];
            if ($minPrice === 0.0 || $priceValue < $minPrice) {
                $minPrice = $priceValue;
            }
        }
    }
    
    return $minPrice;
}

/**
 * Определение протокола сайта
 */
function getSiteProtocol(): string
{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
           (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
           (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ?
           'https' : 'http';
}

/**
 * Получение количества товаров
 */
function getProductsCount(): int
{
    \Bitrix\Main\Loader::includeModule('iblock');
    
    $dbRes = \CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => 2, 'ACTIVE' => 'Y'],
        [],
        false,
        ['ID']
    );
    
    return $dbRes->SelectedRowsCount();
}

// Обработка экспорта
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $type = $_GET['type'] ?? 'excel_xml';
    
    if ($type === 'excel_xml') {
        \Bitrix\Main\Loader::includeModule('iblock');
        \Bitrix\Main\Loader::includeModule('catalog');
        
        // Определяем протокол
        $protocol = getSiteProtocol();
        $host = $_SERVER['HTTP_HOST'];
        $filename = 'products_export_' . date('Y-m-d_H-i-s') . '.xls';
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Начинаем XML с полной стилизацией
        echo '<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:x="urn:schemas-microsoft-com:office:excel">
 
 <Styles>
  <!-- Основной стиль -->
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Center"/>
   <Borders/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#000000"/>
  </Style>
  
  <!-- Стиль для заголовков таблицы -->
  <Style ss:ID="Header">
   <Alignment ss:Vertical="Center" ss:Horizontal="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#000000" ss:Bold="1"/>
   <Interior ss:Color="#D9D9D9" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
  
  <!-- Стиль для обычных ячеек с рамками -->
  <Style ss:ID="Cell">
   <Alignment ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
  
  <!-- Стиль для гиперссылок -->
  <Style ss:ID="Hyperlink">
   <Alignment ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#0000FF" ss:Underline="Single"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
 </Styles>
 
 <Worksheet ss:Name="Товары">
  <Table>
   <!-- Ширина столбцов -->
   <Column ss:Width="60"/>
   <Column ss:Width="200"/>
   <Column ss:Width="150"/>
   <Column ss:Width="250"/>
   <Column ss:Width="100"/>
   <Column ss:Width="80"/>
   
   <!-- Заголовки таблицы -->
   <Row>
    <Cell ss:StyleID="Header"><Data ss:Type="String">ID</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Наименование товара</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Категория</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Ссылка на детальную страницу товара</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Количество торговых предложений</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Минимальная цена</Data></Cell>
   </Row>';
        
        // Получаем отсортированные товары
        $sortedProducts = getSortedProducts();
        
        foreach ($sortedProducts as $product) {
            $productId = $product['id'];
            
            // Формируем ссылку с проверкой протокола
            $url = $protocol . '://' . $host . '/catalog/clothes/' . $product['code'] . '/';
            
            // Получаем дополнительные данные
            $offersCount = getOffersCount($productId);
            $minPrice = getMinPrice($productId);
            
            // Добавляем строку в таблицу со стилизацией
            echo '
   <Row>
    <Cell ss:StyleID="Cell"><Data ss:Type="Number">' . $productId . '</Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String">' . htmlspecialchars($product['name']) . '</Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String">' . htmlspecialchars($product['category_display']) . '</Data></Cell>
    <Cell ss:StyleID="Hyperlink" ss:HRef="' . htmlspecialchars($url) . '"><Data ss:Type="String">' . htmlspecialchars($url) . '</Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="Number">' . $offersCount . '</Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="Number">' . number_format($minPrice, 2, '.', '') . '</Data></Cell>
   </Row>';
            
            // Флаш для больших файлов
            if ($productId % 50 == 0) {
                flush();
            }
        }
        
        // Завершаем XML
        echo '
  </Table>
 </Worksheet>
</Workbook>';
        exit;
        
    } elseif ($type === 'csv') {
        \Bitrix\Main\Loader::includeModule('iblock');
        \Bitrix\Main\Loader::includeModule('catalog');
        
        // Определяем протокол
        $protocol = getSiteProtocol();
        $host = $_SERVER['HTTP_HOST'];
        $filename = 'products_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        
        // Заголовки
        fputcsv($output, [
            'ID',
            'Наименование товара', 
            'Категория',
            'Ссылка на товар',
            'Количество предложений',
            'Минимальная цена'
        ], ';');
        
        // Получаем отсортированные товары
        $sortedProducts = getSortedProducts();
        
        foreach ($sortedProducts as $product) {
            $productId = $product['id'];
            
            // Формируем ссылку с проверкой протокола
            $url = $protocol . '://' . $host . '/catalog/clothes/' . $product['code'] . '/';
            
            fputcsv($output, [
                $productId,
                $product['name'],
                $product['category_display'],
                $url,
                getOffersCount($productId),
                getMinPrice($productId)
            ], ';');
        }
        
        fclose($output);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Экспорт товаров</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            background: #fff;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #3f8ed8;
            padding-bottom: 10px;
        }
        .buttons {
            display: flex;
            gap: 15px;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background-color: #3f8ed8;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #357abd;
        }
        .btn-excel {
            background-color: #28a745;
        }
        .btn-excel:hover {
            background-color: #218838;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #3f8ed8;
            padding: 15px;
            margin: 20px 0;
        }
        .api-info {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        code {
            background-color: #f4f4f4;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: Consolas, monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Экспорт товаров</h1>
        
        <div class="info-box">
            <p>Экспорт всех активных товаров из каталога "Одежда".</p>
            <p><strong>Всего товаров:</strong> <?= getProductsCount() ?></p>
            <p><strong>Сортировка:</strong> по категории и названию товара</p>
            <p><strong>Текущий протокол:</strong> <?= getSiteProtocol() ?>://</p>
        </div>
        
        <div class="buttons">
            <a href="?api_key=12345&action=export&type=excel_xml" class="btn btn-excel">
                📥 Экспортировать в форматированный Excel (XML)
            </a>
            <a href="?api_key=12345&action=export&type=csv" class="btn">
                📊 Экспортировать в CSV
            </a>
        </div>
        
        <div class="info-box">
            <h3>Форматированный Excel (XML) содержит:</h3>
            <ul>
                <li>✅ Формат Excel 2003 XML (SpreadsheetML)</li>
                <li>✅ Открывается во всех версиях Excel</li>
                <li>✅ Все ячейки с рамками</li>
                <li>✅ Заголовки жирным шрифтом с серым фоном</li>
                <li>✅ Гиперссылки на товары</li>
                <li>✅ Настроенная ширина столбцов</li>
                <li>✅ Числовые форматы цен</li>
                <li>✅ Автоматическое определение протокола (http/https)</li>
            </ul>
            
            <h3>Экспортируемые поля:</h3>
            <ul>
                <li><strong>ID</strong> - ID товара</li>
                <li><strong>Наименование товара</strong> - название товара</li>
                <li><strong>Категория</strong> - полный путь категории</li>
                <li><strong>Ссылка на товар</strong> - URL детальной страницы (гиперссылка)</li>
                <li><strong>Количество предложений</strong> - число торговых предложений</li>
                <li><strong>Минимальная цена</strong> - цена "от" в рублях</li>
            </ul>
        </div>
        
        <div class="api-info">
            <h3>API доступ:</h3>
            <p>Для автоматического экспорта используйте API:</p>
            <p><strong>Excel XML:</strong><br>
            <code>GET <?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>?api_key=12345&action=export&type=excel_xml</code></p>
            
            <p><strong>CSV:</strong><br>
            <code>GET <?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>?api_key=12345&action=export&type=csv</code></p>
            
            <p><strong>Пример через curl:</strong><br>
            <code>curl "<?= getSiteProtocol() ?>://<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/export_public.php?api_key=12345&action=export&type=excel_xml" -o products.xls</code></p>
        </div>
    </div>
</body>
</html>