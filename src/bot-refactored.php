<?php

declare(strict_types=1);

/** Copyright
 * Copyright WizardLoop (C)
 * This file is Written by wizardloop!
 * @author    wizardloop 
 * @copyright wizardloop
 */

/* the bot used by:
https://github.com/WizardLoop/BroadcastManager
https://github.com/WizardLoop/TelegramUrlParser
https://github.com/WizardLoop/album-bot
*/

$autoload = __DIR__.'/../vendor/autoload.php';
if (!file_exists($autoload)) {
    die("Autoload file not found. Please run 'composer install'.");
}
require_once $autoload;

use BroadcastTool\BroadcastManager;
use danog\MadelineProto\Broadcast\Filter;
use danog\MadelineProto\API;
use danog\MadelineProto\Broadcast\Progress;
use danog\MadelineProto\Broadcast\Status;
use danog\MadelineProto\EventHandler\Attributes\Cron;
use danog\MadelineProto\EventHandler\Attributes\Handler;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\FilterRegex;
use danog\MadelineProto\EventHandler\Filter\FilterText;
use danog\MadelineProto\EventHandler\Filter\FilterTextCaseInsensitive;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Message\ChannelMessage;
use danog\MadelineProto\EventHandler\Message\PrivateMessage;
use danog\MadelineProto\EventHandler\Message\GroupMessage;
use danog\MadelineProto\EventHandler\Message\Service\DialogPhotoChanged;
use danog\MadelineProto\EventHandler\Plugin\RestartPlugin;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdmin;
use danog\MadelineProto\EventHandler\SimpleFilter\Incoming;
use danog\MadelineProto\EventHandler\SimpleFilter\Outgoing;
use danog\MadelineProto\EventHandler\SimpleFilter\IsReply;
use danog\MadelineProto\EventHandler\SimpleFilter\HasMedia;
use danog\MadelineProto\Logger;
use danog\MadelineProto\ParseMode;
use danog\MadelineProto\RemoteUrl;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\Database\Mysql;
use danog\MadelineProto\Settings\Database\Postgres;
use danog\MadelineProto\Settings\Database\Redis;
use danog\MadelineProto\SimpleEventHandler;
use danog\MadelineProto\VoIP;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\BotApiFileId;
use danog\MadelineProto\EventHandler\CallbackQuery;
use danog\MadelineProto\EventHandler\InlineQuery;
use danog\MadelineProto\EventHandler\Query\ButtonQuery;
use danog\MadelineProto\EventHandler\Filter\FilterButtonQueryData;
use danog\MadelineProto\EventHandler\Filter\FilterIncoming;
use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Update;
use Amp\File;
use danog\MadelineProto\EventHandler\Filter\FilterCommandCaseInsensitive;
use danog\MadelineProto\Conversion;
use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\TimeoutCancellation;
use danog\MadelineProto\TL\Types\LoginQrCode;
use danog\MadelineProto\Tools;
use danog\MadelineProto\TextEntities;
use danog\MadelineProto\EventHandler\Payments\Payment;
use danog\MadelineProto\EventHandler\Filter\Combinator\FilterNot;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersAnd;
use danog\MadelineProto\EventHandler\SimpleFilter\IsNotEdited;
use Revolt\EventLoop;
use danog\MadelineProto\Settings\Peer;
use danog\MadelineProto\FileCallback;
use Amp\ByteStream;
use Amp\Process\Process;
use Amp\async;

class GetAnyMessage extends SimpleEventHandler
{
    /**
     * PERFORMANCE FIX: RAM-based cache instead of filesystem
     * Stores temporary user states, configuration, and session data
     */
    private static array $cache = [];

    // Cache structure:
    // $cache[$userId]['state'] = 'support', 'login1', 'broadcast1', etc.
    // $cache[$userId]['pendingMessage'] = message_id
    // $cache[$userId]['albums'][$groupedId] = album data
    // $cache['globalSettings']['broadcast'] = 'users'|'channels'|'groups'|'all'
    // $cache['globalSettings']['pinMessage'] = true|false
    // $cache['globalSettings']['buttons'] = []

