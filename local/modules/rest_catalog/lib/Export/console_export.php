<?php
/**
 * Консольный скрипт для экспорта товаров в Excel XML
 * Использование: php console_export.php
 */
require_once('config_for_export.php');

// Устанавливаем локаль для правильной сортировки
setlocale(LC_COLLATE, 'ru_RU.UTF-8');
setlocale(LC_CTYPE, 'ru_RU.UTF-8');

// Определяем корень сайта
$documentRoot = DOCUMENTROOT;

// Путь для сохранения
$uploadDir = $documentRoot . UPLOAD_DIR;

// Определяем протокол сайта и URL
$protocol = URL_PROTOCOL;
$host = HOSTNAME;

echo "========================================\n";
echo "Экспорт товаров в форматированный Excel\n";
echo "========================================\n";
echo "Дата/время: " . date('Y-m-d H:i:s') . "\n";
echo "Корень сайта: $documentRoot\n\n";

// Включаем вывод ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Проверяем директорию
if (!is_dir($uploadDir)) {
    echo "Папка ".UPLOAD_DIR." не найдена: $uploadDir\n";
    echo "Создаем папку ".UPLOAD_DIR."...\n";
    
    if (!mkdir($uploadDir, 0755, true)) {
        die("Не удалось создать папку ".UPLOAD_DIR.": $uploadDir\n");
    }
    echo "Папка ".UPLOAD_DIR." создана\n";
} else {
    echo "Папка ".UPLOAD_DIR." найдена\n";
}

// Генерируем имя файла
$timestamp = date('Ymd_His');
$fileName = "products_export_{$timestamp}.xls";
$filePath = $uploadDir . $fileName;

echo "\nСоздание файла: $fileName\n";
echo "Полный путь: $filePath\n";
echo "Подключение к базе данных...\n";

// Данные для подключения к базе данных
$dbConfig = parseDatabaseConfig($documentRoot);

if (!$dbConfig) {
    die("Не удалось получить данные для подключения к БД\n");
}

