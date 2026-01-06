<?php
namespace RestCatalog\Controller;

use Bitrix\Main\Loader;

/**
 * Контроллер для детальной информации о товаре
 */
class ProductDetailController
{
    private const PRODUCT_IBLOCK_ID = 2;
    private const OFFER_IBLOCK_ID = 3;
    
    /**
     * Получение детальной информации о товаре
     * Сортировка торговых предложений по ID по возрастанию
     */
    public static function getProductDetail(int $productId): array
    {
        Loader::includeModule('iblock');
        Loader::includeModule('catalog');
        
        try {
            // Определяем протокол для полных URL
            $protocol = self::getSiteProtocol();
            $host = $_SERVER['HTTP_HOST'];
            
            // Ищем товар
            $dbElement = \CIBlockElement::GetByID($productId);
            if (!$element = $dbElement->GetNext()) {
                return ['error' => 'Product not found', 'error_code' => 'NOT_FOUND'];
            }
            
            // Проверяем инфоблок
            if ((int)$element['IBLOCK_ID'] !== self::PRODUCT_IBLOCK_ID) {
                return ['error' => 'Product not found', 'error_code' => 'NOT_FOUND'];
            }
            
            // Формируем правильный URL
            $detailUrl = self::getProductUrl($element);
            $fullUrl = $protocol . '://' . $host . $detailUrl;
            
            // Галерея
            $gallery = self::getProductGallery($productId);
            
            // Характеристики
            $properties = self::getProductProperties($productId);
            
            // Торговые предложения (отсортированные по ID)
            $offers = self::getProductOffers($productId);
            
            return [
                'result' => [
                    'ID' => (int)$element['ID'],
                    'NAME' => $element['NAME'],
                    'DETAIL_PAGE_URL' => $fullUrl,
                    'GALLERY' => $gallery,
                    'PROPERTIES' => $properties,
                    'OFFERS' => $offers,
                    'PREVIEW_TEXT' => $element['PREVIEW_TEXT'] ?? '',
                    'DETAIL_TEXT' => $element['DETAIL_TEXT'] ?? ''
                ]
            ];
            
        } catch (\Exception $e) {
            error_log("ProductDetailController error: " . $e->getMessage());
            return ['error' => 'Internal server error', 'error_code' => 'INTERNAL_ERROR'];
        }
    }
    