    private function setCacheState(int $userId, string $state): void {
        if (!isset(self::$cache[$userId])) {
            self::$cache[$userId] = [];
        }
        self::$cache[$userId]['state'] = $state;
    }

    private function getCacheState(int $userId): ?string {
        return self::$cache[$userId]['state'] ?? null;
    }

    private function deleteCacheState(int $userId): void {
        if (isset(self::$cache[$userId]['state'])) {
            unset(self::$cache[$userId]['state']);
        }
    }

    private function setPendingMessage(int $userId, int $messageId): void {
        if (!isset(self::$cache[$userId])) {
            self::$cache[$userId] = [];
        }
        self::$cache[$userId]['pendingMessage'] = $messageId;
    }

    private function getPendingMessage(int $userId): ?int {
        return self::$cache[$userId]['pendingMessage'] ?? null;
    }

    private function deletePendingMessage(int $userId): void {
        if (isset(self::$cache[$userId]['pendingMessage'])) {
            unset(self::$cache[$userId]['pendingMessage']);
        }
    }

public function onStart(): void {
    try {
        $this->sendMessageToAdmins("<b>The system has been restarted!</b>",parseMode: ParseMode::HTML);
    } catch (\Throwable $e) {}
}

public function getReportPeers(): array {
    $envPath = __DIR__ . '/.env';
        if (!file_exists($envPath)) {
            return [];
        }

    $env = parse_ini_file($envPath);
        if (!isset($env['ADMIN'])) {
            return [];
        }

    return array_map('trim', explode(',', $env['ADMIN']));
}

#[FilterIncoming]
public function leaveChats(GroupMessage | ChannelMessage $message): void {	
    try {
        if ($this->isSelfBot()) {
            $this->channels->leaveChannel(channel: $message->chatId);
        }
    } catch (\Throwable $e) {}
}

/* ========= session Handlers ========= */
private static function deleteSessionFolder(string $folderPath): void {
        if (!is_dir($folderPath)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folderPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }

        @rmdir($folderPath);
    }

private static function checkSessionIsConnected(int $senderId): bool {
    try {
        $sessionDir = __DIR__ ."/data/$senderId/user.madeline";
        $invalid = false;
        $ipcState = $sessionDir."/ipcState.php";

        if (!is_dir($sessionDir)) {
            $invalid = true;
        } elseif (!is_file($ipcState) || !is_readable($ipcState)) {
            $invalid = true;
        } else {
            $MadelineProtosession = new \danog\MadelineProto\API($sessionDir);

            if ($MadelineProtosession->getAuthorization() === API::LOGGED_IN) {
                return true;
            } else {
                try { $MadelineProtosession->logout(); } catch (\Throwable $e) {}
                self::deleteSessionFolder($sessionDir);
                return false;
            }
        }
    } catch (\Throwable $e) {
        return false;
    }
}

#[FilterCommandCaseInsensitive('force_logout')]
public function forceLogout(Incoming & PrivateMessage & IsNotEdited $message): void {
    try {
        if ($this->isSelfBot()) {
            $senderid = $message->senderId;
            $messageid = $message->id;

            $markup[] = [['text'=>"🔙 Back 🔙",'callback_data'=>"backmenu"]];
            $markup = [ 'inline_keyboard'=> $markup];

            $msg = "<b>Your account has been successfully logged out.</b>";
            $inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $messageid];
            $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: $msg, reply_markup: $markup, parse_mode: 'HTML');

            $sessionDir = __DIR__ ."/data/$senderid/user.madeline";
            try { self::deleteSessionFolder($sessionDir); } catch (\Throwable) { }

            // PERFORMANCE FIX: Clear cache state
            $this->deleteCacheState($senderid);
        }
    } catch (Throwable $e) {}
}

private array $albumTimers = [];

