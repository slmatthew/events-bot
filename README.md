# Events bot
Бот для фиксации нарушений на политических мероприятиях в России. Настроенная версия: [@ruseventsbot](https://t.me/ruseventsbot)

## Настройка
Примеры конфигов находятся в файлах `config.php.example`. Измените все данные на свои и переименуйте оба файла в `config.php`.

Бот изначально работает на LP (метод `getUpdates`), но при должных знаниях Вы можете переоборудовать его под вебхуки.

## Описание
Юзер начинает создавать новый репорт, где вводит мероприятие, указывает нарушение и присылает доказательство (если есть). Далее репорт отправляется на проверку в чат модераторов, где проходит анонимное голосование за/против. Если больше голосов «за» — репорт будет опубликован.

## И ещё кое-что
Пожалуйста, не забывайте указывать [автора бота](https://t.me/slmatthew) при его запуске. Желательно также упомянуть [@prvmsky](https://t.me/prvmsky), ведь именно он решил создать такого бота. Спасибо!