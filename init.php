<?php

class Helper {
    
    //добавляем кастомные поля секции
    private static function setSectionProp($result, $prop, $IBLOCK_ID, $isImg = false) {
        
        $sectionFilds = CIBlockSection::GetList(
            array("SORT"=>"ASC"),
            array("IBLOCK_ID" => $IBLOCK_ID, "ID" => $result['ID']),
            false,
            array($prop),
            false
        );
 
        $flSections = $sectionFilds->Fetch();
        if($isImg) {
            $flSections[$prop] = CFile::GetPath($flSections[$prop]); 
        }
 
        return $flSections[$prop];
    }
 
    //Возвращает URL секции
    private static function setSectionUrl($result) { 
        //добавляем URL
        $getSectionUrl = CIBlockSection::GetByID($result['ID']);
        $sectionUrl = $getSectionUrl->GetNext();
        return $sectionUrl['SECTION_PAGE_URL'];
    }
 
    // метод возвращает тегирование продукции
    public static function getTags($IBLOCK_ID, $DEPTH_LEVEL = 1, $NEED_PHOTO = false) {
        
        if($DEPTH_LEVEL <= 1) {
            $result = [];
            $filtearList = array('ID', 'NAME');
            $rsSection = \Bitrix\Iblock\SectionTable::getList(array(
                'filter' => array(
                    'IBLOCK_ID' => $IBLOCK_ID,
                    'DEPTH_LEVEL' => $DEPTH_LEVEL,
                ), 
                'select' =>  $filtearList,
            ));
            
            while ($arSection = $rsSection->fetch()) {
                $arSection['SECTION_PAGE_URL'] = Helper::setSectionUrl($arSection);
                $result[] = $arSection;
            }
 
            return $result;
        }
 
        if($DEPTH_LEVEL > 1) {
            $result_arr = [];
            $filtearList = array('ID', 'NAME','IBLOCK_ID');
 
            $rsSection = \Bitrix\Iblock\SectionTable::getList(array(
                'filter' => array(
                    'IBLOCK_ID' => $IBLOCK_ID,
                    'DEPTH_LEVEL' => 1,
                ), 
                'select' =>  $filtearList,
            ));
 
            while ($arSection = $rsSection->fetch()) {
                //добавляем кастомные поля
                $arSection['MENU_ICON_IMG'] = Helper::setSectionProp($arSection, 'UF_F_MENU_ICON', $IBLOCK_ID, true);
                
                //добавляем URL
                $arSection['SECTION_PAGE_URL'] = Helper::setSectionUrl($arSection);
 
                //добавляем потомков
                $rsParentSection = CIBlockSection::GetByID($arSection['ID']);
                if ($arParentSection = $rsParentSection->GetNext()) {
                    // выберет потомков без учета активности
                    $arFilter = array(
                        'IBLOCK_ID' => $arParentSection['IBLOCK_ID'],
                        '>LEFT_MARGIN' => $arParentSection['LEFT_MARGIN'],
                        '<RIGHT_MARGIN' => $arParentSection['RIGHT_MARGIN'],
                        '>DEPTH_LEVEL' => $arParentSection['DEPTH_LEVEL']
                    ); 
 
                    $rsSect = CIBlockSection::GetList(
                        array('left_margin' => 'asc'),
                        $arFilter,
                        false,
                        $filtearList,
                        false
                    );
 
                    while ($arSect = $rsSect->GetNext()){
                        //добавляем кастомные поля
                        $arSect['MENU_ICON_IMG'] = Helper::setSectionProp($arSect, 'UF_F_MENU_ICON', $IBLOCK_ID, true);
                        //добавляем URL
                        $arSect['SECTION_PAGE_URL'] = Helper::setSectionUrl($arSect);
 
                        $arSection['CHILDREN'][] = $arSect;
                    }
                }
 
                $result_arr[] = $arSection;
            }
 
            return $result_arr;
        }
                
    }
 
