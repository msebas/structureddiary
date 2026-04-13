<?php

declare(strict_types=1);

use OCP\Util;

$app_id = OCA\StructuredDiary\AppInfo\Application::APP_ID;
if (empty($_['viteDev'])){
    Util::addScript($app_id, $app_id . '-main');
} else {
    Util::addHeader("script", ['nonce' => $_["cspNonce"], "type" => "module",
            "src" => $_['viteUrl'] . "/@vite/client"], "");
    Util::addHeader("script", ['nonce' => $_["cspNonce"], "type" => "module",
            "src" => $_['viteUrl'] . "/src/main.ts"], "");
}
Util::addStyle($app_id, $app_id . '-main');

?>

<div id="structureddiary"></div>
