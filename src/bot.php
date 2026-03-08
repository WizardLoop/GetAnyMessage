<?php

declare(strict_types=1);

/**
 * Copyright WizardLoop (C)
 * This file is Written by wizardloop!
 * @author    wizardloop 
 * @copyright wizardloop
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

public function onStart(): void {
try {
$this->sendMessageToAdmins("<b>The system has been restarted!</b>",parseMode: ParseMode::HTML);
} catch (\Throwable $e) {}
}

/* ========= SET BOT ADMINS ========= */
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

/* ========= LEAVE CHATS ========= */
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

        $content = \amp\File\read($ipcState);

        if (
            str_contains($content, 'ExitFailure') &&
            time() - filemtime($ipcState) > 20
        ) {
            $invalid = true;
        }
    }

    if ($invalid) {
            self::deleteSessionFolder($sessionDir);
			return false;
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

/* ========= album Handlers ========= */
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

    $dir = __DIR__ . "/data/$senderId";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $albumFile = "$dir/album_$groupedId.json";
    $timerFile = "$dir/album_timer_$groupedId";

    $album = file_exists($albumFile)
        ? json_decode(\Amp\File\read($albumFile), true)
        : [];

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

    \Amp\File\write($albumFile, json_encode($album, JSON_PRETTY_PRINT));
    \Amp\File\write($timerFile, (string) time());

    if (isset($this->albumTimers[$senderId][$groupedId])) {
        \Revolt\EventLoop::cancel($this->albumTimers[$senderId][$groupedId]);
    }

    $this->albumTimers[$senderId][$groupedId] =
        \Revolt\EventLoop::delay(1.0, function () use ($senderId, $groupedId, $albumFile, $timerFile) {

        if (!file_exists($timerFile)) return;

        if (time() - (int)\Amp\File\read($timerFile) < 1) return;

        $album = json_decode(\Amp\File\read($albumFile), true) ?? [];
        if (!$album) return;

        @unlink($albumFile);
        @unlink($timerFile);
        unset($this->albumTimers[$senderId][$groupedId]);

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

/* ========= start Handlers ========= */
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

$sessionDir = __DIR__ ."/data/$senderid/user.madeline";
try {

    $invalid = false;
    $ipcState = __DIR__ ."/$sessionDir/ipcState.php";

    if (!is_dir($sessionDir)) {
        $invalid = true;
    } elseif (!is_file($ipcState) || !is_readable($ipcState)) {
        $invalid = true;
    } else {

        $content = \amp\File\read($ipcState);

        if (
            str_contains($content, 'ExitFailure') &&
            time() - filemtime($ipcState) > 20
        ) {
            $invalid = true;
        }
    }

    if ($invalid) {
        $bot_API_markup[] = [
            ['text'=>"📤 Upload Session",'callback_data'=>"UploadS"],
            ['text'=>"Login 📲",'callback_data'=>"Login"]
        ];
            self::deleteSessionFolder($sessionDir);
    } else {

        $MadelineProtosession = new \danog\MadelineProto\API($sessionDir);

        if ($MadelineProtosession->getAuthorization() === API::LOGGED_IN) {
            $bot_API_markup[] = [
                ['text'=>"My Account 📲",'callback_data'=>"MyAccount"]
            ];
        } else {
            try { $MadelineProtosession->logout(); } catch (\Throwable $e) {}
            self::deleteSessionFolder($sessionDir);
            $bot_API_markup[] = [
                ['text'=>"📤 Upload Session",'callback_data'=>"UploadS"],
                ['text'=>"Login 📲",'callback_data'=>"Login"]
            ];
        }
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
    if (file_exists(__DIR__ ."/data/$senderid/grs1.txt")) {
unlink(__DIR__ ."/data/$senderid/grs1.txt");
}
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

$sessionDir = __DIR__ ."/data/$userid/user.madeline";
try {

    $invalid = false;
    $ipcState = __DIR__ ."/$sessionDir/ipcState.php";

    if (!is_dir($sessionDir)) {
        $invalid = true;
    } elseif (!is_file($ipcState) || !is_readable($ipcState)) {
        $invalid = true;
    } else {

        $content = \amp\File\read($ipcState);

        if (
            str_contains($content, 'ExitFailure') &&
            time() - filemtime($ipcState) > 20
        ) {
            $invalid = true;
        }
    }

    if ($invalid) {
        $bot_API_markup[] = [
            ['text'=>"📤 Upload Session",'callback_data'=>"UploadS"],
            ['text'=>"Login 📲",'callback_data'=>"Login"]
        ];
            self::deleteSessionFolder($sessionDir);
    } else {

        $MadelineProtosession = new \danog\MadelineProto\API($sessionDir);

        if ($MadelineProtosession->getAuthorization() === API::LOGGED_IN) {
            $bot_API_markup[] = [
                ['text'=>"My Account 📲",'callback_data'=>"MyAccount"]
            ];
        } else {
            try { $MadelineProtosession->logout(); } catch (\Throwable $e) {}
            self::deleteSessionFolder($sessionDir);
            $bot_API_markup[] = [
                ['text'=>"📤 Upload Session",'callback_data'=>"UploadS"],
                ['text'=>"Login 📲",'callback_data'=>"Login"]
            ];
        }
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

    if (file_exists(__DIR__ ."/data/$userid/grs1.txt")) {
unlink(__DIR__ ."/data/$userid/grs1.txt");
}
} catch (Throwable $e) {
$error = $e->getMessage();
}
}

/* ========= info Handlers ========= */
#[FilterButtonQueryData('Information')]
public function infomenucommand(callbackQuery $query) {
      try {
$userid = $query->userId;    
$msgid = $query->messageId;  
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"🔧 Bot Support",'callback_data'=>"Support"]];
$bot_API_markup[] = [['text'=>"📖 Bot Commands",'callback_data'=>"help"]];
$bot_API_markup[] = [['text'=>"🔙 Back",'callback_data'=>"backmenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

			try {
$query->editText($message = "<b>GetAnyMessage</b> developed by @WizardLoop with PHP.

• Thanks to all our <b>donors</b> for supporting server and development expenses and all those who have reported bugs or suggested new features.
We welcome <b>suggestions</b> for new features and <b>bug reports.</b>
We thank all the users that rely on us for this service, we are constantly working to improve it!", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);
} catch (Throwable $e) {}

} catch (Throwable $e) {}
}
#[FilterCommandCaseInsensitive('info')]
public function infoCommand(Incoming & PrivateMessage  $message): void {
		try {
if ($this->isSelfBot()) {
$senderid = $message->senderId;
$messageid = $message->id;

$bot_API_markup[] = [['text'=>"🔧 Bot Support",'callback_data'=>"Support"]];
$bot_API_markup[] = [['text'=>"📖 Bot Commands",'callback_data'=>"help"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $messageid];
$this->messages->sendMessage(no_webpage: true, peer: $message->senderId, reply_to: $inputReplyToMessage, message: "
<b>GetAnyMessage</b> developed by @WizardLoop with PHP.

• Thanks to all our <b>donors</b> for supporting server and development expenses and all those who have reported bugs or suggested new features.
We welcome <b>suggestions</b> for new features and <b>bug reports.</b>
We thank all the users that rely on us for this service, we are constantly working to improve it!
", reply_markup: $bot_API_markup, parse_mode: 'HTML') ;

}
} catch (Throwable $e) {}
}

/* ========= help Handlers ========= */
#[FilterCommandCaseInsensitive('help')]
public function helpCommand(Incoming & PrivateMessage  $message): void {
				try {
if ($this->isSelfBot()) {
$senderid = $message->senderId;
$messageid = $message->id;

$bot_API_markup[] = [['text'=>"💬 How to get a message?",'callback_data'=>"HowGetMessage"]];
$bot_API_markup[] = [['text'=>"🔗 links format",'callback_data'=>"LinksFormat"]];
$bot_API_markup[] = [['text'=>"📲 Login",'callback_data'=>"LoginInfo"]];
$bot_API_markup[] = [['text'=>"⚙️ Settings",'callback_data'=>"soon"]];
$bot_API_markup[] = [['text'=>"📖 All Bot Commands ",'callback_data'=>"BotCommands"]];
$bot_API_markup[] = [['text'=>"📃 FAQ ❓",'callback_data'=>"FAQ"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $messageid];
$this->messages->sendMessage(no_webpage: true, peer: $message->senderId, reply_to: $inputReplyToMessage, message: "
<b>Welcome to the help menu!</b> 🆘
", reply_markup: $bot_API_markup, parse_mode: 'HTML') ;

}
} catch (Throwable $e) { }
}
#[FilterButtonQueryData('help')]
public function helpCommand2(callbackQuery $query) {
	   try {
$userid = $query->userId;    
$msgid = $query->messageId;  
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"💬 How to get a message?",'callback_data'=>"HowGetMessage"]];
$bot_API_markup[] = [['text'=>"🔗 links format",'callback_data'=>"LinksFormat"]];
$bot_API_markup[] = [['text'=>"📲 Login",'callback_data'=>"LoginInfo"]];
$bot_API_markup[] = [['text'=>"⚙️ Settings",'callback_data'=>"soon"]];
$bot_API_markup[] = [['text'=>"📖 All Bot Commands",'callback_data'=>"BotCommands"]];
$bot_API_markup[] = [['text'=>"📃 FAQ ❓",'callback_data'=>"FAQ"]];
$bot_API_markup[] = [['text'=>"🔙 Back",'callback_data'=>"Information"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "
<b>Welcome to the help menu!</b> 🆘
", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) { }
}
#[FilterButtonQueryData('BotCommands')]
public function BotCommands(callbackQuery $query) {
	try {
$userid = $query->userId;   
$msgid = $query->messageId;   
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"🔙 Back to Help",'callback_data'=>"help"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "
<b>All Bot Commands 📖</b> 

/start - Main Menu

/help - Help Menu

/info - Information

/donate - Donate for the bot

All commands are case-insensitive.
All commands can start with: /!.
Example: <code>/start</code>, <code>!start</code>, <code>.start</code>
", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) { }
}
#[FilterButtonQueryData('LoginInfo')]
public function LoginInfo(callbackQuery $query) {
	try {
$userid = $query->userId;   
$msgid = $query->messageId;   
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"🔙 Back to Help",'callback_data'=>"help"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "
📲 <b>How Login to the account?</b> 

<b>Send the phone number to the bot.</b> 
<i>number in international format.</i>
<blockquote>Ex: <code>+12024561414</code></blockquote>

<b>Send the code you received</b> 
<i>Do not send the code directly from the account you are logging in from, and if you do, add spaces or - between each digit.</i>
<blockquote>Ex: <code>1 2 3 4 5</code> or <code>1-2-3-4-5</code></blockquote>

<b>Send the two-step password.</b> 
<i>If no two-step password is set, you can write anything.</i>

<b>And that's it, you have successfully connected!</b> 
", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) { }
}
#[FilterButtonQueryData('FAQ')]
public function FAQcommand(callbackQuery $query) {
	try {
$userid = $query->userId;   
$msgid = $query->messageId;   
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"🔙 Back to Help",'callback_data'=>"help"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "
<b>Get Any Message FAQ</b> 📃

<b>If you get the error:</b>
PHONE_CODE_EXPIRED
<blockquote>
add spaces or - between each digit.
Ex: <code>1 2 3 4 5</code> or <code>1-2-3-4-5</code>
</blockquote>

<b>If you get the error:</b>
❌ This peer is not present in the internal peer database
<blockquote>
You must interact for the chat to be saved in your account database.
open the chat profile/Play media in the chat/React with an emoji to one of the messages, etc..
or reconnect with your account.
</blockquote>

<b>For help, join to support group.</b>
", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) { }
}
#[FilterButtonQueryData('LinksFormat')]
public function LinksFormat(callbackQuery $query) {
	try {
$userid = $query->userId;   
$msgid = $query->messageId;   
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"💬 Comment",'callback_data'=>"infof1"],['text'=>"Channel 📣",'callback_data'=>"infof2"]];
$bot_API_markup[] = [['text'=>"🗂 Topics",'callback_data'=>"infof4"],['text'=>"Group 👥",'callback_data'=>"infof3"]];
$bot_API_markup[] = [['text'=>"🤖 Bot",'callback_data'=>"infof5"],['text'=>"👤 User",'callback_data'=>"infof6"]];
$bot_API_markup[] = [['text'=>"🔙 Back to Help",'callback_data'=>"help"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "
🔗 <b>select a chat type:</b> 
", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) { }
}
#[FilterButtonQueryData('infof1')]
public function infof1(callbackQuery $query) {
	try {
$userid = $query->userId;   
$msgid = $query->messageId;   
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"💬 Comment",'callback_data'=>"infof1"],['text'=>"Channel 📣",'callback_data'=>"infof2"]];
$bot_API_markup[] = [['text'=>"🗂 Topics",'callback_data'=>"infof4"],['text'=>"Group 👥",'callback_data'=>"infof3"]];
$bot_API_markup[] = [['text'=>"🤖 Bot",'callback_data'=>"infof5"],['text'=>"👤 User",'callback_data'=>"infof6"]];
$bot_API_markup[] = [['text'=>"🔙 Back to Help",'callback_data'=>"help"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "
🔗 <b>Link to channel comment</b> 

<b>public:</b> 
<code>https://t.me/username/id</code>
Discussion Group Username + Message ID.
<blockquote>use plus or nicegram to get id</blockquote>

<b>private:</b> 
<code>https://t.me/c/id/id</code>
Discussion Group ID + Message ID.
<blockquote>use plus or nicegram to get id</blockquote>
", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) { }
}
#[FilterButtonQueryData('infof2')]
public function infof2(callbackQuery $query) {
	try {
$userid = $query->userId;   
$msgid = $query->messageId;   
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"💬 Comment",'callback_data'=>"infof1"],['text'=>"Channel 📣",'callback_data'=>"infof2"]];
$bot_API_markup[] = [['text'=>"🗂 Topics",'callback_data'=>"infof4"],['text'=>"Group 👥",'callback_data'=>"infof3"]];
$bot_API_markup[] = [['text'=>"🤖 Bot",'callback_data'=>"infof5"],['text'=>"👤 User",'callback_data'=>"infof6"]];
$bot_API_markup[] = [['text'=>"🔙 Back to Help",'callback_data'=>"help"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "
🔗 <b>Link to channel message</b> 

<b>public:</b> 
<code>https://t.me/username/id</code>
Channel Username + Message ID.
<blockquote>use plus or nicegram to get id</blockquote>

<b>private:</b> 
<code>https://t.me/c/id/id</code>
Channel ID + Message ID.
<blockquote>use plus or nicegram to get id</blockquote>
", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) { }
}
#[FilterButtonQueryData('infof3')]
public function infof3(callbackQuery $query) {
	try {
$userid = $query->userId;   
$msgid = $query->messageId;   
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"💬 Comment",'callback_data'=>"infof1"],['text'=>"Channel 📣",'callback_data'=>"infof2"]];
$bot_API_markup[] = [['text'=>"🗂 Topics",'callback_data'=>"infof4"],['text'=>"Group 👥",'callback_data'=>"infof3"]];
$bot_API_markup[] = [['text'=>"🤖 Bot",'callback_data'=>"infof5"],['text'=>"👤 User",'callback_data'=>"infof6"]];
$bot_API_markup[] = [['text'=>"🔙 Back to Help",'callback_data'=>"help"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "
🔗 <b>Link to group message</b> 

<b>public:</b> 
<code>https://t.me/username/id</code>
Group Username + Message ID.
<blockquote>use plus or nicegram to get id</blockquote>

<b>private:</b> 
<code>https://t.me/c/id/id</code>
Group ID + Message ID.
<blockquote>use plus or nicegram to get id</blockquote>
", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) { }
}
#[FilterButtonQueryData('infof4')]
public function infof4(callbackQuery $query) {
	try {
$userid = $query->userId;   
$msgid = $query->messageId;   
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"💬 Comment",'callback_data'=>"infof1"],['text'=>"Channel 📣",'callback_data'=>"infof2"]];
$bot_API_markup[] = [['text'=>"🗂 Topics",'callback_data'=>"infof4"],['text'=>"Group 👥",'callback_data'=>"infof3"]];
$bot_API_markup[] = [['text'=>"🤖 Bot",'callback_data'=>"infof5"],['text'=>"👤 User",'callback_data'=>"infof6"]];
$bot_API_markup[] = [['text'=>"🔙 Back to Help",'callback_data'=>"help"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "
🔗 <b>Link to Topics Group message</b> 

<b>public:</b> 
<code>https://t.me/username/id</code>
Group username + message ID.
<blockquote>use plus or nicegram to get id</blockquote>

<b>private:</b> 
<code>https://t.me/c/id/id</code>
Group ID + Message ID.
<blockquote>use plus or nicegram to get id</blockquote>
", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) { }
}
#[FilterButtonQueryData('infof5')]
public function infof5(callbackQuery $query) {
	try {
$userid = $query->userId;   
$msgid = $query->messageId;   
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"💬 Comment",'callback_data'=>"infof1"],['text'=>"Channel 📣",'callback_data'=>"infof2"]];
$bot_API_markup[] = [['text'=>"🗂 Topics",'callback_data'=>"infof4"],['text'=>"Group 👥",'callback_data'=>"infof3"]];
$bot_API_markup[] = [['text'=>"🤖 Bot",'callback_data'=>"infof5"],['text'=>"👤 User",'callback_data'=>"infof6"]];
$bot_API_markup[] = [['text'=>"🔙 Back to Help",'callback_data'=>"help"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "
🔗 <b>Link to Bot Message</b> 🤖
<code>https://t.me/b/username/id</code>
Bot Username + Message ID.
<blockquote>use plus or nicegram to get id</blockquote>
", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) { }
}
#[FilterButtonQueryData('infof6')]
public function infof6(callbackQuery $query) {
	try {
$userid = $query->userId;   
$msgid = $query->messageId;   
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"💬 Comment",'callback_data'=>"infof1"],['text'=>"Channel 📣",'callback_data'=>"infof2"]];
$bot_API_markup[] = [['text'=>"🗂 Topics",'callback_data'=>"infof4"],['text'=>"Group 👥",'callback_data'=>"infof3"]];
$bot_API_markup[] = [['text'=>"🤖 Bot",'callback_data'=>"infof5"],['text'=>"👤 User",'callback_data'=>"infof6"]];
$bot_API_markup[] = [['text'=>"🔙 Back to Help",'callback_data'=>"help"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "
🔗 <b>Link to User Message</b> 👤

<b>by the username:</b> 
<code>https://t.me/u/username/id</code>
User Username + Message ID.
<blockquote>use plus or nicegram to get id</blockquote>

<b>by the id:</b> 
<code>https://t.me/u/id/id</code>
User ID + Message ID.
<blockquote>use plus or nicegram to get id</blockquote>
", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) { }
}
#[FilterButtonQueryData('HowGetMessage')]
public function HowGetMessage(callbackQuery $query) {
	try {
$userid = $query->userId;   
$msgid = $query->messageId;   
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"🔙 Back to Help",'callback_data'=>"help"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "
<b>You can receive a message from any type of chat ✅️</b> 
<blockquote>groups: public/private/topics</blockquote>
<blockquote>channels: public/private/comment</blockquote>
<blockquote>bots: by username</blockquote>
<blockquote>users: by username or id</blockquote>

💬 <b>How to get a message❓</b> 

💡 The first thing to do is copy the <b>message link</b> and send it here.
<b>and you will get the message ✅</b>

<i>If you haven't set up a chat to receive the message, you'll receive a message to your account's <b>'Saved Messages'.</b></i>
", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) { }
}

/* ========= support Handlers ========= */
#[FilterButtonQueryData('Support')]
public function Supportcommand(callbackQuery $query) {
	try {
$userid = $query->userId;
$msgid = $query->messageId;       
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"❌ Cancel",'callback_data'=>"backmenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "👉🏻 Send now a message with a request to the Staff.

💡 The request must be sent <b>in one message</b>, other messages will not be delivered.", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);
Amp\File\write(__DIR__ ."/data/$userid/grs1.txt", 'support');
Amp\File\write(__DIR__ ."/data/$userid/messagetodelete.txt", (string) $msgid);

} catch (Throwable $e) { }
}
#[Handler]
public function handleSupportMsg(Incoming & PrivateMessage $message): void {
           try {
if ($this->isSelfBot()) {
$messagetext = $message->message;
$messageid = $message->id;
$messagefile = $message->media;
$senderid = $message->senderId;
$entities = $message->entities;
$grouped_id = $message->groupedId;
$User_Full = $this->getInfo($message->senderId);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}
$last_name = $User_Full['User']['last_name']?? null;
if($last_name == null){
$last_name = "null";
}

$username = $User_Full['User']['username'] ?? ($User_Full['User']['usernames'][0]['username'] ?? null);
if($username === null){
$username = "(null)";
}else{
$username = "@".$username;
}
	
if(!preg_match('/^\/([Ss]tart)/',$messagetext)){  
    if (file_exists(__DIR__ ."/data/$senderid/grs1.txt")) {
$grs1 = Amp\File\read(__DIR__ ."/data/$senderid/grs1.txt");    
if($grs1 == "support"){

if($grouped_id != null){

try{ unlink(__DIR__ ."/data/$senderid/grs1.txt"); } catch (Throwable $e) {}

 if (file_exists(__DIR__ ."/data/$senderid/messagetodelete.txt")) {
$filexmsgid = Amp\File\read(__DIR__ ."/data/$senderid/messagetodelete.txt");  
try {
$this->messages->deleteMessages(revoke: true, id: [$filexmsgid]); 
} catch (Throwable $e) {}

try { unlink(__DIR__ ."/data/$senderid/messagetodelete.txt"); } catch (Throwable $e) {}
}

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"🔙 Back",'callback_data'=>"backmenu"]
        ]
    ]
];

$this->messages->sendMessage(peer: $senderid, message: "Your message has been sent to support ✅", reply_markup: $bot_API_markup, parse_mode: 'HTML');

$this->processAlbumPart($message);
return;

}else{
	
$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"🔙 Back",'callback_data'=>"backmenu"]
        ]
    ]
];

$this->messages->sendMessage(peer: $senderid, message: "Your message has been sent to support ✅", reply_markup: $bot_API_markup, parse_mode: 'HTML');

$ADMIN = $this->getAdminIds();
foreach ($ADMIN as $user) {
try {
if($messagefile != null){
if($messagetext != null){
$Updatesx = $this->messages->sendMedia(peer: $user, message: "$messagetext",  entities: $entities, media: $messagefile);
}else{
$Updatesx = $this->messages->sendMedia(peer: $user, media: $messagefile);	
}
}else{
if($messagetext != null){
$Updatesx = $this->messages->sendMessage(peer: $user, message: "$messagetext", entities: $entities);
}else{
}
}
$sentMessage2x = $this->extractMessageId($Updatesx);
$inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $sentMessage2x];
$this->messages->sendMessage(peer: $user, reply_to: $inputReplyToMessage, message: "👆 [<code>$senderid</code>]
FIRSTNAME: <a href='mention:$senderid'>$first_name </a>
USERNAME: $username
ID: <code>$senderid</code>

<i>👉 To answer, reply to this message..</i>", parse_mode: 'HTML');

} catch (Throwable $e) {}
}

			try {
$this->messages->deleteMessages(revoke: true, id: [$messageid]); 
} catch (Throwable $e) { }

try{ unlink(__DIR__ ."/data/$senderid/grs1.txt"); } catch (Throwable $e) {}
	
 if (file_exists(__DIR__ ."/data/$senderid/messagetodelete.txt")) {
$filexmsgid = Amp\File\read(__DIR__ ."/data/$senderid/messagetodelete.txt");  
try {
$this->messages->deleteMessages(revoke: true, id: [$filexmsgid]); 
} catch (Throwable $e) {}

try { unlink(__DIR__ ."/data/$senderid/messagetodelete.txt"); } catch (Throwable $e) {}
}
    }
    }
    }
    }
}
} catch (Throwable $e) { }
	}
#[Handler]
public function handlemashovMessagex(Incoming & PrivateMessage & IsReply & FromAdmin $message): void {
try {
if ($this->isSelfBot()) {
$messagetext = $message->message;
$messageid = $message->id;
$senderid = $message->senderId;
$entities = $message->entities;
$messagefile = $message->media;
$User_Full = $this->getInfo($message->senderId);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}
$last_name = $User_Full['User']['last_name']?? null;
if($last_name == null){
$last_name = "null";
}


try {
$usernames = $User_Full['User']['usernames']?? null;
$newLangsCommausername = null;
$peerList2username = [];
foreach ($usernames as $username) {
$usernamexfr = $username['username'];
$usernamexfr = "@".$usernamexfr;
$peerList2username[]=$usernamexfr;
}
$newLangsCommausername = implode(" ", $peerList2username);
}catch (\danog\MadelineProto\Exception $e) {
	
} catch (\danog\MadelineProto\RPCErrorException $e) {
}
$username = $User_Full['User']['username']?? null;
if($username == null){	
if($newLangsCommausername != null){
$username = $newLangsCommausername;
}else{
$username = "(null)";
}
}else{
$username = "@".$username;
}


if(!preg_match('/^\/([Ss]tart)/',$messagetext)){  

try {
$kjhcdj = $message->replyToMsgId;
$messages_Messages = $this->messages->getMessages(id: [$kjhcdj], );
$messages_Messagesxtext = $messages_Messages['messages'][0]['message']?? null;
if($messages_Messagesxtext == null){
$messages_Messagesxtext = "null";
}

preg_match('/ID:\s*(\d+)/', $messages_Messagesxtext, $matches);
$checked = false;

if (isset($matches[1])) {
    $id = $matches[1];

if($id != $meid){
if($id != $senderid){
	
try {
$User_Fullx = $this->getInfo($id);
$first_namex = $User_Fullx['User']['first_name']?? null;
if($first_namex == null){
$first_namex = "null";
}

} catch (Throwable $e) {
$error = $e->getMessage();
$first_namex = "null";
}

if($messagefile != null){
if($messagetext != null){
$sentMessage = $this->messages->sendMedia(peer: $id, message: "$messagetext",  entities: $entities, media: $messagefile);
}else{
$sentMessage = $this->messages->sendMedia(peer: $id, media: $messagefile);	
}
}else{
if($messagetext != null){
$sentMessage = $this->messages->sendMessage(peer: $id, message: "$messagetext", entities: $entities);
}else{
}
}
$checked = true;

$inputReplyToMessagex = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $messageid];
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessagex, message: "<i>message sent ✅</i>", parse_mode: 'HTML');

}
}
}

} catch (Throwable $e) {
$error = $e->getMessage();
$inputReplyToMessagex = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $messageid];
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessagex, message: "<i>❌ $error</i>", parse_mode: 'HTML');
	}

}

}
} catch (Throwable $e) { }
	}

/* ========= donate Handlers ========= */
#[FilterCommandCaseInsensitive('donate')]
public function DonateCommand(Incoming & PrivateMessage  $message): void {
try {
if ($this->isSelfBot()) {
$senderid = $message->senderId;
$messageid = $message->id;

$labeledPrice1 = ['_' => 'labeledPrice', 'label' => 'star', 'amount' => 5];
$invoice1 = ['_' => 'invoice', 'currency' => 'XTR', 'prices' => [$labeledPrice1],];

$labeledPrice2 = ['_' => 'labeledPrice', 'label' => 'star', 'amount' => 25];
$invoice2 = ['_' => 'invoice', 'currency' => 'XTR', 'prices' => [$labeledPrice2],];

$labeledPrice3 = ['_' => 'labeledPrice', 'label' => 'star', 'amount' => 100];
$invoice3 = ['_' => 'invoice', 'currency' => 'XTR', 'prices' => [$labeledPrice3],];

$labeledPrice4 = ['_' => 'labeledPrice', 'label' => 'star', 'amount' => 150];
$invoice4 = ['_' => 'invoice', 'currency' => 'XTR', 'prices' => [$labeledPrice4],];

$labeledPrice5 = ['_' => 'labeledPrice', 'label' => 'star', 'amount' => 250];
$invoice5 = ['_' => 'invoice', 'currency' => 'XTR', 'prices' => [$labeledPrice5],];

$labeledPrice6 = ['_' => 'labeledPrice', 'label' => 'star', 'amount' => 400];
$invoice6 = ['_' => 'invoice', 'currency' => 'XTR', 'prices' => [$labeledPrice6],];

$inputMediaInvoice1 = ['_' => 'inputMediaInvoice', 'title' => 'Support me', 'description' => 'Support me with 5 ⭐️', 'invoice' => $invoice1, 'payload' => "donate|$senderid|5", 'provider_data' => 'test']; 
$inputMediaInvoice2 = ['_' => 'inputMediaInvoice', 'title' => 'Support me', 'description' => 'Support me with 25 ⭐️', 'invoice' => $invoice2, 'payload' => "donate|$senderid|25", 'provider_data' => 'test']; 
$inputMediaInvoice3 = ['_' => 'inputMediaInvoice', 'title' => 'Support me', 'description' => 'Support me with 100 ⭐️', 'invoice' => $invoice3, 'payload' => "donate|$senderid|100", 'provider_data' => 'test']; 
$inputMediaInvoice4 = ['_' => 'inputMediaInvoice', 'title' => 'Support me', 'description' => 'Support me with 150 ⭐️', 'invoice' => $invoice4, 'payload' => "donate|$senderid|150", 'provider_data' => 'test']; 
$inputMediaInvoice5 = ['_' => 'inputMediaInvoice', 'title' => 'Support me', 'description' => 'Support me with 250 ⭐️', 'invoice' => $invoice5, 'payload' => "donate|$senderid|250", 'provider_data' => 'test']; 
$inputMediaInvoice6 = ['_' => 'inputMediaInvoice', 'title' => 'Support me', 'description' => 'Support me with 400 ⭐️', 'invoice' => $invoice6, 'payload' => "donate|$senderid|400", 'provider_data' => 'test']; 


$payments_ExportedInvoice1 = $this->payments->exportInvoice(invoice_media: $inputMediaInvoice1, );
$urlexp1 = $payments_ExportedInvoice1['url']; //5

$payments_ExportedInvoice2 = $this->payments->exportInvoice(invoice_media: $inputMediaInvoice2, );
$urlexp2 = $payments_ExportedInvoice2['url']; //25

$payments_ExportedInvoice3 = $this->payments->exportInvoice(invoice_media: $inputMediaInvoice3, );
$urlexp3 = $payments_ExportedInvoice3['url']; //100

$payments_ExportedInvoice4 = $this->payments->exportInvoice(invoice_media: $inputMediaInvoice4, );
$urlexp4 = $payments_ExportedInvoice4['url']; //150

$payments_ExportedInvoice5 = $this->payments->exportInvoice(invoice_media: $inputMediaInvoice5, );
$urlexp5 = $payments_ExportedInvoice5['url']; //250

$payments_ExportedInvoice6 = $this->payments->exportInvoice(invoice_media: $inputMediaInvoice6, );
$urlexp6 = $payments_ExportedInvoice6['url']; //400


$bot_API_markup = ['inline_keyboard' => 
    [
        [	
['text'=>"⭐️ 5",'url'=>"$urlexp1"],['text'=>"⭐️ 25",'url'=>"$urlexp2"],['text'=>"⭐️ 100",'url'=>"$urlexp3"]
                    ],
                    [	
['text'=>"⭐️ 150",'url'=>"$urlexp4"],['text'=>"⭐️ 250",'url'=>"$urlexp5"],['text'=>"⭐️ 400",'url'=>"$urlexp6"]
        ]
    ]
];

$inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $messageid];
$sentMessage = $this->messages->sendMessage(no_webpage: true, peer: $message->senderId, reply_to: $inputReplyToMessage, message: "Hi, thanks for wanting to donate to me🥰
Choose the donation amount you want to give👇", reply_markup: $bot_API_markup, parse_mode: 'HTML', effect: 5159385139981059251) ;
}
} catch (Throwable $e) { }
}
#[FilterButtonQueryData('Donate')]
public function DonateCommand2(callbackQuery $query) {
try {
$userid = $query->userId;
$msgid = $query->messageId;       
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$originalString = $userid;
$encodedString = $originalString;


$labeledPrice1 = ['_' => 'labeledPrice', 'label' => 'star', 'amount' => 5];
$invoice1 = ['_' => 'invoice', 'currency' => 'XTR', 'prices' => [$labeledPrice1],];

$labeledPrice2 = ['_' => 'labeledPrice', 'label' => 'star', 'amount' => 25];
$invoice2 = ['_' => 'invoice', 'currency' => 'XTR', 'prices' => [$labeledPrice2],];

$labeledPrice3 = ['_' => 'labeledPrice', 'label' => 'star', 'amount' => 100];
$invoice3 = ['_' => 'invoice', 'currency' => 'XTR', 'prices' => [$labeledPrice3],];

$labeledPrice4 = ['_' => 'labeledPrice', 'label' => 'star', 'amount' => 150];
$invoice4 = ['_' => 'invoice', 'currency' => 'XTR', 'prices' => [$labeledPrice4],];

$labeledPrice5 = ['_' => 'labeledPrice', 'label' => 'star', 'amount' => 250];
$invoice5 = ['_' => 'invoice', 'currency' => 'XTR', 'prices' => [$labeledPrice5],];

$labeledPrice6 = ['_' => 'labeledPrice', 'label' => 'star', 'amount' => 400];
$invoice6 = ['_' => 'invoice', 'currency' => 'XTR', 'prices' => [$labeledPrice6],];

$inputMediaInvoice1 = ['_' => 'inputMediaInvoice', 'title' => 'Support me', 'description' => 'Support me with 5 ⭐️', 'invoice' => $invoice1, 'payload' => "donate|$userid|5", 'provider_data' => 'test']; 
$inputMediaInvoice2 = ['_' => 'inputMediaInvoice', 'title' => 'Support me', 'description' => 'Support me with 25 ⭐️', 'invoice' => $invoice2, 'payload' => "donate|$userid|25", 'provider_data' => 'test']; 
$inputMediaInvoice3 = ['_' => 'inputMediaInvoice', 'title' => 'Support me', 'description' => 'Support me with 100 ⭐️', 'invoice' => $invoice3, 'payload' => "donate|$userid|100", 'provider_data' => 'test']; 
$inputMediaInvoice4 = ['_' => 'inputMediaInvoice', 'title' => 'Support me', 'description' => 'Support me with 150 ⭐️', 'invoice' => $invoice4, 'payload' => "donate|$userid|150", 'provider_data' => 'test']; 
$inputMediaInvoice5 = ['_' => 'inputMediaInvoice', 'title' => 'Support me', 'description' => 'Support me with 250 ⭐️', 'invoice' => $invoice5, 'payload' => "donate|$userid|250", 'provider_data' => 'test']; 
$inputMediaInvoice6 = ['_' => 'inputMediaInvoice', 'title' => 'Support me', 'description' => 'Support me with 400 ⭐️', 'invoice' => $invoice6, 'payload' => "donate|$userid|400", 'provider_data' => 'test']; 


$payments_ExportedInvoice1 = $this->payments->exportInvoice(invoice_media: $inputMediaInvoice1, );
$urlexp1 = $payments_ExportedInvoice1['url']; //5

$payments_ExportedInvoice2 = $this->payments->exportInvoice(invoice_media: $inputMediaInvoice2, );
$urlexp2 = $payments_ExportedInvoice2['url']; //25

$payments_ExportedInvoice3 = $this->payments->exportInvoice(invoice_media: $inputMediaInvoice3, );
$urlexp3 = $payments_ExportedInvoice3['url']; //100

$payments_ExportedInvoice4 = $this->payments->exportInvoice(invoice_media: $inputMediaInvoice4, );
$urlexp4 = $payments_ExportedInvoice4['url']; //150

$payments_ExportedInvoice5 = $this->payments->exportInvoice(invoice_media: $inputMediaInvoice5, );
$urlexp5 = $payments_ExportedInvoice5['url']; //250

$payments_ExportedInvoice6 = $this->payments->exportInvoice(invoice_media: $inputMediaInvoice6, );
$urlexp6 = $payments_ExportedInvoice6['url']; //400


$bot_API_markup = ['inline_keyboard' => 
    [
        [	
['text'=>"⭐️ 5",'url'=>"$urlexp1"],['text'=>"⭐️ 25",'url'=>"$urlexp2"],['text'=>"⭐️ 100",'url'=>"$urlexp3"]
                    ],
                    [	
['text'=>"⭐️ 150",'url'=>"$urlexp4"],['text'=>"⭐️ 250",'url'=>"$urlexp5"],['text'=>"⭐️ 400",'url'=>"$urlexp6"]
                    ],
                    [
['text'=>"🔙 Back",'callback_data'=>"backmenu"]


        ]
    ]
];


$query->editText($message = "Hi, thanks for wanting to donate to me🥰
Choose the donation amount you want to give👇", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) {}
}
public function onupdateBotPrecheckoutQuery($update) {
		try{
if ($this->isSelfBot()) {
$userid = $update['user_id'];
$total_amount = $update['total_amount']; 
$query_id = $update['query_id'];
$sucses = $this->messages->setBotPrecheckoutResults(success: true, query_id: $query_id);
}
} catch (\Throwable $e) {}
}
public function onUpdateNewMessage($update) {
		try{
if ($this->isSelfBot()) {
        $msg = $update['message'];
        $messageId = $msg['id'];
        $userId = $msg['from_id'] ?? null;

$User_Full = $this->getInfo($userId);
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

        if (isset($msg['action']['_']) && $msg['action']['_'] === 'messageActionPinMessage') {

    $botId = $this->getSelf()['id'];

    $actorId = $msg['from_id'] ?? null;

    if ($actorId == $botId) {
        $serviceMessageId = $msg['id'];
        try {
        $this->messages->deleteMessages(['id' => [$serviceMessageId], 'revoke' => true]);
		} catch (\Throwable $e) {}
    }
    }

        if (isset($msg['action']['_']) && $msg['action']['_'] === 'messageActionPaymentSentMe') {
            $amount   = $msg['action']['total_amount'];
            $currency = $msg['action']['currency'];
            $payload  = (string) $msg['action']['payload'];
            $charge   = $msg['action']['charge']['id'];
echo $charge;
$parts = explode('|', $payload);
if (count($parts) < 3) return;
$type    = $parts[0];
$uid     = $parts[1];
$price   = (int)$parts[2];

    if($amount != $price){
        return;
    }

    if($type == 'donate'){
$inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $messageId];
$this->messages->sendMessage(peer: $userId, reply_to: $inputReplyToMessage, message: "<b>amount:</b> $amount ⭐️
🎉 thank you for your donation 🎉", parse_mode: 'HTML', effect: 5159385139981059251);	

$this->sendMessageToAdmins("<b>new donate! 🎉</b>
FIRSTNAME: <a href='mention:$userId'>$first_name </a>
ID: <a href='mention:$userId'>$userId </a>
USERNAME: $username
<b>amount:</b> $amount ⭐️

<i>👉 To answer, reply to this message..</i>",parseMode: ParseMode::HTML);
}

        }
    }
} catch (\Throwable $e) {}
}

/* ========= login Handlers ========= */
#[FilterButtonQueryData('Login')]
public function Logincommand(callbackQuery $query) {
$userid = $query->userId;
$msgid = $query->messageId;       

try {

$sessionDir = "data/$userid/user.madeline";
try {

    $invalid = false;
    $ipcState = "$sessionDir/ipcState.php";

    if (!is_dir($sessionDir)) {
        $invalid = true;
    } elseif (!is_file($ipcState) || !is_readable($ipcState)) {
        $invalid = true;
    } else {

        $content = \amp\File\read($ipcState);

        if (
            str_contains($content, 'ExitFailure') &&
            time() - filemtime($ipcState) > 20
        ) {
            $invalid = true;
        }
    }

    if ($invalid) {
$message = "Please enter your phone number along with the country code:
Example: <code>+19876543210</code>

<i>Soon it will also be possible to upload sessions(Pyrogram, Teleton, Zerobias)</i>
";
Amp\File\write("data/$userid/grs1.txt", 'login1');
Amp\File\write("data/$userid/messagetodelete.txt", (string) $msgid);	
    } else {

        $MadelineProtosession = new \danog\MadelineProto\API($sessionDir);
if ($MadelineProtosession->getAuthorization() === API::LOGGED_IN) {


$message = "
You already have a connected account.";
}else{
$message = "Please enter your phone number along with the country code:
Example: <code>+19876543210</code>

<i>Soon it will also be possible to upload sessions(Pyrogram, Teleton, Zerobias)</i>
";
Amp\File\write("data/$userid/grs1.txt", 'login1');
Amp\File\write("data/$userid/messagetodelete.txt", (string) $msgid);
    }

	}

} catch (\Throwable $e) {
$message = "Please enter your phone number along with the country code:
Example: <code>+19876543210</code>

<i>Soon it will also be possible to upload sessions(Pyrogram, Teleton, Zerobias)</i>
";
Amp\File\write("data/$userid/grs1.txt", 'login1');
Amp\File\write("data/$userid/messagetodelete.txt", (string) $msgid);
}

$bot_API_markup[] = [['text'=>"Cancel",'callback_data'=>"backmenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = $message, $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) {}
}
#[Handler]
public function handleLogin(Incoming & PrivateMessage $message): void {
$messagetext = $message->message;
$messageid = $message->id;
$messagefile = $message->media;
$grouped_id = $message->groupedId;
$senderid = $message->senderId;
$User_Full = $this->getInfo($message->senderId);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}
$last_name = $User_Full['User']['last_name']?? null;
if($last_name == null){
$last_name = "null";
}

try {
$usernames = $User_Full['User']['usernames']?? null;
$newLangsCommausername = null;
$peerList2username = [];
foreach ($usernames as $username) {
$usernamexfr = $username['username'];
$usernamexfr = "@".$usernamexfr;
$peerList2username[]=$usernamexfr;
}
$newLangsCommausername = implode(" ", $peerList2username);
}catch (\danog\MadelineProto\Exception $e) {
} catch (\danog\MadelineProto\RPCErrorException $e) {
}
$username = $User_Full['User']['username']?? null;
if($username == null){	
if($newLangsCommausername != null){
$username = $newLangsCommausername;
}else{
$username = "(null)";
}
}else{
$username = "@".$username;
}

try {
	
if(!preg_match('/^\/([Ss]tart)/',$messagetext)){  
    if (file_exists("data/$senderid/grs1.txt")) {
$grs1 = Amp\File\read("data/$senderid/grs1.txt");    

if($grs1 == "login1"){

if(!preg_match("/^\+[0-9]/",$messagetext)){
$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"❌ Cancel",'callback_data'=>"backmenu"]
        ]
    ]
];

 if (file_exists("data/$senderid/messagetodelete.txt")) {
$filexmsgid = Amp\File\read("data/$senderid/messagetodelete.txt");  
try {
$this->messages->deleteMessages(revoke: true, id: [$filexmsgid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

unlink("data/$senderid/messagetodelete.txt");
}

$sentMessage = $this->messages->sendMessage(peer: $senderid, message: "❌ Incorrect format.. Please try again:", reply_markup: $bot_API_markup, parse_mode: 'HTML');
$sentMessage2 = $this->extractMessageId($sentMessage);
Amp\File\write("data/$senderid/messagetodelete.txt", "$sentMessage2");

try {
$this->messages->deleteMessages(revoke: true, id: [$messageid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

}

if(preg_match("/^\+[0-9]/",$messagetext)){	
try {
unlink("data/$senderid/grs1.txt");
} catch (Throwable $e) {}

 if (file_exists("data/$senderid/messagetodelete.txt")) {
$filexmsgid = Amp\File\read("data/$senderid/messagetodelete.txt");  
try {
$this->messages->deleteMessages(revoke: true, id: [$filexmsgid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

unlink("data/$senderid/messagetodelete.txt");
}

Amp\File\write("data/$senderid/apiphone.txt", "$messagetext");

$bot_API_markup[] = [['text'=>"Use Own Configuration",'callback_data'=>"ownAPI"]];
$bot_API_markup[] = [['text'=>"Default Configuration",'callback_data'=>"defaultAPI"]];
$bot_API_markup[] = [['text'=>"❌ Cancel",'callback_data'=>"backmenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$sentMessage = $this->messages->sendMessage(peer: $senderid, message: "Please choose Yᴏᴜʀ Aᴄᴛɪᴏɴ for <b>API ID</b> and <b>API HASH</b>:", reply_markup: $bot_API_markup, parse_mode: 'HTML');

try {
$this->messages->deleteMessages(revoke: true, id: [$messageid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

}
}

if($grs1 == "setAPI_ID"){

try {
unlink("data/$senderid/grs1.txt");
} catch (Throwable $e) {}
Amp\File\write("data/$senderid/API_ID_ACCOUNT.txt", "$messagetext");
 if (file_exists("data/$senderid/messagetodelete.txt")) {
$filexmsgid = Amp\File\read("data/$senderid/messagetodelete.txt");  
try {
$this->messages->deleteMessages(revoke: true, id: [$filexmsgid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

unlink("data/$senderid/messagetodelete.txt");
}

$bot_API_markup[] = [['text'=>"❌ Cancel",'callback_data'=>"backmenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$sentMessage = $this->messages->sendMessage(peer: $senderid, message: "<b>Pʟᴇᴀsᴇ sᴇɴᴅ ʏᴏᴜʀ API HASH:</b>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
$sentMessage2 = $this->extractMessageId($sentMessage);
Amp\File\write("data/$senderid/messagetodelete.txt", "$sentMessage2");
Amp\File\write("data/$senderid/grs1.txt", 'setAPI_HASH');

try {
$this->messages->deleteMessages(revoke: true, id: [$messageid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

}

if($grs1 == "setAPI_HASH"){
try {
unlink("data/$senderid/grs1.txt");
} catch (Throwable $e) {}
Amp\File\write("data/$senderid/API_HASH_ACCOUNT.txt", "$messagetext");
 if (file_exists("data/$senderid/messagetodelete.txt")) {
$filexmsgid = Amp\File\read("data/$senderid/messagetodelete.txt");  
try {
$this->messages->deleteMessages(revoke: true, id: [$filexmsgid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

unlink("data/$senderid/messagetodelete.txt");
}

$sentMessage = $this->messages->sendMessage(peer: $senderid, message: "📲 Sending OTP...", parse_mode: 'HTML');
$sentMessage2 = $this->extractMessageId($sentMessage);

try {
$this->messages->deleteMessages(revoke: true, id: [$messageid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

try {

    if (file_exists("data/$senderid/apiphone.txt")) {
$phone = Amp\File\read("data/$senderid/apiphone.txt");    
	}else{
$phone = "null"; 
	}	
    if (file_exists("data/$senderid/API_ID_ACCOUNT.txt")) {
$api_id = Amp\File\read("data/$senderid/API_ID_ACCOUNT.txt");    
	}else{
$api_id = "null"; 
	}	
    if (file_exists("data/$senderid/API_HASH_ACCOUNT.txt")) {
$api_hash = Amp\File\read("data/$senderid/API_HASH_ACCOUNT.txt");    
	}else{
$api_hash = "null"; 
	}		
	
$settings = (new \danog\MadelineProto\Settings\AppInfo)->setApiId((int)$api_id)->setApiHash($api_hash);
$MadelineProtosession = new \danog\MadelineProto\API("data/$senderid/user.madeline", $settings);
$hiburcheck = $MadelineProtosession->phoneLogin($phone);

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"❌ Cancel",'callback_data'=>"backmenu"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage2, message: "Now send me the code you received:", reply_markup: $bot_API_markup, parse_mode: 'HTML');

Amp\File\write("data/$senderid/grs1.txt", 'login2');
Amp\File\write("data/$senderid/messagetodelete.txt", (string) $sentMessage2);	

} catch (Throwable $e) {
$error = $e->getMessage();

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"🔙 Back",'callback_data'=>"backmenu"]
        ]
    ]
];
$this->messages->editMessage(peer: $senderid, id: $sentMessage2, message: "❌ $error", reply_markup: $bot_API_markup, parse_mode: 'HTML');
  
}
}

if($grs1 == "login2"){

if(!preg_match("/^[0-9]/",$messagetext)){
$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"❌ Cancel",'callback_data'=>"backmenu"]
        ]
    ]
];

 if (file_exists("data/$senderid/messagetodelete.txt")) {
$filexmsgid = Amp\File\read("data/$senderid/messagetodelete.txt");  
try {
$this->messages->deleteMessages(revoke: true, id: [$filexmsgid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

unlink("data/$senderid/messagetodelete.txt");
}

$sentMessage = $this->messages->sendMessage(peer: $senderid, message: "❌ Incorrect format.. Please try again:", reply_markup: $bot_API_markup, parse_mode: 'HTML');
$sentMessage2 = $this->extractMessageId($sentMessage);
Amp\File\write("data/$senderid/messagetodelete.txt", "$sentMessage2");

try {
$this->messages->deleteMessages(revoke: true, id: [$messageid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

}

if(preg_match("/^[0-9]/",$messagetext)){	
try {
unlink("data/$senderid/grs1.txt");
} catch (Throwable $e) {}

 if (file_exists("data/$senderid/messagetodelete.txt")) {
$filexmsgid = Amp\File\read("data/$senderid/messagetodelete.txt");  
try {
$this->messages->deleteMessages(revoke: true, id: [$filexmsgid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

unlink("data/$senderid/messagetodelete.txt");
}

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"❌ Cancel",'callback_data'=>"backmenu"]
        ]
    ]
];

$sentMessage = $this->messages->sendMessage(peer: $senderid, message: "Now send me the two-step password(2FA):
If not set send '<code>none</code>'", reply_markup: $bot_API_markup, parse_mode: 'HTML');
$sentMessage2 = $this->extractMessageId($sentMessage);

Amp\File\write("data/$senderid/grs1.txt", 'login3');
Amp\File\write("data/$senderid/messagetodelete.txt", (string) $sentMessage2);	
Amp\File\write("data/$senderid/apicode.txt", "$messagetext");

try {
$this->messages->deleteMessages(revoke: true, id: [$messageid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

}

}

if($grs1 == "login3"){

try {
unlink("data/$senderid/grs1.txt");
} catch (Throwable $e) {}

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"🔙 Back",'callback_data'=>"backmenu"]
        ]
    ]
];

 if (file_exists("data/$senderid/messagetodelete.txt")) {
$filexmsgid = Amp\File\read("data/$senderid/messagetodelete.txt");  
try {
$this->messages->deleteMessages(revoke: true, id: [$filexmsgid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

unlink("data/$senderid/messagetodelete.txt");
}

try {
$this->messages->deleteMessages(revoke: true, id: [$messageid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

if (file_exists("data/$senderid/apiphone.txt")) {
$APIIDHA3 = Amp\File\read("data/$senderid/apiphone.txt"); 
}
if (file_exists("data/$senderid/apicode.txt")) {
$APIIDHA4 = Amp\File\read("data/$senderid/apicode.txt"); 
}

try {
$MadelineProtosession = new \danog\MadelineProto\API("data/$senderid/user.madeline");
$authorization = $MadelineProtosession->completePhoneLogin($APIIDHA4);
if ($authorization['_'] === 'account.password') {
    $authorization = $MadelineProtosession->complete2falogin($messagetext);


}
if ($authorization['_'] === 'account.needSignup') {
$sentMessage = $this->messages->sendMessage(peer: $senderid, message: "❌ No existing Telegram account, please create one first!", reply_markup: $bot_API_markup, parse_mode: 'HTML');
$MadelineProtosession->logout();
    if (file_exists("data/$senderid/user.madeline")) {		
$folderPath = "data/$senderid/user.madeline"; 
    $folderPath = rtrim($folderPath, '/') . '/';
    if (is_dir($folderPath)) {
        // Use glob() to get all files in the folder
        $files = glob($folderPath . '*'); // Gets all files and folders

        foreach ($files as $file) {
            // Check if it's a file and delete it
            if (is_file($file)) {
                unlink($file);
            }
        }
    } else {

    }

rmdir("data/$senderid/user.madeline");
	}
}
if ($authorization['_'] === 'account.noPassword') {
    throw new \danog\MadelineProto\Exception('2FA is enabled but no password is set!');
}

if($authorization == true){
$sentMessage = $this->messages->sendMessage(peer: $senderid, message: "✅ You have successfully connected.", reply_markup: $bot_API_markup, parse_mode: 'HTML');

date_default_timezone_set("Asia/Jerusalem");
$today = date("d-m-Y H:i"); 
Amp\File\write("data/$senderid/logindate.txt", "$today");
}
if($authorization != true){
$sentMessage = $this->messages->sendMessage(peer: $senderid, message: "❌ Unknown error.", reply_markup: $bot_API_markup, parse_mode: 'HTML');
$MadelineProtosession->logout();
    if (file_exists("data/$senderid/user.madeline")) {		
$folderPath = "data/$senderid/user.madeline"; // Replace with your folder path
    $folderPath = rtrim($folderPath, '/') . '/';
    
    // Check if the path is a directory
    if (is_dir($folderPath)) {
        // Use glob() to get all files in the folder
        $files = glob($folderPath . '*'); // Gets all files and folders

        foreach ($files as $file) {
            // Check if it's a file and delete it
            if (is_file($file)) {
                unlink($file);
            }
        }
    } else {

    }

rmdir("data/$senderid/user.madeline");
	}
}

} catch (Throwable $e) {
$error = $e->getMessage();

$sentMessage = $this->messages->sendMessage(peer: $senderid, message: "❌ $error", reply_markup: $bot_API_markup);
$MadelineProtosession->logout();
    if (file_exists("data/$senderid/user.madeline")) {		
$folderPath = "data/$senderid/user.madeline"; // Replace with your folder path
    $folderPath = rtrim($folderPath, '/') . '/';
    
    // Check if the path is a directory
    if (is_dir($folderPath)) {
        // Use glob() to get all files in the folder
        $files = glob($folderPath . '*'); // Gets all files and folders

        foreach ($files as $file) {
            // Check if it's a file and delete it
            if (is_file($file)) {
                unlink($file);
            }
        }
    } else {

    }

rmdir("data/$senderid/user.madeline");
	}


}

}

}
}

} catch (Throwable $e) {}

}
#[FilterButtonQueryData('defaultAPI')]
public function defaultAPI(callbackQuery $query) {
	try {

$userid = $query->userId;
$msgid = $query->messageId;       
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

    if (file_exists("data/$userid/apiphone.txt")) {
$phone = Amp\File\read("data/$userid/apiphone.txt");    
	}else{
$phone = "null"; 
	}	
	
	
$sentMessage = $this->messages->sendMessage(peer: $userid, message: "📲 Sending OTP...", parse_mode: 'HTML');
$sentMessage2 = $this->extractMessageId($sentMessage);

try {
$this->messages->deleteMessages(revoke: true, id: [$msgid]); 
} catch (Throwable $e) {
}

try {
$API_ID = parse_ini_file('.env')['API_ID'];
$API_HASH = parse_ini_file('.env')['API_HASH'];
$settings = (new \danog\MadelineProto\Settings\AppInfo)->setApiId((int)$API_ID)->setApiHash($API_HASH);
$MadelineProtosession = new \danog\MadelineProto\API("data/$userid/user.madeline", $settings);
$hiburcheck = $MadelineProtosession->phoneLogin($phone);

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"❌ Cancel",'callback_data'=>"backmenu"]
        ]
    ]
];

$this->messages->editMessage(peer: $userid, id: $sentMessage2, message: "Now send me the code you received:", reply_markup: $bot_API_markup, parse_mode: 'HTML');

Amp\File\write("data/$userid/grs1.txt", 'login2');
Amp\File\write("data/$userid/messagetodelete.txt", (string) $sentMessage2);	

} catch (Throwable $e) {
$error = $e->getMessage();

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"🔙 Back",'callback_data'=>"backmenu"]
        ]
    ]
];
$this->messages->editMessage(peer: $userid, id: $sentMessage2, message: "❌ $error", reply_markup: $bot_API_markup, parse_mode: 'HTML');
  
}

} catch (Throwable $e) {
$error = $e->getMessage();
	}
}
#[FilterButtonQueryData('ownAPI')]
public function ownAPI(callbackQuery $query) {
	try {
$userid = $query->userId;
$msgid = $query->messageId;       
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"❌ Cancel",'callback_data'=>"backmenu"]
        ]
    ]
];

$this->messages->editMessage(peer: $userid, id: $msgid, message: "<b>Pʟᴇᴀsᴇ sᴇɴᴅ ʏᴏᴜʀ API ID:</b>", reply_markup: $bot_API_markup, parse_mode: 'HTML');

Amp\File\write("data/$userid/grs1.txt", 'setAPI_ID');
Amp\File\write("data/$userid/messagetodelete.txt", (string) $msgid);	

} catch (Throwable $e) {
$error = $e->getMessage();
	}
}
#[FilterButtonQueryData('MyAccount')]
public function MyAccount(callbackQuery $query) {
$userid = $query->userId;
$msgid = $query->messageId;       

try {

$sessionDir = "data/$userid/user.madeline";
try {

    $invalid = false;
    $ipcState = "$sessionDir/ipcState.php";

    if (!is_dir($sessionDir)) {
        $invalid = true;
    } elseif (!is_file($ipcState) || !is_readable($ipcState)) {
        $invalid = true;
    } else {

        $content = \amp\File\read($ipcState);

        if (
            str_contains($content, 'ExitFailure') &&
            time() - filemtime($ipcState) > 20
        ) {
            $invalid = true;
        }
    }

    if ($invalid) {
$message = "❌ You do not have an account connected.";
    } else {

        $MadelineProtosession = new \danog\MadelineProto\API($sessionDir);
if ($MadelineProtosession->getAuthorization() === API::LOGGED_IN) {

$me = $MadelineProtosession->getSelf();
$first_name = $me['first_name']?? null;
if($first_name == null){	
$first_name = "(null)";
}
$last_name = $me['last_name']?? null;
if($last_name == null){	
$last_name = "(null)";
}
$premium = $me['premium']?? null;
if($premium == true){	
//$premium = "yes";
$premium = "│ ⭐️  <b>PREMIUM:</b> Yes ✅";
}else{
//$premium = "no";
$premium = "│ ⭐️  <b>PREMIUM:</b> No ❌
│ - <i>for 2GB+ need premium.</i>";
}
$id = $me['id']?? null;
if($id == null){	
$id = "(null)";
}
$phone = $me['phone']?? null;
if($phone == null){	
$phone = "(null)";
}
$status = $me['status']?? null;
if($status == null){	
$status = "(null)";
}else{

$status_1 = $status['expires']?? null;
if($status_1 != null){
$status = "Online";
}

$status_2 = $status['was_online']?? null;
if($status_2 != null){
$status = "Offline";
}

$status_3 = $status['by_me']?? null;
if($status_3 != null){
$status = "Last Leen";
}

}

$lang_code = $me['lang_code']?? null;
if($lang_code == null){	
$lang_code = "(null)";
}

try {
$usernames = $me['usernames']?? null;
$newLangsCommausername = null;
$peerList2username = [];
foreach ($usernames as $username) {
$usernamexfr = $username['username'];
$usernamexfr = "@".$usernamexfr;
$peerList2username[]=$usernamexfr;
}
$newLangsCommausername = implode(" ", $peerList2username);
}catch (\danog\MadelineProto\Exception $e) {
} catch (\danog\MadelineProto\RPCErrorException $e) {
}
$username = $me['username']?? null;
if($username == null){	
if($newLangsCommausername != null){
$username = $newLangsCommausername;
}else{
$username = "(null)";
}
}else{
$username = "@".$username;
}

    if (file_exists("data/$userid/logindate.txt")) { 
$startdate = Amp\File\read("data/$userid/logindate.txt");  
}else{
$startdate = "(null)"; 
}

$bot_API_markup[] = [['text'=>"🌐 Set a Proxy",'callback_data'=>"soon"]];
$bot_API_markup[] = [['text'=>"📴 Logout 🛑",'callback_data'=>"Logout"]];

$message = "
╭─────────────────╮
│     👤 <b>YOUR ACCOUNT</b> 📱
├─────────────────
│ 🔘 <b>Status:</b> connected ✅ 
├─────────────────
│ 📱  <b>PHONE:</b> $phone
├─────────────────
│ 🆔  <b>ID:</b> $id
│ 🎭  <b>USERNAME:</b> $username
│ 👤  <b>FIRST NAME:</b> $first_name
│ 👤  <b>LAST NAME:</b> $last_name
├─────────────────
$premium
├─────────────────
│ ℹ️  <b>Login on:</b> $startdate
╰─────────────────╯
";
}else{
        try { $MadelineProtosession->logout(); } catch (\Throwable $e) {}
            self::deleteSessionFolder($sessionDir);
$message = "<b>Your account has been successfully logged out.</b>";

    }

	}

} catch (\Throwable $e) {
$message = "❌ You do not have an account connected.";
}

$bot_API_markup[] = [['text'=>"🔙 Back",'callback_data'=>"backmenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = $message, $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) {}
}
#[FilterButtonQueryData('Logout')]
public function logoutCommand(callbackQuery $query) {
try {
$userid = $query->userId;
$msgid = $query->messageId;  

$sessionDir = "data/$userid/user.madeline";
try {

    $invalid = false;
    $ipcState = "$sessionDir/ipcState.php";

    if (!is_dir($sessionDir)) {
        $invalid = true;
    } elseif (!is_file($ipcState) || !is_readable($ipcState)) {
        $invalid = true;
    } else {

        $content = \amp\File\read($ipcState);

        if (
            str_contains($content, 'ExitFailure') &&
            time() - filemtime($ipcState) > 20
        ) {
            $invalid = true;
        }
    }

    if ($invalid) {
$message = "❌ You do not have an account connected.";
            self::deleteSessionFolder($sessionDir);
    } else {

        $MadelineProtosession = new \danog\MadelineProto\API($sessionDir);
        try { $MadelineProtosession->logout(); } catch (\Throwable $e) {}
		
            self::deleteSessionFolder($sessionDir);
$message = "<b>Your account has been successfully logged out.</b>";

    }

} catch (\Throwable $e) {
$message = "❌ You do not have an account connected.";
}

$bot_API_markup[] = [['text'=>"🔙 Back",'callback_data'=>"backmenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = $message, $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = true, $scheduleDate = NULL);

} catch (Throwable $e) {}
}

/* ========= Message Handlers ========= */
#[Handler]
public function handlegetmsg(Incoming & PrivateMessage $message): void {		
try {
if ($this->isSelfBot()) {
try {

$messagetext = $message->message;
$entities = $message->entities;
$messagefile = $message->media;
$messageid = $message->id;
$senderid = $message->senderId;
$User_Full = $this->getInfo($message->senderId);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}
$last_name = $User_Full['User']['last_name']?? null;
if($last_name == null){
$last_name = "null";
}
$username = $User_Full['User']['username']?? null;
if($username == null){
$username = "null";
}

$inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $messageid];
	
if(!preg_match('/^\/([Ss]tart)/',$messagetext)){

if(preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i',$messagetext)){

$isadmin = true;

$sessionDir = "data/$senderid/user.madeline";
$session1 = false;
$mepremium = false;
try {

    $invalid = false;
    $ipcState = "$sessionDir/ipcState.php";

    if (!is_dir($sessionDir)) {
        $invalid = true;
    } elseif (!is_file($ipcState) || !is_readable($ipcState)) {
        $invalid = true;
    } else {

        $content = \amp\File\read($ipcState);

        if (
            str_contains($content, 'ExitFailure') &&
            time() - filemtime($ipcState) > 20
        ) {
            $invalid = true;
        }
    }

    if ($invalid) {
    } else {

        $MadelineProtosession = new \danog\MadelineProto\API($sessionDir);
if ($MadelineProtosession->getAuthorization() === API::LOGGED_IN) {
$session1 = true;
$mepremium = $MadelineProtosession->getSelf()['premium']?? false;
}else{
        try { $MadelineProtosession->logout(); } catch (\Throwable $e) {}
            self::deleteSessionFolder($sessionDir);
    }

	}

} catch (\Throwable $e) {}

$today = date("d-m-Y H:i"); 
    if (!file_exists("data/$senderid/time_limit.txt")) {
$zmanxcheck = time(); 
	}
    if (file_exists("data/$senderid/time_limit.txt")) {	
$zmanxcheck = Amp\File\read("data/$senderid/time_limit.txt"); 
	}
	
	
if(time() >= $zmanxcheck){	
try{
if (!file_exists("data/$senderid/time_limit.txt")) {
$zmanx = time()+10;
$zmanx1 = (string) $zmanx;
Amp\File\write("data/$senderid/time_limit.txt", $zmanx1); 
	}
if (file_exists("data/$senderid/time_limit.txt")) {
$zmanx = time()+10;
$zmanx1 = (string) $zmanx;
Amp\File\write("data/$senderid/time_limit.txt", $zmanx1); 
}
} catch (\Throwable $exception) {}

$VAR_SENT = false; 

if($session1 == true){
$MadelineProto = new \danog\MadelineProto\API($sessionDir);
$me_session_id = $MadelineProto->getSelf()['id'];

if($isadmin != false){
if (!preg_match('/^http(s)?:\/\/t\.me\/.+\/?$/i', $messagetext)) {
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "Unsupported format!
For all supported formats /help", parse_mode: 'HTML');
}
if (preg_match('/^http(s)?:\/\/t\.me\/.+\/?$/i', $messagetext)) {

if(!function_exists("extractTelegramPaths")){
function extractTelegramPaths($url) {

  $path = parse_url($url, PHP_URL_PATH);

  if (empty($path)) {
    return null; 
  }
  
$segments = explode('/', trim($path, '/'));


  $out1 = isset($segments[0]) ? $segments[0] : null;
  $out2 = isset($segments[1]) ? $segments[1] : null;
  $out3 = isset($segments[2]) ? $segments[2] : null;
  $out4 = isset($segments[3]) ? $segments[3] : null;
  $out5 = isset($segments[4]) ? $segments[4] : null;
  
  return [
    'out1' => $out1,
    'out2' => $out2,
    'out3' => $out3,
    'out4' => $out4,
	'out5' => $out5,
  ];
}
}
$result1 = extractTelegramPaths($messagetext);
if ($result1 === null) {
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "Unsupported format!
For all supported formats /help", parse_mode: 'HTML');
} else {
$out1 = $result1['out1'] ?? null; 
$out2 = $result1['out2'] ?? null; 
$out3 = $result1['out3'] ?? null; 
$out4 = $result1['out4'] ?? null; 
$out5 = $result1['out5'] ?? null; 


if(!preg_match('/^\+/',$out1)){	
	
if ($out5 != null) {
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "Unsupported format!
For all supported formats /help", parse_mode: 'HTML');
}else{
if ($out1 === 'c' || $out1 === 'C') {

if($out4 != null){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "Unsupported format!
For all supported formats /help", parse_mode: 'HTML');
}else{
try {
$out1 = $result1['out1'] ?? null; //username
$out2 = $result1['out2'] ?? null; //id
$out3 = $result1['out3'] ?? null; //id

$usernamex = $out2;
$numbersx = $out3;

try {
$User_Full = $MadelineProto->getInfo("$usernamex");
$type = $User_Full['type']?? null;
} catch (Throwable $e) {
$type = 'channel';	
}

if($type == 'channel'){
$messages_Messages = $MadelineProto->channels->getMessages(channel: "-100$usernamex", id: [$numbersx], );
}
if($type != 'channel'){
$messages_Messages = $MadelineProto->channels->getMessages(channel: "-$usernamex", id: [$numbersx], );
}

$msgvar = $messages_Messages['messages'][0] ?? [];
    if (($msgvar['_'] ?? null) !== 'message') {
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ MESSAGE_EMPTY</i>", parse_mode: 'HTML');
    return;
    }

$messages_Messagesxtext = $messages_Messages['messages'][0]['message']?? null;
if($messages_Messagesxtext == null){
//$messages_Messagesxtext = "null";
}
$messages_Messagesxent = $messages_Messages['messages'][0]['entities']?? null;
//------
$messages_Messagesxmedia = $messages_Messages['messages'][0]['media']?? null;
//------
$messages_Messagesxmediaphoto = $messages_Messagesxmedia['photo']?? null;
$messages_Messagesxmediaphoto322 = $messages_Messagesxmedia['poll']?? null;
$messages_Messagesxmediaphoto3r = $messages_Messagesxmedia['round']?? null;
//------
$messages_Messagesxmediageo = $messages_Messages['messages'][0]['media']['geo']?? null;
$messages_Messagesxmediageolong = $messages_Messages['messages'][0]['media']['geo']['long']?? null;
$messages_Messagesxmediageolat = $messages_Messages['messages'][0]['media']['geo']['lat']?? null;
$messages_Messagesxmediageoaccess_hash = $messages_Messages['messages'][0]['media']['geo']['access_hash']?? null;
$messages_Messagesxmediageoaccuracy_radius = $messages_Messages['messages'][0]['media']['geo']['accuracy_radius']?? null;
//------
$inputGeoPoint = ['_' => 'inputGeoPoint', 'lat' => $messages_Messagesxmediageolat, 'long' => $messages_Messagesxmediageolong, 'accuracy_radius' => $messages_Messagesxmediageoaccuracy_radius];
$inputMediaGeoPoint = ['_' => 'inputMediaGeoPoint', 'geo_point' => $inputGeoPoint];
//------
$messages_Messagesxmediaphone_number = $messages_Messages['messages'][0]['media']['phone_number']?? null;
$messages_Messagesxmediafirst_name = $messages_Messages['messages'][0]['media']['first_name']?? null;
$messages_Messagesxmedialast_name = $messages_Messages['messages'][0]['media']['last_name']?? null;
$messages_Messagesxmediavcard = $messages_Messages['messages'][0]['media']['vcard']?? null;
$messages_Messagesuser_id = $messages_Messages['messages'][0]['media']['user_id']?? null;
//------
$inputMediaContact = ['_' => 'inputMediaContact', 'phone_number' => "$messages_Messagesxmediaphone_number", 'first_name' => "$messages_Messagesxmediafirst_name", 'last_name' => "$messages_Messagesxmedialast_name", 'vcard' => "$messages_Messagesxmediavcard"];
//------
$messages_Messagesxmediawebpae = $messages_Messages['messages'][0]['media']['webpage']?? null;
//------
$messages_Messagereply_markup = $messages_Messages['messages'][0]['reply_markup']['rows']?? null;
$bot_API_markup_output = $messages_Messages['messages'][0]['reply_markup']?? null;
//------

if($messages_Messagesxent == null){
$messages_Messagesxtext = "$messages_Messagesxtext";
}else{
$messages_Messagesxtext = "$messages_Messagesxtext";
}	

try { 

if($messages_Messagereply_markup != null){
}else{
$messages_Messagereply_markup = null;
}

if($messages_Messagesxmedia == null){
$messageLengthvartxt = mb_strlen($messages_Messagesxtext);

try {
$sentMessage = $MadelineProto->messages->sendMessage(peer: $me_session_id, message: "$messages_Messagesxtext", entities: $messages_Messagesxent);

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];
$this->messages->sendMessage(peer: $senderid, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

}else{
$messageLengthvartxt = mb_strlen($messages_Messagesxtext);

$info = $MadelineProto->getDownloadInfo($messages_Messagesxmedia);
$nameoffile = $info['name'];
$extfile = $info['ext'];
$extfile2 = $info['size'];

  if (!function_exists('bytesToMegabytes')) {
         function bytesToMegabytes($bytes) {
             $megabytes = $bytes / 1048576; // 1024 * 1024
             return round($megabytes, 2);
         }
     }
	 
$fileSizeInBytes = $extfile2; 
$fileSizeInMegabytes = bytesToMegabytes($fileSizeInBytes);
$filesizex = "File size: " . $fileSizeInMegabytes . " MB";

if($mepremium === false){
$maxBytes = 2 * 1024 * 1024 * 1024;
if($fileSizeInBytes > $maxBytes){
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ for 2GB+ need premium.</i>", parse_mode: 'HTML');
    return;
}

}

$botAPI_file = $MadelineProto->MTProtoToBotAPI($messages_Messagesxmedia);

        $fileId = null;
        $thumbFileId = null;
        $duration = null;
        $width = null;
        $height = null;
        $method = null;
		
foreach (['audio', 'document', 'photo', 'sticker', 'video', 'voice', 'video_note', 'animation'] as $type) {
    if (isset($botAPI_file[$type]) && is_array($botAPI_file[$type])) {
        $method = $type;
		
                if ($type === 'photo') {
                    $bestPhoto = end($botAPI_file['photo']);
                    $fileId = $bestPhoto['file_id'] ?? null;
                    $width = $bestPhoto['width'] ?? null;
                    $height = $bestPhoto['height'] ?? null;
					
                } else {
                    $file = $botAPI_file[$type];
                    $fileId = $file['file_id'] ?? null;

                    if (in_array($type, ['video', 'animation', 'video_note', 'audio', 'voice', 'document'])) {
                        $duration = $file['duration'] ?? null;
                        $width = $file['width'] ?? null;
                        $height = $file['height'] ?? null;
                        if (isset($file['thumb']['file_id'])) {
                            $thumbFileId = $file['thumb']['file_id'];
                        }
                    }
                }
        break;
}
}
$result['file_type'] = $method;

$resultx = [
            'bot_api_file_id' => $fileId,
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
            'thumb_file_id' => $thumbFileId
        ];

        try { unlink(__DIR__."/"."data/$senderid/thumb.jpg"); } catch (\Throwable $e) {}
        if ($thumbFileId && in_array($result['file_type'], ['video', 'animation', 'video_note', 'audio', 'voice', 'document'])) {
            \Amp\async(function () use ($thumbFileId, $senderid, $MadelineProto) {
                try {
                    $MadelineProto->downloadToFile($thumbFileId, __DIR__."/"."data/$senderid/thumb.jpg");
                } catch (\Throwable $e) {}
            });
        }

  if (!function_exists('createProgressBar')) {
function createProgressBar($count) {
/*
    $count = max(0, min(100, $count));
    

    $totalLength = 20;
    $filledLength = (int) ($totalLength * ($count / 100));
    

    $progressBar = '[' . str_repeat('█', $filledLength) . str_repeat('░', $totalLength - $filledLength) . ']';

*/



    $count = max(0, min(100, $count)); // Ensure $count is within 0-100
    $totalLength = 10; // Fixed length of the progress bar

    // Determine the number of filled and empty slots
    $filledLength = (int) ($totalLength * ($count / 100));
    $emptyLength = $totalLength - $filledLength;

    // Determine color based on percentage
    if ($count <= 30) {
        $filledChar = '🔴';
    } elseif ($count <= 60) {
        $filledChar = '🟠';
    } elseif ($count <= 90) {
        $filledChar = '🟡';
    } else {
        $filledChar = '🟢';
    }
    $emptyChar = '🔘';

    // Create the progress bar string
    $progressBar = '[' . str_repeat($filledChar, $filledLength) . str_repeat($emptyChar, $emptyLength) . ']';
	
	
	
	
	
    return $progressBar;
}
}
   if (!function_exists('createProgressBar2')) {
function createProgressBar2($count2) {
    // Ensure $count is between 0 and 100.
$count = max(0, min(100, $count2));
$totalLength = 20;
$filledLength = (int) ($totalLength * ($count2 / 100));
$progressBar2 = " (" . number_format($count, 1) . "%)";

    // Append percentage with one decimal place.
    return $progressBar2;
}
} 

try {

if ($result['file_type'] == 'photo') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update1 = 0;
$current_time = time();

if ($current_time - $last_update1 >= 5 || $progress == 100) {
$last_update1 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);


try {
$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => "$me_session_id",
    'media' => [
        '_' => 'inputMediaUploadedPhoto',
        'file' => new \danog\MadelineProto\FileCallback(
            "data/$nameoffile$extfile",
            function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
	   
static $last_update2 = 0;
$current_time = time();

if ($current_time - $last_update2 >= 5 || $progress == 100) {
$last_update2 = $current_time;
	
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "🚀 Upload progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}


            }
        )
    ],
   'message' => "$messages_Messagesxtext", 'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
} catch (\Throwable $e) {}
	  
}

if ($result['file_type'] == 'video') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update3 = 0;	
$current_time = time();

if ($current_time - $last_update3 >= 5 || $progress == 100) {
$last_update3 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {

$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeVideo',
    'round_message' => false,
    'supports_streaming' => true,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
if ($width !== null) {
    $attributes['w'] = (int) $width;
}
if ($height !== null) {
    $attributes['h'] = (int) $height;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}

$VAR_SENT = true;
} catch (\Throwable $e) {
$this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "❌ $error"]);
}


try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}




}

if ($result['file_type'] == 'animation') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update5 = 0;	
$current_time = time();

if ($current_time - $last_update5 >= 5 || $progress == 100) {
$last_update5 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
	
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";

$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [['_' => 'documentAttributeAnimated']],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}


}

if ($result['file_type'] == 'video_note') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update7 = 0;	
$current_time = time();

if ($current_time - $last_update7 >= 5 || $progress == 100) {
$last_update7 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeVideo',
    'round_message' => true,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
if ($width !== null) {
    $attributes['w'] = (int) $width;
}
if ($height !== null) {
    $attributes['h'] = (int) $height;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);


try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'audio') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update9 = 0;
$current_time = time();

if ($current_time - $last_update9 >= 5 || $progress == 100) {
$last_update9 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeAudio',
    'voice' => false,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'voice') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);


$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update11 = 0;
$current_time = time();

if ($current_time - $last_update11 >= 5 || $progress == 100) {
$last_update11 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeAudio',
    'voice' => true,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'document') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update13 = 0;
$current_time = time();

if ($current_time - $last_update13 >= 5 || $progress == 100) {
$last_update13 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeFilename',
    'file_name' => "$nameoffile$extfile"
];

$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'sticker') {

try{
$sentMessage = $MadelineProto->messages->sendMedia([
    'peer' => $senderid,
    'media' => $messages_Messagesxmedia,
   'message' => "$messages_Messagesxtext", 'entities' => $messages_Messagesxent,
]);
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->sendMessage(peer: $senderid, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}
}

}
finally {
if (file_exists("data/$nameoffile$extfile")) {
try { unlink(__DIR__."/"."data/$nameoffile$extfile"); } catch (\Throwable $e) {}
}
if (file_exists(__DIR__."/"."data/$senderid/thumb.jpg")) {
try { unlink(__DIR__."/"."data/$senderid/thumb.jpg"); } catch (\Throwable $e) {}
}
}

}

} catch (Throwable $e) {
$error = $e->getMessage();
$estring = (string) $e;

if(preg_match("/messageMediaWebPage/",$estring)){

try {
$sentMessage = $MadelineProto->messages->sendMessage(peer: $me_session_id, message: "$messages_Messagesxtext", entities: $messages_Messagesxent);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];
$this->messages->sendMessage(peer: $senderid, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

}

if(preg_match("/messageMedia/",$estring)){

if(!preg_match("/messageMediaWebPage/",$estring)){
if(!preg_match("/messageMediaGeo/",$estring)){
if(!preg_match("/messageMediaPaidMedia/",$estring)){
if(!preg_match("/messageMediaContact/",$estring)){
	
$sentMessagex = $this->messages->sendMessage(['peer' => $senderid, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

try{
$sentMessagese = $MadelineProto->messages->sendMedia(peer: "$me_session_id", message: "$messages_Messagesxtext", media: $messages_Messagesxmedia );
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

}
}
}
}

if(preg_match("/messageMediaGeo/",$estring)){

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

try{
$sentMessagese = $MadelineProto->messages->sendMedia(peer: "$me_session_id", message: "$messages_Messagesxtext", media: $inputMediaGeoPoint );
$sentMessage22se = $this->extractMessageId($sentMessagese);
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


}

if(preg_match("/messageMediaPaidMedia/",$estring)){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "ERROR: messageMediaPaidMedia");
}

if(preg_match("/messageMediaContact/",$estring)){


$sentMessagex = $this->messages->sendMessage(['peer' => $senderid, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

try{
$sentMessagese = $MadelineProto->messages->sendMedia(peer: "$me_session_id", message: "$messages_Messagesxtext", media: $inputMediaContact );
$sentMessage22se = $this->extractMessageId($sentMessagese);
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


}

}

if(!$VAR_SENT){
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');
}

} catch (\danog\MadelineProto\RPCError\FileReferenceExpiredError $e) {
if (file_exists("data/$nameoffile$extfile")) {
try { unlink(__DIR__."/"."data/$nameoffile$extfile"); } catch (\Throwable $e) {}
}

}

} catch (Throwable $e) {
$error = $e->getMessage();
if(!$VAR_SENT){
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');
}

}
}

}elseif($out1 === 'b' || $out1 === 'B') {

try {
$out1 = $result1['out1'] ?? null; //username
$out2 = $result1['out2'] ?? null; //id
$out3 = $result1['out3'] ?? null; //id

$numbersx = $out3;

$messages_Messages = $MadelineProto->messages->getMessages(id: [$numbersx], );

$msgvar = $messages_Messages['messages'][0] ?? [];
    if (($msgvar['_'] ?? null) !== 'message') {
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ MESSAGE_EMPTY</i>", parse_mode: 'HTML');
    return;
    }

$messages_Messagesxtext = $messages_Messages['messages'][0]['message']?? null;
if($messages_Messagesxtext == null){
//$messages_Messagesxtext = "null";
}
$messages_Messagesxent = $messages_Messages['messages'][0]['entities']?? null;

$messages_Messagesxmedia = $messages_Messages['messages'][0]['media']?? null;

$messages_Messagesxmediaphoto = $messages_Messagesxmedia['photo']?? null;
$messages_Messagesxmediaphoto322 = $messages_Messagesxmedia['poll']?? null;
$messages_Messagesxmediaphoto3r = $messages_Messagesxmedia['round']?? null;

$messages_Messagesxmediageo = $messages_Messages['messages'][0]['media']['geo']?? null;
$messages_Messagesxmediageolong = $messages_Messages['messages'][0]['media']['geo']['long']?? null;
$messages_Messagesxmediageolat = $messages_Messages['messages'][0]['media']['geo']['lat']?? null;
$messages_Messagesxmediageoaccess_hash = $messages_Messages['messages'][0]['media']['geo']['access_hash']?? null;
$messages_Messagesxmediageoaccuracy_radius = $messages_Messages['messages'][0]['media']['geo']['accuracy_radius']?? null;
//------
$inputGeoPoint = ['_' => 'inputGeoPoint', 'lat' => $messages_Messagesxmediageolat, 'long' => $messages_Messagesxmediageolong, 'accuracy_radius' => $messages_Messagesxmediageoaccuracy_radius];
$inputMediaGeoPoint = ['_' => 'inputMediaGeoPoint', 'geo_point' => $inputGeoPoint];

$messages_Messagesxmediaphone_number = $messages_Messages['messages'][0]['media']['phone_number']?? null;
$messages_Messagesxmediafirst_name = $messages_Messages['messages'][0]['media']['first_name']?? null;
$messages_Messagesxmedialast_name = $messages_Messages['messages'][0]['media']['last_name']?? null;
$messages_Messagesxmediavcard = $messages_Messages['messages'][0]['media']['vcard']?? null;
$messages_Messagesuser_id = $messages_Messages['messages'][0]['media']['user_id']?? null;
//------
$inputMediaContact = ['_' => 'inputMediaContact', 'phone_number' => "$messages_Messagesxmediaphone_number", 'first_name' => "$messages_Messagesxmediafirst_name", 'last_name' => "$messages_Messagesxmedialast_name", 'vcard' => "$messages_Messagesxmediavcard"];


$messages_Messagereply_markup = $messages_Messages['messages'][0]['reply_markup']['rows']?? null;
$bot_API_markup_output = $messages_Messages['messages'][0]['reply_markup']?? null;

if($messages_Messagesxent == null){
$messages_Messagesxtext = "$messages_Messagesxtext";
}else{
$messages_Messagesxtext = "$messages_Messagesxtext";
}	

try { 

if($messages_Messagereply_markup != null){
}else{
$messages_Messagereply_markup = null;
}

if($messages_Messagesxmedia == null){
$messageLengthvartxt = mb_strlen($messages_Messagesxtext);

try {
$sentMessage = $MadelineProto->messages->sendMessage(peer: $me_session_id, message: "$messages_Messagesxtext", entities: $messages_Messagesxent);

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];
$this->messages->sendMessage(peer: $senderid, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

}else{
$messageLengthvartxt = mb_strlen($messages_Messagesxtext);

$info = $MadelineProto->getDownloadInfo($messages_Messagesxmedia);
$nameoffile = $info['name'];
$extfile = $info['ext'];
$extfile2 = $info['size'];

  if (!function_exists('bytesToMegabytes')) {
         function bytesToMegabytes($bytes) {
             $megabytes = $bytes / 1048576; // 1024 * 1024
             return round($megabytes, 2);
         }
     }
	 
$fileSizeInBytes = $extfile2; 
$fileSizeInMegabytes = bytesToMegabytes($fileSizeInBytes);
$filesizex = "File size: " . $fileSizeInMegabytes . " MB";

if($mepremium === false){
$maxBytes = 2 * 1024 * 1024 * 1024;
if($fileSizeInBytes > $maxBytes){
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ for 2GB+ need premium.</i>", parse_mode: 'HTML');
    return;
}

}

$botAPI_file = $MadelineProto->MTProtoToBotAPI($messages_Messagesxmedia);

        $fileId = null;
        $thumbFileId = null;
        $duration = null;
        $width = null;
        $height = null;
        $method = null;
		
foreach (['audio', 'document', 'photo', 'sticker', 'video', 'voice', 'video_note', 'animation'] as $type) {
    if (isset($botAPI_file[$type]) && is_array($botAPI_file[$type])) {
        $method = $type;
		
                if ($type === 'photo') {
                    $bestPhoto = end($botAPI_file['photo']);
                    $fileId = $bestPhoto['file_id'] ?? null;
                    $width = $bestPhoto['width'] ?? null;
                    $height = $bestPhoto['height'] ?? null;
					
                } else {
                    $file = $botAPI_file[$type];
                    $fileId = $file['file_id'] ?? null;

                    if (in_array($type, ['video', 'animation', 'video_note', 'audio', 'voice', 'document'])) {
                        $duration = $file['duration'] ?? null;
                        $width = $file['width'] ?? null;
                        $height = $file['height'] ?? null;
                        if (isset($file['thumb']['file_id'])) {
                            $thumbFileId = $file['thumb']['file_id'];
                        }
                    }
                }
        break;
}
}
$result['file_type'] = $method;

$resultx = [
            'bot_api_file_id' => $fileId,
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
            'thumb_file_id' => $thumbFileId
        ];

        try { unlink(__DIR__."/"."data/$senderid/thumb.jpg"); } catch (\Throwable $e) {}
        if ($thumbFileId && in_array($result['file_type'], ['video', 'animation', 'video_note', 'audio', 'voice', 'document'])) {
            \Amp\async(function () use ($thumbFileId, $senderid, $MadelineProto) {
                try {
                    $MadelineProto->downloadToFile($thumbFileId, __DIR__."/"."data/$senderid/thumb.jpg");
                } catch (\Throwable $e) {}
            });
        }


  if (!function_exists('createProgressBar')) {
function createProgressBar($count) {
/*
    $count = max(0, min(100, $count));
    

    $totalLength = 20;
    $filledLength = (int) ($totalLength * ($count / 100));
    

    $progressBar = '[' . str_repeat('█', $filledLength) . str_repeat('░', $totalLength - $filledLength) . ']';

*/



    $count = max(0, min(100, $count)); // Ensure $count is within 0-100
    $totalLength = 10; // Fixed length of the progress bar

    // Determine the number of filled and empty slots
    $filledLength = (int) ($totalLength * ($count / 100));
    $emptyLength = $totalLength - $filledLength;

    // Determine color based on percentage
    if ($count <= 30) {
        $filledChar = '🔴';
    } elseif ($count <= 60) {
        $filledChar = '🟠';
    } elseif ($count <= 90) {
        $filledChar = '🟡';
    } else {
        $filledChar = '🟢';
    }
    $emptyChar = '🔘';

    // Create the progress bar string
    $progressBar = '[' . str_repeat($filledChar, $filledLength) . str_repeat($emptyChar, $emptyLength) . ']';
	
	
	
	
	
    return $progressBar;
}
}
   if (!function_exists('createProgressBar2')) {
function createProgressBar2($count2) {
    // Ensure $count is between 0 and 100.
$count = max(0, min(100, $count2));
$totalLength = 20;
$filledLength = (int) ($totalLength * ($count2 / 100));
$progressBar2 = " (" . number_format($count, 1) . "%)";

    // Append percentage with one decimal place.
    return $progressBar2;
}
} 

try {
if ($result['file_type'] == 'photo') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update1 = 0;
$current_time = time();

if ($current_time - $last_update1 >= 5 || $progress == 100) {
$last_update1 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);


try {
$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => "$me_session_id",
    'media' => [
        '_' => 'inputMediaUploadedPhoto',
        'file' => new \danog\MadelineProto\FileCallback(
            "data/$nameoffile$extfile",
            function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
	   
static $last_update2 = 0;
$current_time = time();

if ($current_time - $last_update2 >= 5 || $progress == 100) {
$last_update2 = $current_time;
	
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "🚀 Upload progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}


            }
        )
    ],
   'message' => "$messages_Messagesxtext", 'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
} catch (\Throwable $e) {}
	  
}

if ($result['file_type'] == 'video') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update3 = 0;	
$current_time = time();

if ($current_time - $last_update3 >= 5 || $progress == 100) {
$last_update3 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {

$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeVideo',
    'round_message' => false,
    'supports_streaming' => true,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
if ($width !== null) {
    $attributes['w'] = (int) $width;
}
if ($height !== null) {
    $attributes['h'] = (int) $height;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}

$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}




}

if ($result['file_type'] == 'animation') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update5 = 0;	
$current_time = time();

if ($current_time - $last_update5 >= 5 || $progress == 100) {
$last_update5 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
	
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";

$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [['_' => 'documentAttributeAnimated']],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}


}

if ($result['file_type'] == 'video_note') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update7 = 0;	
$current_time = time();

if ($current_time - $last_update7 >= 5 || $progress == 100) {
$last_update7 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeVideo',
    'round_message' => true,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
if ($width !== null) {
    $attributes['w'] = (int) $width;
}
if ($height !== null) {
    $attributes['h'] = (int) $height;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);


try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'audio') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update9 = 0;
$current_time = time();

if ($current_time - $last_update9 >= 5 || $progress == 100) {
$last_update9 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeAudio',
    'voice' => false,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'voice') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);


$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update11 = 0;
$current_time = time();

if ($current_time - $last_update11 >= 5 || $progress == 100) {
$last_update11 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeAudio',
    'voice' => true,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'document') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update13 = 0;
$current_time = time();

if ($current_time - $last_update13 >= 5 || $progress == 100) {
$last_update13 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeFilename',
    'file_name' => "$nameoffile$extfile"
];

$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'sticker') {

try{
$sentMessage = $MadelineProto->messages->sendMedia([
    'peer' => $senderid,
    'media' => $messages_Messagesxmedia,
   'message' => "$messages_Messagesxtext", 'entities' => $messages_Messagesxent,
]);
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->sendMessage(peer: $senderid, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}
}

}
finally {
if (file_exists("data/$nameoffile$extfile")) {
try { unlink(__DIR__."/"."data/$nameoffile$extfile"); } catch (\Throwable $e) {}
}
if (file_exists(__DIR__."/"."data/$senderid/thumb.jpg")) {
try { unlink(__DIR__."/"."data/$senderid/thumb.jpg"); } catch (\Throwable $e) {}
}
}

}

} catch (Throwable $e) {
$error = $e->getMessage();
$estring = (string) $e;

if(preg_match("/messageMediaWebPage/",$estring)){

try {
$sentMessage = $MadelineProto->messages->sendMessage(peer: $me_session_id, message: "$messages_Messagesxtext", entities: $messages_Messagesxent);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];
$this->messages->sendMessage(peer: $senderid, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

}

if(preg_match("/messageMedia/",$estring)){

if(!preg_match("/messageMediaWebPage/",$estring)){
if(!preg_match("/messageMediaGeo/",$estring)){
if(!preg_match("/messageMediaPaidMedia/",$estring)){
if(!preg_match("/messageMediaContact/",$estring)){
	
$sentMessagex = $this->messages->sendMessage(['peer' => $senderid, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

try{
$sentMessagese = $MadelineProto->messages->sendMedia(peer: "$me_session_id", message: "$messages_Messagesxtext", media: $messages_Messagesxmedia );
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

}
}
}
}

if(preg_match("/messageMediaGeo/",$estring)){

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

try{
$sentMessagese = $MadelineProto->messages->sendMedia(peer: "$me_session_id", message: "$messages_Messagesxtext", media: $inputMediaGeoPoint );
$sentMessage22se = $this->extractMessageId($sentMessagese);
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


}

if(preg_match("/messageMediaPaidMedia/",$estring)){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "ERROR: messageMediaPaidMedia");
}

if(preg_match("/messageMediaContact/",$estring)){


$sentMessagex = $this->messages->sendMessage(['peer' => $senderid, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

try{
$sentMessagese = $MadelineProto->messages->sendMedia(peer: "$me_session_id", message: "$messages_Messagesxtext", media: $inputMediaContact );
$sentMessage22se = $this->extractMessageId($sentMessagese);
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


}

}

if(!$VAR_SENT){
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');
}

} catch (\danog\MadelineProto\RPCError\FileReferenceExpiredError $e) {
if (file_exists("data/$nameoffile$extfile")) {
unlink("data/$nameoffile$extfile");
}

}

} catch (Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');

}




}elseif($out1 === 'u' || $out1 === 'U') {

try {
$out1 = $result1['out1'] ?? null; //username
$out2 = $result1['out2'] ?? null; //id
$out3 = $result1['out3'] ?? null; //id

$numbersx = $out3;

$messages_Messages = $MadelineProto->messages->getMessages(id: [$numbersx], );

$msgvar = $messages_Messages['messages'][0] ?? [];
    if (($msgvar['_'] ?? null) !== 'message') {
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ MESSAGE_EMPTY</i>", parse_mode: 'HTML');
    return;
    }

$messages_Messagesxtext = $messages_Messages['messages'][0]['message']?? null;
if($messages_Messagesxtext == null){
//$messages_Messagesxtext = "null";
}
$messages_Messagesxent = $messages_Messages['messages'][0]['entities']?? null;

$messages_Messagesxmedia = $messages_Messages['messages'][0]['media']?? null;

$messages_Messagesxmediaphoto = $messages_Messagesxmedia['photo']?? null;
$messages_Messagesxmediaphoto322 = $messages_Messagesxmedia['poll']?? null;
$messages_Messagesxmediaphoto3r = $messages_Messagesxmedia['round']?? null;

$messages_Messagesxmediageo = $messages_Messages['messages'][0]['media']['geo']?? null;
$messages_Messagesxmediageolong = $messages_Messages['messages'][0]['media']['geo']['long']?? null;
$messages_Messagesxmediageolat = $messages_Messages['messages'][0]['media']['geo']['lat']?? null;
$messages_Messagesxmediageoaccess_hash = $messages_Messages['messages'][0]['media']['geo']['access_hash']?? null;
$messages_Messagesxmediageoaccuracy_radius = $messages_Messages['messages'][0]['media']['geo']['accuracy_radius']?? null;
//------
$inputGeoPoint = ['_' => 'inputGeoPoint', 'lat' => $messages_Messagesxmediageolat, 'long' => $messages_Messagesxmediageolong, 'accuracy_radius' => $messages_Messagesxmediageoaccuracy_radius];
$inputMediaGeoPoint = ['_' => 'inputMediaGeoPoint', 'geo_point' => $inputGeoPoint];

$messages_Messagesxmediaphone_number = $messages_Messages['messages'][0]['media']['phone_number']?? null;
$messages_Messagesxmediafirst_name = $messages_Messages['messages'][0]['media']['first_name']?? null;
$messages_Messagesxmedialast_name = $messages_Messages['messages'][0]['media']['last_name']?? null;
$messages_Messagesxmediavcard = $messages_Messages['messages'][0]['media']['vcard']?? null;
$messages_Messagesuser_id = $messages_Messages['messages'][0]['media']['user_id']?? null;
//------
$inputMediaContact = ['_' => 'inputMediaContact', 'phone_number' => "$messages_Messagesxmediaphone_number", 'first_name' => "$messages_Messagesxmediafirst_name", 'last_name' => "$messages_Messagesxmedialast_name", 'vcard' => "$messages_Messagesxmediavcard"];


$messages_Messagereply_markup = $messages_Messages['messages'][0]['reply_markup']['rows']?? null;
$bot_API_markup_output = $messages_Messages['messages'][0]['reply_markup']?? null;

if($messages_Messagesxent == null){
$messages_Messagesxtext = "$messages_Messagesxtext";
}else{
$messages_Messagesxtext = "$messages_Messagesxtext";
}	

try { 

if($messages_Messagereply_markup != null){
}else{
$messages_Messagereply_markup = null;
}

if($messages_Messagesxmedia == null){
$messageLengthvartxt = mb_strlen($messages_Messagesxtext);

try {
$sentMessage = $MadelineProto->messages->sendMessage(peer: $me_session_id, message: "$messages_Messagesxtext", entities: $messages_Messagesxent);

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];
$this->messages->sendMessage(peer: $senderid, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

}else{
$messageLengthvartxt = mb_strlen($messages_Messagesxtext);

$info = $MadelineProto->getDownloadInfo($messages_Messagesxmedia);
$nameoffile = $info['name'];
$extfile = $info['ext'];
$extfile2 = $info['size'];

  if (!function_exists('bytesToMegabytes')) {
         function bytesToMegabytes($bytes) {
             $megabytes = $bytes / 1048576; // 1024 * 1024
             return round($megabytes, 2);
         }
     }
	 
$fileSizeInBytes = $extfile2; 
$fileSizeInMegabytes = bytesToMegabytes($fileSizeInBytes);
$filesizex = "File size: " . $fileSizeInMegabytes . " MB";

if($mepremium === false){
$maxBytes = 2 * 1024 * 1024 * 1024;
if($fileSizeInBytes > $maxBytes){
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ for 2GB+ need premium.</i>", parse_mode: 'HTML');
    return;
}

}

$botAPI_file = $MadelineProto->MTProtoToBotAPI($messages_Messagesxmedia);

        $fileId = null;
        $thumbFileId = null;
        $duration = null;
        $width = null;
        $height = null;
        $method = null;
		
foreach (['audio', 'document', 'photo', 'sticker', 'video', 'voice', 'video_note', 'animation'] as $type) {
    if (isset($botAPI_file[$type]) && is_array($botAPI_file[$type])) {
        $method = $type;
		
                if ($type === 'photo') {
                    $bestPhoto = end($botAPI_file['photo']);
                    $fileId = $bestPhoto['file_id'] ?? null;
                    $width = $bestPhoto['width'] ?? null;
                    $height = $bestPhoto['height'] ?? null;
					
                } else {
                    $file = $botAPI_file[$type];
                    $fileId = $file['file_id'] ?? null;

                    if (in_array($type, ['video', 'animation', 'video_note', 'audio', 'voice', 'document'])) {
                        $duration = $file['duration'] ?? null;
                        $width = $file['width'] ?? null;
                        $height = $file['height'] ?? null;
                        if (isset($file['thumb']['file_id'])) {
                            $thumbFileId = $file['thumb']['file_id'];
                        }
                    }
                }
        break;
}
}
$result['file_type'] = $method;

$resultx = [
            'bot_api_file_id' => $fileId,
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
            'thumb_file_id' => $thumbFileId
        ];

        try { unlink(__DIR__."/"."data/$senderid/thumb.jpg"); } catch (\Throwable $e) {}
        if ($thumbFileId && in_array($result['file_type'], ['video', 'animation', 'video_note', 'audio', 'voice', 'document'])) {
            \Amp\async(function () use ($thumbFileId, $senderid, $MadelineProto) {
                try {
                    $MadelineProto->downloadToFile($thumbFileId, __DIR__."/"."data/$senderid/thumb.jpg");
                } catch (\Throwable $e) {}
            });
        }


  if (!function_exists('createProgressBar')) {
function createProgressBar($count) {
/*
    $count = max(0, min(100, $count));
    

    $totalLength = 20;
    $filledLength = (int) ($totalLength * ($count / 100));
    

    $progressBar = '[' . str_repeat('█', $filledLength) . str_repeat('░', $totalLength - $filledLength) . ']';

*/



    $count = max(0, min(100, $count)); // Ensure $count is within 0-100
    $totalLength = 10; // Fixed length of the progress bar

    // Determine the number of filled and empty slots
    $filledLength = (int) ($totalLength * ($count / 100));
    $emptyLength = $totalLength - $filledLength;

    // Determine color based on percentage
    if ($count <= 30) {
        $filledChar = '🔴';
    } elseif ($count <= 60) {
        $filledChar = '🟠';
    } elseif ($count <= 90) {
        $filledChar = '🟡';
    } else {
        $filledChar = '🟢';
    }
    $emptyChar = '🔘';

    // Create the progress bar string
    $progressBar = '[' . str_repeat($filledChar, $filledLength) . str_repeat($emptyChar, $emptyLength) . ']';
	
	
	
	
	
    return $progressBar;
}
}
   if (!function_exists('createProgressBar2')) {
function createProgressBar2($count2) {
    // Ensure $count is between 0 and 100.
$count = max(0, min(100, $count2));
$totalLength = 20;
$filledLength = (int) ($totalLength * ($count2 / 100));
$progressBar2 = " (" . number_format($count, 1) . "%)";

    // Append percentage with one decimal place.
    return $progressBar2;
}
} 

try {
if ($result['file_type'] == 'photo') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update1 = 0;
$current_time = time();

if ($current_time - $last_update1 >= 5 || $progress == 100) {
$last_update1 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);


try {
$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => "$me_session_id",
    'media' => [
        '_' => 'inputMediaUploadedPhoto',
        'file' => new \danog\MadelineProto\FileCallback(
            "data/$nameoffile$extfile",
            function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
	   
static $last_update2 = 0;
$current_time = time();

if ($current_time - $last_update2 >= 5 || $progress == 100) {
$last_update2 = $current_time;
	
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "🚀 Upload progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}


            }
        )
    ],
   'message' => "$messages_Messagesxtext", 'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
} catch (\Throwable $e) {}
	  
}

if ($result['file_type'] == 'video') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update3 = 0;	
$current_time = time();

if ($current_time - $last_update3 >= 5 || $progress == 100) {
$last_update3 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {

$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeVideo',
    'round_message' => false,
    'supports_streaming' => true,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
if ($width !== null) {
    $attributes['w'] = (int) $width;
}
if ($height !== null) {
    $attributes['h'] = (int) $height;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}

$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}




}

if ($result['file_type'] == 'animation') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update5 = 0;	
$current_time = time();

if ($current_time - $last_update5 >= 5 || $progress == 100) {
$last_update5 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
	
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";

$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [['_' => 'documentAttributeAnimated']],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}


}

if ($result['file_type'] == 'video_note') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update7 = 0;	
$current_time = time();

if ($current_time - $last_update7 >= 5 || $progress == 100) {
$last_update7 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeVideo',
    'round_message' => true,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
if ($width !== null) {
    $attributes['w'] = (int) $width;
}
if ($height !== null) {
    $attributes['h'] = (int) $height;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);


try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'audio') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update9 = 0;
$current_time = time();

if ($current_time - $last_update9 >= 5 || $progress == 100) {
$last_update9 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeAudio',
    'voice' => false,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'voice') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);


$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update11 = 0;
$current_time = time();

if ($current_time - $last_update11 >= 5 || $progress == 100) {
$last_update11 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeAudio',
    'voice' => true,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'document') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update13 = 0;
$current_time = time();

if ($current_time - $last_update13 >= 5 || $progress == 100) {
$last_update13 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeFilename',
    'file_name' => "$nameoffile$extfile"
];

$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'sticker') {

try{
$sentMessage = $MadelineProto->messages->sendMedia([
    'peer' => $senderid,
    'media' => $messages_Messagesxmedia,
   'message' => "$messages_Messagesxtext", 'entities' => $messages_Messagesxent,
]);
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->sendMessage(peer: $senderid, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}
}

}
finally {
if (file_exists("data/$nameoffile$extfile")) {
try { unlink(__DIR__."/"."data/$nameoffile$extfile"); } catch (\Throwable $e) {}
}
if (file_exists(__DIR__."/"."data/$senderid/thumb.jpg")) {
try { unlink(__DIR__."/"."data/$senderid/thumb.jpg"); } catch (\Throwable $e) {}
}
}

}

} catch (Throwable $e) {
$error = $e->getMessage();
$estring = (string) $e;

if(preg_match("/messageMediaWebPage/",$estring)){

try {
$sentMessage = $MadelineProto->messages->sendMessage(peer: $me_session_id, message: "$messages_Messagesxtext", entities: $messages_Messagesxent);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];
$this->messages->sendMessage(peer: $senderid, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

}

if(preg_match("/messageMedia/",$estring)){

if(!preg_match("/messageMediaWebPage/",$estring)){
if(!preg_match("/messageMediaGeo/",$estring)){
if(!preg_match("/messageMediaPaidMedia/",$estring)){
if(!preg_match("/messageMediaContact/",$estring)){
	
$sentMessagex = $this->messages->sendMessage(['peer' => $senderid, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

try{
$sentMessagese = $MadelineProto->messages->sendMedia(peer: "$me_session_id", message: "$messages_Messagesxtext", media: $messages_Messagesxmedia );
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

}
}
}
}

if(preg_match("/messageMediaGeo/",$estring)){

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

try{
$sentMessagese = $MadelineProto->messages->sendMedia(peer: "$me_session_id", message: "$messages_Messagesxtext", media: $inputMediaGeoPoint );
$sentMessage22se = $this->extractMessageId($sentMessagese);
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


}

if(preg_match("/messageMediaPaidMedia/",$estring)){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "ERROR: messageMediaPaidMedia");
}

if(preg_match("/messageMediaContact/",$estring)){


$sentMessagex = $this->messages->sendMessage(['peer' => $senderid, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

try{
$sentMessagese = $MadelineProto->messages->sendMedia(peer: "$me_session_id", message: "$messages_Messagesxtext", media: $inputMediaContact );
$sentMessage22se = $this->extractMessageId($sentMessagese);
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


}

}

if(!$VAR_SENT){
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');
}

} catch (\danog\MadelineProto\RPCError\FileReferenceExpiredError $e) {
if (file_exists("data/$nameoffile$extfile")) {
unlink("data/$nameoffile$extfile");
}

}

} catch (Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');

}

	

}else{

if($out3 != null){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "Unsupported format!
For all supported formats /help", parse_mode: 'HTML');

}else{
try {
$out1 = $result1['out1'] ?? null; //username
$out2 = $result1['out2'] ?? null; //id

if(preg_match("/^[0-9]/",$out1)){
$usernamex = $out1;
$numbersx = $out2;
}else{
$usernamex = "@".$out1;
$numbersx = $out2;
}

try {
$User_Full = $MadelineProto->getInfo($usernamex);
$type = $User_Full['type']?? null;
} catch (\Throwable $e) {
$type = 'channel';	
}

if($type == 'channel'){
try {
	
$messages_Messages = $this->channels->getMessages(channel: "$usernamex", id: [$numbersx], );

$messages_Messagesxtext = $messages_Messages['messages'][0]['message']?? null;
if($messages_Messagesxtext == null){
//$messages_Messagesxtext = "null";
}
$messages_Messagesxent = $messages_Messages['messages'][0]['entities']?? null;

$messages_Messagesxmedia = $messages_Messages['messages'][0]['media']?? null;

if($messages_Messagesxent == null){
$messages_Messagesxtext = "$messages_Messagesxtext";
}else{
$messages_Messagesxtext = "$messages_Messagesxtext";
}	

$messages_Messagesxmediageo = $messages_Messages['messages'][0]['media']['geo']?? null;
$messages_Messagesxmediageolong = $messages_Messages['messages'][0]['media']['geo']['long']?? null;
$messages_Messagesxmediageolat = $messages_Messages['messages'][0]['media']['geo']['lat']?? null;
$messages_Messagesxmediageoaccess_hash = $messages_Messages['messages'][0]['media']['geo']['access_hash']?? null;
$messages_Messagesxmediageoaccuracy_radius = $messages_Messages['messages'][0]['media']['geo']['accuracy_radius']?? null;
//------
$inputGeoPoint = ['_' => 'inputGeoPoint', 'lat' => $messages_Messagesxmediageolat, 'long' => $messages_Messagesxmediageolong, 'accuracy_radius' => $messages_Messagesxmediageoaccuracy_radius];
$inputMediaGeoPoint = ['_' => 'inputMediaGeoPoint', 'geo_point' => $inputGeoPoint];

$messages_Messagesxmediaphone_number = $messages_Messages['messages'][0]['media']['phone_number']?? null;
$messages_Messagesxmediafirst_name = $messages_Messages['messages'][0]['media']['first_name']?? null;
$messages_Messagesxmedialast_name = $messages_Messages['messages'][0]['media']['last_name']?? null;
$messages_Messagesxmediavcard = $messages_Messages['messages'][0]['media']['vcard']?? null;
$messages_Messagesuser_id = $messages_Messages['messages'][0]['media']['user_id']?? null;
//------
$inputMediaContact = ['_' => 'inputMediaContact', 'phone_number' => "$messages_Messagesxmediaphone_number", 'first_name' => "$messages_Messagesxmediafirst_name", 'last_name' => "$messages_Messagesxmedialast_name", 'vcard' => "$messages_Messagesxmediavcard"];


$messages_Messagereply_markup = $messages_Messages['messages'][0]['reply_markup']['rows']?? null;
$bot_API_markup_output = $messages_Messages['messages'][0]['reply_markup']?? null;


if($messages_Messagesxmedia == null){

if($messages_Messagereply_markup == null){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "$messages_Messagesxtext", entities: $messages_Messagesxent);
}
if($messages_Messagereply_markup != null){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "$messages_Messagesxtext", entities: $messages_Messagesxent, reply_markup: $bot_API_markup_output);
}


}else{
try {
if($messages_Messagereply_markup == null){
$sentMessage = $this->messages->sendMedia(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "$messages_Messagesxtext", entities: $messages_Messagesxent, media: $messages_Messagesxmedia);
}
if($messages_Messagereply_markup != null){
$sentMessage = $this->messages->sendMedia(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "$messages_Messagesxtext", entities: $messages_Messagesxent, media: $messages_Messagesxmedia, reply_markup: $bot_API_markup_output);
}

} catch (Throwable $e) {
$error = $e->getMessage();
$estring = (string) $e;

if(preg_match("/messageMediaWebPage/",$estring)){
	
if($messages_Messagereply_markup == null){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "$messages_Messagesxtext", entities: $messages_Messagesxent);
}
if($messages_Messagereply_markup != null){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "$messages_Messagesxtext", entities: $messages_Messagesxent, reply_markup: $bot_API_markup_output);
}

}elseif(preg_match("/poll/",$estring)){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: $error);

}elseif(preg_match("/messageMediaGeo/",$estring)){
$sentMessage = $this->messages->sendMedia(peer: $message->senderId, reply_to: $inputReplyToMessage, media: $inputMediaGeoPoint);

}elseif(preg_match("/messageMediaPaidMedia/",$estring)){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "ERROR: messageMediaPaidMedia");

}elseif(preg_match("/messageMediaContact/",$estring)){
$sentMessage = $this->messages->sendMedia(peer: $message->senderId, reply_to: $inputReplyToMessage, media: $inputMediaContact);

}elseif($error === 'MEDIA_CAPTION_TOO_LONG') {
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ MEDIA_CAPTION_TOO_LONG</i>", parse_mode: 'HTML');

}else{
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');
}

}
}

} catch (Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');
}	
}
if($type != 'channel'){
$messages_Messages = $MadelineProto->channels->getMessages(channel: "$usernamex", id: [$numbersx], );

$msgvar = $messages_Messages['messages'][0] ?? [];
    if (($msgvar['_'] ?? null) !== 'message') {
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ MESSAGE_EMPTY</i>", parse_mode: 'HTML');
    return;
    }

$messages_Messagesxtext = $messages_Messages['messages'][0]['message']?? null;
if($messages_Messagesxtext == null){
//$messages_Messagesxtext = "null";
}
$messages_Messagesxent = $messages_Messages['messages'][0]['entities']?? null;

$messages_Messagesxmedia = $messages_Messages['messages'][0]['media']?? null;

$messages_Messagesxmediaphoto = $messages_Messagesxmedia['photo']?? null;
$messages_Messagesxmediaphoto322 = $messages_Messagesxmedia['poll']?? null;
$messages_Messagesxmediaphoto3r = $messages_Messagesxmedia['round']?? null;

$messages_Messagesxmediageo = $messages_Messages['messages'][0]['media']['geo']?? null;
$messages_Messagesxmediageolong = $messages_Messages['messages'][0]['media']['geo']['long']?? null;
$messages_Messagesxmediageolat = $messages_Messages['messages'][0]['media']['geo']['lat']?? null;
$messages_Messagesxmediageoaccess_hash = $messages_Messages['messages'][0]['media']['geo']['access_hash']?? null;
$messages_Messagesxmediageoaccuracy_radius = $messages_Messages['messages'][0]['media']['geo']['accuracy_radius']?? null;
//------
$inputGeoPoint = ['_' => 'inputGeoPoint', 'lat' => $messages_Messagesxmediageolat, 'long' => $messages_Messagesxmediageolong, 'accuracy_radius' => $messages_Messagesxmediageoaccuracy_radius];
$inputMediaGeoPoint = ['_' => 'inputMediaGeoPoint', 'geo_point' => $inputGeoPoint];

$messages_Messagesxmediaphone_number = $messages_Messages['messages'][0]['media']['phone_number']?? null;
$messages_Messagesxmediafirst_name = $messages_Messages['messages'][0]['media']['first_name']?? null;
$messages_Messagesxmedialast_name = $messages_Messages['messages'][0]['media']['last_name']?? null;
$messages_Messagesxmediavcard = $messages_Messages['messages'][0]['media']['vcard']?? null;
$messages_Messagesuser_id = $messages_Messages['messages'][0]['media']['user_id']?? null;
//------
$inputMediaContact = ['_' => 'inputMediaContact', 'phone_number' => "$messages_Messagesxmediaphone_number", 'first_name' => "$messages_Messagesxmediafirst_name", 'last_name' => "$messages_Messagesxmedialast_name", 'vcard' => "$messages_Messagesxmediavcard"];


$messages_Messagereply_markup = $messages_Messages['messages'][0]['reply_markup']['rows']?? null;
$bot_API_markup_output = $messages_Messages['messages'][0]['reply_markup']?? null;

if($messages_Messagesxent == null){
$messages_Messagesxtext = "$messages_Messagesxtext";
}else{
$messages_Messagesxtext = "$messages_Messagesxtext";
}	

try { 

if($messages_Messagereply_markup != null){
}else{
$messages_Messagereply_markup = null;
}

if($messages_Messagesxmedia == null){
$messageLengthvartxt = mb_strlen($messages_Messagesxtext);

try {
$sentMessage = $MadelineProto->messages->sendMessage(peer: $me_session_id, message: "$messages_Messagesxtext", entities: $messages_Messagesxent);

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];
$this->messages->sendMessage(peer: $senderid, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

}else{
$messageLengthvartxt = mb_strlen($messages_Messagesxtext);

$info = $MadelineProto->getDownloadInfo($messages_Messagesxmedia);
$nameoffile = $info['name'];
$extfile = $info['ext'];
$extfile2 = $info['size'];

  if (!function_exists('bytesToMegabytes')) {
         function bytesToMegabytes($bytes) {
             $megabytes = $bytes / 1048576; // 1024 * 1024
             return round($megabytes, 2);
         }
     }
	 
$fileSizeInBytes = $extfile2; 
$fileSizeInMegabytes = bytesToMegabytes($fileSizeInBytes);
$filesizex = "File size: " . $fileSizeInMegabytes . " MB";

if($mepremium === false){
$maxBytes = 2 * 1024 * 1024 * 1024;
if($fileSizeInBytes > $maxBytes){
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ for 2GB+ need premium.</i>", parse_mode: 'HTML');
    return;
}

}

$botAPI_file = $MadelineProto->MTProtoToBotAPI($messages_Messagesxmedia);

        $fileId = null;
        $thumbFileId = null;
        $duration = null;
        $width = null;
        $height = null;
        $method = null;
		
foreach (['audio', 'document', 'photo', 'sticker', 'video', 'voice', 'video_note', 'animation'] as $type) {
    if (isset($botAPI_file[$type]) && is_array($botAPI_file[$type])) {
        $method = $type;
		
                if ($type === 'photo') {
                    $bestPhoto = end($botAPI_file['photo']);
                    $fileId = $bestPhoto['file_id'] ?? null;
                    $width = $bestPhoto['width'] ?? null;
                    $height = $bestPhoto['height'] ?? null;
					
                } else {
                    $file = $botAPI_file[$type];
                    $fileId = $file['file_id'] ?? null;

                    if (in_array($type, ['video', 'animation', 'video_note', 'audio', 'voice', 'document'])) {
                        $duration = $file['duration'] ?? null;
                        $width = $file['width'] ?? null;
                        $height = $file['height'] ?? null;
                        if (isset($file['thumb']['file_id'])) {
                            $thumbFileId = $file['thumb']['file_id'];
                        }
                    }
                }
        break;
}
}
$result['file_type'] = $method;

$resultx = [
            'bot_api_file_id' => $fileId,
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
            'thumb_file_id' => $thumbFileId
        ];

        try { unlink(__DIR__."/"."data/$senderid/thumb.jpg"); } catch (\Throwable $e) {}
        if ($thumbFileId && in_array($result['file_type'], ['video', 'animation', 'video_note', 'audio', 'voice', 'document'])) {
            \Amp\async(function () use ($thumbFileId, $senderid, $MadelineProto) {
                try {
                    $MadelineProto->downloadToFile($thumbFileId, __DIR__."/"."data/$senderid/thumb.jpg");
                } catch (\Throwable $e) {}
            });
        }


  if (!function_exists('createProgressBar')) {
function createProgressBar($count) {
/*
    $count = max(0, min(100, $count));
    

    $totalLength = 20;
    $filledLength = (int) ($totalLength * ($count / 100));
    

    $progressBar = '[' . str_repeat('█', $filledLength) . str_repeat('░', $totalLength - $filledLength) . ']';

*/



    $count = max(0, min(100, $count)); // Ensure $count is within 0-100
    $totalLength = 10; // Fixed length of the progress bar

    // Determine the number of filled and empty slots
    $filledLength = (int) ($totalLength * ($count / 100));
    $emptyLength = $totalLength - $filledLength;

    // Determine color based on percentage
    if ($count <= 30) {
        $filledChar = '🔴';
    } elseif ($count <= 60) {
        $filledChar = '🟠';
    } elseif ($count <= 90) {
        $filledChar = '🟡';
    } else {
        $filledChar = '🟢';
    }
    $emptyChar = '🔘';

    // Create the progress bar string
    $progressBar = '[' . str_repeat($filledChar, $filledLength) . str_repeat($emptyChar, $emptyLength) . ']';
	
	
	
	
	
    return $progressBar;
}
}
   if (!function_exists('createProgressBar2')) {
function createProgressBar2($count2) {
    // Ensure $count is between 0 and 100.
$count = max(0, min(100, $count2));
$totalLength = 20;
$filledLength = (int) ($totalLength * ($count2 / 100));
$progressBar2 = " (" . number_format($count, 1) . "%)";

    // Append percentage with one decimal place.
    return $progressBar2;
}
} 

try {
if ($result['file_type'] == 'photo') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update1 = 0;
$current_time = time();

if ($current_time - $last_update1 >= 5 || $progress == 100) {
$last_update1 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);


try {
$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => "$me_session_id",
    'media' => [
        '_' => 'inputMediaUploadedPhoto',
        'file' => new \danog\MadelineProto\FileCallback(
            "data/$nameoffile$extfile",
            function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
	   
static $last_update2 = 0;
$current_time = time();

if ($current_time - $last_update2 >= 5 || $progress == 100) {
$last_update2 = $current_time;
	
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "🚀 Upload progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}


            }
        )
    ],
   'message' => "$messages_Messagesxtext", 'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
} catch (\Throwable $e) {}
	  
}

if ($result['file_type'] == 'video') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update3 = 0;	
$current_time = time();

if ($current_time - $last_update3 >= 5 || $progress == 100) {
$last_update3 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {

$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeVideo',
    'round_message' => false,
    'supports_streaming' => true,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
if ($width !== null) {
    $attributes['w'] = (int) $width;
}
if ($height !== null) {
    $attributes['h'] = (int) $height;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}

$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}




}

if ($result['file_type'] == 'animation') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update5 = 0;	
$current_time = time();

if ($current_time - $last_update5 >= 5 || $progress == 100) {
$last_update5 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
	
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";

$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [['_' => 'documentAttributeAnimated']],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}


}

if ($result['file_type'] == 'video_note') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update7 = 0;	
$current_time = time();

if ($current_time - $last_update7 >= 5 || $progress == 100) {
$last_update7 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeVideo',
    'round_message' => true,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
if ($width !== null) {
    $attributes['w'] = (int) $width;
}
if ($height !== null) {
    $attributes['h'] = (int) $height;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);


try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'audio') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update9 = 0;
$current_time = time();

if ($current_time - $last_update9 >= 5 || $progress == 100) {
$last_update9 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeAudio',
    'voice' => false,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'voice') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);


$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update11 = 0;
$current_time = time();

if ($current_time - $last_update11 >= 5 || $progress == 100) {
$last_update11 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeAudio',
    'voice' => true,
    'file_name' => "$nameoffile$extfile"
];

if ($duration !== null) {
    $attributes['duration'] = (int) $duration;
}
$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'document') {

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid , 'reply_to' => $inputReplyToMessage, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

$output_file_name = $MadelineProto->downloadToFile(
$messages_Messagesxmedia,
    new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $filesizex) {

static $last_update13 = 0;
$current_time = time();

if ($current_time - $last_update13 >= 5 || $progress == 100) {
$last_update13 = $current_time;		
$count = $progress; 
$progressBar = createProgressBar($count);
$count2 = $progress; 
$progressBar2 = createProgressBar2($count2);  

            try {
$this->messages->editMessage(peer: $senderid, id: $sentMessage22, message: "⚡️ Download progress: $progressBar2
$progressBar
💾 $filesizex
");
            } catch (\Throwable $e) {}

}

        }
    )
);

try {
$thumbPath = __DIR__ . "/data/$senderid/thumb.jpg";
			
$attributes = [
    '_' => 'documentAttributeFilename',
    'file_name' => "$nameoffile$extfile"
];

$force_file = false;

$mediaArray = [
    '_' => 'inputMediaUploadedDocument',
    'file' => new \danog\MadelineProto\FileCallback(
        "data/$nameoffile$extfile",
        function ($progress, $speed, $time) use ($MadelineProto, $senderid, $sentMessage22, $me_session_id, $filesizex) {
            static $last_update4 = 0;
            $current_time = time();

            if ($current_time - $last_update4 >= 5 || $progress == 100) {
                $last_update4 = $current_time;

                try {
                    $this->messages->editMessage(
                        peer: $senderid,
                        id: $sentMessage22,
                        message: "🚀 Upload progress: "
                            . createProgressBar2($progress)
                            . "\n"
                            . createProgressBar($progress)
                            . "\n💾 $filesizex"
                    );
                } catch (\Throwable $e) {}
            }
        }
    ),
    'attributes' => [$attributes],
	    'force_file' => $force_file,
];

if (!empty($thumbPath) && file_exists($thumbPath)) {
    $mediaArray['thumb'] = new \danog\MadelineProto\FileCallback(
        $thumbPath,
        function () {}
    );
}

$sentMessagese = $MadelineProto->messages->sendMedia([
    'peer' => $me_session_id,
    'media' => $mediaArray,
    'message' => $messages_Messagesxtext,
    'entities' => $messages_Messagesxent,
]);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

try {
unlink("data/$nameoffile$extfile");
unlink($thumbPath);
} catch (\Throwable $e) {}

}

if ($result['file_type'] == 'sticker') {

try{
$sentMessage = $MadelineProto->messages->sendMedia([
    'peer' => $senderid,
    'media' => $messages_Messagesxmedia,
   'message' => "$messages_Messagesxtext", 'entities' => $messages_Messagesxent,
]);
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->sendMessage(peer: $senderid, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}
}

}
finally {
if (file_exists("data/$nameoffile$extfile")) {
try { unlink(__DIR__."/"."data/$nameoffile$extfile"); } catch (\Throwable $e) {}
}
if (file_exists(__DIR__."/"."data/$senderid/thumb.jpg")) {
try { unlink(__DIR__."/"."data/$senderid/thumb.jpg"); } catch (\Throwable $e) {}
}
}

}

} catch (Throwable $e) {
$error = $e->getMessage();
$estring = (string) $e;

if(preg_match("/messageMediaWebPage/",$estring)){

try {
$sentMessage = $MadelineProto->messages->sendMessage(peer: $me_session_id, message: "$messages_Messagesxtext", entities: $messages_Messagesxent);

try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];
$this->messages->sendMessage(peer: $senderid, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

}

if(preg_match("/messageMedia/",$estring)){

if(!preg_match("/messageMediaWebPage/",$estring)){
if(!preg_match("/messageMediaGeo/",$estring)){
if(!preg_match("/messageMediaPaidMedia/",$estring)){
if(!preg_match("/messageMediaContact/",$estring)){
	
$sentMessagex = $this->messages->sendMessage(['peer' => $senderid, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

try{
$sentMessagese = $MadelineProto->messages->sendMedia(peer: "$me_session_id", message: "$messages_Messagesxtext", media: $messages_Messagesxmedia );
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}

}
}
}
}

if(preg_match("/messageMediaGeo/",$estring)){

$sentMessagex = $this->messages->sendMessage(['peer' => $senderid, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

try{
$sentMessagese = $MadelineProto->messages->sendMedia(peer: "$me_session_id", message: "$messages_Messagesxtext", media: $inputMediaGeoPoint );
$sentMessage22se = $this->extractMessageId($sentMessagese);
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


}

if(preg_match("/messageMediaPaidMedia/",$estring)){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "ERROR: messageMediaPaidMedia");
}

if(preg_match("/messageMediaContact/",$estring)){


$sentMessagex = $this->messages->sendMessage(['peer' => $senderid, 'message' => "Processing... Please wait."]);
$sentMessage22 = $this->extractMessageId($sentMessagex);

try{
$sentMessagese = $MadelineProto->messages->sendMedia(peer: "$me_session_id", message: "$messages_Messagesxtext", media: $inputMediaContact );
$sentMessage22se = $this->extractMessageId($sentMessagese);
try {

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"Android 📱",'url'=>"tg://openmessage?user_id=$me_session_id"],['text'=>"iOS 🔗",'url'=>"https://t.me/@id$me_session_id"]
        ]
    ]
];

$this->messages->editMessage(peer: $senderid, id: $sentMessage22, reply_to: $inputReplyToMessage, message: "<b>DONE ✅</b>
Your message is in 'saved messages'.

🔗 link to chat <code>‎$me_session_id</code>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
} catch (\Throwable $e) {}


$VAR_SENT = true;
} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "❌ $error");
}


}

}

if(!$VAR_SENT){
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');
}

} catch (\danog\MadelineProto\RPCError\FileReferenceExpiredError $e) {
if (file_exists("data/$nameoffile$extfile")) {
unlink("data/$nameoffile$extfile");
}

}

}

} catch (Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');

}

}

}	
	
}

}
if(preg_match('/^\+/',$out1)){	
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "Unsupported format!
For all supported formats /help", parse_mode: 'HTML');
}
}

}


}

if($isadmin != true){

$bot_API_markup[] = [['text'=>"🔔 Updates",'url'=>"https://t.me/GetAnyMessageUpdates"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];
$inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $messageid];
$this->messages->sendMessage(no_webpage: true, peer: $message->senderId, reply_to: $inputReplyToMessage, message: "You need to join @GetAnyMessageUpdates in order to use this bot. Being a part of this channel keeps you informed of the latest updates.

So please join channel and enjoy 😇", reply_markup: $bot_API_markup, parse_mode: 'HTML') ;

}

}else{
	
if($isadmin != false){
if (!preg_match('/^http(s)?:\/\/t\.me\/.+\/?$/i', $messagetext)) {
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "Unsupported format!
For all supported formats /help", parse_mode: 'HTML');
}
if (preg_match('/^http(s)?:\/\/t\.me\/.+\/?$/i', $messagetext)) {

if(!function_exists("extractTelegramPaths")){
function extractTelegramPaths($url) {

  $path = parse_url($url, PHP_URL_PATH);

  if (empty($path)) {
    return null; 
  }
  
$segments = explode('/', trim($path, '/'));


  $out1 = isset($segments[0]) ? $segments[0] : null;
  $out2 = isset($segments[1]) ? $segments[1] : null;
  $out3 = isset($segments[2]) ? $segments[2] : null;
  $out4 = isset($segments[3]) ? $segments[3] : null;
  $out5 = isset($segments[4]) ? $segments[4] : null;
  
  return [
    'out1' => $out1,
    'out2' => $out2,
    'out3' => $out3,
    'out4' => $out4,
	'out5' => $out5,
  ];
}
}
$result1 = extractTelegramPaths($messagetext);
if ($result1 === null) {
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "Unsupported format!
For all supported formats /help", parse_mode: 'HTML');
} else {
$out1 = $result1['out1'] ?? null; 
$out2 = $result1['out2'] ?? null; 
$out3 = $result1['out3'] ?? null; 
$out4 = $result1['out4'] ?? null; 
$out5 = $result1['out5'] ?? null; 

if(!preg_match('/^\+/',$out1)){	
if ($out5 != null) {
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "Unsupported format!
For all supported formats /help", parse_mode: 'HTML');
}else{
if ($out1 === 'c' || $out1 === 'C') {

if($out4 != null){
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ To use this feature you need to log in with your own account.</i>", parse_mode: 'HTML');
}else{
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ To use this feature you need to log in with your own account.</i>", parse_mode: 'HTML');
}

}elseif($out1 === 'b' || $out1 === 'B') {
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ To use this feature you need to log in with your own account.</i>", parse_mode: 'HTML');

}elseif($out1 === 'u' || $out1 === 'U') {
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ To use this feature you need to log in with your own account.</i>", parse_mode: 'HTML');

}else{

if($out3 != null){
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ To use this feature you need to log in with your own account.</i>", parse_mode: 'HTML');

}else{
try {
	
$out1 = $result1['out1'] ?? null; //username
$out2 = $result1['out2'] ?? null; //id

if(preg_match("/^[0-9]/",$out1)){
$usernamex = $out1;
$numbersx = $out2;
}else{
$usernamex = "@".$out1;
$numbersx = $out2;
}

try {
$User_Full = $this->getInfo($usernamex);
$type = $User_Full['type']?? null;
} catch (\Throwable $e) {
$type = 'channel';	
}

if($type == 'channel'){
try {
	
$messages_Messages = $this->channels->getMessages(channel: "$usernamex", id: [$numbersx], );

$messages_Messagesxtext = $messages_Messages['messages'][0]['message']?? null;
if($messages_Messagesxtext == null){
//$messages_Messagesxtext = "null";
}
$messages_Messagesxent = $messages_Messages['messages'][0]['entities']?? null;

$messages_Messagesxmedia = $messages_Messages['messages'][0]['media']?? null;

if($messages_Messagesxent == null){
$messages_Messagesxtext = "$messages_Messagesxtext";
}else{
$messages_Messagesxtext = "$messages_Messagesxtext";
}	

$messages_Messagesxmediageo = $messages_Messages['messages'][0]['media']['geo']?? null;
$messages_Messagesxmediageolong = $messages_Messages['messages'][0]['media']['geo']['long']?? null;
$messages_Messagesxmediageolat = $messages_Messages['messages'][0]['media']['geo']['lat']?? null;
$messages_Messagesxmediageoaccess_hash = $messages_Messages['messages'][0]['media']['geo']['access_hash']?? null;
$messages_Messagesxmediageoaccuracy_radius = $messages_Messages['messages'][0]['media']['geo']['accuracy_radius']?? null;
//------
$inputGeoPoint = ['_' => 'inputGeoPoint', 'lat' => $messages_Messagesxmediageolat, 'long' => $messages_Messagesxmediageolong, 'accuracy_radius' => $messages_Messagesxmediageoaccuracy_radius];
$inputMediaGeoPoint = ['_' => 'inputMediaGeoPoint', 'geo_point' => $inputGeoPoint];

$messages_Messagesxmediaphone_number = $messages_Messages['messages'][0]['media']['phone_number']?? null;
$messages_Messagesxmediafirst_name = $messages_Messages['messages'][0]['media']['first_name']?? null;
$messages_Messagesxmedialast_name = $messages_Messages['messages'][0]['media']['last_name']?? null;
$messages_Messagesxmediavcard = $messages_Messages['messages'][0]['media']['vcard']?? null;
$messages_Messagesuser_id = $messages_Messages['messages'][0]['media']['user_id']?? null;
//------
$inputMediaContact = ['_' => 'inputMediaContact', 'phone_number' => "$messages_Messagesxmediaphone_number", 'first_name' => "$messages_Messagesxmediafirst_name", 'last_name' => "$messages_Messagesxmedialast_name", 'vcard' => "$messages_Messagesxmediavcard"];


$messages_Messagereply_markup = $messages_Messages['messages'][0]['reply_markup']['rows']?? null;
$bot_API_markup_output = $messages_Messages['messages'][0]['reply_markup']?? null;


if($messages_Messagesxmedia == null){

if($messages_Messagereply_markup == null){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "$messages_Messagesxtext", entities: $messages_Messagesxent);
}
if($messages_Messagereply_markup != null){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "$messages_Messagesxtext", entities: $messages_Messagesxent, reply_markup: $bot_API_markup_output);
}


}else{
try {
if($messages_Messagereply_markup == null){
$sentMessage = $this->messages->sendMedia(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "$messages_Messagesxtext", entities: $messages_Messagesxent, media: $messages_Messagesxmedia);
}
if($messages_Messagereply_markup != null){
$sentMessage = $this->messages->sendMedia(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "$messages_Messagesxtext", entities: $messages_Messagesxent, media: $messages_Messagesxmedia, reply_markup: $bot_API_markup_output);
}

} catch (Throwable $e) {
$error = $e->getMessage();
$estring = (string) $e;

if(preg_match("/messageMediaWebPage/",$estring)){
	
if($messages_Messagereply_markup == null){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "$messages_Messagesxtext", entities: $messages_Messagesxent);
}
if($messages_Messagereply_markup != null){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "$messages_Messagesxtext", entities: $messages_Messagesxent, reply_markup: $bot_API_markup_output);
}

}elseif(preg_match("/poll/",$estring)){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: $error);

}elseif(preg_match("/messageMediaGeo/",$estring)){
$sentMessage = $this->messages->sendMedia(peer: $message->senderId, reply_to: $inputReplyToMessage, media: $inputMediaGeoPoint);

}elseif(preg_match("/messageMediaPaidMedia/",$estring)){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "ERROR: messageMediaPaidMedia");

}elseif(preg_match("/messageMediaContact/",$estring)){
$sentMessage = $this->messages->sendMedia(peer: $message->senderId, reply_to: $inputReplyToMessage, media: $inputMediaContact);

}elseif($error === 'MEDIA_CAPTION_TOO_LONG') {
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ MEDIA_CAPTION_TOO_LONG</i>", parse_mode: 'HTML');

}else{
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');
}

}
}

} catch (Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');
}	
}
if($type != 'channel'){
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ To use this feature you need to log in with your own account.</i>", parse_mode: 'HTML');
}

} catch (Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');
}

}




}	
	
}
}
if(preg_match('/^\+/',$out1)){	
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "Unsupported format!
For all supported formats /help", parse_mode: 'HTML');
}

}

}


}

if($isadmin != true){

$bot_API_markup[] = [['text'=>"🔔 Updates",'url'=>"https://t.me/GetAnyMessageUpdates"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];
$inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $messageid];
$this->messages->sendMessage(no_webpage: true, peer: $message->senderId, reply_to: $inputReplyToMessage, message: "You need to join @GetAnyMessageUpdates in order to use this bot. Being a part of this channel keeps you informed of the latest updates.

So please join channel and enjoy 😇", reply_markup: $bot_API_markup, parse_mode: 'HTML') ;

}


}

}

if(time() < $zmanxcheck){	

$ezman = $zmanxcheck - time();
$szman = $ezman;
$newLangsComma = "Limit: Allow 1 request per 10 seconds. 
⏳ <b>Please wait:</b> $szman seconds";

$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "$newLangsComma", parse_mode: 'HTML');

}

}




}

} catch (Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');
}
}
} catch (\Throwable $exception) {}
}

/* ========= Admin Handlers ========= */
#[FilterCommandCaseInsensitive('admin')]
public function admincommand(Incoming & PrivateMessage & FromAdmin $message): void {
try {
$senderid = $message->senderId;
$User_Full = $this->getInfo($message->senderId);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}
$last_name = $User_Full['User']['last_name']?? null;
if($last_name == null){
$last_name = "null";
}
$username = $User_Full['User']['username']?? null;
if($username == null){
$username = "null";
}

$bot_API_markup[] = [['text'=>"📊 Statistics",'callback_data'=>"Statistics"]];
$bot_API_markup[] = [['text'=>"📮 Broadcast",'callback_data'=>"Broadcast"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$this->messages->sendMessage(peer: $message->senderId, message: "🛠 <b>System Management Menu!</b>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
    if (file_exists("data/$senderid/grs1.txt")) {
unlink("data/$senderid/grs1.txt");
}

} catch (Throwable $e) {}
}
#[FilterButtonQueryData('backadmin')] 
public function adminbackcommand(callbackQuery $query) {
	try {
$userid = $query->userId;    

$ADMIN = $this->getAdminIds();
if (in_array((string)$userid, array_map('strval', $ADMIN), true)) {
	
$bot_API_markup[] = [['text'=>"📊 Statistics",'callback_data'=>"Statistics"]];
$bot_API_markup[] = [['text'=>"📮 Broadcast",'callback_data'=>"Broadcast"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];


$query->editText($message = "🛠 <b>System Management Menu!</b>", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = false, $scheduleDate = NULL);
if (file_exists("data/$userid/grs1.txt")) {
unlink("data/$userid/grs1.txt");
}
}

} catch (Throwable $e) {
$error = $e->getMessage();
}
}
#[FilterButtonQueryData('backadmin2')] 
public function adminbackcommand2(callbackQuery $query) {
	try {
$userid = $query->userId;  
$msgid = $query->messageId;  

$ADMIN = $this->getAdminIds();
if (in_array((string)$userid, array_map('strval', $ADMIN), true)) {

try {
$this->messages->deleteMessages(revoke: true, id: [$msgid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

$bot_API_markup[] = [['text'=>"📊 Statistics",'callback_data'=>"Statistics"]];
$bot_API_markup[] = [['text'=>"📮 Broadcast",'callback_data'=>"Broadcast"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];


$this->messages->sendMessage(peer: $userid, message: "🛠 <b>System Management Menu!</b>", reply_markup: $bot_API_markup, parse_mode: 'HTML');

if (file_exists("data/$userid/grs1.txt")) {
unlink("data/$userid/grs1.txt");
}

    if (file_exists("data/BUTTONS.txt")) {
unlink("data/BUTTONS.txt");  
	}	
 if (file_exists("data/$userid/txt.txt")) {
unlink("data/$userid/txt.txt");  
}
  if (file_exists("data/$userid/ent.txt")) {
unlink("data/$userid/ent.txt");  
  }	  
  if (file_exists("data/$userid/media.txt")) {
unlink("data/$userid/media.txt");  
  }	 

}

} catch (Throwable $e) {
$error = $e->getMessage();
}
}

public static function getPlugins(): array {
    return [\danog\MadelineProto\EventHandler\Plugin\RestartPlugin::class];
}
public static function getPluginPaths(): string|array|null {
    return null;
}

#[FilterButtonQueryData('Statistics')]
public function StatsUsers(callbackQuery $query) {
    try {
        $bot_API_markup = [
            'inline_keyboard' => [
                [['text' => "🔙 back 🔙", 'callback_data' => "backadmin"]]
            ]
        ];

        $query->editText("⌛️", null, ParseMode::HTML);

        $numChannels = $numSupergroups = $numChats = $numBots = 0;
        $numUsers = 0;

$dialogs = $this->getDialogIds();

        foreach ($dialogs as $id) {
            try {
                $info = $this->getInfo($id);

                switch ($info['type'] ?? 'user') {
                    case 'channel':    $numChannels++; break;
                    case 'supergroup': $numSupergroups++; break;
                    case 'chat':       $numChats++; break;
                    case 'bot':        $numBots++; break;
                    case 'user':        $numUsers++; break;
                  //  default:           $numUsers++; break;
                }
            } catch (Throwable $e) {
				//$numUsers++;
			    continue;
            }
        }

$allIds = $numUsers + $numChannels + $numSupergroups + $numChats + $numBots;


        $fmt = fn($n) => number_format($n, 0, '.', ',');

        $message = "<b>🧮 Statistics 📊</b>
- - - - - - - - - -
📢 channels: {$fmt($numChannels)}
💬 groups: {$fmt($numChats)}
👥 super groups: {$fmt($numSupergroups)}
🤖 bots: {$fmt($numBots)}
👤 users: {$fmt($numUsers)}
- - - - - - - - - -
<b>🎯 total: {$fmt($allIds)}</b>";

        $query->editText($message, $bot_API_markup, ParseMode::HTML);

    } catch (Throwable $e) { }
}

#[FilterButtonQueryData('closeMsg')]
public function closecommand(callbackQuery $query) {
	try {
$this->messages->deleteMessages(revoke: true, id: [$query->messageId]); 
} catch (\Throwable $e) {
$query->answer($message = "I can't close the message, close it yourself.", $alert = false, $url = null, $cacheTime = 0);		
}
}

#[FilterButtonQueryData('Broadcast')] 
public function broadcastCommand(callbackQuery $query) {
	try {
$userid = $query->userId;    
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"Last Broadcast Data 📊",'callback_data'=>"LastBrodDATA"]];
$bot_API_markup[] = [['text'=>"send broadcast 📮",'callback_data'=>"setBroadcast"]];
$bot_API_markup[] = [['text'=>"delete last broadcast 🗑",'callback_data'=>"deleteLastBroadcast"]];
$bot_API_markup[] = [['text'=>"delete all broadcast 🗑",'callback_data'=>"deleteAllBroadcast"]];
$bot_API_markup[] = [['text'=>"unpin all broadcast ⛓️‍💥",'callback_data'=>"cancelPinned"]];
$bot_API_markup[] = [['text'=>"back",'callback_data'=>"backadmin"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "<b>broadcast menu:</b>", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = false, $scheduleDate = NULL);
} catch (Throwable $e) {}
}

#[FilterButtonQueryData('deleteLastBroadcast')]
public function deleteLastBroadcast(callbackQuery $query) {
try {
$BOT_NAME  = $env['BOT_NAME'] ?? 'GetAnyMessage';
$path = __DIR__."/bot_{$BOT_NAME}.madeline";

$API = new \danog\MadelineProto\API($path);
$manager = new BroadcastManager($API);
BroadcastManager::setDataDir(__DIR__ . '/data');
if (!$manager->hasLastBroadcast()) {
$query->answer($message = "no have broadcast to delete!", $alert = true, $url = null, $cacheTime = 0);
	}else{
$query->answer($message = "please wait...", $alert = false, $url = null, $cacheTime = 0);
$allUsers = $this->getDialogIds(); 
$manager->deleteLastBroadcastForAll($allUsers, $query->userId, 20);
    }
} catch (Throwable $e) {}
}

#[FilterButtonQueryData('deleteAllBroadcast')]
public function deleteAllBroadcast(callbackQuery $query) {
try {
$BOT_NAME  = $env['BOT_NAME'] ?? 'GetAnyMessage';
$path = __DIR__."/bot_{$BOT_NAME}.madeline";
$API = new \danog\MadelineProto\API($path);
$manager = new BroadcastManager($API);
BroadcastManager::setDataDir(__DIR__ . '/data');

if (!$manager->hasAllBroadcast()) {
$query->answer($message = "no have broadcast to delete!", $alert = true, $url = null, $cacheTime = 0);
	}else{
$query->answer($message = "please wait...", $alert = false, $url = null, $cacheTime = 0);
$allUsers = $this->getDialogIds(); 
$manager->deleteAllBroadcastsForAll($allUsers, $query->userId, 20);
    }
} catch (Throwable $e) {}
}

#[FilterButtonQueryData('cancelPinned')]
public function cancelPinned(callbackQuery $query) {
try {
$BOT_NAME  = $env['BOT_NAME'] ?? 'GetAnyMessage';
$path = __DIR__."/bot_{$BOT_NAME}.madeline";
$API = new \danog\MadelineProto\API($path);
$manager = new BroadcastManager($API);
BroadcastManager::setDataDir(__DIR__ . '/data');
$query->answer($message = "please wait...", $alert = false, $url = null, $cacheTime = 0);
$allUsers = $this->getDialogIds(); 
$subfilter = 'users';
$filter_sub = $manager->filterPeers($allUsers, $subfilter);
$subs = $filter_sub['targets'];
$manager->unpinAllMessagesForAll($subs, $query->userId, 20);
} catch (Throwable $e) {}
}

#[FilterButtonQueryData('LastBrodDATA')]
public function LastBrodDATA(callbackQuery $query) {  
try{

$BOT_NAME  = $env['BOT_NAME'] ?? 'GetAnyMessage';
$path = __DIR__."/bot_{$BOT_NAME}.madeline";
$API = new \danog\MadelineProto\API($path);
$manager = new BroadcastManager($API);
BroadcastManager::setDataDir(__DIR__ . '/data');
if ($manager->lastBroadcastData()) {
$filex = $manager->lastBroadcastData();
$bot_API_markup[] = [['text'=>"back",'callback_data'=>"Broadcast"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = $filex, $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = false, $scheduleDate = NULL);

	}else{
$filex = "📊 no data yet."; 
$query->answer($message = $filex, $alert = true, $url = null, $cacheTime = 0);	
	}
} catch (Throwable $e) {}
}

#[FilterButtonQueryData('setBroadcast')] 
public function setBroadcast(callbackQuery $query) {
	try {
$userid = $query->userId;    
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"❌ back ❌",'callback_data'=>"backadmin"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "<b>send the message:</b>", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = false, $scheduleDate = NULL);

$userDir = __DIR__ . "/data/$userid";
        if (!is_dir($userDir)) {
            mkdir($userDir, 0777, true);
        }
		
Amp\File\write(__DIR__."/data/$userid/grs1.txt", 'broadcast1');
$msgqutryid = $query->messageId;
Amp\File\write(__DIR__."/data/$userid/messagetodelete.txt", "$msgqutryid");
} catch (Throwable $e) {}
}

#[Handler]
public function handlebroadcast1(Incoming & PrivateMessage & FromAdmin $message): void {
		try {
$messagetext = $message->message;
$messageid = $message->id;
$messagefile = $message->media;
$grouped_id = $message->groupedId;
$entities = $message->entities;
$senderid = $message->senderId;
$User_Full = $this->getInfo($message->senderId);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}
$last_name = $User_Full['User']['last_name']?? null;
if($last_name == null){
$last_name = "null";
}
$username = $User_Full['User']['username']?? null;
if($username == null){
$username = "null";
}

$userDir = __DIR__ . "/data/$senderid";
        if (!is_dir($userDir)) {
            mkdir($userDir, 0777, true);
        }
		

    if (file_exists(__DIR__."/data/$senderid/grs1.txt")) {
$check = Amp\File\read(__DIR__."/data/$senderid/grs1.txt");    
if($check == "broadcast1"){
    
if(!preg_match('/^\/([Ss]tart)/',$messagetext)){   

$messageLength = mb_strlen($messagetext);

if($messageLength > 1024) {
	
			try {
$this->messages->deleteMessages(revoke: true, id: [$messageid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}
$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>"❌ cancel ❌",'callback_data'=>"backadmin2"]
        ]
    ]
];

$sentMessage = $this->messages->sendMessage(peer: $message->senderId, message: "please send text up to 1024 characters
characters you sent: $messageLength", reply_markup: $bot_API_markup);


 if (file_exists(__DIR__."/data/$senderid/messagetodelete.txt")) {
$filexmsgid = Amp\File\read(__DIR__."/data/$senderid/messagetodelete.txt");  

			try {
$this->messages->deleteMessages(revoke: true, id: [$filexmsgid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

unlink(__DIR__."/data/$senderid/messagetodelete.txt");
}

}else{
unlink(__DIR__."/data/$senderid/grs1.txt"); 

if($messagetext != null){
Amp\File\write(__DIR__."/data/$senderid/txt.txt", "$messagetext");
Amp\File\write(__DIR__."/data/$senderid/ent.txt", json_encode(array_map(static fn($e) => $e->toMTProto(),$entities,)));	
}
if(!$messagefile){
}else{
$botApiFileId = $message->media->botApiFileId;
Amp\File\write(__DIR__."/data/$senderid/media.txt", "$botApiFileId");
}

			try {
$this->messages->deleteMessages(revoke: true, id: [$messageid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

 if (file_exists(__DIR__."/data/$senderid/messagetodelete.txt")) {
$filexmsgid = Amp\File\read(__DIR__."/data/$senderid/messagetodelete.txt");  

			try {
$this->messages->deleteMessages(revoke: true, id: [$filexmsgid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

unlink(__DIR__."/data/$senderid/messagetodelete.txt");
}


if (file_exists(__DIR__."/data/broadcastsend.txt")) {
$broadcast_send = Amp\File\read(__DIR__."/data/broadcastsend.txt");
}
if (!file_exists(__DIR__."/data/broadcastsend.txt")) {
$broadcast_send = "users";
}

if (file_exists(__DIR__."/data/pinmessage.txt")) {
$bot_API_markup[] = [['text'=>"📌 pin: ✔️",'callback_data'=>"pin1"]];
}
if (!file_exists(__DIR__."/data/pinmessage.txt")) {
$bot_API_markup[] = [['text'=>"📌 pin: ✖️",'callback_data'=>"pin2"]];
}

$bot_API_markup[] = [['text'=>"📮 target: $broadcast_send",'callback_data'=>"target_fil"]];

$bot_API_markup[] = [['text'=>"👁 see buttons",'callback_data'=>"see_buttons"]];
$bot_API_markup[] = [['text'=>"🔌 ass buttons ➕",'callback_data'=>"add_buttons"]];

$bot_API_markup[] = [['text'=>"✅ send ✅",'callback_data'=>"sendbroadcast"]];

$bot_API_markup[] = [['text'=>"❌ cancel ❌",'callback_data'=>"backadmin2"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

 if (file_exists(__DIR__."/data/$senderid/txt.txt")) {
$filexmsgidtxt = Amp\File\read(__DIR__."/data/$senderid/txt.txt");  
}else{
$filexmsgidtxt = null; 
}
  if (file_exists(__DIR__."/data/$senderid/ent.txt")) {
$filexmsgident = json_decode(Amp\File\read(__DIR__."/data/$senderid/ent.txt"),true);  
  }else{
$filexmsgident = null;  
  }	  
  if (file_exists(__DIR__."/data/$senderid/media.txt")) {
$filexmsgidmedia = Amp\File\read(__DIR__."/data/$senderid/media.txt");  
  }else{
$filexmsgidmedia = null;  
  }	 

if($filexmsgidmedia != null){
	
if($filexmsgidtxt != null){
$sentMessage = $this->messages->sendMedia(peer: $message->senderId, message: "$filexmsgidtxt", entities: $filexmsgident, media: $filexmsgidmedia, reply_markup: $bot_API_markup);
}else{
$sentMessage = $this->messages->sendMedia(peer: $message->senderId, media: $filexmsgidmedia, reply_markup: $bot_API_markup);
}

}else{

if($filexmsgidtxt != null){
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, message: "$filexmsgidtxt", entities: $filexmsgident, reply_markup: $bot_API_markup);
}
}

}


	
}


}

}

} catch (Throwable $e) {}
	}

#[FilterButtonQueryData('backBroadMenu')] 
public function hazarashidur(callbackQuery $query) {
	try {
$userid = $query->userId; 
$msgqutryid = $query->messageId;   
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

if (file_exists(__DIR__."/data/broadcastsend.txt")) {
$broadcast_send = Amp\File\read(__DIR__."/data/broadcastsend.txt");
}
if (!file_exists(__DIR__."/data/broadcastsend.txt")) {
$broadcast_send = "users";
}


if (file_exists(__DIR__."/data/pinmessage.txt")) {
$bot_API_markup[] = [['text'=>"📌 pin: ✔️",'callback_data'=>"pin1"]];
}
if (!file_exists(__DIR__."/data/pinmessage.txt")) {
$bot_API_markup[] = [['text'=>"📌 pin: ✖️",'callback_data'=>"pin2"]];
}

$bot_API_markup[] = [['text'=>"📮 target: $broadcast_send",'callback_data'=>"target_fil"]];

$bot_API_markup[] = [['text'=>"👁 see buttons",'callback_data'=>"see_buttons"]];
$bot_API_markup[] = [['text'=>"🔌 ass buttons ➕",'callback_data'=>"add_buttons"]];

$bot_API_markup[] = [['text'=>"✅ send ✅",'callback_data'=>"sendbroadcast"]];

$bot_API_markup[] = [['text'=>"❌ cancel ❌",'callback_data'=>"backadmin2"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

 if (file_exists(__DIR__."/data/$userid/txt.txt")) {
$filexmsgidtxt = Amp\File\read(__DIR__."/data/$userid/txt.txt");  
}else{
$filexmsgidtxt = null; 
}
  if (file_exists(__DIR__."/data/$userid/ent.txt")) {
$filexmsgident = json_decode(Amp\File\read(__DIR__."/data/$userid/ent.txt"),true);  
  }else{
$filexmsgident = null;  
  }	

if($filexmsgidtxt != null){
$this->messages->editMessage(peer: $userid, id: $msgqutryid, message: "$filexmsgidtxt", entities: $filexmsgident, reply_markup: $bot_API_markup);
}else{
$query->editText($message = "broadcast menu:", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = false, $scheduleDate = NULL);
}
} catch (Throwable $e) {}
}

#[FilterButtonQueryData('pin1')] 
public function addsoheshidur1forneitza1(callbackQuery $query) {
	try {
$userid = $query->userId;    
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

Amp\File\write(__DIR__."/data/pinmessage.txt","on");

$bot_API_markup[] = [['text'=>"back",'callback_data'=>"backBroadMenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "✔️", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = false, $scheduleDate = NULL);
} catch (Throwable $e) {}
}

#[FilterButtonQueryData('pin2')] 
public function addsoheshidur1forneitza2(callbackQuery $query) {
	try {
$userid = $query->userId;    
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

if (file_exists(__DIR__."/data/pinmessage.txt")) {
unlink(__DIR__."/data/pinmessage.txt");
}

$bot_API_markup[] = [['text'=>"back",'callback_data'=>"backBroadMenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "✖️", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = false, $scheduleDate = NULL);
} catch (Throwable $e) {}
}

#[FilterButtonQueryData('target_fil')] 
public function broadsetsenders(callbackQuery $query) {
	try {
$userid = $query->userId;    
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$bot_API_markup[] = [['text'=>"users",'callback_data'=>"target_fil1"]];
$bot_API_markup[] = [['text'=>"channels",'callback_data'=>"target_fil2"]];
$bot_API_markup[] = [['text'=>"groups",'callback_data'=>"target_fil3"]];
$bot_API_markup[] = [['text'=>"all",'callback_data'=>"target_fil4"]];
$bot_API_markup[] = [['text'=>"back",'callback_data'=>"backBroadMenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "<b>please choose: 🔘</b>", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = false, $scheduleDate = NULL);
} catch (Throwable $e) {}
}
#[FilterButtonQueryData('target_fil1')] 
public function broadsetsenders1(callbackQuery $query) {
	try {
$userid = $query->userId;    
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

Amp\File\write(__DIR__."/data/broadcastsend.txt","users");

$bot_API_markup[] = [['text'=>"back",'callback_data'=>"backBroadMenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "choosen: users", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = false, $scheduleDate = NULL);
} catch (Throwable $e) {}
}
#[FilterButtonQueryData('target_fil2')] 
public function broadsetsenders2(callbackQuery $query) {
	try {
$userid = $query->userId;    
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

Amp\File\write(__DIR__."/data/broadcastsend.txt","channels");

$bot_API_markup[] = [['text'=>"back",'callback_data'=>"backBroadMenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "choosen: channels", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = false, $scheduleDate = NULL);
} catch (Throwable $e) {}
}
#[FilterButtonQueryData('target_fil3')] 
public function broadsetsenders3(callbackQuery $query) {
	try {
$userid = $query->userId;    
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

Amp\File\write(__DIR__."/data/broadcastsend.txt","groups");

$bot_API_markup[] = [['text'=>"back",'callback_data'=>"backBroadMenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "choosen: groups", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = false, $scheduleDate = NULL);
} catch (Throwable $e) {}
}
#[FilterButtonQueryData('target_fil4')] 
public function broadsetsenders4(callbackQuery $query) {
	try {
$userid = $query->userId;    
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

Amp\File\write(__DIR__."/data/broadcastsend.txt","all");

$bot_API_markup[] = [['text'=>"back",'callback_data'=>"backBroadMenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "choosen: all", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = false, $scheduleDate = NULL);
} catch (Throwable $e) {}
}

#[FilterButtonQueryData('add_buttons')] 
public function hosafkaf(callbackQuery $query) {
	try {
$userid = $query->userId;    
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$buttons = __DIR__."/data/menubuttons1.txt";

$bot_API_markup[] = [['text'=>"❌ cancel ❌",'callback_data'=>"backBroadMenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$query->editText($message = "<b>Send the buttons you want to configure in the following format:</b>

• <u>List of buttons (single button per line):</u>
<pre>Button text 1 - http://www.example.com/
Button text 2 - http://www.example2.com/</pre>

• <u>Number of buttons per line:</u>
<pre>Button text 1 - http://www.example.com/ &amp;&amp; Button text 2 - http://www.example2.com/</pre>

• <u>Add a menu button:</u>
<pre>Button name - data: menu name</pre>

<u>Menu names data:</u>
<pre>
closeMsg (Close message)
</pre>

<u>Example:</u>
<pre>Close - data:closeMsg</pre>", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = false, $scheduleDate = NULL);

Amp\File\write(__DIR__."/data/$userid/grs1.txt", 'addBUTTONS');
$msgqutryid = $query->messageId;
Amp\File\write(__DIR__."/data/$userid/messagetodelete.txt", "$msgqutryid");
} catch (Throwable $e) {}
}

#[Handler]
public function handlebuttons(Incoming & PrivateMessage & FromAdmin $message): void {
		try {
$messagetext = $message->message;
$entities = $message->entities;
$messagefile = $message->media;
$messageid = $message->id;
$senderid = $message->senderId;
$User_Full = $this->getInfo($message->senderId);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}
$last_name = $User_Full['User']['last_name']?? null;
if($last_name == null){
$last_name = "null";
}
$username = $User_Full['User']['username']?? null;
if($username == null){
$username = "null";
}

    if (file_exists(__DIR__."/data/$senderid/grs1.txt")) {
$edit = Amp\File\read(__DIR__."/data/$senderid/grs1.txt");    
if($edit == "addBUTTONS"){
 
if(!preg_match('/^\/([Ss]tart)/',$messagetext)){   

if (!function_exists("parseButtons")) {
    function parseButtons(string $text): array|false
    {
        $lines = explode("\n", trim($text));
        $keyboard = [];

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if ($line === '') continue;

            $row = [];
            $buttons = explode('&&', $line);

            foreach ($buttons as $btnNumber => $button) {
                $button = trim($button);

                if (!preg_match('/^(.+?)\s*-\s*(.+)$/u', $button, $m)) {
                    return false;
                }

                $text  = trim($m[1]);
                $value = trim($m[2]);

                // URL
                if (preg_match('#^https?://#i', $value)) {

                    $row[] = [
                        'text' => $text,
                        'url'  => $value
                    ];

                // CALLBACK DATA
                } elseif (preg_match('#^data:\s*(.+)$#u', $value, $dm)) {

                    $callback = trim($dm[1]); 
                    if (strlen($callback) > 64) {
                        return false;
                    }

                    $row[] = [
                        'text' => $text,
                        'callback_data' => $callback
                    ];

                } else {
                    // לא URL ולא data:
                    return false;
                }
            }

            $keyboard[] = $row;
        }

        return $keyboard;
    }
}

$parsedButtons = parseButtons($messagetext);

if ($parsedButtons !== false) {
unlink(__DIR__."/data/$senderid/grs1.txt");

			try {
$this->messages->deleteMessages(revoke: true, id: [$messageid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

 if (file_exists(__DIR__."/data/$senderid/messagetodelete.txt")) {
$filexmsgid = Amp\File\read(__DIR__."/data/$senderid/messagetodelete.txt");  
$bot_API_markup = [];
$bot_API_markup[] = [['text'=>"back",'callback_data'=>"backBroadMenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];
$Updates = $this->messages->editMessage(peer: $senderid, id: $filexmsgid, message: "✔️", reply_markup: $bot_API_markup, parse_mode: 'HTML');

Amp\File\write(__DIR__."/data/BUTTONS.txt", json_encode($parsedButtons, JSON_UNESCAPED_UNICODE));

}


} else {
			try {
$this->messages->deleteMessages(revoke: true, id: [$messageid]); 
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_DELETE_FORBIDDEN/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_DELETE_FORBIDDEN') {	
}
}

 if (file_exists(__DIR__."/data/$senderid/messagetodelete.txt")) {
$filexmsgid = Amp\File\read(__DIR__."/data/$senderid/messagetodelete.txt");  
$bot_API_markup = [];
$bot_API_markup[] = [['text'=>"❌ cancal ❌",'callback_data'=>"backBroadMenu"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

			try {
$Updates = $this->messages->editMessage(peer: $senderid, id: $filexmsgid, message: "<b>Please submit the buttons you would like to add in the correct format!</b>", reply_markup: $bot_API_markup, parse_mode: 'HTML');
}catch (\danog\MadelineProto\Exception $e) {
$estring = (string) $e;
if(preg_match("/MESSAGE_NOT_MODIFIED/",$estring)){
}

} catch (\danog\MadelineProto\RPCErrorException $e) {
    if ($e->rpc === 'MESSAGE_NOT_MODIFIED') {	
}
}


}
}








	
}


}





	
}

} catch (Throwable $e) {}
}

#[FilterButtonQueryData('see_buttons')] 
public function buttonsmanageview(callbackQuery $query) {
	try {
$userid = $query->userId;    
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$buttons = __DIR__."/data/BUTTONS.txt";

    if (file_exists($buttons)) {

if (!function_exists('loadButtons')) {
    function loadButtons(string $file): array|null
    {
        if (!file_exists($file)) {
            return null;
        }

        $json = Amp\File\read($file);
        $buttons = json_decode($json, true);

        if (!is_array($buttons)) {
            return null;
        }

        return $buttons;
    }
}

$buttonsData = loadButtons($buttons);

if ($buttonsData === null || empty($buttonsData)) {
$BUTTONS = "No buttons have been defined yet...";  
$query->answer($message = "$BUTTONS", $alert = true, $url = null, $cacheTime = 0);
	}else{

$buttonsData[] = [['text' => 'back', 'callback_data' => 'backBroadMenu']];
$bot_API_markup = ['inline_keyboard' => $buttonsData];

$query->editText($message = "your buttons:", $replyMarkup = $bot_API_markup, ParseMode::HTML, $noWebpage = false, $scheduleDate = NULL);
}


	}
	
    if (!file_exists($buttons)) {
$BUTTONS = "No buttons have been defined yet...";  
$query->answer($message = "$BUTTONS", $alert = true, $url = null, $cacheTime = 10);
	}
	
} catch (Throwable $e) {}
}

#[FilterButtonQueryData('sendbroadcast')] 
public function buttonsmanageview2(callbackQuery $query) {
	try{
$userid = $query->userId;  
$msgqutryid = $query->messageId;   
$User_Full = $this->getInfo($userid);
$first_name = $User_Full['User']['first_name']?? null;
if($first_name == null){
$first_name = "null";
}

$buttons = __DIR__."/data/BUTTONS.txt";

    if (file_exists($buttons)) {

if (!function_exists('loadButtons')) {
    function loadButtons(string $file): array|null
    {
        if (!file_exists($file)) {
            return null;
        }

        $json = Amp\File\read($file);
        $buttons = json_decode($json, true);

        if (!is_array($buttons)) {
            return null;
        }

        return $buttons;
    }
}

$buttonsData = loadButtons($buttons);

if ($buttonsData === null || empty($buttonsData)) {
$bot_API_markup = null;  
}else{
$bot_API_markup = ['inline_keyboard' => $buttonsData];
}


	}else{
$bot_API_markup = null;  
	}
	

			try {
$this->messages->deleteMessages(revoke: true, id: [$msgqutryid]); 
} catch (Throwable $e) {}

 if (file_exists(__DIR__."/data/$userid/txt.txt")) {
$filexmsgidtxt = Amp\File\read(__DIR__."/data/$userid/txt.txt");  
}else{
$filexmsgidtxt = null; 
}
  if (file_exists(__DIR__."/data/$userid/ent.txt")) {
$filexmsgident = json_decode(Amp\File\read(__DIR__."/data/$userid/ent.txt"),true);  
  }else{
$filexmsgident = null;  
  }	  
  if (file_exists(__DIR__."/data/$userid/media.txt")) {
$filexmsgidmedia = Amp\File\read(__DIR__."/data/$userid/media.txt");  
  }else{
$filexmsgidmedia = null;  
  }	 

        try {
$dialogs = $this->getDialogIds();
        } catch (Throwable $e) { $dialogs = []; }

    if (!file_exists(__DIR__."/data/pinmessage.txt")) {
$pinmessage = false;
	}else{
$pinmessage = true;
	}

    if (!file_exists(__DIR__."/data/broadcastsend.txt")) {
$subfilter = 'users';
	}else{
$check2 = Amp\File\read(__DIR__."/data/broadcastsend.txt");  

if($check2 == "users"){
$subfilter = 'users';
}elseif($check2 == "channels"){
$subfilter = 'channels';
}elseif($check2 == "groups"){	
$subfilter = 'groups';
}elseif($check2 == "all"){
$subfilter = 'all';
}else{
$subfilter = 'users';
}
}


if($filexmsgidmedia != null){

if($filexmsgidtxt != null){
$messages = [['message' => "$filexmsgidtxt", 'entities' => $filexmsgident, 'media' => $filexmsgidmedia, 'reply_markup' => $bot_API_markup]];
}else{
$messages = [['media' => $filexmsgidmedia, 'reply_markup' => $bot_API_markup]];
}

}else{

if($filexmsgidtxt != null){
$messages = [['message' => "$filexmsgidtxt", 'entities' => $filexmsgident, 'reply_markup' => $bot_API_markup]];
}

}

$BOT_NAME  = $env['BOT_NAME'] ?? 'GetAnyMessage';
$path = __DIR__."/bot_{$BOT_NAME}.madeline";
$api = new \danog\MadelineProto\API($path);
$manager = new BroadcastManager($api);
BroadcastManager::setDataDir(__DIR__ . '/data');

if(!$manager->progress()){
$filter_sub = $manager->filterPeers($dialogs, $subfilter);
$subs = $filter_sub['targets'];

$manager->broadcastWithProgress($subs, $messages, $userid, $pinmessage, 20);

}else{
$message = "There is an active broadcast right now, please wait...";  
$query->answer($message = $message, $alert = true, $url = null, $cacheTime = 0);
}

} catch (Throwable $e) {}
}

#[FilterButtonQueryData('soon')]
public function comingsoon(callbackQuery $query) {  
$userid = $query->userId;  
$query->answer($message = "Soon it will work 💡", $alert = true, $url = null, $cacheTime = 0);
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
$dbHost    = $env['DB_HOST'];
$dbPort    = $env['DB_PORT'];
$dbUser    = $env['DB_USER'];
$dbPass    = $env['DB_PASS'];
$dbName    = $env['DB_NAME'];

$settings = new \danog\MadelineProto\Settings;
$settings->setAppInfo((new \danog\MadelineProto\Settings\AppInfo)->setApiId((int)$API_ID)->setApiHash($API_HASH));

/* add this on weak server
->setMaxMediaSocketCount(1000)
*/
$connection = (new \danog\MadelineProto\Settings\Connection())->setTimeout(600.0)->setRetry(true);
$settings->setConnection($connection);

/* enable this on weak server
$files = (new \danog\MadelineProto\Settings\Files())->setUploadParallelChunks(7)->setDownloadParallelChunks(12);
$settings->setFiles($files);

$settings->getPeer()->setFullFetch(false)->setCacheAllPeersOnStartup(false);
*/

$logger = (new \danog\MadelineProto\Settings\Logger)->setLevel(\danog\MadelineProto\Logger::ERROR);
$settings->setLogger($logger);

// ---- MySQL --- db
if($DB_FLAG != 'no'){
$db = (new \danog\MadelineProto\Settings\Database\Mysql())
    ->setUri("tcp://$dbHost:$dbPort")
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