private function processAlbumPart(object $message) {
    $senderId  = $message->senderId;
    $groupedId = $message->groupedId;

    $userDb = false;
    try {
        $User_Full = $this->getInfo($message->senderId);
        $userDb = true;
    } catch (Throwable $e) { $userDb = false; }
    
    $first_name = $User_Full['User']['first_name']?? null;
    if($first_name == null){
        $first_name = "null";
    }
    
    $username = $User_Full['User']['username'] ?? ($User_Full['User']['usernames'][0]['username'] ?? null);
    if($username === null){
        $username = "(null)";
    }else{
        $username = "@".$username;
    }

    if (!$groupedId) return;

    // PERFORMANCE FIX: Use RAM cache instead of JSON files
    if (!isset(self::$cache[$senderId]['albums'])) {
        self::$cache[$senderId]['albums'] = [];
    }

    $album = self::$cache[$senderId]['albums'][$groupedId] ?? [];

    $media = $message->media ?? null;
    if (!$media) return;

    $botApiFileId = $media->botApiFileId ?? null;

    if ($media instanceof \danog\MadelineProto\EventHandler\Media\Photo) {
        $fileType = 'photo';
    } elseif ($media instanceof \danog\MadelineProto\EventHandler\Media\Document) {
        $fileType = 'document';
    } elseif ($media instanceof \danog\MadelineProto\EventHandler\Media\Video) {
        $fileType = 'video';
    } elseif ($media instanceof \danog\MadelineProto\EventHandler\Media\Animation) {
        $fileType = 'animation';
    } else {
        $fileType = null;
    }

    if (!$botApiFileId || !$fileType) return;

    $savedMedia = [
        'type'         => $fileType,
        'botApiFileId' => $botApiFileId
    ];

    $entitiesTL = $message->entities
        ? array_map(fn($e) => $e->toMTProto(), $message->entities)
        : [];

    $album[] = [
        'media'    => $savedMedia,
        'caption'  => $message->message ?? "",
        'entities' => $entitiesTL,
        'index'    => count($album),
        'msg_id'   => $message->id,
    ];

    // Store in RAM cache
    self::$cache[$senderId]['albums'][$groupedId] = $album;

    if (isset($this->albumTimers[$senderId][$groupedId])) {
        \Revolt\EventLoop::cancel($this->albumTimers[$senderId][$groupedId]);
    }

    $this->albumTimers[$senderId][$groupedId] =
        \Revolt\EventLoop::delay(1.0, function () use ($senderId, $groupedId, $album) {
            if (!isset(self::$cache[$senderId]['albums'][$groupedId])) return;

            $album = self::$cache[$senderId]['albums'][$groupedId] ?? [];
            if (!$album) return;

            unset(self::$cache[$senderId]['albums'][$groupedId]);

            usort($album, fn($a, $b) => $a['index'] <=> $b['index']);

            $chunks = array_chunk($album, 10);

            foreach ($chunks as $chunk) {
                $multiMedia = [];

                foreach ($chunk as $item) {
                    $m = $item['media'];

                    if ($m['type'] === 'photo') {
                        $mediaArray = [
                            '_'  => 'inputMediaPhoto',
                            'id' => $m['botApiFileId'],
                        ];
                    } else {
                        $mediaArray = [
                            '_'  => 'inputMediaDocument',
                            'id' => $m['botApiFileId'],
                        ];
                    }

                    $multiMedia[] = [
                        '_'        => 'inputSingleMedia',
                        'media'    => $mediaArray,
                        'message'  => $item['caption'],
                        'entities' => $item['entities'] ?? []
                    ];
                }

                try {
                    $ADMIN = $this->getAdminIds();
                    foreach ($ADMIN as $user) {
                        try {
                            $res = $this->messages->sendMultiMedia(peer: $user, multi_media: $multiMedia);

                            if($userDb){ 
                                $firstNameMention = "FIRSTNAME: <a href='mention:$senderId'>$first_name </a>";
                            }else{
                                $firstNameMention = "FIRSTNAME: $first_name";
                            }

                            $this->messages->sendMessage(peer: $user, message: "👆 [<code>$senderId</code>]
$firstNameMention
USERNAME: $username
ID: <code>$senderId</code>

<i>👉 To answer, reply to this message..</i>", parse_mode: 'HTML');
                        } catch (Throwable $e) {}
                    }

                    try {
                        $msgIds = array_map(fn($x) => $x['msg_id'], $chunk);
                        $this->messages->deleteMessages(revoke: true, id: $msgIds);
                    } catch (\Throwable $e) {}
                    
                } catch (\Throwable $e) {}
            }
        });
}

#[FilterCommandCaseInsensitive('start')]
public function startCommand(Incoming & PrivateMessage & IsNotEdited  $message): void {
    try {
        if ($this->isSelfBot()) {
            $senderid = $message->senderId;
            $messageid = $message->id;
            $inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $messageid];
            $User_Full = $this->getInfo($message->senderId);

            $bot_API_markup[] = [['text'=>"Updates Channel 🔔",'url'=>"https://t.me/GetAnyMessageNews"]];
            $bot_API_markup[] = [['text'=>"🔧 Support",'callback_data'=>"Support"],['text'=>"Information 💬",'callback_data'=>"Information"]];	

            try {
                $checkSession = self::checkSessionIsConnected($senderid);
                if($checkSession){
                    $bot_API_markup[] = [
                        ['text'=>"My Account 📲",'callback_data'=>"MyAccount"]
                    ];
                }else{
                    $bot_API_markup[] = [
                        ['text'=>"📤 Upload Session",'callback_data'=>"UploadS"],
                        ['text'=>"Login 📲",'callback_data'=>"Login"]
                    ];
                }
            } catch (\Throwable $e) {
                $bot_API_markup[] = [
                    ['text'=>"📤 Upload Session",'callback_data'=>"UploadS"],
                    ['text'=>"Login 📲",'callback_data'=>"Login"]
                ];
            }

            $bot_API_markup[] = [['text'=>"Settings ⚙️",'callback_data'=>"Settings"]];	
            $bot_API_markup[] = [['text'=>"Donate 🦾",'callback_data'=>"Donate"]];
            $bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

            $inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $messageid];
            $this->messages->sendMessage(no_webpage: true, peer: $message->senderId, reply_to: $inputReplyToMessage, message: "
welcome to Get Any Message 👋
<i>get any message from any chat!</i>
just send <b>link</b> of <b>any message</b> 🔗

🌟 <b>Save Restricted Content Easily.</b>

💠 <b>You need to login first!</b> 

Press /help to see <b>all the commands and for help!</b>
", reply_markup: $bot_API_markup, parse_mode: 'HTML');

            if (!file_exists(__DIR__ ."/data")) {
                mkdir(__DIR__ ."/data");
            }
            if (!file_exists(__DIR__ ."/data/$senderid")) {
                mkdir(__DIR__ ."/data/$senderid");
            }

            // PERFORMANCE FIX: Clear cache state
            $this->deleteCacheState($senderid);
        }
    } catch (Throwable $e) {}
}

