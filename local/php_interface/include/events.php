<?
use \Bitrix\Main\EventManager;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\UserTable;
use \Bitrix\Iblock\ElementTable;
use \Bitrix\Main\Mail\Event;

$eventManager = EventManager::getInstance();

$eventManager->addEventHandler("iblock", "OnBeforeIBlockElementAdd", ["MyIBlockEventsHundlers","MyOnBeforeIBlockElementAddHundler"]);
$eventManager->addEventHandler("iblock", "OnBeforeIBlockElementUpdate", ["MyIBlockEventsHundlers","MyOnBeforeIBlockElementUpdateHundler"]);

$eventManager->addEventHandler("iblock", "OnAfterIBlockElementUpdate", ["MyIBlockEventsHundlers","MyOnAfterIBlockElementUpdateHundler"]);

$eventManager->addEventHandler("main", "OnBeforeUserUpdate", ["MyUserEventHundlers","MyOnBeforeUserUpdateHundler"]);
$eventManager->addEventHandler("main", "OnAfterUserUpdate", ["MyUserEventHundlers","MyOnAfterUserUpdateHundler"]);

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
            "SITE_ID" => MY_SITE_ID,
            "DESCRIPTION" => Loc::getMessage("TEXT_LOGBOOK_CHANGE_AUTHOR_REVIEW", [
                "#ID_REVIEW#" => $arFields["ID"],
                "#OLD_ID_AUTHOR#" => self::$oldAuthorId,
                "#CURRENT_ID_AUTHOR#" => $currentAuthorValue
            ])
        ]);

        return true;
    }

}

class MyUserEventHundlers{

    private static ?string $oldAuthorStatusId = null;
    private static $arrayAuthorStatusNames = [];

    private static function SetOldAuthorStatusId($userId){

        $resUser = UserTable::getList([
            'select' => ["ID", "NAME", "UF_AUTHOR_STATUS"],
            'filter' => ["ID" => $userId]
        ])->fetch();

        self::$oldAuthorStatusId = $resUser["UF_AUTHOR_STATUS"] ? $resUser["UF_AUTHOR_STATUS"] : ""; 

    }

    private static function SetArrayAuthorStatusNames(){

        $resStatusesUsers = ElementTable::getList([
            'select' => ["ID", "NAME"],
            'filter' => ["IBLOCK_ID" => ID_IBLOCK_STATUSES_USERS]
        ]);

        while($val = $resStatusesUsers->fetch()){
            self::$arrayAuthorStatusNames[$val["ID"]] = $val["NAME"];
        }

        self::$arrayAuthorStatusNames[""] = Loc::getMessage("EMPTY_STATUS_VALUE");

    }

    public static function MyOnBeforeUserUpdateHundler(&$arFields){

        if(!array_key_exists("UF_AUTHOR_STATUS", $arFields)){
            return true;
        }

        self::SetOldAuthorStatusId($arFields["ID"]);
        self::SetArrayAuthorStatusNames();

        return true;
    }

    public static function MyOnAfterUserUpdateHundler(&$arFields){
        
        if(!array_key_exists("UF_AUTHOR_STATUS", $arFields) || self::$oldAuthorStatusId == $arFields["UF_AUTHOR_STATUS"]){
            return true;
        }

        Event::send([
            "EVENT_NAME" => MAIL_EVENT,
            "LID" => MY_SITE_ID,
            "C_FIELDS" => [
                "OLD_UF_STATUS" => self::$arrayAuthorStatusNames[self::$oldAuthorStatusId],
                "NEW_UF_STATUS" => self::$arrayAuthorStatusNames[$arFields["UF_AUTHOR_STATUS"]]
            ]
        ]);

        #file_put_contents($_SERVER["DOCUMENT_ROOT"]."/local/help_DELETE/hundlersUsers.txt", print_r($arFields["UF_AUTHOR_STATUS"], true), FILE_APPEND);
        return true;
    }



}