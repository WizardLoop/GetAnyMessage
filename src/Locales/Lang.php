<?php
/* 
the project created by @wizardloop                                                                                                                                                                                                                                                     
*/
namespace GetAnyMessage\Locales;

use Amp\File;
use function Amp\File\write;
use function Amp\File\read;
use function Amp\File\exists;
use function Amp\File\mkdir;

class Lang
{
    private object $context;
    public function __construct(object $context) {
        $this->context = $context;
    }

    public function getUserLang(int $senderId): string {
        try {
            $path = __DIR__ . "/../data/$senderId/lang.txt";

    if (file_exists($path)) {
                return trim(read($path));
            } else {
                return 'en';
            }
        } catch (\Throwable $e) {
return 'en'; 
        }
    }
	
    function loadTranslations(string $lang): array {
    $path = __DIR__ . "/$lang.json";
    if (!file_exists($path)) {
        $path = __DIR__ . "/en.json"; 
    }
    return json_decode(read($path), true);
}

    function getAvailableLanguages(): array {
    $files = glob(__DIR__ . "/*.json");
    $langs = [];
    foreach ($files as $file) {
        $code = basename($file, '.json');
        $translations = json_decode(file_get_contents($file), true);
        $langs[] = [
            'code' => $code,
            'name' => $translations['language_name'] ?? strtoupper($code),
            'emoji' => $translations['language_flag'] ?? '🏳️'
        ];
    }
    return $langs;
}

    function getLanguageButtons(): array {
    $settings_button_back_text = '🔙';
    $settings_button_back_data = 'backStart';

    $langs = $this->getAvailableLanguages(); 

    $bot_API_markup = [];

    foreach ($langs as $lang) {
        $bot_API_markup[] = [
            'text' => $lang['emoji'] . ' ' . $lang['name'], 
            'callback_data' => "setlang:" . $lang['code']
        ];
    }

    $keyboard = [];
    $row = [];

    foreach ($bot_API_markup as $button) {
        $row[] = $button;
        if (count($row) == 2) {
            $keyboard[] = $row;
            $row = [];
        }
    }

    if (count($row) > 0) {
        $keyboard[] = $row;
    }

    $keyboard[] = [
        ['text' => $settings_button_back_text, 'callback_data' => $settings_button_back_data]
    ];

    return [
        'inline_keyboard' => $keyboard
    ];
}

    public function setUserLang(int $senderId, string $lang): bool {
    try {
        $dir = __DIR__ . "/../data/$senderId";
        $path = $dir . "/lang.txt";

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        return file_put_contents($path, $lang) !== false;
    } catch (\Throwable $e) {
        return false;
    }
}

}