    //функция устанавливает минимальное значение цены для секции
    public static function addSectionMinPrice($blockId, $ids = []) {
        $sectionFilterFields = !empty($ids) ? Array("IBLOCK_ID"=> $blockId, "ID"=> $ids) : Array("IBLOCK_ID"=> $blockId);
 
        $sectionName = CIBlockSection::GetList(
            $arOrder = Array("SORT"=>"ASC"),
            $arFilter = $sectionFilterFields,
            $bIncCnt = Array("ELEMENT_SUBSECTIONS"),
            $arSelect = Array("ID", "NAME", "UF_SECTION_MIN_PRICE",  "IBLOCK_ID"),
            false
        );
 
        while($arRes = $sectionName->GetNext()) {
            
            //добавляем минимальную цену
            $res = CIBlockElement::GetList(
                Array("PROPERTY_ATT_PRICE"=>"DESC"),
                Array("IBLOCK_ID" => $arRes['IBLOCK_SECTION_ID'], "INCLUDE_SUBSECTIONS" => "Y","SECTION_ID"=>$arRes['ID']),
                false,
                array(),
                Array("PROPERTY_ATT_PRICE")
            );
 
            $minPriceArr = [];
            while($ob = $res->Fetch()){
                if($ob["PROPERTY_ATT_PRICE_VALUE"] > 0) {
                    $numberMatch = preg_match('/\d+/',$ob["PROPERTY_ATT_PRICE_VALUE"],$matches);
                    $minPriceArr[] = $matches[0];
                } 
            }
    
            $bs = new CIBlockSection;
            $bs->Update($arRes["ID"], array('UF_SECTION_MIN_PRICE' =>  min($minPriceArr) > 0 ? min($minPriceArr) : 0));
            
        }
 
    }
 
    //функция возвращает секции
    public static function catalogList($blockId, $ids) {
        $result = [];
 
        $sectionName = CIBlockSection::GetList(
            $arOrder = Array("SORT"=>"ASC"),
            $arFilter = Array("IBLOCK_ID"=> $blockId, "ID"=> $ids),
            $bIncCnt = Array("ELEMENT_SUBSECTIONS"),
            $arSelect = Array("ID", "NAME", "SECTION_PAGE_URL", "CODE", "PICTURE", "UF_SECTION_MIN_PRICE",  "IBLOCK_ID"),
            false
        );
        $sectionName->SetUrlTemplates("/catalog/#SECTION_CODE#/#ELEMENT_CODE#");
 
        while($arRes = $sectionName->GetNext()) {
            $catImg = CFile::GetPath($arRes["PICTURE"]);
            $arRes["DETAIL_PICTURE"] = $catImg;
            
            $result[] = $arRes;
        }
 
        return $result;
 
    }
 
    //функция возвращает элементы из каталога
    public static function itemList($ids) {
        $result = [];
 
        $list = \CIBlockElement::GetList(
            $arOrder = Array("SORT"=>"ASC"),
            $arFilter = Array("IBLOCK_ID"=> 14, "ID"=> $ids),
            false,
            false,
            $arSelectFields = Array("ID","NAME","DETAIL_PAGE_URL","DETAIL_PICTURE","PROPERTY_ATT_PRICE", "PROPERTY_ATT_PRICE_ED", "IBLOCK_ID")
        );
 
        while($arRes = $list->GetNext()) {
            //добавляем картинку
            if($catImg = CFile::GetPath($arRes["DETAIL_PICTURE"])) {
                $arRes["DETAIL_PICTURE"] = $catImg;
            } else {
                $arRes["DETAIL_PICTURE"] = '';
            }
 
            $result[] = $arRes;
        }
 
        return $result;
    }
 
    public static function getNews($idBlock, $limit = '') {
        $result = [];
    
        $list = \CIBlockElement::GetList(
            $arOrder = Array("SORT_BY1" => "ACTIVE_FROM",
            "SORT_BY2" => "SORT",
            "SORT_ORDER1" => "DESC",
            "SORT_ORDER2" => "ASC"),
            $arFilter = Array("IBLOCK_ID" => 8),
            false,
            Array("nPageSize"=>2),
            $arSelectFields = Array("ID","NAME","DETAIL_PAGE_URL","PREVIEW_PICTURE")
        );
    
    
        while($arRes = $list->GetNext()) {
            $catImg = CFile::GetPath($arRes["PREVIEW_PICTURE"]);
            $arRes["PREVIEW_PICTURE"] = $catImg;
            $result[] = $arRes;
        }
    
        return $result;
    
    }
 
}

?>