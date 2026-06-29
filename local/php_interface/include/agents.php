<?php
use \Bitrix\Iblock\ElementTable;
use \Bitrix\Main\Loader;
use \Bitrix\Main\Type\DateTime as BxDt;
use \Bitrix\Main\Localization\Loc;

class MyAgent{
    
    public static function Agent_ex_610($timeLastStart = false){

        $timeLastStart = $timeLastStart ? new BxDt($timeLastStart) : BxDt::createFromPhp(new \DateTime('2000-01-01'));
        $currentDateTime = new BxDt();
        $countModifyReviews = 0;
        
        if(Loader::includeModule("iblock")){
            $req = CIBlockElement::GetList(
                [],
                [
                    "ACTIVE" => "Y", 
                    "IBLOCK_ID" => ID_IBLOCK_REVIEWS,
                    "DATE_MODIFY_FROM" => $timeLastStart,
                    "DATE_MODIFY_TO" => $currentDateTime
                ],
                false,
                false,
                ["ID"]
            );

            while($val = $req->fetch()){
                $countModifyReviews = $countModifyReviews + 1;
            }

            if($countModifyReviews){
                
                CEventLog::Add([
                    "SEVERITY" => "INFO",
                    "AUDIT_TYPE_ID" => "ex2_610",
                    "SITE_ID" => MY_SITE_ID,
                    "DESCRIPTION" => Loc::getMessage("TEXT_REVIES_MODIFY", [
                        "#TIME_LAST_START#" => $timeLastStart->toString(),
                        "#COUNT_REVIEWS#" => $countModifyReviews])
                ]);
                
            }
        }
        file_put_contents($_SERVER["DOCUMENT_ROOT"]."/local/help_DELETE/agentsLog.txt", print_r("MyAgent::Agent_ex_610("."'".$currentDateTime->toString()."'".");", true), FILE_APPEND);
        return "MyAgent::Agent_ex_610("."'".$currentDateTime->toString()."'".");";
    }

}