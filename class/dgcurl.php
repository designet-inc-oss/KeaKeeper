<?php

define('CONTENT_TYPE_JSON', 'Content-Type: application/json');

/*****************************************************************************
* function        : curl_sendpost
* Description     : send config-xxx command to server
* args            : $post_auth_url    server
*                   $jsondata         jsondata
*                   &$ref_body        response
*                   &$errmsg          error message
* return          : false or true
*****************************************************************************/
function curl_sendpost($post_auth_url, $jsondata, &$ref_body, &$errmsg)
{
    /* cURL セッションを初期化する */
    $ch = curl_init($post_auth_url);
    if ($ch === false) {
        $errmsg = "curl init failed.($post_auth_url)($jsondata)";
        return false;
    }

    /* オプション設定 */
    $curl_option = [
        CURLOPT_HTTPHEADER     => array(CONTENT_TYPE_JSON),
        CURLOPT_POST           => false,
        CURLOPT_POSTFIELDS     => $jsondata,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_SSL_VERIFYPEER => false,
    ];

    /* CURL 転送用の複数のオプションを設定する */
    $ret =  curl_setopt_array($ch, $curl_option);
    if ($ret === false) {
        $errmsg = "curl set option failed.($post_auth_url)($jsondata)";
        return false;
    }

    /* cURL セッションを実行する */
    $body = curl_exec($ch);

    /*  cURL セッションを閉じる */
    curl_close($ch);

    if ($body === false) {
        $errmsg = "curl exec failed.($post_auth_url)($jsondata)";
        return false;
    }

    /* データ格納 */
    $ref_body = $body;

    return true;
}

?>