    /**
     * Получение правильного URL товара
     */
    private static function getProductUrl(array $element): string
    {
        $productId = (int)$element['ID'];
        
        // Используем стандартный Битрикс метод
        if (!empty($element['DETAIL_PAGE_URL'])) {
            return $element['DETAIL_PAGE_URL'];
        }
        
        // Формируем URL по умолчанию
        $code = $element['CODE'] ?: 'product' . $productId;
        $sectionId = (int)$element['IBLOCK_SECTION_ID'];
        
        if ($sectionId > 0) {
            $dbSection = \CIBlockSection::GetByID($sectionId);
            if ($section = $dbSection->GetNext()) {
                $sectionCode = $section['CODE'] ?: 'section' . $sectionId;
                // Убираем "clothes/" если есть
                $sectionCode = str_replace('clothes/', '', $sectionCode);
                return '/catalog/' . $sectionCode . '/' . $code . '/';
            }
        }
        
        return '/catalog/' . $code . '/';
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
    
    /**
     * Получение галереи товара
     */
    private static function getProductGallery(int $productId): array
    {
        $gallery = [];
        
        // Ищем свойство MORE_PHOTO через старый API для надежности
        $dbProps = \CIBlockElement::GetProperty(
            self::PRODUCT_IBLOCK_ID,
            $productId,
            [],
            ['CODE' => 'MORE_PHOTO']
        );
        
        while ($prop = $dbProps->Fetch()) {
            if ($prop['VALUE']) {
                $file = \CFile::GetFileArray($prop['VALUE']);
                if ($file) {
                    $gallery[] = $file['SRC'];
                }
            }
        }
        
        return $gallery;
    }
    
    /**
     * Получение характеристик товара
     */
    private static function getProductProperties(int $productId): array
    {
        $properties = [];
        $propertyCodes = ['BRAND_REF', 'MANUFACTURER', 'MATERIAL'];
        
        foreach ($propertyCodes as $code) {
            $dbProp = \CIBlockElement::GetProperty(
                self::PRODUCT_IBLOCK_ID,
                $productId,
                [],
                ['CODE' => $code]
            );
            
            if ($prop = $dbProp->Fetch()) {
                if (!empty($prop['VALUE'])) {
                    $value = self::formatPropertyValue($prop['VALUE'], $prop['PROPERTY_TYPE']);
                    $name = $prop['NAME'] ?: $code;
                    
                    $properties[] = [
                        'code' => $code,
                        'name' => $name,
                        'value' => $value
                    ];
                }
            }
        }
        
        return $properties;
    }
    
    /**
     * Получение торговых предложений с сортировкой по ID по возрастанию
     */
    private static function getProductOffers(int $productId): array
    {
        $offers = [];
        
        // Ищем предложения привязанные к товару с сортировкой по ID
        $dbOffers = \CIBlockElement::GetList(
            ['ID' => 'ASC'], // СОРТИРОВКА ПО ID ПО ВОЗРАСТАНИЮ
            [
                'IBLOCK_ID' => self::OFFER_IBLOCK_ID,
                'ACTIVE' => 'Y',
                'PROPERTY_CML2_LINK' => $productId
            ],
            false,
            false,
            ['ID', 'NAME']
        );
        
        while ($offer = $dbOffers->Fetch()) {
            $offerId = (int)$offer['ID'];
            
            // Получаем свойства предложения
            $offerProps = self::getOfferProperties($offerId);
            
            // Получаем цену
            $price = self::getOfferPrice($offerId);
            
            $offers[] = [
                'id' => $offerId,
                'name' => $offer['NAME'],
                'article' => $offerProps['ARTNUMBER'] ?? '',
                'color' => $offerProps['COLOR_REF'] ?? '',
                'size' => $offerProps['SIZES_CLOTHES'] ?? $offerProps['SIZES_SHOES'] ?? '',
                'price' => $price
            ];
        }
        
        return $offers;
    }
    
    /**
     * Альтернативный метод с явной сортировкой после получения
     */
    private static function getProductOffersSorted(int $productId): array
    {
        $offers = [];
        
        // Получаем все предложения
        $dbOffers = \CIBlockElement::GetList(
            [], // Без сортировки на уровне запроса
            [
                'IBLOCK_ID' => self::OFFER_IBLOCK_ID,
                'ACTIVE' => 'Y',
                'PROPERTY_CML2_LINK' => $productId
            ],
            false,
            false,
            ['ID', 'NAME']
        );
        
        while ($offer = $dbOffers->Fetch()) {
            $offerId = (int)$offer['ID'];
            
            $offerProps = self::getOfferProperties($offerId);
            $price = self::getOfferPrice($offerId);
            
            $offers[] = [
                'id' => $offerId,
                'name' => $offer['NAME'],
                'article' => $offerProps['ARTNUMBER'] ?? '',
                'color' => $offerProps['COLOR_REF'] ?? '',
                'size' => $offerProps['SIZES_CLOTHES'] ?? $offerProps['SIZES_SHOES'] ?? '',
                'price' => $price
            ];
        }
        
        // СОРТИРОВКА ПО ID ПО ВОЗРАСТАНИЮ
        usort($offers, function($a, $b) {
            return $a['id'] <=> $b['id'];
        });
        
        return $offers;
    }
    
    /**
     * Получение свойств предложения
     */
    private static function getOfferProperties(int $offerId): array
    {
        $properties = [];
        $codes = ['ARTNUMBER', 'COLOR_REF', 'SIZES_SHOES', 'SIZES_CLOTHES'];
        
        foreach ($codes as $code) {
            $dbProp = \CIBlockElement::GetProperty(
                self::OFFER_IBLOCK_ID,
                $offerId,
                [],
                ['CODE' => $code]
            );
            
            if ($prop = $dbProp->Fetch()) {
                if (!empty($prop['VALUE'])) {
                    $properties[$code] = self::formatPropertyValue($prop['VALUE'], $prop['PROPERTY_TYPE']);
                }
            }
        }
        
        return $properties;
    }
    
    /**
     * Получение цены предложения
     */
    private static function getOfferPrice(int $offerId): float
    {
        $dbPrice = \CPrice::GetList(
            [],
            [
                'PRODUCT_ID' => $offerId,
                'CATALOG_GROUP_ID' => 1
            ],
            false,
            ['nTopCount' => 1],
            ['PRICE']
        );
        
        if ($price = $dbPrice->Fetch()) {
            return (float)$price['PRICE'];
        }
        
        return 0.0;
    }
    
    /**
     * Форматирование значения свойства
     */
    private static function formatPropertyValue($value, string $type)
    {
        switch ($type) {
            case 'S':
            case 'STRING':
            case 'N':
            case 'NUMBER':
                return $value;
            case 'L':
            case 'LIST':
                $enum = \CIBlockPropertyEnum::GetByID($value);
                return $enum ? $enum['VALUE'] : $value;
            case 'E':
            case 'ELEMENT':
                $element = \CIBlockElement::GetByID($value);
                return $element ? $element['NAME'] : $value;
            default:
                return $value;
        }
    }
}