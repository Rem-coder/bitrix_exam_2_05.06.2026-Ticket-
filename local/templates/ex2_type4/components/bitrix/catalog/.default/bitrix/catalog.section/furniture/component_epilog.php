<?
use \Bitrix\Main\Localization\Loc;

if($arResult["COUNT_CORRECT_REV"]){

    $currentProperty = $APPLICATION->GetPageProperty('ex2_meta');
    if(empty($currentProperty)){
        $currentProperty = $APPLICATION->GetDirProperty('ex2_meta');
    };

    $correctProperty = str_replace(Loc::getMessage("STR_REPLACE"), $arResult["COUNT_CORRECT_REV"], $currentProperty);
    $APPLICATION->SetPageProperty('ex2_meta', $correctProperty);
}