<?php
/* 
the project created by @wizardloop                                                                                                                                                                                                                                                     
*/
namespace GetAnyMessage\Handlers;

use GetAnyMessage\Locales\Lang;

use danog\MadelineProto\EventHandler\Message\PrivateMessage;
use danog\MadelineProto\EventHandler\SimpleFilter\FromAdmin;
use danog\MadelineProto\EventHandler\SimpleFilter\Incoming;

use Amp\File;
use function Amp\File\write;
use function Amp\File\read;
use function Amp\File\exists;
use function Amp\File\mkdir;

class AdminPanel
{
    private object $context;
    public function __construct(object $context) {
        $this->context = $context;
    }

    public function handle(Incoming & PrivateMessage & FromAdmin $message): void
    {
		try{
        $senderId = $message->senderId;

$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$txt = $translate['admin_panel'] ?? 'admin_panel';
$button_txt1 = $translate['admin_panel_button_text1'] ?? 'admin_panel_button_text1';
$button_txt2 = $translate['admin_panel_button_text2'] ?? 'admin_panel_button_text2';
$button_data1 = $translate['admin_panel_button_data1'] ?? 'admin_panel_button_data1';
$button_data2 = $translate['admin_panel_button_data2'] ?? 'admin_panel_button_data2';
$admin_check = $translate['admin_check'] ?? 'admin_check';

        $admins = $this->context->getAdminIds();
        if (!in_array($senderId, $admins, true)) {
            $this->context->messages->sendMessage(
                peer: $senderId,
                message: $admin_check
            );
            return;
        }

        $buttons = [
            [['text' => $button_txt1, 'callback_data' => $button_data1]],
            [['text' => $button_txt2, 'callback_data' => $button_data2]],
        ];

        $this->context->messages->sendMessage(
            peer: $senderId,
            message: $txt,
            reply_markup: ['inline_keyboard' => $buttons]
        );
if (file_exists(__DIR__."/../data/$senderId/grs1.txt")) {
unlink(__DIR__."/../data/$senderId/grs1.txt");
}
        } catch (\Throwable $e) {}
		}

    public function statUsers(int $senderId, int $msgid): void
    {
		try{

$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$translate1 = $translate['stat_users1'] ?? 'stat_users1';
$translate2 = $translate['stat_users2'] ?? 'stat_users2';
$button_text = $translate['admin_back_button_text'] ?? 'admin_back_button_text';
$button_data = $translate['admin_back_button_data'] ?? 'admin_back_button_data';

        $admins = $this->context->getAdminIds();
        if (!in_array($senderId, $admins, true)) {
            return;
        }

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>$button_text,'callback_data'=>$button_data]
        ]
    ]
];

$this->context->messages->editMessage(peer: $senderId, id: $msgid, message: $translate1, reply_markup: null, parse_mode: 'HTML');

$dialogs = $this->context->getDialogIds();
$numTotal = count($dialogs);

$numChannels = $numSupergroups = $numChats = $numBots = 0;

        foreach ($dialogs as $peer) {
            try {
                $info = $this->context->getInfo($peer);
                if (!isset($info['type'])) continue;
                switch ($info['type']) {
                    case 'channel': $numChannels++; break;
                    case 'supergroup': $numSupergroups++; break;
                    case 'chat': $numChats++; break;
                    case 'bot': $numBots++; break;
                }
            } catch (Throwable $e) { continue; }
        }

$numUsers = $numTotal - ($numChannels + $numSupergroups + $numChats + $numBots);

$fmt = fn($n) => number_format($n, 0, '.', ',');

$message = "<b>$translate2</b>
- - - - - - - - - -
📢 CHANNELS: " . $fmt($numChannels) . "
💬 GROUPS: " . $fmt($numChats) . "
👥 SUPER-GROUPS: " . $fmt($numSupergroups) . "
🤖 BOTS: " . $fmt($numBots) . "
👤 USERS: " . $fmt($numUsers) . "
- - - - - - - - - -
<b>🎯 TOTAL: " . $fmt($numTotal) . "</b>";

