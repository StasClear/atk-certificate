# Локальный запуск на Windows с сохранением в Яндекс.Диск

## Как это работает

Программа запускается на компьютере сотрудника из отдельной папки. PDF-сертификаты, реестр и таблица `certificates.xlsx` сохраняются в папку Яндекс.Диска на этом же компьютере. Дальше официальное приложение Яндекс.Диск синхронизирует эти файлы в облако.

OAuth-токен Яндекс.Диска для такого режима не нужен.

## Что нужно установить

1. Яндекс.Диск для Windows: https://disk.yandex.ru/download
2. PHP 8.1 или новее.

Самый простой вариант для Windows - Laragon. После установки Laragon обычно добавляет PHP в систему. Если файл `start-windows.cmd` пишет, что PHP не найден, нужно добавить PHP в `PATH` или запускать через среду, где доступна команда `php`.

## Установка пакета

1. Распакуйте архив приложения в отдельную папку, например:

```text
C:\ATK-Certificates
```

2. Убедитесь, что Яндекс.Диск синхронизируется на этом компьютере.

3. Создайте или проверьте файл `config.local.php` рядом с `certificate.php`.

Пример для стандартной папки Яндекс.Диска:

```php
<?php
return [
    'data_dir' => '%USERPROFILE%\\YandexDisk\\АТК Сертификаты',
    'yandex_disk_token' => '',
    'yandex_disk_folder' => '/АТК Сертификаты',
    'yandex_disk_publish_files' => false,
    'pdf_generator' => [
        'enabled' => true,
        'browser_path' => 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    ],
];
```

Если папка Яндекс.Диска называется иначе, замените строку `data_dir`, например:

```php
'data_dir' => 'D:\\YandexDisk\\АТК Сертификаты',
```

## Запуск

1. Дважды нажмите `start-windows.cmd`.
2. Откроется браузер с адресом:

```text
http://127.0.0.1:8090/certificate.php
```

3. Заполните сертификат и нажмите `Сгенерировать`.

Окно запуска закрывать нельзя, пока идет работа с сертификатами.

## Где будут файлы

В папке, указанной в `data_dir`, появятся:

- `certificates.xlsx` - таблица всех созданных сертификатов;
- `certificates-registry.json` - технический реестр;
- `certificate-counter.json` - счетчик номеров;
- `generated-pdfs\` - PDF-файлы сертификатов.

При стандартной настройке это будет:

```text
%USERPROFILE%\YandexDisk\АТК Сертификаты
```

## Проверка

После первого сертификата проверьте:

1. В папке Яндекс.Диска появилась папка `АТК Сертификаты`.
2. Внутри есть файл `certificates.xlsx`.
3. Внутри `generated-pdfs` есть PDF сертификата.
4. Значок Яндекс.Диска показывает, что синхронизация завершена.

## Если PDF не создается

Проверьте путь к браузеру в `config.local.php`.

Для Microsoft Edge обычно:

```text
C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe
```

или:

```text
C:\Program Files\Microsoft\Edge\Application\msedge.exe
```

Для Google Chrome обычно:

```text
C:\Program Files\Google\Chrome\Application\chrome.exe
```

После изменения пути закройте окно запуска и снова откройте `start-windows.cmd`.

