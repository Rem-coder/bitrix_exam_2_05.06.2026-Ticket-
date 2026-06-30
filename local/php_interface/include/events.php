<?
use \Bitrix\Main\EventManager;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\UserTable;
use \Bitrix\Iblock\ElementTable;
use \Bitrix\Main\Mail\Event;
use \Bitrix\Main\Loader;

$eventManager = EventManager::getInstance();

$eventManager->addEventHandler("iblock", "OnBeforeIBlockElementAdd", ["MyIBlockEventsHundlers","MyOnBeforeIBlockElementAddHundler"]);
$eventManager->addEventHandler("iblock", "OnBeforeIBlockElementUpdate", ["MyIBlockEventsHundlers","MyOnBeforeIBlockElementUpdateHundler"]);

$eventManager->addEventHandler("iblock", "OnAfterIBlockElementUpdate", ["MyIBlockEventsHundlers","MyOnAfterIBlockElementUpdateHundler"]);

$eventManager->addEventHandler("main", "OnBeforeUserUpdate", ["MyUserEventHundlers","MyOnBeforeUserUpdateHundler"]);
$eventManager->addEventHandler("main", "OnAfterUserUpdate", ["MyUserEventHundlers","MyOnAfterUserUpdateHundler"]);

$eventManager->addEventHandler("search", "BeforeIndex", ["MyIndexHundlers","MyBeforeIndexHundler"]);

$eventManager->addEventHandler("main", "OnBuildGlobalMenu", ["MyMenuHundlers","MyOnBuildGlobalMenuHundler"]);

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

        return true;
    }



}

class MyIndexHundlers{
    
    private static ?array $arLoginsAuthors = null;

    private static function setArLoginsAuthors(){

        if(!Loader::includeModule("iblock")){
            self::$arLoginsAuthors = [];
            return;
        }

        $arUsers = [];
        $arReviews = [];

        $resReviews = CIBlockElement::GetList(
            [],
            ["ACTIVE" => "Y", "IBLOCK_ID" => ID_IBLOCK_REVIEWS],
            false,
            false,
            ["ID", "PROPERTY_AUTHOR"]
        );

        while($val = $resReviews->GetNext()){
            $arReviews[$val["ID"]] = $val["PROPERTY_AUTHOR_VALUE"];
        }

        if(empty($arReviews)){
            self::$arLoginsAuthors = [];
            return;
        }

        $resUsers = UserTable::getList([
            'select' => ["ID", "LOGIN"],
            'filter' => ["ID" => array_unique($arReviews)] 
       ]);

        while($val = $resUsers->fetch()){
            $arUsers[$val["ID"]] = $val["LOGIN"];
        }

        foreach($arReviews as $key => $value){
            self::$arLoginsAuthors[$key] = empty($arUsers[$value]) ? Loc::getMessage("EMPTY_AUTHOR") :$arUsers[$value];
        }
        
    }

    public static function MyBeforeIndexHundler($arFields){
       
        if(self::$arLoginsAuthors === null){
            self::setArLoginsAuthors();
        }

        if($arFields["PARAM2"] != ID_IBLOCK_REVIEWS 
        || $arFields["MODULE_ID"] != "iblock" 
        || substr($arFields["ITEM_ID"], 0, 1) == 'S' 
        || empty(self::$arLoginsAuthors)){
            return $arFields;
        }

        $arFields["TITLE"] = $arFields["TITLE"]." ".self::$arLoginsAuthors[$arFields["ITEM_ID"]];

        file_put_contents($_SERVER["DOCUMENT_ROOT"]."/local/help_DELETE/index_log.txt", print_r($arFields["TITLE"], true), FILE_APPEND);

        return $arFields;
    }

}

class MyMenuHundlers{

    public static function MyOnBuildGlobalMenuHundler(&$aGlobalMenu, &$aModuleMenu){

        global $USER;

        $currentUserGroups = $USER->GetUserGroupArray();

        if(!in_array(ID_USER_GROUP_CONTENTS_REDACTOR, $USER->GetUserGroupArray())){
            return;
        }

        foreach($aGlobalMenu as $key => $value){
            if($value["menu_id"] == ID_MENU_CONTENT){
                continue;
            };
            unset($aGlobalMenu[$key]);
        }

        foreach($aModuleMenu as $key => $value){
            if($value["parent_menu"] == ID_PATENT_MENU_CONTENT){
                continue;
            };
            unset($aModuleMenu[$key]);
        }

        $aGlobalMenu["global_menu_fast_page"] = [
            "menu_id" => "fast_page",
            "text" => Loc::getMessage("TITLE_FAST_MENU"),
            "title" => Loc::getMessage("TITLE_FAST_MENU"),
            "sort" => 200,
            "items_id" => "global_menu_fast_page",
            "items" => [
                0 => [
                    "text" => Loc::getMessage("TEXT_BUTTON_ONE"),
                    "url" => "https://test1" 
                ],
                1 => [
                    "text" => Loc::getMessage("TEXT_BUTTON_TWO"),
                    "url" => "https://test2" 
                ]]
        ];
    }

}