$this->context->messages->editMessage(peer: $senderId, id: $msgid, message: $message, reply_markup: $bot_API_markup, parse_mode: 'HTML');
        } catch (\Throwable $e) {}
		}

    public function admin_back(int $senderId, int $msgid): void
    {
		try{

$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$txt = $translate['admin_panel'] ?? 'admin_panel';
$button_txt1 = $translate['admin_panel_button_text1'] ?? 'admin_panel_button_text1';
$button_txt2 = $translate['admin_panel_button_text2'] ?? 'admin_panel_button_text2';
$button_data1 = $translate['admin_panel_button_data1'] ?? 'admin_panel_button_data1';
$button_data2 = $translate['admin_panel_button_data2'] ?? 'admin_panel_button_data2';

        $admins = $this->context->getAdminIds();
        if (!in_array($senderId, $admins, true)) {
            return;
        }

        $buttons = [
            [['text' => $button_txt1, 'callback_data' => $button_data1]],
            [['text' => $button_txt2, 'callback_data' => $button_data2]],
        ];

$this->context->messages->editMessage(
peer: $senderId, 
id: $msgid, 
message: $txt, 
reply_markup: ['inline_keyboard' => $buttons], 
parse_mode: 'HTML'
);

if (file_exists(__DIR__."/../data/$senderId/grs1.txt")) {
unlink(__DIR__."/../data/$senderId/grs1.txt");
}

        } catch (\Throwable $e) {}
		}

    public function broadcast_back(int $senderId, int $msgid): void
    {
		try{

$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$txt = $translate['admin_panel'] ?? 'admin_panel';
$button_txt1 = $translate['admin_panel_button_text1'] ?? 'admin_panel_button_text1';
$button_txt2 = $translate['admin_panel_button_text2'] ?? 'admin_panel_button_text2';
$button_data1 = $translate['admin_panel_button_data1'] ?? 'admin_panel_button_data1';
$button_data2 = $translate['admin_panel_button_data2'] ?? 'admin_panel_button_data2';

        $admins = $this->context->getAdminIds();
        if (!in_array($senderId, $admins, true)) {
            return;
        }

try { 
$this->context->messages->deleteMessages(revoke: true, id: [$msgid]); 
} catch (Throwable $e) { }
	
$buttons = [
[['text' => $button_txt1, 'callback_data' => $button_data1]],
[['text' => $button_txt2, 'callback_data' => $button_data2]],
        ];

$this->context->messages->sendMessage(
 peer: $senderId,
 message: $txt,
 reply_markup: ['inline_keyboard' => $buttons]
);

  if (file_exists(__DIR__."/../data/$senderId/grs1.txt")) {
unlink(__DIR__."/../data/$senderId/grs1.txt");
}
  if (file_exists(__DIR__."/../data/$senderId/txt.txt")) {
unlink(__DIR__."/../data/$senderId/txt.txt");  
}
  if (file_exists(__DIR__."/../data/$senderId/ent.txt")) {
unlink(__DIR__."/../data/$senderId/ent.txt");  
  }	  
  if (file_exists(__DIR__."/../data/$senderId/media.txt")) {
unlink(__DIR__."/../data/$senderId/media.txt");  
  }	 

        } catch (\Throwable $e) {}
		}

    public function admin_broadcast(int $senderId, int $msgid): void
    {
		try{

$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$txt = $translate['admin_broadcast'] ?? 'admin_broadcast';
$button_txt = $translate['admin_broadcast_back_text'] ?? 'admin_broadcast_back_text';
$button_data = $translate['admin_broadcast_back_data'] ?? 'admin_broadcast_back_data';

        $admins = $this->context->getAdminIds();
        if (!in_array($senderId, $admins, true)) {
            return;
        }

        $buttons = [
            [['text' => $button_txt, 'callback_data' => $button_data]],
        ];

$this->context->messages->editMessage(
peer: $senderId, 
id: $msgid, 
message: $txt, 
reply_markup: ['inline_keyboard' => $buttons]
);

write(__DIR__."/../data/$senderId/grs1.txt", 'broadcast1');
write(__DIR__."/../data/$senderId/messagetodelete.txt", "$msgid");

        } catch (\Throwable $e) {}
		}
		
    public function handle_broadcast(Incoming & PrivateMessage & FromAdmin $message): void
    {
		try{
$messagetext = $message->message;
$messageid = $message->id;
$messagefile = $message->media;
$grouped_id = $message->groupedId;
$entities = $message->entities;
$senderId = $message->senderId;

        $admins = $this->context->getAdminIds();
        if (!in_array($senderId, $admins, true)) {
            return;
        }

$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 

    if (file_exists(__DIR__."/../data/$senderId/grs1.txt")) {
$check = read(__DIR__."/../data/$senderId/grs1.txt");    
if($check == "broadcast1"){
if (!preg_match('/^\/([Ss]tart|[Aa]dmin)/', $messagetext)) { 

$messageLength = mb_strlen($messagetext);
if($messageLength > 1024) {

$txt = $translate['admin_broadcast'] ?? 'admin_broadcast';
$button_txt = $translate['admin_broadcast_back_text'] ?? 'admin_broadcast_back_text';
$button_data = $translate['admin_broadcast_back_data'] ?? 'admin_broadcast_back_data';
	
			try {
$this->context->messages->deleteMessages(revoke: true, id: [$messageid]); 
        } catch (\Throwable $e) {}

$bot_API_markup = ['inline_keyboard' => 
    [
        [
['text'=>$button_txt,'callback_data'=>$button_data]
        ]
    ]
];

$sentMessage = $this->context->messages->sendMessage(peer: $senderId, message: "broadcast_limit", reply_markup: $bot_API_markup);

 if (file_exists(__DIR__."/../data/$senderId/messagetodelete.txt")) {
$filexmsgid = read(__DIR__."/../data/$senderId/messagetodelete.txt");  
			try {
$this->context->messages->deleteMessages(revoke: true, id: [$filexmsgid]); 
        } catch (\Throwable $e) {}
unlink(__DIR__."/../data/$senderId/messagetodelete.txt");
}

}else{
$button_txt1 = $translate['admin_broadcast_back_text'] ?? 'admin_broadcast_back_text';
$button_data1 = $translate['admin_broadcast_back_data'] ?? 'admin_broadcast_back_data';
$button_txt2 = $translate['broadcast_type'] ?? 'broadcast_type';
$button_data2 = $translate['broadcast_type_data'] ?? 'broadcast_type_data'; 
$button_txt3 = $translate['broadcast_send'] ?? 'broadcast_send';
$button_data3 = $translate['broadcast_send_data'] ?? 'broadcast_send_data';


unlink(__DIR__."/../data/$senderId/grs1.txt"); 

if($messagetext != null){
write(__DIR__."/../data/$senderId/txt.txt", "$messagetext");
write(__DIR__."/../data/$senderId/ent.txt", json_encode(array_map(static fn($e) => $e->toMTProto(),$entities,)));	
}
if(!$messagefile){
}else{
$botApiFileId = $message->media->botApiFileId;
write(__DIR__."/../data/$senderId/media.txt", "$botApiFileId");
}

			try {
$this->context->messages->deleteMessages(revoke: true, id: [$messageid]); 
        } catch (\Throwable $e) {}

 if (file_exists(__DIR__."/../data/$senderId/messagetodelete.txt")) {
$filexmsgid = read(__DIR__."/../data/$senderId/messagetodelete.txt");  
			try {
$this->context->messages->deleteMessages(revoke: true, id: [$filexmsgid]); 
        } catch (\Throwable $e) {}
unlink(__DIR__."/../data/$senderId/messagetodelete.txt");
}

if (file_exists(__DIR__."/../data/broadcastsend.txt")) {
$broadcast_send = read(__DIR__."/../data/broadcastsend.txt");
}
if (!file_exists(__DIR__."/../data/broadcastsend.txt")) {
$broadcast_send = "ALL";
}
$bot_API_markup[] = [['text'=>$button_txt2.$broadcast_send,'callback_data'=>$button_data2]];
$bot_API_markup[] = [['text'=>$button_txt3,'callback_data'=>$button_data3]];

$bot_API_markup[] = [['text'=>$button_txt1,'callback_data'=>$button_data1]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

 if (file_exists(__DIR__."/../data/$senderId/txt.txt")) {
$filexmsgidtxt = read(__DIR__."/../data/$senderId/txt.txt");  
}else{
$filexmsgidtxt = null; 
}
  if (file_exists(__DIR__."/../data/$senderId/ent.txt")) {
$filexmsgident = json_decode(read(__DIR__."/../data/$senderId/ent.txt"),true);  
  }else{
$filexmsgident = null;  
  }	  
  if (file_exists(__DIR__."/../data/$senderId/media.txt")) {
$filexmsgidmedia = read(__DIR__."/../data/$senderId/media.txt");  
  }else{
$filexmsgidmedia = null;  
  }	 

if($filexmsgidmedia != null){
	
if($filexmsgidtxt != null){
$sentMessage = $this->context->messages->sendMedia(peer: $senderId, message: "$filexmsgidtxt", entities: $filexmsgident, media: $filexmsgidmedia, reply_markup: $bot_API_markup);
}else{
$sentMessage = $this->context->messages->sendMedia(peer: $senderId, media: $filexmsgidmedia, reply_markup: $bot_API_markup);
}

}else{

if($filexmsgidtxt != null){
$sentMessage = $this->context->messages->sendMessage(peer: $senderId, message: "$filexmsgidtxt", entities: $filexmsgident, reply_markup: $bot_API_markup);
}
}

}


	
}


}

}

        } catch (\Throwable $e) {}

		}

    public function back_broadcast(int $senderId, int $msgid): void
{
	try{
$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$txt = $translate['broadcast_menu'] ?? 'broadcast_menu';
$button_txt1 = $translate['admin_broadcast_back_text'] ?? 'admin_broadcast_back_text';
$button_data1 = $translate['admin_broadcast_back_data'] ?? 'admin_broadcast_back_data';
$button_txt2 = $translate['broadcast_type'] ?? 'broadcast_type';
$button_data2 = $translate['broadcast_type_data'] ?? 'broadcast_type_data';
$button_txt3 = $translate['broadcast_send'] ?? 'broadcast_send';
$button_data3 = $translate['broadcast_send_data'] ?? 'broadcast_send_data';


if (file_exists(__DIR__."/../data/broadcastsend.txt")) {
$broadcast_send = read(__DIR__."/../data/broadcastsend.txt");
}
if (!file_exists(__DIR__."/../data/broadcastsend.txt")) {
$broadcast_send = "ALL";
}

$bot_API_markup[] = [['text'=>$button_txt2.$broadcast_send,'callback_data'=>$button_data2]];
$bot_API_markup[] = [['text'=>$button_txt3,'callback_data'=>$button_data3]];
$bot_API_markup[] = [['text'=>$button_txt1,'callback_data'=>$button_data1]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

 if (file_exists(__DIR__."/../data/$senderId/txt.txt")) {
$filexmsgidtxt = read(__DIR__."/../data/$senderId/txt.txt");  
}else{
$filexmsgidtxt = null; 
}
  if (file_exists(__DIR__."/../data/$senderId/ent.txt")) {
$filexmsgident = json_decode(read(__DIR__."/../data/$senderId/ent.txt"),true);  
  }else{
$filexmsgident = null;  
  }	

if($filexmsgidtxt != null){
$this->context->messages->editMessage(
peer: $senderId, 
id: $msgid, 
message: $filexmsgidtxt, 
entities: $filexmsgident,
reply_markup: $bot_API_markup
);

}else{
$this->context->messages->editMessage(
peer: $senderId, 
id: $msgid, 
message: $txt, 
reply_markup: $bot_API_markup
);

}

} catch (\Throwable $e) {}
}

    public function type_users(int $senderId, int $msgid) : void
{
try {
$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$txt = $translate['broadcast_type_select'] ?? 'broadcast_type_select';

$button_txt1 = $translate['broadcast_type_button_text1'] ?? 'broadcast_type_button_text1';
$button_data1 = $translate['broadcast_type_button_data1'] ?? 'broadcast_type_button_data1';
$button_txt2 = $translate['broadcast_type_button_text2'] ?? 'broadcast_type_button_text2';
$button_data2 = $translate['broadcast_type_button_data2'] ?? 'broadcast_type_button_data2';
$button_txt3 = $translate['broadcast_type_button_text3'] ?? 'broadcast_type_button_text3';
$button_data3 = $translate['broadcast_type_button_data3'] ?? 'broadcast_type_button_data3';
$button_txt4 = $translate['broadcast_type_button_text4'] ?? 'broadcast_type_button_text4';
$button_data4 = $translate['broadcast_type_button_data4'] ?? 'broadcast_type_button_data4';
$button_txt5 = $translate['broadcast_type_button_text5'] ?? 'broadcast_type_button_text5';
$button_data5 = $translate['broadcast_type_button_data5'] ?? 'broadcast_type_button_data5';

$bot_API_markup[] = [['text'=>$button_txt1,'callback_data'=>$button_data1]];
$bot_API_markup[] = [['text'=>$button_txt2,'callback_data'=>$button_data2]];
$bot_API_markup[] = [['text'=>$button_txt3,'callback_data'=>$button_data3]];
$bot_API_markup[] = [['text'=>$button_txt4,'callback_data'=>$button_data4]];
$bot_API_markup[] = [['text'=>$button_txt5,'callback_data'=>$button_data5]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$this->context->messages->editMessage(
peer: $senderId, 
id: $msgid, 
message: $txt, 
reply_markup: $bot_API_markup
);
} catch (\Throwable $e) {}
}

    public function typemode1(int $senderId, int $msgid) : void
{
try {
$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$txt = $translate['typemode_txt1'] ?? 'typemode_txt1';
$button_txt1 = $translate['broadcast_type_button_text5'] ?? 'broadcast_type_button_text5';
$button_data1 = $translate['broadcast_type_button_data5'] ?? 'broadcast_type_button_data5';

$bot_API_markup[] = [['text'=>$button_txt1,'callback_data'=>$button_data1]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$this->context->messages->editMessage(
peer: $senderId, 
id: $msgid, 
message: $txt, 
reply_markup: $bot_API_markup
);
write(__DIR__."/../data/broadcastsend.txt","USERS");
} catch (\Throwable $e) {}
}

    public function typemode2(int $senderId, int $msgid) : void
{
try {
$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$txt = $translate['typemode_txt2'] ?? 'typemode_txt2';
$button_txt1 = $translate['broadcast_type_button_text5'] ?? 'broadcast_type_button_text5';
$button_data1 = $translate['broadcast_type_button_data5'] ?? 'broadcast_type_button_data5';

$bot_API_markup[] = [['text'=>$button_txt1,'callback_data'=>$button_data1]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$this->context->messages->editMessage(
peer: $senderId, 
id: $msgid, 
message: $txt, 
reply_markup: $bot_API_markup
);
write(__DIR__."/../data/broadcastsend.txt","CHANNELS");
} catch (\Throwable $e) {}
}

    public function typemode3(int $senderId, int $msgid) : void
{
try {
$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$txt = $translate['typemode_txt3'] ?? 'typemode_txt3';
$button_txt1 = $translate['broadcast_type_button_text5'] ?? 'broadcast_type_button_text5';
$button_data1 = $translate['broadcast_type_button_data5'] ?? 'broadcast_type_button_data5';

$bot_API_markup[] = [['text'=>$button_txt1,'callback_data'=>$button_data1]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$this->context->messages->editMessage(
peer: $senderId, 
id: $msgid, 
message: $txt, 
reply_markup: $bot_API_markup
);
write(__DIR__."/../data/broadcastsend.txt","GROUPS");
} catch (\Throwable $e) {}
}

    public function typemode4(int $senderId, int $msgid) : void
{
try {
$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$txt = $translate['typemode_txt4'] ?? 'typemode_txt4';
$button_txt1 = $translate['broadcast_type_button_text5'] ?? 'broadcast_type_button_text5';
$button_data1 = $translate['broadcast_type_button_data5'] ?? 'broadcast_type_button_data5';

$bot_API_markup[] = [['text'=>$button_txt1,'callback_data'=>$button_data1]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$this->context->messages->editMessage(
peer: $senderId, 
id: $msgid, 
message: $txt, 
reply_markup: $bot_API_markup
);
write(__DIR__."/../data/broadcastsend.txt","ALL");
} catch (\Throwable $e) {}
}

}
