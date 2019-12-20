<?php

include 'config.php';
include 'modules/exceptions.php';
include 'modules/telegram.php';
include 'modules/commands.php';
include 'modules/database.php';

$cmd = new CommandsEngine();

$tg = new TelegramLongpoll(BOT_TOKEN);

$tg->setLpParams(['message', 'callback_query'], 25);
$tg->startLp(function($data) use($tg) {
	foreach($data['result'] as $key => $update) {
		if(!isset($update['message']) && !isset($update['callback_query'])) continue;

		if(isset($update['message'])) {
			$chat_id = $update['message']['chat']['id'];
			$from_id = isset($update['message']['from']) ? $update['message']['from']['id'] : 0;
			$text = isset($update['message']['text']) ? $update['message']['text'] : '';
			$text_l = mb_strtolower($text);

			if($chat_id != BOT_MODER_CHAT && $from_id > 0) {
				try {
					$ut = new UsersTable();

					$user = $ut->getById($from_id);
					if(empty($user)) {
						$ut->create($from_id);
					}

					$user = $ut->getById($from_id);

					if((int)$user['step'] == 0) {
						$rkb = new ReplyKeyboard(true, true);
						$rkb->addButton("Новый репорт");

						if($text_l == '/start' || $text_l == '/help') {
							$tg->send($chat_id, implode("\n", [
								"*Привет!*\n",
								"Это — бот для анонимной отправки сообщений о нарушениях на политических мероприятиях в России. Например, через бота Вы можете сообщить о вбросах на выборах любого уровня. Достаточно лишь указать мероприятие, описать нарушение и прикрепить доказательства.\n",
								"Исходный код бота доступен здесь: https://github.com/slmatthew/events-bot"
							]), ['reply_markup' => $rkb->get(), 'parse_mode' => 'Markdown']);
						} elseif($text_l == 'новый репорт') {
							$ut = new UsersTable();
							if($ut->setStep($from_id, 1)) {
								$rt = new ReportsTable();
								$rt->create($from_id, '', '', []);

								$tg->send($chat_id, implode("\n", [
									"*Хорошо.* Вы решили отправить новый репорт.\n",
									"Пожалуйста, напишите название мероприятия, на котором было замечено нарушение. Если есть возможность, то можете указать улицу и номер дома.\n",
									"_Например:_ митинг на Тверской, угол дома 1 возле поворота"
								]), ['parse_mode' => 'Markdown', 'reply_markup' => json_encode(['remove_keyboard' => true])]);
							} else $tg->send($chat_id, "Упс! Кажется, у нас проблемы. Попробуйте позже.");
						} else {
							$tg->send($chat_id, "Вы можете посмотреть информацию о боте введя команду /help", ['reply_markup' => $rkb->get()]);
						}
					} else {
						$rt = new ReportsTable();

						$user_report = $rt->getLastByUserId($from_id);
						if(empty($user_report)) return $tg->send($from_id, "У вас нет активного репорта");

						switch((int)$user['step']) {
							case 1: // челик ОТПРАВИТ мероприятие И БУДЕТ ЭТОТ ОБРАБОТЧИК
								$rt->setEvent($user_report['id'], $text);

								$tg->send($chat_id, implode("\n", [
									"*Отлично, Вы указали мероприятие.* Самое время описать нарушение, которое Вы заметили.\n",
									"_Например:_ избиение человека"
								]), ['parse_mode' => 'Markdown']);

								$ut->setStep($from_id, 2);
								break;

							case 2: // челик ОТПРАВИТ правонарушение И БУДЕТ ЭТОТ ОБРАБОТЧИК
								$rt->setAccident($user_report['id'], $text);

								$tg->send($chat_id, "*Почти закончили!* Осталось подтверждение нарушения. На данный момент поддерживаются только фотографии. Максимум одно вложение. Если у Вас нет доказательств нарушения, то введите команду /skip", ['parse_mode' => 'Markdown']);

								$ut->setStep($from_id, 3);
								break;

							case 3: // челик ОТПРАВИТ пруф И БУДЕТ ЭТОТ ОБРАБОТЧИК
								if(isset($update['message']['photo']) || $text_l == '/skip') {
									if($text_l == '/skip') {
										$rt->setProof($user_report['id'], []);

										$file_id = '';
									} else {
										$photo = $update['message']['photo'][count($update['message']['photo']) - 1];
										$file_id = $photo['file_id'];

										$rt->setProof($user_report['id'], [$file_id]);
									}

									$tg->send($chat_id, "*Великолепно!* Ваш репорт отправлен на проверку модераторам. Если модераторы решат опубликовать Ваш репорт, он появится в канале @".BOT_CHANNEL, ['parse_mode' => 'Markdown']);
								
									$user_report = $rt->getLastByUserId($from_id);

									$ikb = new InlineKeyboard();
									$ikb->addButton("👍🏻", ['callback_data' => "like#{$user_report['id']}"]);
									$ikb->addButton("👎🏻", ['callback_data' => "dislike#{$user_report['id']}"]);

									if(strlen($file_id) > 0) {
										$msgr = $tg->sendPhoto(BOT_MODER_CHAT, implode("\n", [
											"⚡️ Новый репорт!\n",
											"*Мероприятие:* {$user_report['event']}",
											"*Нарушение:* {$user_report['accident']}"
										]), $file_id, ['parse_mode' => 'Markdown', 'reply_markup' => $ikb->get()]);
									} else {
										$msgr = $tg->send(BOT_MODER_CHAT, implode("\n", [
											"⚡️ Новый репорт!\n",
											"*Мероприятие:* {$user_report['event']}",
											"*Нарушение:* {$user_report['accident']}"
										]), ['parse_mode' => 'Markdown', 'reply_markup' => $ikb->get()]);
									}

									$ut->setStep($from_id, 0);
									$rt->setUserCompleted($user_report['id'], 1);
									$rt->addMessageId($user_report['id'], $msgr['result']['message_id']);
								} else $tg->send($chat_id, "Пожалуйста, отправьте фотографию с правонарушением или введите команду /skip, если у Вас нет доказательств.");
								break;

							default:
								$rkb = new ReplyKeyboard(true, true);
								$rkb->addButton("Новый репорт");

								$tg->send($chat_id, "Вы можете посмотреть информацию о боте введя команду /help", ['reply_markup' => $rkb->get()]);

								$ut->setStep($from_id, 0);
								break;
						}
					}
				} catch(DatabaseException $e) {
					try {
						$ut = new UsersTable();
						$ut->setStep($from_id, 0);
					} catch(DatabaseException $e) {

					} finally {
						$tg->send($chat_id, "Ой... Что-то не так на нашей стороне. Попробуйте отправить репорт позже.");
					}
				}
			}
		} elseif(isset($update['callback_query'])) {
			if(isset($update['callback_query']['message'])) {
				$chat_id = $update['callback_query']['message']['chat']['id'];
				$from_id = $update['callback_query']['from']['id'];
				$cbq_id = (string)$update['callback_query']['id'];
				$data = isset($update['callback_query']['data']) ? $update['callback_query']['data'] : '';

				if($chat_id == BOT_MODER_CHAT) {
					$realdata = explode('#', $data);
					if(isset($realdata[0]) && isset($realdata[1]) && in_array($realdata[0], ['like', 'dislike']) && (int)$realdata[1] > 0) {
						$ut = new UsersTable();

						try {
							$user = $ut->getById($from_id);
							if(!empty($user) && $user['moderator']) {
								$rt = new ReportsTable();
								$report = $rt->getById((int)$realdata[1]);
								if(!empty($report) && !$report['completed'] && !$report['posted']) {
									$vote_type = $realdata[0] == 'like' ? 'plus' : 'minus';

									if($rt->addVote($report['id'], $from_id, $vote_type)) {
										$tg->sendCbAnswer($cbq_id, ['text' => "Вы выбрали ".($realdata[0] == 'like' ? '👍🏻' : '👎🏻')]);
									} else $tg->sendCbAnswer($cbq_id, ['text' => "🚫 Произошла ошибка"]);
								} else $tg->sendCbAnswer($cbq_id, ['text' => "🚫 С этим репортом нельзя взаимодействовать"]);
							} else throw new AccessException(json_encode($user, JSON_UNESCAPED_UNICODE));
						} catch(DatabaseException $e) {
							$tg->sendCbAnswer($cbq_id, ['text' => "Что-то не так с нашей базой данных :( Мы уже решаем проблему!"]);
						} catch(VoteException $e) {
							$tg->sendCbAnswer($cbq_id, ['text' => "Вы уже проголосовали"]);
						} catch(ParamException $e) {
							$tg->sendCbAnswer($cbq_id, ['text' => "Что-то не так с нашим ботом :( Мы уже решаем проблему!"]);
						} catch(AccessException $e) {
							$tg->sendCbAnswer($cbq_id, ['text' => "Вы не можете голосовать"]); 
						}
					} else $tg->sendCbAnswer($cbq_id, ['text' => "🚫 Невалидный репорт"]);
				}
			}
		}
	}
});

?>