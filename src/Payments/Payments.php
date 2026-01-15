<?php
/* 
the project created by @wizardloop                                                                                                                                                                                                                                                     
*/
namespace GetAnyMessage\Payments;

use GetAnyMessage\Locales\Lang;

class Payments
{
    private object $context;
    public function __construct(object $context) {
        $this->context = $context;
    }

    public function sendDonationOptions(int $senderId, int $replyToMsgId): void {
        try {
			
$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$payment1 = $translate['payment1'] ?? 'support me';
$payment2 = $translate['payment2'] ?? 'Support me with:';
$payment_text = $translate['payment_text'] ?? 'Hi, thank you for wanting to donate to me🥰<br>Choose the donation amount you would like to give👇';

            $payload = (string) $senderId;
            $prices = [5, 25, 100, 150, 250, 400];
            $buttons = [];

            foreach ($prices as $price) {
                $labeledPrice = ['_' => 'labeledPrice', 'label' => 'star', 'amount' => $price];
                $invoice = ['_' => 'invoice', 'currency' => 'XTR', 'prices' => [$labeledPrice]];
                $inputMediaInvoice = [
                    '_' => 'inputMediaInvoice',
                    'title' => $payment1,
                    'description' => "$payment2 $price ⭐️",
                    'invoice' => $invoice,
                    'payload' => $payload,
                    'provider_data' => 'test'
                ];
                $exported = $this->context->payments->exportInvoice(invoice_media: $inputMediaInvoice);
                $buttons[] = ['text' => "⭐️ $price", 'url' => $exported['url']];
            }
            $inlineKeyboard = array_chunk($buttons, 3);
            $replyMarkup = ['inline_keyboard' => $inlineKeyboard];
            $inputReplyTo = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $replyToMsgId];
            $this->context->messages->sendMessage(
                no_webpage: true,
                peer: $senderId,
                reply_to: $inputReplyTo,
                message: $payment_text,
                reply_markup: $replyMarkup,
                parse_mode: 'HTML',
                effect: 5159385139981059251
            );
        } catch (\Throwable $e) {
            $this->context->messages->sendMessage(peer: $senderId, message: $e->getMessage());
        }
    }

    public function handlePreCheckout(array $update): void {
        try {
            $userId = $update['user_id'];
            $amount = $update['total_amount'];
            $queryId = $update['query_id'];
            $payload = $update['payload'];

$lang = (new Lang($this->context))->getUserLang($userId);
$translate = (new Lang($this->context))->loadTranslations($lang); 
$payment_amount = $translate['payment_amount'] ?? 'payment_amount:';
$payment_thanks = $translate['payment_thanks'] ?? '🎉 Thank you for your donation 🎉';
$payment_send = $translate['payment_send'] ?? 'Donation received! 🎉';

            $userInfo = $this->context->getInfo($userId);
            $firstName = $userInfo['User']['first_name'] ?? 'null';

            $usernames = $userInfo['User']['usernames'] ?? [];
            $usernameList = array_map(fn($u) => "@" . $u['username'], $usernames);
            $usernameString = implode(" ", $usernameList);
            $username = $userInfo['User']['username'] ?? ($usernameString ?: '(null)');
            if ($username && !str_starts_with($username, '@')) {
                $username = "@" . $username;
            }

            $success = $this->context->messages->setBotPrecheckoutResults(success: true, query_id: $queryId);
            if ($success) {
                $this->context->messages->sendMessage(
                    peer: $userId,
                    message: "$payment_amount $amount ⭐️\n$payment_thanks",
                    parse_mode: 'HTML',
                    effect: 5159385139981059251
                );

                $this->sendMessageToAdmins("$payment_send\nFIRSTNAME: <a href='mention:$userId'>$firstName</a>\nID: <a href='mention:$userId'>$userId</a>\nUSERNAME: $username\n$payment_amount $amount ⭐️", 'HTML');
            }
        } catch (\Throwable $e) {
        }
    }

    private function sendMessageToAdmins(string $text, string $parseMode): void {
        try{
        $admins = method_exists($this->context, 'getAdminIds') ? $this->context->getAdminIds() : [];
            foreach ($admins as $adminId) {
                 try {
                    $this->context->messages->sendMessage(peer: $adminId, message: $text, parse_mode: $parseMode);
                 } catch (\Throwable $e) { continue; }
            }
        } catch (\Throwable $e) {}
    }

    public function donate(int $senderId, int $msgid): void {
		try{
$lang = (new Lang($this->context))->getUserLang($senderId);
$translate = (new Lang($this->context))->loadTranslations($lang); 

$payment1 = $translate['payment1'] ?? 'support me';
$payment2 = $translate['payment2'] ?? 'Support me with:';
$payment_text = $translate['payment_text'] ?? 'Hi, thank you for wanting to donate to me🥰<br>Choose the donation amount you would like to give👇';
$back_menu_txt = $translate['back_menu_txt'] ?? '🔙 Back 🔙';
$back_menu_data = $translate['back_menu_data'] ?? 'backStart';

            $payload = (string) $senderId;
            $prices = [5, 25, 100, 150, 250, 400];
            $buttons = [];

            foreach ($prices as $price) {
                $labeledPrice = ['_' => 'labeledPrice', 'label' => 'star', 'amount' => $price];
                $invoice = ['_' => 'invoice', 'currency' => 'XTR', 'prices' => [$labeledPrice]];
                $inputMediaInvoice = [
                    '_' => 'inputMediaInvoice',
                    'title' => $payment1,
                    'description' => "$payment2 $price ⭐️",
                    'invoice' => $invoice,
                    'payload' => $payload,
                    'provider_data' => 'test'
                ];
                $exported = $this->context->payments->exportInvoice(invoice_media: $inputMediaInvoice);
                $buttons[] = ['text' => "⭐️ $price", 'url' => $exported['url']];
            }
            $inlineKeyboard = array_chunk($buttons, 3);
			$inlineKeyboard[] = [['text'=>$back_menu_txt,'callback_data'=>$back_menu_data]];
            $replyMarkup = ['inline_keyboard' => $inlineKeyboard];

$this->context->messages->editMessage(
peer: $senderId, 
id: $msgid, 
message: $payment_text, 
reply_markup: $replyMarkup, 
parse_mode: 'HTML'
);

        } catch (\Throwable $e) {}
		}

}
