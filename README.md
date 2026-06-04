# Сертификат на техническое обслуживание

## Состав проекта

- `certificate.php` - форма генерации и печати сертификата.
- `index.html` - статическая демо-версия для GitHub Pages.
- `background-chery.png` - фоновое изображение сертификата Chery.
- `background-tenet.png` - фоновое изображение сертификата Tenet.
- `fonts/TENETSans-Regular.otf` - фирменный шрифт TENET Sans.
- `fonts/TENETSans-SemiExpandedBold.otf` - жирное начертание фирменного шрифта.
- `logo-chery.svg` - логотип Chery.
- `logo-tenet.svg` - логотип Tenet.
- `certificate-counter.json` - создается автоматически при первой генерации номера.

## Установка

1. Загрузите файлы `certificate.php`, `background-chery.png`, `background-tenet.png`, `logo-chery.svg` и `logo-tenet.svg` в одну папку на сервере.
2. Убедитесь, что PHP может записывать в эту папку. Это нужно для файла счетчика `certificate-counter.json`.
3. Откройте `certificate.php` в браузере.

## Онлайн-демо

Для GitHub Pages используется `index.html`. Это статическая демо-версия для просмотра заказчиком: она не запускает PHP и создает тестовый номер сертификата в браузере. Рабочий серверный счетчик номеров остается в `certificate.php`.

## Реестр и Яндекс Диск

Рабочая PHP-версия сохраняет каждый сформированный сертификат в `certificates-registry.json`, создает локальный PDF в `generated-pdfs/` и обновляет таблицу `certificates.xlsx`.

Для загрузки PDF и XLSX на Яндекс Диск создайте `config.local.php` по примеру `config.example.php` и впишите временный или рабочий OAuth-токен:

```php
<?php
return [
    'yandex_disk_token' => 'ваш_токен',
    'yandex_disk_folder' => '/АТК Сертификаты',
    'yandex_disk_publish_files' => true,
    'pdf_generator' => [
        'enabled' => true,
        'browser_path' => 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    ],
];
```

Файлы `config.local.php`, `certificates-registry.json`, `certificates.xlsx` и папка `generated-pdfs/` не выгружаются в GitHub.

## Использование

1. Заполните фамилию, имя, отчество.
2. Выберите бренд: Chery или Tenet.
3. Выберите ТО. Пробег подставится автоматически.
4. Заполните скидку, VIN и срок действия.
5. Нажмите `Сгенерировать` для предпросмотра или `Печать` для печати сертификата.

## Автоматический пробег

- `ТО-0` - `5000 км`
- `ТО-1` - `10000 км`
- `ТО-2` - `20000 км`
- `ТО-3` - `30000 км`
- `ТО-4` - `40000 км`
- `ТО-5` - `50000 км`
- `ТО-6` - `60000 км`
- `ТО-7` - `70000 км`
- `ТО-8` - `80000 км`
- `ТО-9` - `90000 км`
- `ТО-10` - `100000 км`

## Логотипы

Файл автоматически ищет логотипы рядом с `certificate.php`. Основные имена:

- `logo-chery.svg`
- `logo-tenet.svg`

Также поддерживаются варианты `chery.*`, `cherry.*`, `tenet.*`, `tenant.*` в форматах `svg`, `png`, `webp`, `jpg`, `jpeg`.
