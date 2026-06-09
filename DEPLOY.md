# Разворачивание сертификатов АТК

## Что входит в приложение

Это одностраничное PHP-приложение. Основной файл: `certificate.php`.

Приложение:

- формирует сертификат на техническое обслуживание;
- автоматически присваивает номер сертификата;
- создает PDF через headless-браузер;
- ведет локальный реестр `certificates-registry.json`;
- создает таблицу учета `certificates.xlsx`;
- выгружает PDF и XLSX на Яндекс Диск.

## Требования к серверу

Минимальное окружение:

- PHP 8.1 или новее;
- веб-сервер Apache или Nginx;
- включенные PHP-расширения:
  - `curl` для Яндекс.Диска;
  - `zip` для XLSX;
  - `mbstring`;
  - `json`;
- права на запись в папку приложения;
- установленный браузер для PDF:
  - Linux: Google Chrome или Chromium;
  - Windows: Microsoft Edge, Google Chrome или Chromium.

На Linux-сервере для Nginx/PHP-FPM обычно нужны пакеты:

```bash
apt-get update
apt-get install -y php-fpm php-cli php-curl php-zip php-mbstring unzip
```

Для стабильной генерации PDF на Ubuntu-сервере рекомендуется поставить обычный Google Chrome:

```bash
wget -O /tmp/google-chrome-stable_current_amd64.deb https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
apt-get install -y /tmp/google-chrome-stable_current_amd64.deb
```

Путь к браузеру после установки обычно:

```text
/usr/bin/google-chrome
```

## Установка файлов

1. Распакуйте архив в папку сайта, например:

```text
/var/www/atk-certificate
```

2. Создайте конфиг из примера:

```bash
cp config.example.php config.local.php
```

3. Откройте `config.local.php` и заполните:

```php
return [
    'yandex_disk_token' => 'ВАШ_ТОКЕН',
    'yandex_disk_folder' => '/АТК Сертификаты',
    'yandex_disk_publish_files' => true,
    'pdf_generator' => [
        'enabled' => true,
        'browser_path' => '/usr/bin/google-chrome',
    ],
];
```

Для Windows путь может быть, например:

```text
C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe
```

4. Дайте веб-серверу права на запись в папку приложения:

```bash
chown -R www-data:www-data /var/www/atk-certificate
find /var/www/atk-certificate -type d -exec chmod 755 {} +
find /var/www/atk-certificate -type f -exec chmod 644 {} +
```

Приложение само создаст рабочие файлы:

- `certificate-counter.json`;
- `certificates-registry.json`;
- `certificates.xlsx`;
- папку `generated-pdfs/`.

## Пример Nginx

Если приложение должно открываться по адресу `/atk/`:

```nginx
location = /atk {
    return 301 /atk/;
}

location /atk/ {
    root /var/www;
    index certificate.php;
    try_files $uri $uri/ /atk/certificate.php?$query_string;
}

location ~ ^/atk/(.+\.php)$ {
    root /var/www;
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

В этом варианте удобно сделать симлинк:

```bash
ln -sfn /var/www/atk-certificate /var/www/atk
nginx -t
systemctl reload nginx
```

Если приложение открывается в корне отдельного сайта, настройте корень сайта на папку приложения и используйте `certificate.php` как индексный файл.

## Получение токена Яндекс.Диска

Токен нужен для загрузки PDF и таблицы XLSX на Яндекс Диск.

1. Войдите в аккаунт Яндекса, на Диск которого должны сохраняться файлы.
2. Откройте Яндекс OAuth и создайте приложение для доступа к API:

```text
https://oauth.yandex.ru/client/new
```

3. В правах приложения выберите доступ к Яндекс Диску. Для работы приложения нужны права на чтение и запись файлов Диска.
4. Получите OAuth-токен для созданного приложения. Его нужно вставить в `config.local.php` в поле `yandex_disk_token`.
5. Не публикуйте `config.local.php` в Git и не передавайте токен посторонним.

Официальная документация:

- REST API Яндекс.Диска: https://yandex.ru/dev/disk/rest?lang=ru
- OAuth Яндекс ID: https://yandex.ru/dev/id/doc/en/concepts/ya-oauth-intro
- Получение токена вручную: https://yandex.com/dev/id/doc/en/tokens/debug-token

## Проверка после установки

1. Откройте сайт с `certificate.php`.
2. Заполните форму сертификата.
3. Нажмите `Сгенерировать`.
4. Проверьте, что появились:
   - PDF в `generated-pdfs/`;
   - строка в `certificates-registry.json`;
   - обновленный `certificates.xlsx`;
   - PDF и `certificates.xlsx` в папке Яндекс.Диска.

Если PDF не создается, проверьте:

- правильный путь `pdf_generator.browser_path`;
- запускается ли браузер от пользователя веб-сервера;
- есть ли права на запись в папку приложения;
- нет ли ограничений snap Chromium. На Ubuntu лучше использовать обычный Google Chrome `.deb`.
