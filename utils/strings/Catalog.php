<?php

# Сообщения

define("MSG_", "");
define("MSG_DELIMITER", "-------------------------------");
define("MSG_FILE_INIT", "<b>Обработка файла</b>: %s, обнаружено записей: %s");
define("MSG_FILE_DELETED", "<b>Файл %s удален</b>");
define("MSG_WARN_FILE_NOT_DELETED", "<b>Не удалось удалить файл %s</b>");
define("MSG_IMPORT_STATS", "Обработано %s разделов и %s товаров. Дубликатов - %s");
define("MSG_IMPORT_COMPLETE", "Импорт завершен");

define("MSG_EMPTY_CAT", "У товара не заполнено UID_GROUP (UID = %s)");
define("MSG_PRODUCT_CREATED", "Товар создан (ID = %s)");
define("MSG_PRODUCT_UPDATED", "Товар обновлен (ID = %s)");
define("MSG_CATEGORY_CREATED", "Категория создана (ID = %s)");
define("MSG_CATEGORY_UPDATED", "Категория обновлена (ID = %s)");
define("MSG_PRODUCT_REMOVED", "Товар удален (ID = %s)");
define("MSG_CATEGORY_REMOVED", "Категория удалена (ID = %s)");
define("MSG_WARN_CATEGORY_NOT_FOUND", "Категория не найдена в каталоге (UID = %s, UID_GROUP = %s)");

# Ошибки

define("ERROR_UNKNOWN", "<b>Неизвестная ошибка</b>: %s");
define("ERROR_FTP_CONNECT", "Не удалось установить соединение по FTP");
define("ERROR_FTP_AUTH", "Не удалось авторизоваться с указанными login/password");

define("MSG_WARN_NO_FILES", "Файлы UT_SITE_1_*.json в директории не обнаружены.");