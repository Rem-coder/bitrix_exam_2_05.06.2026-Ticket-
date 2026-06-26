<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\UserTable;

$arrUsersStatusPublic = [];
$arrReviewsForItems = [];
$countCorrectReviews = 0;

$resUsers = UserTable::getList([
	'select' => ["ID"],
	'filter' => ["UF_AUTHOR_STATUS" => ID_STATUS_AUTHOR_PUBLIC] 
]);

while($userData = $resUsers->fetch()){
	$arrUsersStatusPublic[] = $userData["ID"];
}

if(!empty($arrUsersStatusPublic)){

	$resReviews = CIBlockElement::GetList(
		[],
		[
			"ACTIVE" => "Y",
			"IBLOCK_ID" => ID_IBLOCK_REVIEWS,
			"PROPERTY_AUTHOR" => $arrUsersStatusPublic
		],
		false,
		false,
		["ID", "NAME", "PROPERTY_AUTHOR", "PROPERTY_PRODUCT"]
	);
	
	while($revData = $resReviews->GetNext()){
		$arrReviewsForItems[$revData["PROPERTY_PRODUCT_VALUE"]][] =  $revData["NAME"];
		$countCorrectReviews = $countCorrectReviews + 1;
	}
}

foreach ($arResult['ITEMS'] as $key => $arItem)
{
	$arItem['PRICES']['PRICE']['PRINT_VALUE'] = number_format((float)$arItem['PRICES']['PRICE']['PRINT_VALUE'], 0, '.', ' ');
	$arItem['PRICES']['PRICE']['PRINT_VALUE'] .= ' '.$arItem['PROPERTIES']['PRICECURRENCY']['VALUE_ENUM'];

	$arItem['REVIEWS_DATA'] = $arrReviewsForItems[$arItem["ID"]];

	

	$arResult['ITEMS'][$key] = $arItem;
}

$arResult["COUNT_CORRECT_REV"] = $countCorrectReviews;

$this->getComponent()->SetResultCacheKeys(["COUNT_CORRECT_REV"]);
