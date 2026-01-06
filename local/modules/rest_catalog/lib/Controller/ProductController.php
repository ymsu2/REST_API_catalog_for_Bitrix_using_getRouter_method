<?php
namespace RestCatalog\Controller;

use Bitrix\Main\Loader;

/**
 * Контроллер для работы с товарами
 */
class ProductController
{
    private const PRODUCT_IBLOCK_ID = 2;
    private const OFFER_IBLOCK_ID = 3;
    
    /**
     * Получение товаров в категории
     * Сортировка по ID категории по возрастанию
     */
    public static function getCategoryProducts(int $categoryId): array
    {
        Loader::includeModule('iblock');
        Loader::includeModule('catalog');
        
        try {
            $protocol = self::getSiteProtocol();
            $host = $_SERVER['HTTP_HOST'];
            
            // Проверяем существование категории
            $dbSection = \CIBlockSection::GetByID($categoryId);
            if (!$section = $dbSection->GetNext()) {
                return ['result' => []];
            }
            
            // Находим все подразделы (рекурсивно)
            $leftMargin = (int)$section['LEFT_MARGIN'];
            $rightMargin = (int)$section['RIGHT_MARGIN'];
            
            $dbSubSections = \CIBlockSection::GetList(
                ['LEFT_MARGIN' => 'ASC'],
                [
                    'IBLOCK_ID' => self::PRODUCT_IBLOCK_ID,
                    'ACTIVE' => 'Y',
                    'GLOBAL_ACTIVE' => 'Y',
                    '>=LEFT_MARGIN' => $leftMargin,
                    '<=RIGHT_MARGIN' => $rightMargin
                ],
                false,
                ['ID']
            );
            
            $sectionIds = [];
            while ($subSection = $dbSubSections->Fetch()) {
                $sectionIds[] = (int)$subSection['ID'];
            }
            
            if (empty($sectionIds)) {
                return ['result' => []];
            }
            
            // Получаем элементы в этих разделах с сортировкой по ID категории
            $dbProducts = \CIBlockElement::GetList(
                ['ID' => 'ASC'], // СОРТИРОВКА ПО ID ТОВАРА ПО ВОЗРАСТАНИЮ
                [
                    'IBLOCK_ID' => self::PRODUCT_IBLOCK_ID,
                    'ACTIVE' => 'Y',
                    'SECTION_ID' => $sectionIds,
                    'INCLUDE_SUBSECTIONS' => 'Y'
                ],
                false,
                false,
                ['ID', 'NAME', 'CODE', 'IBLOCK_SECTION_ID', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'DETAIL_PAGE_URL']
            );
            
            $result = [];
            while ($element = $dbProducts->GetNext()) {
                $productId = (int)$element['ID'];
                $sectionId = (int)$element['IBLOCK_SECTION_ID'];
                
                // Формируем правильный URL
                if (!empty($element['DETAIL_PAGE_URL'])) {
                    $detailUrl = $element['DETAIL_PAGE_URL'];
                } else {
                    $code = $element['CODE'] ?: 'product' . $productId;
                    
                    if ($sectionId > 0) {
                        $dbParentSection = \CIBlockSection::GetByID($sectionId);
                        if ($parentSection = $dbParentSection->GetNext()) {
                            $sectionCode = $parentSection['CODE'] ?: 'section' . $sectionId;
                            // Убираем "clothes/" если есть
                            $sectionCode = str_replace('clothes/', '', $sectionCode);
                            $detailUrl = '/catalog/' . $sectionCode . '/' . $code . '/';
                        } else {
                            $detailUrl = '/catalog/' . $code . '/';
                        }
                    } else {
                        $detailUrl = '/catalog/' . $code . '/';
                    }
                }
                
                // Полный URL
                $fullUrl = $protocol . '://' . $host . $detailUrl;
                
                // Картинка
                $pictureId = $element['PREVIEW_PICTURE'] ?: $element['DETAIL_PICTURE'];
                $picturePath = '';
                if ($pictureId) {
                    $file = \CFile::GetFileArray($pictureId);
                    $picturePath = $file ? $file['SRC'] : '';
                }
                
                // Минимальная цена
                $minPrice = self::getProductMinPrice($productId);
                
                $result[] = [
                    'ID' => $productId,
                    'NAME' => $element['NAME'],
                    'DETAIL_PAGE_URL' => $fullUrl,
                    'PICTURE' => $picturePath,
                    'MIN_PRICE' => $minPrice,
                    'SECTION_ID' => $sectionId // Добавляем для возможной сортировки
                ];
            }
            
            // СОРТИРОВКА ПО ID КАТЕГОРИИ ПО ВОЗРАСТАНИЮ, затем по ID товара
            usort($result, function($a, $b) {
                // Сначала сравниваем по ID категории
                if ($a['SECTION_ID'] != $b['SECTION_ID']) {
                    return $a['SECTION_ID'] <=> $b['SECTION_ID'];
                }
                
                // Если категории одинаковые, сравниваем по ID товара
                return $a['ID'] <=> $b['ID'];
            });
            
            // Убираем SECTION_ID из финального результата
            $finalResult = [];
            foreach ($result as $item) {
                unset($item['SECTION_ID']);
                $finalResult[] = $item;
            }
            
            return ['result' => $finalResult];
            
        } catch (\Exception $e) {
            error_log("ProductController error: " . $e->getMessage());
            return ['result' => []];
        }
    }
    