try {
    // Подключаемся к базе данных
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8",
        $dbConfig['login'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "Подключение к БД успешно\n";
    
    echo "Получение данных из базы...\n";
    
    // Получаем все товары
    $sqlProducts = "
        SELECT 
            e.ID,
            e.NAME,
            e.CODE,
            e.IBLOCK_SECTION_ID,
            e.SORT
        FROM b_iblock_element e
        WHERE e.IBLOCK_ID = 2 
          AND e.ACTIVE = 'Y'
    ";
    
    $stmtProducts = $pdo->query($sqlProducts);
    $allProducts = $stmtProducts->fetchAll();
    
    $totalProducts = count($allProducts);
    
    echo "Найдено товаров: $totalProducts\n";
    
    if ($totalProducts == 0) {
        die("Товары не найдены в инфоблоке ID=2\n");
    }
    
    // Получаем информацию о всех категориях
    echo "Получаем информацию о категориях...\n";
    
    $sqlSections = "
        SELECT ID, NAME, IBLOCK_SECTION_ID
        FROM b_iblock_section 
        WHERE IBLOCK_ID = 2
    ";
    
    $stmtSections = $pdo->query($sqlSections);
    $allSections = $stmtSections->fetchAll();
    
    // Строим кэш разделов
    $categoriesCache = [];
    foreach ($allSections as $section) {
        $sectionId = (int)$section['ID'];
        $categoriesCache[$sectionId] = [
            'name' => $section['NAME'],
            'parent_id' => (int)$section['IBLOCK_SECTION_ID']
        ];
    }
    
    echo "Найдено категорий: " . count($allSections) . "\n";
    
    // Подготавливаем массив товаров с категориями
    echo "Подготавливаю данные для сортировки...\n";
    
    $productsWithCategories = [];
    
    foreach ($allProducts as $product) {
        $productId = (int)$product['ID'];
        
        // Получаем полный путь категории как строку
        $sectionId = (int)$product['IBLOCK_SECTION_ID'];
        $categoryInfo = getFullCategoryPathForSorting($sectionId, $categoriesCache);
        
        // Формируем данные товара
        $code = $product['CODE'] ?: PRODUCT_FOLDER . $productId;
        $url = $protocol . '://' . $host . CATALOG_FOLDER . $code . '/';
       
        // Количество предложений
        $offersCount = getOffersCount($pdo, $productId);
        
        // Минимальная цена
        $minPrice = getMinPrice($pdo, $productId);
        
        $productsWithCategories[] = [
            'product_id' => $productId,
            'name' => $product['NAME'],
            'category_path' => $categoryInfo['display_path'],
            'category_sort_key' => $categoryInfo['sort_key'],
            'url' => $url,
            'offers_count' => $offersCount,
            'min_price' => $minPrice
        ];
    }
    
    // СОРТИРОВКА: сначала по категории, затем по названию товара
    echo "Сортирую товары по категории и названию...\n";
    
    usort($productsWithCategories, function($a, $b) {
        // Сначала сравниваем по категории с использованием локали
        $categoryCompare = strcoll($a['category_sort_key'], $b['category_sort_key']);
        if ($categoryCompare !== 0) {
            return $categoryCompare;
        }
        
        // Если категории одинаковые, сравниваем по названию товара
        return strcoll($a['name'], $b['name']);
    });
    
    echo "Товары отсортированы\n";
    
    // Создаем XML для Excel
    echo "Формирую Excel файл...\n";
    
    $xml = createExcelXmlHeader();
    
    // Записываем товары в XML
    foreach ($productsWithCategories as $item) {
        $xml .= createTableRow([
            $item['product_id'],
            htmlspecialchars($item['name']),
            htmlspecialchars($item['category_path']),
            $item['url'],
            $item['offers_count'],
            number_format($item['min_price'], 2, '.', '')
        ]);
    }
    
    // Завершаем XML
    $xml .= '
  </Table>
 </Worksheet>
</Workbook>';
    
    echo "Сохранение файла...\n";
    
    // Сохраняем файл
    $bytesWritten = file_put_contents($filePath, $xml);
    
    if ($bytesWritten !== false) {
        $fileSize = filesize($filePath);
        $fileSizeKB = round($fileSize / 1024, 2);
        
        echo "Файл успешно сохранен!\n";
        echo "РЕЗУЛЬТАТ ЭКСПОРТА:\n";
        echo "Файл: $fileName\n";
        echo "Размер: {$fileSizeKB} KB\n";
        echo "Дата: " . date('Y-m-d H:i:s') . "\n";
        echo "Путь: $filePath\n";
        echo "URL: $protocol://$host".UPLOAD_DIR."$fileName\n";
        echo "Товаров: $totalProducts\n";
    } else {
        echo "Ошибка при сохранении файла!\n";
    }
    
} catch (PDOException $e) {
    echo "\nОШИБКА БАЗЫ ДАННЫХ: " . $e->getMessage() . "\n";
    echo "Код ошибки: " . $e->getCode() . "\n";
    die();
} catch (Exception $e) {
    echo "\nОШИБКА: " . $e->getMessage() . "\n";
    die();
}

echo "\n========================================\n";

/**
 * Парсит конфигурацию базы данных из settings.php
 */
function parseDatabaseConfig(string $documentRoot): ?array
{
    $settingsFile = $documentRoot . '/bitrix/.settings.php';
    
    if (!file_exists($settingsFile)) {
        echo "Файл настроек не найден: $settingsFile\n";
        return null;
    }
    
    $settings = require $settingsFile;
    
    if (!isset($settings['connections']['value']['default'])) {
        echo "Не найдена конфигурация БД в settings.php\n";
        return null;
    }
    
    $dbConfig = $settings['connections']['value']['default'];
    
    return [
        'host' => $dbConfig['host'] ?? 'localhost',
        'database' => $dbConfig['database'] ?? '',
        'login' => $dbConfig['login'] ?? '',
        'password' => $dbConfig['password'] ?? '',
    ];
}

/**
 * Создает начало XML для Excel
 */
function createExcelXmlHeader(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>
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
}

/**
 * Создает строку таблицы
 */
function createTableRow(array $data): string
{
    $url = htmlspecialchars($data[3]);
    $displayUrl = htmlspecialchars($data[3]);
    
    return '
   <Row>
    <Cell ss:StyleID="Cell"><Data ss:Type="Number">' . $data[0] . '</Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String">' . $data[1] . '</Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String">' . $data[2] . '</Data></Cell>
    <Cell ss:StyleID="Hyperlink" ss:HRef="' . $url . '"><Data ss:Type="String">' . $displayUrl . '</Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="Number">' . $data[4] . '</Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="Number">' . $data[5] . '</Data></Cell>
   </Row>';
}

/**
 * Получает полный путь категории для сортировки
 */
function getFullCategoryPathForSorting(int $sectionId, array $categoriesCache): array
{
    if ($sectionId <= 0 || !isset($categoriesCache[$sectionId])) {
        return [
            'display_path' => 'Без категории',
            'sort_key' => 'zzzzzzzzzz'
        ];
    }
    
    $pathParts = [];
    $sortPathParts = [];
    $currentId = $sectionId;
    
    // Собираем все уровни категорий
    while (isset($categoriesCache[$currentId])) {
        $categoryName = $categoriesCache[$currentId]['name'];
        $pathParts[] = $categoryName;
        $sortPathParts[] = $categoryName;
        
        $currentId = $categoriesCache[$currentId]['parent_id'];
        
        if ($currentId <= 0) {
            break;
        }
    }
    
    // Разворачиваем, чтобы получить правильный порядок (от корня к листу)
    $pathParts = array_reverse($pathParts);
    $sortPathParts = array_reverse($sortPathParts);
    
    $displayPath = implode(' / ', $pathParts);
    $sortKey = implode(' / ', $sortPathParts);
    
    return [
        'display_path' => $displayPath,
        'sort_key' => $sortKey
    ];
}

/**
 * Получение количества предложений
 */
function getOffersCount(PDO $pdo, int $productId): int
{
    $sql = "
        SELECT COUNT(*) as count
        FROM b_iblock_element e
        INNER JOIN b_iblock_element_property ep ON ep.IBLOCK_ELEMENT_ID = e.ID
        INNER JOIN b_iblock_property p ON p.ID = ep.IBLOCK_PROPERTY_ID
        WHERE e.IBLOCK_ID = 3
          AND e.ACTIVE = 'Y'
          AND p.CODE = 'CML2_LINK'
          AND ep.VALUE = :productId
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':productId' => $productId]);
    $result = $stmt->fetch();
    
    return (int)($result['count'] ?? 0);
}

