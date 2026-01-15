<?php 

declare(strict_types=1);

/* 
the project created by @wizardloop                                                                                                                                                                                                                                                     
*/

namespace GetAnyMessage;

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    die("Autoload file not found. Please run 'composer install'.");
}
require_once $autoload;

use GetAnyMessage\Handlers\AdminPanel;
use GetAnyMessage\Handlers\Handlers;
use GetAnyMessage\Locales\Lang;
use GetAnyMessage\Payments\Payments;

use danog\MadelineProto\Broadcast\Filter;
use danog\MadelineProto\API;
use danog\MadelineProto\Broadcast\Progress;
use danog\MadelineProto\Broadcast\Status;
use danog\MadelineProto\EventHandler\Attributes\Handler;
use danog\MadelineProto\EventHandler\Filter\FilterCommand;
use danog\MadelineProto\EventHandler\Filter\FilterText;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Message\ChannelMessage;
use danog\MadelineProto\EventHandler\Message\PrivateMessage;
use danog\MadelineProto\EventHandler\Message\GroupMessage;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdmin;
use danog\MadelineProto\EventHandler\SimpleFilter\Incoming;
use danog\MadelineProto\EventHandler\SimpleFilter\IsReply;
use danog\MadelineProto\SimpleEventHandler;
use danog\MadelineProto\EventHandler\CallbackQuery;
use danog\MadelineProto\EventHandler\InlineQuery;
use danog\MadelineProto\EventHandler\Query\ButtonQuery;
use danog\MadelineProto\EventHandler\Filter\FilterButtonQueryData;
use danog\MadelineProto\EventHandler\Filter\Combinator\FiltersOr;
use danog\MadelineProto\EventHandler\Filter\FilterIncoming;
use danog\MadelineProto\EventHandler\Update;
use Amp\File;
use danog\MadelineProto\EventHandler\Filter\FilterCommandCaseInsensitive;


class GetAnyMessage extends SimpleEventHandler
{

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

#[FilterCommandCaseInsensitive('start')]
public function startCommand(Incoming & PrivateMessage  $message): void {
    (new Handlers($this))->startHandle($message);
}

#[FiltersOr(
new FilterButtonQueryData('backStart'),
new FilterButtonQueryData('setLanguage'),
)]
public function CallbackHandlers(CallbackQuery $query): void {
    try {
    $Handle = new Handlers($this);
    $senderId = $query->userId;
    $data = $query->data;
    $msgid = $query->messageId;  

    match ($data) {
        'backStart' => $Handle->backStart($senderId, $msgid),
        'setLanguage' => $Handle->setLanguage($senderId, $msgid),
        default => null,
    };
    } catch (\Throwable $e) {}
}

#[FilterCommandCaseInsensitive('lang')]
public function languageCommand(Incoming & PrivateMessage $message): void {
    (new Handlers($this))->langCommand($message);
}

