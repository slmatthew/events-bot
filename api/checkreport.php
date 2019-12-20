<?php

include 'config.php';
include 'exceptions.php';
include 'database.php';
include 'telegram.php';

if(isset($_REQUEST['key']) && $_REQUEST['key'] == API_KEY) {
	$rt = new ReportsTable();
	$tg = new Telegram(BOT_TOKEN);

	try {
		$result = [];

		$reports = $rt->getAllNotCompleted();
		if(!empty($reports)) {
			foreach($reports as $key => $report) {
				$time = time() - $report['ts'];
				if($time >= 300) {
					$result[$report['id']] = $rt->setCompleted($report['id'], 1);

					$poll = json_decode($report['poll'], true);

					$plus = count($poll['plus']);
					$minus = count($poll['minus']);

					$rkb = new ReplyKeyboard(true, true);
					$rkb->addButton("Новый репорт");

					if($plus > $minus) {
						if(strlen($report['proof']) == 0) {
							$tg->send("@".BOT_CHANNEL, implode("\n", [
								"⚡️ Новый репорт!\n",
								"*Мероприятие:* {$report['event']}",
								"*Нарушение:* {$report['accident']}"
							]), ['parse_mode' => 'Markdown']);

							$tg->editText(implode("\n", [
								"⚡️ Новый репорт!\n",
								"*Мероприятие:* {$report['event']}",
								"*Нарушение:* {$report['accident']}\n",
								"_Этот репорт был опубликован_"
							]), ['parse_mode' => 'Markdown', 'message_id' => $report['moder_msg_id']]);

							echo "no photo post\n";
						} else {
							$tg->sendPhoto("@".BOT_CHANNEL, implode("\n", [
								"⚡️ Новый репорт!\n",
								"*Мероприятие:* {$report['event']}",
								"*Нарушение:* {$report['accident']}"
							]), explode(',', $report['proof'])[0], ['parse_mode' => 'Markdown']);

							$tg->editCaption(implode("\n", [
								"⚡️ Новый репорт!\n",
								"*Мероприятие:* {$report['event']}",
								"*Нарушение:* {$report['accident']}\n",
								"_Этот репорт был опубликован_"
							]), ['parse_mode' => 'Markdown', 'message_id' => $report['moder_msg_id']]);

							echo "photo post\n";
						}

						$tg->send($report['user_id'], "Ваш репорт с мероприятия «*{$report['event']}*» был опубликован в нашем канале: @".BOT_CHANNEL, ['parse_mode' => 'Markdown', 'reply_markup' => $rkb->get()]);
					} elseif($plus <= $minus) {
						$tg->send($report['user_id'], "Ваш репорт с мероприятия «*{$report['event']}*» был отклонен модераторами", ['parse_mode' => 'Markdown', 'reply_markup' => $rkb->get()]);
						
						if(strlen($report['proof']) == 0) {
							$tg->editText(implode("\n", [
								"⚡️ Новый репорт!\n",
								"*Мероприятие:* {$report['event']}",
								"*Нарушение:* {$report['accident']}\n",
								"_Этот репорт был опубликован_"
							]), ['parse_mode' => 'Markdown', 'message_id' => $report['moder_msg_id']]);
						} else {
							$tg->editCaption(implode("\n", [
								"⚡️ Новый репорт!\n",
								"*Мероприятие:* {$report['event']}",
								"*Нарушение:* {$report['accident']}\n",
								"_Этот репорт был опубликован_"
							]), ['parse_mode' => 'Markdown', 'message_id' => $report['moder_msg_id']]);
						}

						echo "not post\n";
					} else {
						$tg->send($report['user_id'], "Ваш репорт с мероприятия «*{$report['event']}*» был закрыт по истечению времени", ['parse_mode' => 'Markdown', 'reply_markup' => $rkb->get()]);
						
						if(strlen($report['proof']) == 0) {
							$tg->editText(implode("\n", [
								"⚡️ Новый репорт!\n",
								"*Мероприятие:* {$report['event']}",
								"*Нарушение:* {$report['accident']}\n",
								"_Этот репорт был опубликован_"
							]), ['parse_mode' => 'Markdown', 'message_id' => $report['moder_msg_id']]);
						} else {
							$tg->editCaption(implode("\n", [
								"⚡️ Новый репорт!\n",
								"*Мероприятие:* {$report['event']}",
								"*Нарушение:* {$report['accident']}\n",
								"_Этот репорт был опубликован_"
							]), ['parse_mode' => 'Markdown', 'message_id' => $report['moder_msg_id']]);
						}

						echo "not post\n";
					}
				}
			}
		}
	} catch(DatabaseException $e) {

	}
}

?>