    /**
     * Альтернативный метод с явной сортировкой по ID категории
     */
    public static function getCategoryProductsSorted(int $categoryId): array
    {
        Loader::includeModule('iblock');
        Loader::includeModule('catalog');
        
        try {
            $protocol = self::getSiteProtocol();
            $host = $_SERVER['HTTP_HOST'];
            
            // Получаем все товары в категории и подкатегориях
            $dbProducts = \CIBlockElement::GetList(
                ['IBLOCK_SECTION_ID' => 'ASC', 'ID' => 'ASC'], // СОРТИРОВКА ПО ID КАТЕГОРИИ, ЗАТЕМ ПО ID ТОВАРА
                [
                    'IBLOCK_ID' => self::PRODUCT_IBLOCK_ID,
                    'ACTIVE' => 'Y',
                    'SECTION_ID' => $categoryId,
                    'INCLUDE_SUBSECTIONS' => 'Y'
                ],
                false,
                false,
                ['ID', 'NAME', 'CODE', 'IBLOCK_SECTION_ID', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'DETAIL_PAGE_URL']
            );
            
            $result = [];
            while ($element = $dbProducts->GetNext()) {
                $productId = (int)$element['ID'];
                
                // Формируем правильный URL
                if (!empty($element['DETAIL_PAGE_URL'])) {
                    $detailUrl = $element['DETAIL_PAGE_URL'];
                } else {
                    $code = $element['CODE'] ?: 'product' . $productId;
                    $sectionId = (int)$element['IBLOCK_SECTION_ID'];
                    
                    if ($sectionId > 0) {
                        $dbParentSection = \CIBlockSection::GetByID($sectionId);
                        if ($parentSection = $dbParentSection->GetNext()) {
                            $sectionCode = $parentSection['CODE'] ?: 'section' . $sectionId;
                            $sectionCode = str_replace('clothes/', '', $sectionCode);
                            $detailUrl = '/catalog/' . $sectionCode . '/' . $code . '/';
                        } else {
                            $detailUrl = '/catalog/' . $code . '/';
                        }
                    } else {
                        $detailUrl = '/catalog/' . $code . '/';
                    }
                }
                
                // Полный URL
                $fullUrl = $protocol . '://' . $host . $detailUrl;
                
                // Картинка
                $pictureId = $element['PREVIEW_PICTURE'] ?: $element['DETAIL_PICTURE'];
                $picturePath = '';
                if ($pictureId) {
                    $file = \CFile::GetFileArray($pictureId);
                    $picturePath = $file ? $file['SRC'] : '';
                }
                
                // Минимальная цена
                $minPrice = self::getProductMinPrice($productId);
                
                $result[] = [
                    'ID' => $productId,
                    'NAME' => $element['NAME'],
                    'DETAIL_PAGE_URL' => $fullUrl,
                    'PICTURE' => $picturePath,
                    'MIN_PRICE' => $minPrice
                ];
            }
            
            return ['result' => $result];
            
        } catch (\Exception $e) {
            error_log("ProductController error: " . $e->getMessage());
            return ['result' => []];
        }
    }
    
    /**
     * Получение минимальной цены товара
     */
    private static function getProductMinPrice(int $productId): float
    {
        $minPrice = PHP_FLOAT_MAX;
        
        try {
            // 1. Проверяем цену основного товара
            $dbPrice = \CPrice::GetList(
                [],
                [
                    'PRODUCT_ID' => $productId,
                    'CATALOG_GROUP_ID' => 1
                ],
                false,
                ['nTopCount' => 1],
                ['PRICE']
            );
            
            if ($price = $dbPrice->Fetch()) {
                $minPrice = min($minPrice, (float)$price['PRICE']);
            }
            
            // 2. Проверяем цены торговых предложений
            $dbOffers = \CIBlockElement::GetList(
                [],
                [
                    'IBLOCK_ID' => self::OFFER_IBLOCK_ID,
                    'ACTIVE' => 'Y',
                    'PROPERTY_CML2_LINK' => $productId
                ],
                false,
                false,
                ['ID']
            );
            
            while ($offer = $dbOffers->Fetch()) {
                $offerId = (int)$offer['ID'];
                
                $dbOfferPrice = \CPrice::GetList(
                    [],
                    [
                        'PRODUCT_ID' => $offerId,
                        'CATALOG_GROUP_ID' => 1
                    ],
                    false,
                    ['nTopCount' => 1],
                    ['PRICE']
                );
                
                if ($offerPrice = $dbOfferPrice->Fetch()) {
                    $minPrice = min($minPrice, (float)$offerPrice['PRICE']);
                }
            }
            
            return $minPrice === PHP_FLOAT_MAX ? 0.0 : $minPrice;
            
        } catch (\Exception $e) {
            error_log("getProductMinPrice error: " . $e->getMessage());
            return 0.0;
        }
    }
    
    /**
     * Определение протокола сайта
     */
    private static function getSiteProtocol(): string
    {
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        
        if ($request->isHttps()) {
            return 'https';
        }
        
        if ($request->getServer()->get('HTTP_X_FORWARDED_PROTO') === 'https') {
            return 'https';
        }
        
        return 'http';
    }
}