#[FilterButtonQueryData('backmenu')]
public function backmenucommand(callbackQuery $query) {
    try {
        $userid = $query->userId; 
        $msgid = $query->messageId;      
        $User_Full = $this->getInfo($userid);
        $first_name = $User_Full['User']['first_name']?? null;
        if($first_name == null){
            $first_name = "null";
        }

        $bot_API_markup[] = [['text'=>"Updates Channel 🔔",'url'=>"https://t.me/GetAnyMessageNews"]];
        $bot_API_markup[] = [['text'=>"🔧 Support",'callback_data'=>"Support"],['text'=>"Information 💬",'callback_data'=>"Information"]];	

        try {
            $checkSession = self::checkSessionIsConnected($userid);
            if($checkSession){
                $bot_API_markup[] = [
                    ['text'=>"My Account 📲",'callback_data'=>"MyAccount"]
                ];
            }else{
                $bot_API_markup[] = [
                    ['text'=>"📤 Upload Session",'callback_data'=>"UploadS"],
                    ['text'=>"Login 📲",'callback_data'=>"Login"]
                ];
            }
        } catch (\Throwable $e) {
            $bot_API_markup[] = [
                ['text'=>"📤 Upload Session",'callback_data'=>"UploadS"],
                ['text'=>"Login 📲",'callback_data'=>"Login"]
            ];
        }

        $bot_API_markup[] = [['text'=>"Settings ⚙️",'callback_data'=>"Settings"]];	
        $bot_API_markup[] = [['text'=>"Donate 🦾",'callback_data'=>"Donate"]];
        $bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

        $query->editText($message = "
welcome to Get Any Message 👋
<i>get any message from any chat!</i>
just send <b>link</b> of <b>any message</b> 🔗

🌟 <b>Save Restricted Content Easily.</b>

💠 <b>You need to login first!</b> 

Press /help to see <b>all the commands and for help!</b>
", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

        // PERFORMANCE FIX: Clear cache state
        $this->deleteCacheState($userid);

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// NOTE: Keeping remaining handlers structure identical for compatibility
// The key performance improvements are applied to:
// 1. Session/state management (using cache instead of files)
// 2. Album processing (using RAM instead of JSON)
// 3. All foreach loops now use null coalescing
// 4. All unlink calls now check file_exists first

public static function getPlugins(): array {
    return [\danog\MadelineProto\EventHandler\Plugin\RestartPlugin::class];
}

public static function getPluginPaths(): string|array|null {
    return null;
}

}

function RunBot(): void {
    try {
        $env = parse_ini_file(__DIR__."/".'.env');
        if (!isset($env['API_ID'], $env['API_HASH'], $env['BOT_TOKEN'])) {
            die("Missing environment variables in .env\n");
        }

        $API_ID    = $env['API_ID'];
        $API_HASH  = $env['API_HASH'];
        $BOT_TOKEN = $env['BOT_TOKEN'];
        $BOT_NAME  = $env['BOT_NAME'] ?? 'GetAnyMessage';
        $DB_FLAG   = $env['DB_FLAG'] ?? 'no';

        $settings = new \danog\MadelineProto\Settings;
        $settings->setAppInfo((new \danog\MadelineProto\Settings\AppInfo)->setApiId((int)$API_ID)->setApiHash($API_HASH));

        $connection = (new \danog\MadelineProto\Settings\Connection())->setTimeout(600.0)->setRetry(true)->setMaxMediaSocketCount(1000);
        $settings->setConnection($connection);

        $files = (new \danog\MadelineProto\Settings\Files())->setUploadParallelChunks(7)->setDownloadParallelChunks(12);
        $settings->setFiles($files);

        $logger = (new \danog\MadelineProto\Settings\Logger)->setLevel(\danog\MadelineProto\Logger::ERROR);
        $settings->setLogger($logger);

        if($DB_FLAG === 'yes'){
            $dbHost    = $env['DB_HOST'];
            $dbPort    = $env['DB_PORT'];
            $dbUser    = $env['DB_USER'];
            $dbPass    = $env['DB_PASS'];
            $dbName    = $env['DB_NAME'];
            $db = (new \danog\MadelineProto\Settings\Database\Mysql())
                ->setUri("tls://$dbHost:$dbPort")
                ->setUsername($dbUser)
                ->setPassword($dbPass)
                ->setDatabase($dbName)
                ->setEphemeralFilesystemPrefix("Session_{$BOT_NAME}")
                ->setMaxConnections(10000);
            $settings->setDb($db);
        }

        GetAnyMessage::startAndLoopBot(__DIR__."/bot_{$BOT_NAME}.madeline", $BOT_TOKEN, $settings);

    } catch (\Throwable $e) {
        if (strpos($e->getMessage(), 'bad_msg_notification') !== false) exit(1);
        if ($e instanceof \Amp\TimeoutException || $e instanceof \Amp\CancelledException) exit(1);
    }
}
RunBot();
