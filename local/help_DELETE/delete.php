<?

echo "<pre>".htmlspecialchars(print_r($arRes, true))."<pre>";


file_put_contents($_SERVER["DOCUMENT_ROOT"]."/local/help_DELETE/log.txt", print_r($res, true), FILE_APPEND);

array_fiarray_values($arr)
