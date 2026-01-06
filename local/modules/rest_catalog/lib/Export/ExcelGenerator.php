<?php
namespace RestCatalog\Export;

    require_once('config_for_export.php');

    /**
    * Генератор Excel файлов с базовым форматированием
    */
    class ExcelGenerator
    {

    /**
    * Создает Excel файл с товарами (версия для консоли)
    * @param string $outputPath Путь для сохранения файла
    * @return bool
    */
    public static function createProductsExcelConsole(string $outputPath): bool
    {
        // Загружаем модули
        \Bitrix\Main\Loader::includeModule('iblock');
        \Bitrix\Main\Loader::includeModule('catalog');
    
        // Создаем XML для Excel 2003 (SpreadsheetML)
        $xml = self::createExcelXml();
    
        // Получаем данные
        $dbRes = \CIBlockElement::GetList(
            ['NAME' => 'ASC'],
            [
                'IBLOCK_ID' => 2,
                'ACTIVE' => 'Y'
            ],
            false,
            false,
            ['ID', 'NAME', 'CODE', 'IBLOCK_SECTION_ID']
        );
    
        $rowNum = 2; // Начинаем с 2 строки (после заголовков)
    
        // Определяем хост для консоли
        $host = HOSTNAME;
    
        while ($element = $dbRes->GetNext()) {
            $productId = (int)$element['ID'];
        
            // Категория
            $categoryPath = self::getCategoryPath((int)$element['IBLOCK_SECTION_ID']);
        
            // Ссылка
            $code = $element['CODE'] ?: PRODUCT_FOLDER . $productId;
            $url = URL_PROTOCOL.'://' . $host . CATALOG_FOLDER . $code . '/';
        
            // Количество предложений и цена
            $offersCount = self::getOffersCount($productId);
            $minPrice = self::getMinPrice($productId);
        
            // Добавляем строку
            $xml .= self::createTableRow($rowNum, [
                $productId,
                htmlspecialchars($element['NAME']),
                htmlspecialchars($categoryPath),
                $url,
                $offersCount,
                number_format($minPrice, 2, '.', '')
            ]);
        
            $rowNum++;
        }
    
        // Закрываем XML
        $xml .= self::closeExcelXml();
    
        // Сохраняем файл
        return file_put_contents($outputPath, $xml) !== false;
    }

    /**
     * Создает Excel файл с товарами
     * @param string $outputPath Путь для сохранения файла
     * @return bool
     */
    public static function createProductsExcel(string $outputPath): bool
    {
        // Загружаем модули
        \Bitrix\Main\Loader::includeModule('iblock');
        \Bitrix\Main\Loader::includeModule('catalog');
        
        // Создаем XML для Excel 2003 (SpreadsheetML)
        $xml = self::createExcelXml();
        
        // Получаем данные
        $dbRes = \CIBlockElement::GetList(
            ['NAME' => 'ASC'],
            [
                'IBLOCK_ID' => 2,
                'ACTIVE' => 'Y'
            ],
            false,
            false,
            ['ID', 'NAME', 'CODE', 'IBLOCK_SECTION_ID', 'DETAIL_PAGE_URL']
        );
        
        $rowNum = 2; // Начинаем с 2 строки (после заголовков)
        
        while ($element = $dbRes->GetNext()) {
            $productId = (int)$element['ID'];
            
            // Категория
            $categoryPath = self::getCategoryPath((int)$element['IBLOCK_SECTION_ID']);
            
            // Ссылка
            if (!empty($element['DETAIL_PAGE_URL'])) {
                $url = $element['DETAIL_PAGE_URL'];
                if (strpos($url, 'http') !== 0) {
                    $url = 'http://' . $_SERVER['HTTP_HOST'] . $url;
                }
            } else {
                $code = $element['CODE'] ?: 'product' . $productId;
                $url = 'http://' . $_SERVER['HTTP_HOST'] . '/catalog/clothes/' . $code . '/';
            }
            
            // Количество предложений и цена
            $offersCount = self::getOffersCount($productId);
            $minPrice = self::getMinPrice($productId);
            
            // Добавляем строку
            $xml .= self::createTableRow($rowNum, [
                $productId,
                htmlspecialchars($element['NAME']),
                htmlspecialchars($categoryPath),
                $url,
                $offersCount,
                number_format($minPrice, 2, '.', '')
            ]);
            
            $rowNum++;
        }
        
        // Закрываем XML
        $xml .= self::closeExcelXml();
        
        // Сохраняем файл
        return file_put_contents($outputPath, $xml) !== false;
    }
    
    /**
     * Создает начало XML для Excel
     */
    private static function createExcelXml(): string
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
   </Row>
';
    }
    
    /**
     * Создает строку таблицы
     */
    private static function createTableRow(int $rowNum, array $data): string
    {
        $xml = "   <Row>\n";
        
        // ID (обычная ячейка)
        $xml .= "    <Cell ss:StyleID=\"Cell\"><Data ss:Type=\"Number\">{$data[0]}</Data></Cell>\n";
        
        // Наименование (обычная ячейка)
        $xml .= "    <Cell ss:StyleID=\"Cell\"><Data ss:Type=\"String\">{$data[1]}</Data></Cell>\n";
        
        // Категория (обычная ячейка)
        $xml .= "    <Cell ss:StyleID=\"Cell\"><Data ss:Type=\"String\">{$data[2]}</Data></Cell>\n";
        
        // Ссылка (гиперссылка)
        $url = htmlspecialchars($data[3]);
        $xml .= "    <Cell ss:StyleID=\"Hyperlink\" ss:HRef=\"{$url}\"><Data ss:Type=\"String\">{$url}</Data></Cell>\n";
        
        // Количество предложений
        $xml .= "    <Cell ss:StyleID=\"Cell\"><Data ss:Type=\"Number\">{$data[4]}</Data></Cell>\n";
        
        // Цена
        $xml .= "    <Cell ss:StyleID=\"Cell\"><Data ss:Type=\"Number\">{$data[5]}</Data></Cell>\n";
        
        $xml .= "   </Row>\n";
        
        return $xml;
    }
    
    /**
     * Закрывает XML
     */
    private static function closeExcelXml(): string
    {
        return "  </Table>\n </Worksheet>\n</Workbook>";
    }
    
    /**
     * Получение пути категории
     */
    private static function getCategoryPath(int $sectionId): string
    {
        if ($sectionId <= 0) {
            return 'Без категории';
        }
        
        $path = [];
        while ($sectionId > 0) {
            $dbSection = \CIBlockSection::GetByID($sectionId);
            if ($section = $dbSection->GetNext()) {
                $path[] = $section['NAME'];
                $sectionId = (int)$section['IBLOCK_SECTION_ID'];
            } else {
                break;
            }
        }
        
        return !empty($path) ? implode(' / ', array_reverse($path)) : 'Без категории';
    }
    
    /**
     * Получение количества предложений
     */
    private static function getOffersCount(int $productId): int
    {
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
    private static function getMinPrice(int $productId): float
    {
        $minPrice = 0.0;
        
        $dbPrice = \CPrice::GetList(
            [],
            [
                'PRODUCT_ID' => $productId,
                'CATALOG_GROUP_ID' => 1
            ],
            false,
            false,
            ['PRICE']
        );
        
        if ($price = $dbPrice->Fetch()) {
            $minPrice = (float)$price['PRICE'];
        }
        
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
                [
                    'PRODUCT_ID' => $offer['ID'],
                    'CATALOG_GROUP_ID' => 1
                ],
                false,
                false,
                ['PRICE']
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
}