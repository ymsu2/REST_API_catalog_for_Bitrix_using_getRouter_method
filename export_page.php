<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// Проверяем права
global $USER;
if (!$USER->IsAdmin()) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    die('Доступ запрещен');
}

// Устанавливаем локаль для правильной сортировки
setlocale(LC_COLLATE, 'ru_RU.UTF-8');
setlocale(LC_CTYPE, 'ru_RU.UTF-8');

// Вспомогательные функции
function getProductsCount(): int
{
    \Bitrix\Main\Loader::includeModule('iblock');
    
    $filter = [
        'IBLOCK_ID' => 2,
        'ACTIVE' => 'Y'
    ];
    
    $dbRes = \CIBlockElement::GetList([], $filter, false, false, ['ID']);
    
    $count = 0;
    while ($row = $dbRes->Fetch()) {
        $count++;
    }
    
    return $count;
}

// Получаем полный путь категории с кэшированием для сортировки
function getFullCategoryPath(int $sectionId, &$categoriesCache = null): array
{
    static $allCategories = null;
    
    if ($allCategories === null) {
        \Bitrix\Main\Loader::includeModule('iblock');
        $dbSections = \CIBlockSection::GetList(
            ['SORT' => 'ASC'],
            ['IBLOCK_ID' => 2, 'ACTIVE' => 'Y'],
            false,
            ['ID', 'NAME', 'IBLOCK_SECTION_ID']
        );
        
        $allCategories = [];
        while ($section = $dbSections->Fetch()) {
            $allCategories[(int)$section['ID']] = [
                'name' => $section['NAME'],
                'parent_id' => (int)$section['IBLOCK_SECTION_ID']
            ];
        }
    }
    
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
}

function getOffersCount(int $productId): int
{
    \Bitrix\Main\Loader::includeModule('iblock');
    
    $filter = [
        'IBLOCK_ID' => 3,
        'ACTIVE' => 'Y',
        'PROPERTY_CML2_LINK' => $productId
    ];
    
    $dbRes = \CIBlockElement::GetList([], $filter, false, false, ['ID']);
    
    $count = 0;
    while ($row = $dbRes->Fetch()) {
        $count++;
    }
    
    return $count;
}

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

// Функция для получения протокола сайта
function getSiteProtocol(): string
{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
           (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
           (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ?
           'https' : 'http';
}

// Получаем все товары с категориями для сортировки
function getSortedProducts(): array
{
    \Bitrix\Main\Loader::includeModule('iblock');
    
    $products = [];
    
    // Получаем все товары
    $dbRes = \CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => 2, 'ACTIVE' => 'Y'],
        false,
        false,
        ['ID', 'NAME', 'CODE', 'IBLOCK_SECTION_ID']
    );
    
    while ($element = $dbRes->Fetch()) {
        $productId = (int)$element['ID'];
        $sectionId = (int)$element['IBLOCK_SECTION_ID'];
        
        // Получаем информацию о категории для сортировки
        $categoryInfo = getFullCategoryPath($sectionId);
        
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
        // Сначала сравниваем по категории
        $categoryCompare = strcoll($a['category_sort_key'], $b['category_sort_key']);
        if ($categoryCompare !== 0) {
            return $categoryCompare;
        }
        
        // Если категории одинаковые, сравниваем по названию товара
        return strcoll($a['name'], $b['name']);
    });
    
    return $products;
}

