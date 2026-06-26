<?
use \Bitrix\Main\EventManager;
use \Bitrix\Main\Localization\Loc;

$eventManager = EventManager::getInstance();

$eventManager->addEventHandler("iblock", "OnBeforeIBlockElementAdd", ["MyIBlockEventsHundlers","MyOnBeforeIBlockElementAddHundler"]);
$eventManager->addEventHandler("iblock", "OnBeforeIBlockElementUpdate", ["MyIBlockEventsHundlers","MyOnBeforeIBlockElementUpdateHundler"]);

$eventManager->addEventHandler("iblock", "OnAfterIBlockElementUpdate", ["MyIBlockEventsHundlers","MyOnAfterIBlockElementUpdateHundler"]);

class MyIBlockEventsHundlers{

    private static ?string $oldAuthorId = null;

    private static function CheckAndReplaceTextAnonse(&$arFields){

        $currentAnonseText = str_replace(Loc::getMessage("PLACEHOLDER_ANONSE"), "", $arFields["PREVIEW_TEXT"]);
        $lengthAnones = strlen(trim($currentAnonseText));
         
        if($lengthAnones < 5){
            global $APPLICATION;
            $APPLICATION->ThrowException(Loc::getMessage("TEXT_EXCEPTION_SMALL_LENGTH_ANONSE", ["#LENGHT_ANONSE#" => $lengthAnones])); 
            return false;
        }

        $arFields["PREVIEW_TEXT"] =  $currentAnonseText;
        return true;
    }

    private static function GetReviewAythorId($reviewId){

        $reviewRes = CIBlockElement::GetList([],["IBLOCK_ID" => ID_IBLOCK_REVIEWS, "ID" => $reviewId], false, false, ["ID", "PROPERTY_AUTHOR"])->GetNext();

        #file_put_contents($_SERVER["DOCUMENT_ROOT"]."/local/help_DELETE/iblockEventsLog.txt", print_r($reviewRes, true), FILE_APPEND);

        return $reviewRes["PROPERTY_AUTHOR_VALUE"] ? $reviewRes["PROPERTY_AUTHOR_VALUE"] : Loc::getMessage("EMPTY_AUTHOR_VALUE");
    }

    public static function MyOnBeforeIBlockElementAddHundler(&$arFields){

        if($arFields["IBLOCK_ID"] != ID_IBLOCK_REVIEWS){
            return true;
        }

        $res = self::CheckAndReplaceTextAnonse($arFields);
        return $res;

    }

    public static function MyOnBeforeIBlockElementUpdateHundler(&$arFields){

        
        if($arFields["IBLOCK_ID"] != ID_IBLOCK_REVIEWS){
            return true;
        }

        $res = self::CheckAndReplaceTextAnonse($arFields);

        #file_put_contents($_SERVER["DOCUMENT_ROOT"]."/local/help_DELETE/iblockEventsLog.txt", print_r($arFields["ID"], true), FILE_APPEND);
        if($res){
            self::$oldAuthorId = self::GetReviewAythorId($arFields["ID"]);
        }

        return $res;
        
    }

    public static function MyOnAfterIBlockElementUpdateHundler(&$arFields){

        #file_put_contents($_SERVER["DOCUMENT_ROOT"]."/local/help_DELETE/iblockEventsLog.txt", print_r(self::$oldAuthorId, true), FILE_APPEND);

        if(self::$oldAuthorId === null){
            return true;
        }
        $currentAuthorValue = self::GetReviewAythorId($arFields["ID"]);

        #file_put_contents($_SERVER["DOCUMENT_ROOT"]."/local/help_DELETE/iblockEventsLog.txt", print_r($currentAuthorValue, true), FILE_APPEND);

        if(self::$oldAuthorId == $currentAuthorValue){
            return true;
        }

        CEventLog::Add([
            "SEVERITY" => "INFO",
            "AUDIT_TYPE_ID" => "ex2_590",
            "ITEM_ID" => $arFields["ID"],
            "SITE_ID" => SITE_ID,
            "DESCRIPTION" => Loc::getMessage("TEXT_LOGBOOK_CHANGE_AUTHOR_REVIEW", [
                "#ID_REVIEW#" => $arFields["ID"],
                "#OLD_ID_AUTHOR#" => self::$oldAuthorId,
                "#CURRENT_ID_AUTHOR#" => $currentAuthorValue
            ])
        ]);

        return true;
    }

}