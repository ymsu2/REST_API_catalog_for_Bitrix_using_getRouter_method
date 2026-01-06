<?php
namespace RestCatalog\Controller;

use Bitrix\Main\Loader;

/**
 * Контроллер для работы с категориями
 */
class CategoryController
{
    private const IBLOCK_ID = 2;
    
    /**
     * Получение дерева категорий
     * Сортировка по ID категории по возрастанию
     */
    public static function getCategories(): array
    {
        Loader::includeModule('iblock');
        
        try {
            $protocol = self::getSiteProtocol();
            $host = $_SERVER['HTTP_HOST'];
            
            // Получаем ВСЕ активные разделы с сортировкой по ID по возрастанию
            $dbSections = \CIBlockSection::GetList(
                ['ID' => 'ASC'], // СОРТИРОВКА ПО ID ПО ВОЗРАСТАНИЮ
                [
                    'IBLOCK_ID' => self::IBLOCK_ID,
                    'ACTIVE' => 'Y',
                    'GLOBAL_ACTIVE' => 'Y'
                ],
                false,
                ['ID', 'NAME', 'CODE', 'DEPTH_LEVEL', 'IBLOCK_SECTION_ID', 'PICTURE', 'DETAIL_PICTURE']
            );
            
            // Собираем все разделы в массив
            $allSections = [];
            while ($section = $dbSections->GetNext()) {
                $sectionId = (int)$section['ID'];
                $allSections[$sectionId] = [
                    'ID' => $sectionId,
                    'NAME' => $section['NAME'],
                    'CODE' => $section['CODE'],
                    'DEPTH_LEVEL' => (int)$section['DEPTH_LEVEL'],
                    'PARENT_ID' => (int)$section['IBLOCK_SECTION_ID'],
                    'PICTURE' => $section['PICTURE'] ?: $section['DETAIL_PICTURE'],
                    'SORT' => (int)$section['ID'] // Используем ID как ключ сортировки
                ];
            }
            
            // Сортируем массив разделов по ID (уже отсортирован в запросе)
            ksort($allSections);
            
            // Строим дерево категорий рекурсивно
            $buildTree = function($parentId = 0) use (&$buildTree, $allSections, $protocol, $host) {
                $result = [];
                
                // Сортируем дочерние элементы по ID
                $children = array_filter($allSections, function($section) use ($parentId) {
                    return $section['PARENT_ID'] == $parentId;
                });
                
                // СОРТИРОВКА ПО ID ПО ВОЗРАСТАНИЮ
                usort($children, function($a, $b) {
                    return $a['ID'] <=> $b['ID'];
                });
                
                foreach ($children as $section) {
                    $sectionId = $section['ID'];
                    
                    // Формируем URL категории
                    $detailUrl = self::getCategoryUrl($sectionId, $section['CODE']);
                    $fullUrl = $protocol . '://' . $host . $detailUrl;
                    
                    // Получаем картинку
                    $picturePath = self::getPicturePath($section['PICTURE']);
                    
                    $formatted = [
                        'ID' => $sectionId,
                        'NAME' => $section['NAME'],
                        'DETAIL_PAGE_URL' => $fullUrl,
                        'PICTURE' => $picturePath
                    ];
                    
                    // Рекурсивно получаем детей
                    $childItems = $buildTree($sectionId);
                    
                    // Сортируем детей по ID
                    usort($childItems, function($a, $b) {
                        return $a['ID'] <=> $b['ID'];
                    });
                    
                    $formatted['CHILDREN'] = $childItems;
                    
                    $result[] = $formatted;
                }
                
                return $result;
            };
            
            // Получаем корневые категории (PARENT_ID = 0) и сортируем по ID
            $rootCategories = array_filter($allSections, function($section) {
                return $section['PARENT_ID'] == 0;
            });
            
            // СОРТИРОВКА КОРНЕВЫХ КАТЕГОРИЙ ПО ID ПО ВОЗРАСТАНИЮ
            usort($rootCategories, function($a, $b) {
                return $a['ID'] <=> $b['ID'];
            });
            
            $resultCategories = [];
            foreach ($rootCategories as $rootCategory) {
                $sectionId = $rootCategory['ID'];
                
                // Формируем URL категории
                $detailUrl = self::getCategoryUrl($sectionId, $rootCategory['CODE']);
                $fullUrl = $protocol . '://' . $host . $detailUrl;
                
                // Получаем картинку
                $picturePath = self::getPicturePath($rootCategory['PICTURE']);
                
                $formatted = [
                    'ID' => $sectionId,
                    'NAME' => $rootCategory['NAME'],
                    'DETAIL_PAGE_URL' => $fullUrl,
                    'PICTURE' => $picturePath
                ];
                
                // Получаем детей рекурсивно
                $children = $buildTree($sectionId);
                
                // Сортируем детей по ID
                usort($children, function($a, $b) {
                    return $a['ID'] <=> $b['ID'];
                });
                
                $formatted['CHILDREN'] = $children;
                $resultCategories[] = $formatted;
            }
            
            return ['result' => $resultCategories];
            
        } catch (\Exception $e) {
            error_log("CategoryController error: " . $e->getMessage());
            return ['result' => []];
        }
    }
    
    /**
     * Получение правильного URL категории
     */
    private static function getCategoryUrl(int $sectionId, ?string $code = null): string
    {
        // Используем стандартный Битрикс метод для получения URL
        $dbSection = \CIBlockSection::GetByID($sectionId);
        if ($section = $dbSection->GetNext()) {
            if (!empty($section['SECTION_PAGE_URL'])) {
                return $section['SECTION_PAGE_URL'];
            }
        }
        
        // Формируем URL по умолчанию
        if (!$code) {
            $code = 'section' . $sectionId;
        }
        
        // Убираем "clothes/" из пути, если он есть
        $code = str_replace('clothes/', '', $code);
        
        return '/catalog/' . $code . '/';
    }
    
    /**
     * Получение пути к картинке
     */
    private static function getPicturePath(?int $fileId): string
    {
        if (!$fileId) {
            return '';
        }
        
        $file = \CFile::GetFileArray($fileId);
        return $file ? $file['SRC'] : '';
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