// Обработка экспорта в Excel XML
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $type = $_GET['type'] ?? 'excel_xml';
    
    if ($type === 'excel_xml') {
        \Bitrix\Main\Loader::includeModule('iblock');
        \Bitrix\Main\Loader::includeModule('catalog');
        
        // Определяем протокол
        $protocol = getSiteProtocol();
        $host = $_SERVER['HTTP_HOST'];
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="products_export_' . date('Y-m-d_H-i-s') . '.xls"');
        header('Cache-Control: max-age=0');
        
        // Начинаем XML
        echo '<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:x="urn:schemas-microsoft-com:office:excel">
 
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Center"/>
   <Borders/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#000000"/>
  </Style>
  <Style ss:ID="Header">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#000000" ss:Bold="1"/>
   <Interior ss:Color="#D9D9D9" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
  <Style ss:ID="Cell">
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
  <Style ss:ID="Hyperlink">
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
   <Column ss:Width="60"/>
   <Column ss:Width="200"/>
   <Column ss:Width="150"/>
   <Column ss:Width="250"/>
   <Column ss:Width="100"/>
   <Column ss:Width="80"/>
   
   <!-- Заголовки -->
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
        $totalProducts = count($sortedProducts);
        
        foreach ($sortedProducts as $product) {
            $productId = $product['id'];
            
            // Формируем ссылку
            $url = $protocol . '://' . $host . '/catalog/clothes/' . $product['code'] . '/';
            
            // Получаем дополнительные данные
            $offersCount = getOffersCount($productId);
            $minPrice = getMinPrice($productId);
            
            // Добавляем строку в XML
            echo '
   <Row>
    <Cell ss:StyleID="Cell"><Data ss:Type="Number">' . $productId . '</Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String">' . htmlspecialchars($product['name']) . '</Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String">' . htmlspecialchars($product['category_display']) . '</Data></Cell>
    <Cell ss:StyleID="Hyperlink" ss:HRef="' . htmlspecialchars($url) . '"><Data ss:Type="String">' . htmlspecialchars($url) . '</Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="Number">' . $offersCount . '</Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="Number">' . number_format($minPrice, 2, '.', '') . '</Data></Cell>
   </Row>';
            
            // Флаш каждые 50 строк для больших файлов
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
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products_export_' . date('Y-m-d') . '.csv"');
        
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
            
            // Формируем ссылку
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

// Получаем количество товаров
$productsCount = getProductsCount();

// Страница с кнопками экспорта
$APPLICATION->SetTitle('Экспорт товаров');
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>

<div class="adm-detail-content">
    <div class="adm-detail-title">Экспорт товаров</div>
    
    <div class="adm-detail-content-item-block">
        <div class="adm-info-message">
            <p>Экспортировать все активные товары из инфоблока "Одежда".</p>
            <p>Всего товаров: <?= $productsCount ?></p>
            <p>Сортировка: по категории и названию товара</p>
        </div>
        
        <div class="adm-detail-content-btns">
            <a href="?action=export&type=excel_xml" class="adm-btn adm-btn-save">
                📥 Экспортировать в форматированный Excel (XML)
            </a>
            <a href="?action=export&type=csv" class="adm-btn adm-btn-success">
                📊 Экспортировать в CSV
            </a>
        </div>
        
        <div class="adm-detail-content-item-block">
            <h3>Форматированный Excel (XML) содержит:</h3>
            <ul>
                <li>Формат Excel 2003 XML (SpreadsheetML)</li>
                <li>Открывается во всех версиях Excel</li>
                <li>Все ячейки с рамками</li>
                <li>Заголовки жирным шрифтом с серым фоном</li>
                <li>Гиперссылки на товары</li>
                <li>Настроенная ширина столбцов</li>
                <li>Числовые форматы цен</li>
                <li>Автоматическое определение протокола (http/https)</li>
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
            
            <h3>Сортировка:</h3>
            <p>Товары сортируются по полям "Наименование категории" и "Наименование товара" с учетом русского алфавита</p>
        </div>
    </div>
</div>

<style>
.adm-detail-content-btns {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin: 20px 0;
}
.adm-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 10px 20px;
    background-color: #3f8ed8;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-size: 14px;
}
.adm-btn:hover {
    background-color: #357abd;
}
.adm-btn-save {
    background-color: #28a745;
}
.adm-btn-save:hover {
    background-color: #218838;
}
.adm-btn-success {
    background-color: #17a2b8;
}
.adm-btn-success:hover {
    background-color: #138496;
}
</style>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';