#[Handler]
public function handleSetLanguage(CallbackQuery $query): void {
    try {
            $callbackData = $query->data; 
            $senderId = $query->userId; 
			
$bot_API_markup[] = [['text'=> "🔙", 'callback_data'=> "backStart"]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

        if (strpos($callbackData, 'setlang:') === 0) {

            $lang = explode(':', $callbackData)[1];
			$langObj = new Lang($this);
            $langSaved = $langObj->setUserLang($senderId, $lang);
            $lang = $langObj->getUserLang($senderId);
            $translate = $langObj->loadTranslations($lang); 
            $msg = $translate['language_selected'] ?? "Language changed.";

            if ($langSaved) {
$query->editText(
$message = $msg, 
$replyMarkup = $bot_API_markup
);
            } else {
$query->editText(
$message = "⚠️ unable to preserve the language.", 
$replyMarkup = $bot_API_markup
);
            }
        }
    } catch (\Throwable $e) {
$query->editText(
$message = "⚠️ " . $e->getMessage(), 
$replyMarkup = $bot_API_markup
);
    }
}

####### PAYMENTS #######
#[FilterButtonQueryData('donate')]
public function docateHandler(CallbackQuery $query): void {
    $Handle = (new Payments($this))->donate($query->userId, $query->messageId);
}

#[FilterCommandCaseInsensitive('donate')]
public function donateCommand(Incoming & PrivateMessage $message): void {
(new Payments($this))->sendDonationOptions(senderId: $message->senderId, replyToMsgId: $message->id);
}

public function onupdateBotPrecheckoutQuery($update): void {
(new Payments($this))->handlePreCheckout($update);
}


####### SAVE MESSAGES HANDLERS #######
#[Handler]
public function handlegetmsg(Incoming & PrivateMessage $message): void {		
try {

if ($this->isSelfBot()) {

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

$VAR_SENT = false; 

if (!function_exists(__NAMESPACE__ . '\\extractTelegramPaths')) {
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

if (!preg_match('/^http(s)?:\/\/t\.me\/.+\/?$/i', $messagetext)) {
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "Unsupported format!", parse_mode: 'HTML');
}
if (preg_match('/^http(s)?:\/\/t\.me\/.+\/?$/i', $messagetext)) {

if ($result1 === null) {
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "Unsupported format!", parse_mode: 'HTML');
} else {
$out1 = $result1['out1'] ?? null; 
$out2 = $result1['out2'] ?? null; 
$out3 = $result1['out3'] ?? null; 
$out4 = $result1['out4'] ?? null; 
$out5 = $result1['out5'] ?? null; 

if(!preg_match('/^\+/',$out1)){	
if ($out5 != null) {
$sentMessage = $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyToMessage, message: "Unsupported format!", parse_mode: 'HTML');
}else{
if ($out1 === 'c' || $out1 === 'C') {

if($out4 != null){
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ To use this feature use @GetAnyMessageRobot or @SaveAnyMessageBot</i>", parse_mode: 'HTML');
}else{
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ To use this feature use @GetAnyMessageRobot or @SaveAnyMessageBot</i>", parse_mode: 'HTML');
}

}elseif($out1 === 'b' || $out1 === 'B') {
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ To use this feature use @GetAnyMessageRobot or @SaveAnyMessageBot</i>", parse_mode: 'HTML');

}elseif($out1 === 'u' || $out1 === 'U') {
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ To use this feature use @GetAnyMessageRobot or @SaveAnyMessageBot</i>", parse_mode: 'HTML');

}else{

if($out3 != null){
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ To use this feature use @GetAnyMessageRobot or @SaveAnyMessageBot</i>", parse_mode: 'HTML');

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

} catch (\Throwable $e) {
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
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ To use this feature use @GetAnyMessageRobot or @SaveAnyMessageBot</i>", parse_mode: 'HTML');
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


}

}

} catch (\Throwable $e) {
$error = $e->getMessage();
$this->messages->sendMessage(peer: $senderid, reply_to: $inputReplyToMessage, message: "<i>❌ $error</i>", parse_mode: 'HTML');
}
}



####### ADMIN _ COMMANDS #######
#[FilterCommandCaseInsensitive('admin')]
public function AdminCommand(Incoming & PrivateMessage & FromAdmin $message): void {
(new AdminPanel($this))->handle($message);
}

#[FilterCommandCaseInsensitive('restart')]
public function restartCommand(Incoming & PrivateMessage & FromAdmin $message): void {
    try {
		$inputReplyTo = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $message->id];
        $this->messages->sendMessage(peer: $message->senderId, reply_to: $inputReplyTo, message: "✅");
        $this->restart();
    } catch (Throwable $e) {}
}

#[FiltersOr(
new FilterButtonQueryData('admin_back'), 
new FilterButtonQueryData('admin_stats'), 
new FilterButtonQueryData('admin_broadcast'), 
new FilterButtonQueryData('broadcast_back'),
new FilterButtonQueryData('back_broadcast'),
new FilterButtonQueryData('type_users'),
new FilterButtonQueryData('typemode1'),
new FilterButtonQueryData('typemode2'),
new FilterButtonQueryData('typemode3'),
new FilterButtonQueryData('typemode4'),
)]
    public function handleAdminButtons(CallbackQuery $query): void {
    try {
    $admin = new AdminPanel($this);
    $senderId = $query->userId;
    $data = $query->data;
    $msgid = $query->messageId;  
	
    match ($data) {
  'admin_stats' => $admin->statUsers($senderId, $msgid),
  'admin_back' => $admin->admin_back($senderId, $msgid),
  'broadcast_back' => $admin->broadcast_back($senderId, $msgid),
  'admin_broadcast' => $admin->admin_broadcast($senderId, $msgid),
  'back_broadcast' => $admin->back_broadcast($senderId, $msgid),
  'type_users' => $admin->type_users($senderId, $msgid),
  'typemode1' => $admin->typemode1($senderId, $msgid),
  'typemode2' => $admin->typemode2($senderId, $msgid),
  'typemode3' => $admin->typemode3($senderId, $msgid),
  'typemode4' => $admin->typemode4($senderId, $msgid),
    default => null,
    };
    } catch (\Throwable $e) {}
}

    #[Handler]
   public function handlebroadcast1(Incoming & PrivateMessage & FromAdmin $message): void {
(new AdminPanel($this))->handle_broadcast($message);
	}

  #[FilterButtonQueryData('send_broadcast')]
 public function send_broadcast(callbackQuery $query): void {
try {
$senderId = $query->userId;
$data = $query->data;
$msgid = $query->messageId;  
	
$lang = (new Lang($this))->getUserLang($senderId);
$translate = (new Lang($this))->loadTranslations($lang); 
$txt = $translate['broadcast_sender'] ?? 'broadcast_sender';

try { 
$this->messages->deleteMessages(revoke: true, id: [$msgid]); 
} catch (Throwable $e) { }
	
$sentMessage = $this->messages->sendMessage(
 peer: $senderId,
 message: $txt
);

$sentMessage2 = $this->extractMessageId($sentMessage);
Amp\File\write(__DIR__."/data/messagetoeditbroadcast1.txt", "$sentMessage2");
Amp\File\write(__DIR__."/data/messagetoeditbroadcast2.txt", "$senderId");

 if (file_exists(__DIR__."/data/$senderId/txt.txt")) {
$filexmsgidtxt = Amp\File\read(__DIR__."/data/$senderId/txt.txt");  
}else{
$filexmsgidtxt = null; 
}
  if (file_exists(__DIR__."/data/$senderId/ent.txt")) {
$filexmsgident = json_decode(Amp\File\read(__DIR__."/data/$senderId/ent.txt"),true);  
  }else{
$filexmsgident = null;  
  }	  
  if (file_exists(__DIR__."/data/$senderId/media.txt")) {
$filexmsgidmedia = Amp\File\read(__DIR__."/data/$senderId/media.txt");  
  }else{
$filexmsgidmedia = null;  
  }	 

    if (file_exists(__DIR__."/data/broadcastsend.txt")) {
$check2 = Amp\File\read(__DIR__."/data/broadcastsend.txt");    
if($check2 == "USERS"){


if($filexmsgidmedia != null){
	
if($filexmsgidtxt != null){

$broadcastId = $this->broadcastMessages(
messages: [['message' => "$filexmsgidtxt", 'entities' => $filexmsgident, 'media' => $filexmsgidmedia]],
            pin: false,
            filter: new Filter(
        allowUsers: true,
        allowBots: true,
        allowGroups: false,
        allowChannels: false,
        blacklist: [], 
        whitelist: null 
)
);


}else{
$broadcastId = $this->broadcastMessages(
messages: [['media' => $filexmsgidmedia]],
            pin: false,
            filter: new Filter(
        allowUsers: true,
        allowBots: true,
        allowGroups: false,
        allowChannels: false,
        blacklist: [], 
        whitelist: null 
)
);
}

}else{

if($filexmsgidtxt != null){

$broadcastId = $this->broadcastMessages(
messages: [['message' => "$filexmsgidtxt", 'entities' => $filexmsgident]],
            pin: false,
            filter: new Filter(
        allowUsers: true,
        allowBots: true,
        allowGroups: false,
        allowChannels: false,
        blacklist: [], 
        whitelist: null 
)
);

}
}

}
if($check2 == "CHANNELS"){


if($filexmsgidmedia != null){
	
if($filexmsgidtxt != null){

$broadcastId = $this->broadcastMessages(
messages: [['message' => "$filexmsgidtxt", 'entities' => $filexmsgident, 'media' => $filexmsgidmedia]],
            pin: false,
            filter: new Filter(
        allowUsers: false,
        allowBots: true,
        allowGroups: false,
        allowChannels: true,
        blacklist: [], 
        whitelist: null 
)
);


}else{
$broadcastId = $this->broadcastMessages(
messages: [['media' => $filexmsgidmedia]],
            pin: false,
            filter: new Filter(
        allowUsers: false,
        allowBots: true,
        allowGroups: false,
        allowChannels: true,
        blacklist: [], 
        whitelist: null 
)
);
}

}else{

if($filexmsgidtxt != null){

$broadcastId = $this->broadcastMessages(
messages: [['message' => "$filexmsgidtxt", 'entities' => $filexmsgident]],
            pin: false,
            filter: new Filter(
        allowUsers: false,
        allowBots: true,
        allowGroups: false,
        allowChannels: true,
        blacklist: [], 
        whitelist: null 
)
);

}
}

}
if($check2 == "GROUPS"){


if($filexmsgidmedia != null){
	
if($filexmsgidtxt != null){

$broadcastId = $this->broadcastMessages(
messages: [['message' => "$filexmsgidtxt", 'entities' => $filexmsgident, 'media' => $filexmsgidmedia]],
            pin: false,
            filter: new Filter(
        allowUsers: false,
        allowBots: true,
        allowGroups: true,
        allowChannels: false,
        blacklist: [], 
        whitelist: null 
)
);


}else{
$broadcastId = $this->broadcastMessages(
messages: [['media' => $filexmsgidmedia]],
            pin: false,
            filter: new Filter(
        allowUsers: false,
        allowBots: true,
        allowGroups: true,
        allowChannels: false,
        blacklist: [], 
        whitelist: null 
)
);
}

}else{

if($filexmsgidtxt != null){

$broadcastId = $this->broadcastMessages(
messages: [['message' => "$filexmsgidtxt", 'entities' => $filexmsgident]],
            pin: false,
            filter: new Filter(
        allowUsers: false,
        allowBots: true,
        allowGroups: true,
        allowChannels: false,
        blacklist: [], 
        whitelist: null 
)
);

}
}

}
if($check2 == "ALL"){

if($filexmsgidmedia != null){
	
if($filexmsgidtxt != null){

$broadcastId = $this->broadcastMessages(
messages: [['message' => "$filexmsgidtxt", 'entities' => $filexmsgident, 'media' => $filexmsgidmedia]],
            pin: false,
            filter: new Filter(
        allowUsers: true,
        allowBots: true,
        allowGroups: true,
        allowChannels: true,
        blacklist: [], 
        whitelist: null 
)
);


}else{
$broadcastId = $this->broadcastMessages(
messages: [['media' => $filexmsgidmedia]],
            pin: false,
            filter: new Filter(
        allowUsers: true,
        allowBots: true,
        allowGroups: true,
        allowChannels: true,
        blacklist: [], 
        whitelist: null 
)
);
}

}else{

if($filexmsgidtxt != null){

$broadcastId = $this->broadcastMessages(
messages: [['message' => "$filexmsgidtxt", 'entities' => $filexmsgident]],
            pin: false,
            filter: new Filter(
        allowUsers: true,
        allowBots: true,
        allowGroups: true,
        allowChannels: true,
        blacklist: [], 
        whitelist: null 
)
);

}
}

}	
}
    if (!file_exists(__DIR__."/data/broadcastsend.txt")) {

if($filexmsgidmedia != null){
	
if($filexmsgidtxt != null){

$broadcastId = $this->broadcastMessages(
messages: [['message' => "$filexmsgidtxt", 'entities' => $filexmsgident, 'media' => $filexmsgidmedia]],
            pin: false,
            filter: new Filter(
        allowUsers: true,
        allowBots: true,
        allowGroups: true,
        allowChannels: true,
        blacklist: [], 
        whitelist: null 
)
);


}else{
$broadcastId = $this->broadcastMessages(
messages: [['media' => $filexmsgidmedia]],
            pin: false,
            filter: new Filter(
        allowUsers: true,
        allowBots: true,
        allowGroups: true,
        allowChannels: true,
        blacklist: [], 
        whitelist: null 
)
);
}

}else{

if($filexmsgidtxt != null){

$broadcastId = $this->broadcastMessages(
messages: [['message' => "$filexmsgidtxt", 'entities' => $filexmsgident]],
            pin: false,
            filter: new Filter(
        allowUsers: true,
        allowBots: true,
        allowGroups: true,
        allowChannels: true,
        blacklist: [], 
        whitelist: null 
)
);

}
}

}

} catch (Throwable $e) {}
}

private int $lastLog = 0;
    #[Handler]
   public function handleBroadcastProgress(Progress $progress): void {
		try {
$progressStr = (string) $progress;

if (time() - $this->lastLog > 5 || $progress->status === Status::GATHERING_PEERS) {
            $this->lastLog = time();
 if (file_exists(__DIR__."/data/messagetoeditbroadcast2.txt")) {
$filexmsgid1 = Amp\File\read(__DIR__."/data/messagetoeditbroadcast2.txt");  
 if (file_exists(__DIR__."/data/messagetoeditbroadcast1.txt")) {
$filexmsgid2 = Amp\File\read(__DIR__."/data/messagetoeditbroadcast1.txt");  
			try {
$this->messages->editMessage(peer: $filexmsgid1, id: $filexmsgid2, message: "⏳ $progressStr", reply_markup: null);
} catch (Throwable $e) {}
}
}
}

if (time() - $this->lastLog > 5 || $progress->status === Status::FINISHED) {
            $this->lastLog = time();
if (file_exists(__DIR__."/data/broadcastsend.txt")) {
$broadcast_send = Amp\File\read(__DIR__."/data/broadcastsend.txt");
}
if (!file_exists(__DIR__."/data/broadcastsend.txt")) {
$broadcast_send = "ALL";
}

$pendingCount = $progress->pendingCount;
$sucessCount = $progress->successCount;
$sucessCount2 = $progress->failCount;

 if (file_exists(__DIR__."/data/messagetoeditbroadcast2.txt")) {
$filexmsgid1 = Amp\File\read(__DIR__."/data/messagetoeditbroadcast2.txt");  

 if (file_exists(__DIR__."/data/messagetoeditbroadcast1.txt")) {
$filexmsgid2 = Amp\File\read(__DIR__."/data/messagetoeditbroadcast1.txt");  

$bot_API_markup = ['inline_keyboard' => [[['text'=>"🔙",'callback_data'=>"admin_back"]]]];

			try {
$this->messages->editMessage(peer: $filexmsgid1, id: $filexmsgid2, message: "✅ $sucessCount
⏳ $pendingCount
❌ $sucessCount2", reply_markup: $bot_API_markup);
} catch (Throwable $e) {}

 if (file_exists(__DIR__."/data/$filexmsgid1/txt.txt")) {
unlink(__DIR__."/data/$filexmsgid1/txt.txt");  
}
  if (file_exists(__DIR__."/data/$filexmsgid1/ent.txt")) {
unlink(__DIR__."/data/$filexmsgid1/ent.txt");  
  }	  
  if (file_exists(__DIR__."/data/$filexmsgid1/media.txt")) {
unlink(__DIR__."/data/$filexmsgid1/media.txt");  
  }	 

 }
 }
 }
} catch (Throwable $e) {}
}


}

function RunBot(): void {
	try {
		
$env = parse_ini_file(__DIR__ . '/.env');

if (!isset($env['API_ID'], $env['API_HASH'], $env['BOT_TOKEN'])) {
    die("Missing environment variables in .env\n");
}

$API_ID   = $env['API_ID'];
$API_HASH = $env['API_HASH'];
$BOT_TOKEN = $env['BOT_TOKEN'];

$settings = new \danog\MadelineProto\Settings;

$settings->setAppInfo((new \danog\MadelineProto\Settings\AppInfo)->setApiId((int)$API_ID)->setApiHash($API_HASH));

GetAnyMessage::startAndLoopBot(__DIR__.'/bot.madeline', $BOT_TOKEN, $settings);

} catch (\Throwable $e) {
// if ($e instanceof \Amp\TimeoutException) {
echo "\n" . $e->getMessage() . "\n";
}
}
RunBot();