/**
 * Получение минимальной цены
 */
function getMinPrice(PDO $pdo, int $productId): float
{
    $minPrice = 0.0;
    
    // Цена основного товара
    $sqlMain = "
        SELECT MIN(PRICE) as min_price
        FROM b_catalog_price
        WHERE PRODUCT_ID = :productId
          AND CATALOG_GROUP_ID = 1
    ";
    
    $stmt = $pdo->prepare($sqlMain);
    $stmt->execute([':productId' => $productId]);
    $result = $stmt->fetch();
    
    if ($result && $result['min_price'] !== null) {
        $minPrice = (float)$result['min_price'];
    }
    
    // Цены предложений
    $sqlOffers = "
        SELECT MIN(cp.PRICE) as min_price
        FROM b_catalog_price cp
        INNER JOIN b_iblock_element e ON e.ID = cp.PRODUCT_ID
        INNER JOIN b_iblock_element_property ep ON ep.IBLOCK_ELEMENT_ID = e.ID
        INNER JOIN b_iblock_property p ON p.ID = ep.IBLOCK_PROPERTY_ID
        WHERE e.IBLOCK_ID = 3
          AND e.ACTIVE = 'Y'
          AND p.CODE = 'CML2_LINK'
          AND ep.VALUE = :productId
          AND cp.CATALOG_GROUP_ID = 1
    ";
    
    $stmt = $pdo->prepare($sqlOffers);
    $stmt->execute([':productId' => $productId]);
    $result = $stmt->fetch();
    
    if ($result && $result['min_price'] !== null) {
        $offerPrice = (float)$result['min_price'];
        if ($minPrice === 0.0 || $offerPrice < $minPrice) {
            $minPrice = $offerPrice;
        }
    }
    
    return $minPrice;
}