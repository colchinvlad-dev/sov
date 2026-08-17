<?php
// Универсальный обработчик: если есть Bitrix — используем его, если нет — демо-режим

// Проверяем наличие Bitrix
$bitrixLoaded = false;
if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php")) {
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
    if (CModule::IncludeModule("iblock")) {
        $bitrixLoaded = true;
    }
}

// Только POST запросы
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Метод не разрешён"]);
    exit();
}

// Получаем и валидируем данные
$elementId = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
$action = isset($_POST["action"]) ? trim($_POST["action"]) : "";

if ($elementId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Неверный ID элемента"]);
    exit();
}

if (!in_array($action, ["like", "unlike"])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Недопустимое действие"]);
    exit();
}

// --- Защита от накрутки через сессию ---
session_start();
$sessionKey = "liked_elements_" . $elementId;
$isLiked = isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey] === true;

if ($action === "like" && $isLiked) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Вы уже лайкнули"]);
    exit();
}
if ($action === "unlike" && !$isLiked) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Вы не лайкали"]);
    exit();
}

// === РАБОТА С ДАННЫМИ ===
if ($bitrixLoaded) {
    // --- Режим Bitrix ---
    $propertyCode = "LIKES_COUNT";
    $iblockId = 1;
    
    // Получаем текущее значение
    $currentLikes = 0;
    $res = \CIBlockElement::GetProperty($iblockId, $elementId, [], ["CODE" => $propertyCode]);
    if ($prop = $res->Fetch()) {
        $currentLikes = (int)$prop["VALUE"];
    }
    
    // Обновляем
    if ($action === "like") {
        $newLikes = $currentLikes + 1;
        $_SESSION[$sessionKey] = true;
    } else {
        $newLikes = max(0, $currentLikes - 1);
        unset($_SESSION[$sessionKey]);
    }
    
    \CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, [$propertyCode => $newLikes]);
    
} else {
    // --- Демо-режим (храним в JSON) ---
    $storageFile = __DIR__ . "/likes_data.json";
    
    if (!file_exists($storageFile)) {
        file_put_contents($storageFile, json_encode([]));
    }
    
    $data = json_decode(file_get_contents($storageFile), true);
    if (!is_array($data)) {
        $data = [];
    }
    
    $currentLikes = isset($data[$elementId]) ? (int)$data[$elementId] : 0;
    
    if ($action === "like") {
        $newLikes = $currentLikes + 1;
        $_SESSION[$sessionKey] = true;
    } else {
        $newLikes = max(0, $currentLikes - 1);
        unset($_SESSION[$sessionKey]);
    }
    
    $data[$elementId] = $newLikes;
    file_put_contents($storageFile, json_encode($data));
}

// Ответ
header('Content-Type: application/json');
echo json_encode([
    "success" => true,
    "message" => $action === "like" ? "Лайк поставлен!" : "Лайк убран!",
    "new_count" => $newLikes,
    "is_liked" => ($action === "like")
]);

exit();