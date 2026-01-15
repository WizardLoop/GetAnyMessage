<?php
/* 
the project created by @wizardloop                                                                                                                                                                                                                                                     
*/
namespace GetAnyMessage\Handlers;

use GetAnyMessage\Locales\Lang;
use GetAnyMessage\Storage\Account;

use danog\MadelineProto\EventHandler\Message\PrivateMessage;
use danog\MadelineProto\EventHandler\SimpleFilter\Incoming;

use Amp\File;
use function Amp\File\write;
use function Amp\File\read;
use function Amp\File\exists;

class Handlers
{
    private object $context;
    public function __construct(object $context) {
        $this->context = $context;
    }

    public function startHandle(Incoming & PrivateMessage $message): void {
		try{

if($this->context->isSelfBot()){

        $senderId = $message->senderId;
        $messageid = $message->id;

$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$welcome = $translate['welcome'] ?? 'welcome';
$welcome_button1_txt = $translate['welcome_button1_txt'] ?? '👥 Support Chat';
$welcome_button1_data = $translate['welcome_button1_data'] ?? 'https://t.me/GetAnyMessageChat';
$welcome_button2_txt = $translate['welcome_button2_txt'] ?? 'Updates Channel 🔔';
$welcome_button2_data = $translate['welcome_button2_data'] ?? 'https://t.me/GetAnyMessageUpdates';
$welcome_button10_txt = $translate['welcome_button10_txt'] ?? 'Donate 🦾';
$welcome_button10_data = $translate['welcome_button10_data'] ?? 'Donate';
$source_code_txt = $translate['source_code_txt'] ?? '⭐️ Source Code 🔗';
$source_code_url = $translate['source_code_url'] ?? 'https://github.com/WizardLoop/GetAnyMessage';

$language_name = $translate['language_name'] ?? 'English';
$language_flag = $translate['language_flag'] ?? '🇬🇧';
$language_button = $language_flag . " " . $language_name;
$language_data = $translate['language_data'] ?? 'setLanguage';

$bot_API_markup[] = [['text'=>$welcome_button1_txt,'url'=>$welcome_button1_data],['text'=>$welcome_button2_txt,'url'=>$welcome_button2_data]];
$bot_API_markup[] = [['text'=>$welcome_button10_txt,'callback_data'=>$welcome_button10_data]];
$bot_API_markup[] = [['text'=>$language_button,'callback_data'=>$language_data]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $messageid];
$this->context->messages->sendMessage(peer: $message->senderId,
reply_to: $inputReplyToMessage,
message: $welcome,
reply_markup: $bot_API_markup,
parse_mode: 'HTML',
effect: 5046509860389126442);

    if (!file_exists(__DIR__."/../data")) {
        mkdir(__DIR__."/../data");
    }
    if (!file_exists(__DIR__."/../data/$senderId")) {
        mkdir(__DIR__."/../data/$senderId");
    }
    if (file_exists(__DIR__."/../data/$senderId/grs1.txt")) {
        unlink(__DIR__."/../data/$senderId/grs1.txt");
    }

}

        } catch (\Throwable $e) {
$inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $message->id];
$this->context->messages->sendMessage(peer: $message->senderId,
reply_to: $inputReplyToMessage,
message: $e->getMessage());
		}
	}

    public function setLanguage(int $senderId, int $msgid): void {
		try{	
$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$language_choose = $translate['language_choose'] ?? 'language_choose';

$languageButtons = (new Lang($this->context))->getLanguageButtons(); 
	
$this->context->messages->editMessage(
peer: $senderId, 
id: $msgid, 
message: $language_choose, 
reply_markup: $languageButtons,
parse_mode: 'HTML'
);

        } catch (\Throwable $e) {}
		}

    public function backStart(int $senderId, int $msgid): void {
		try{
$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$welcome = $translate['welcome'] ?? 'welcome';
$welcome_button1_txt = $translate['welcome_button1_txt'] ?? '👥 Support Chat';
$welcome_button1_data = $translate['welcome_button1_data'] ?? 'https://t.me/GetAnyMessageChat';
$welcome_button2_txt = $translate['welcome_button2_txt'] ?? 'Updates Channel 🔔';
$welcome_button2_data = $translate['welcome_button2_data'] ?? 'https://t.me/GetAnyMessageUpdates';
$welcome_button10_txt = $translate['welcome_button10_txt'] ?? 'Donate 🦾';
$welcome_button10_data = $translate['welcome_button10_data'] ?? 'Donate';
$source_code_txt = $translate['source_code_txt'] ?? '⭐️ Source Code 🔗';
$source_code_url = $translate['source_code_url'] ?? 'https://github.com/WizardLoop/GetAnyMessage';

$language_name = $translate['language_name'] ?? 'English';
$language_flag = $translate['language_flag'] ?? '🇬🇧';
$language_button = $language_flag . " " . $language_name;
$language_data = $translate['language_data'] ?? 'setLanguage';

$bot_API_markup[] = [['text'=>$welcome_button1_txt,'url'=>$welcome_button1_data],['text'=>$welcome_button2_txt,'url'=>$welcome_button2_data]];
$bot_API_markup[] = [['text'=>$welcome_button10_txt,'callback_data'=>$welcome_button10_data]];
$bot_API_markup[] = [['text'=>$language_button,'callback_data'=>$language_data]];
$bot_API_markup = [ 'inline_keyboard'=> $bot_API_markup,];

$this->context->messages->editMessage(
peer: $senderId, 
id: $msgid, 
message: $welcome, 
reply_markup: $bot_API_markup, 
parse_mode: 'HTML'
);

if (file_exists(__DIR__."/../data/$senderId/grs1.txt")) {
unlink(__DIR__."/../data/$senderId/grs1.txt");
}

        } catch (\Throwable $e) {}
		}

    public function langCommand(Incoming & PrivateMessage $message): void {
		try{

if($this->context->isSelfBot()){

        $senderId = $message->senderId;
        $messageid = $message->id;

$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$language_choose = $translate['language_choose'] ?? 'language_choose';

$languageButtons = (new Lang($this->context))->getLanguageButtons(); 

$inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $messageid];
$this->context->messages->sendMessage(peer: $message->senderId,
reply_to: $inputReplyToMessage,
message: $language_choose,
reply_markup: $languageButtons,
parse_mode: 'HTML'
);
}
        } catch (\Throwable $e) {
$inputReplyToMessage = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $message->id];
$this->context->messages->sendMessage(peer: $message->senderId,
reply_to: $inputReplyToMessage,
message: $e->getMessage());
		}
	}

}
