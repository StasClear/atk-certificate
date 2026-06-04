<?php
declare(strict_types=1);

$brands = [
    'chery' => [
        'label' => 'Chery',
        'dealer' => 'АТК-МОТОРС ЛЕСНАЯ',
        'logo_names' => ['logo-chery', 'chery', 'cherry', 'Logo-Chery', 'Chery', 'Cherry', 'CHERY', 'CHERRY'],
    ],
    'tenet' => [
        'label' => 'Tenet',
        'dealer' => 'АТК-МОТОРС ЛЕСНАЯ',
        'logo_names' => ['logo-tenet', 'tenet', 'tenant', 'Logo-Tenet', 'Tenet', 'Tenant', 'TENET', 'TENANT'],
    ],
];

$logoExtensions = ['svg', 'png', 'webp', 'jpg', 'jpeg'];
$serviceMileageMap = [
    'ТО-0' => '5 000 км',
    'ТО-2' => '15 000 км',
    'ТО-4' => '25 000 км',
    'ТО-5' => '35 000 км',
    'ТО-6' => '45 000 км',
    'ТО-7' => '55 000 км',
    'ТО-8' => '65 000 км',
];
$errors = [];
$generated = false;
$autoPrint = false;

function field(string $name, string $default = ''): string
{
    return trim((string)($_POST[$name] ?? $default));
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function find_logo(array $brand, array $extensions): ?string
{
    foreach ($brand['logo_names'] as $name) {
        foreach ($extensions as $extension) {
            $file = $name . '.' . $extension;
            if (is_file(__DIR__ . DIRECTORY_SEPARATOR . $file)) {
                return rawurlencode($file);
            }
        }
    }

    return null;
}

function next_certificate_number(): string
{
    $counterFile = __DIR__ . DIRECTORY_SEPARATOR . 'certificate-counter.json';
    $handle = fopen($counterFile, 'c+');

    if (!$handle) {
        throw new RuntimeException('Не удалось открыть файл счетчика сертификатов.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Не удалось заблокировать файл счетчика.');
        }

        rewind($handle);
        $raw = stream_get_contents($handle) ?: '';
        $data = json_decode($raw, true);
        $last = is_array($data) && isset($data['last']) ? (int)$data['last'] : 0;
        $next = $last + 1;

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode([
            'last' => $next,
            'updated_at' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($handle);
        flock($handle, LOCK_UN);

        return 'ATK-' . date('Y') . '-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    } finally {
        fclose($handle);
    }
}

if (($_GET['action'] ?? '') === 'next-number') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        echo json_encode([
            'ok' => true,
            'number' => next_certificate_number(),
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $exception) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => $exception->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}

$selectedBrandKey = field('brand', 'chery');
if (!isset($brands[$selectedBrandKey])) {
    $selectedBrandKey = 'chery';
}

$data = [
    'last_name' => field('last_name'),
    'first_name' => field('first_name'),
    'middle_name' => field('middle_name'),
    'brand' => $selectedBrandKey,
    'service_to' => field('service_to', 'ТО-0'),
    'mileage' => field('mileage'),
    'discount' => field('discount'),
    'vin' => strtoupper(field('vin')),
    'valid_until' => field('valid_until'),
    'certificate_number' => field('certificate_number'),
];

if (!isset($serviceMileageMap[$data['service_to']])) {
    $data['service_to'] = 'ТО-0';
}

if ($data['mileage'] === '') {
    $data['mileage'] = $serviceMileageMap[$data['service_to']];
}

$action = field('action');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['last_name' => 'Фамилия', 'first_name' => 'Имя', 'service_to' => 'ТО', 'mileage' => 'Пробег', 'discount' => 'Скидка', 'vin' => 'VIN', 'valid_until' => 'Срок действия'] as $key => $label) {
        if ($data[$key] === '') {
            $errors[] = 'Заполните поле "' . $label . '".';
        }
    }

    if ($data['vin'] !== '' && !preg_match('/^[A-HJ-NPR-Z0-9]{6,17}$/u', $data['vin'])) {
        $errors[] = 'VIN должен содержать от 6 до 17 латинских букв и цифр без I, O, Q.';
    }

    if ($data['valid_until'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['valid_until'])) {
        $errors[] = 'Укажите срок действия через поле даты.';
    }

    if (!$errors) {
        try {
            $data['certificate_number'] = next_certificate_number();
            $generated = true;
            $autoPrint = $action === 'print';
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$selectedBrand = $brands[$data['brand']];
$logo = find_logo($selectedBrand, $logoExtensions);
$brandAssets = [];
foreach ($brands as $key => $brand) {
    $brandAssets[$key] = [
        'label' => $brand['label'],
        'dealer' => $brand['dealer'],
        'logo' => find_logo($brand, $logoExtensions),
    ];
}
$serviceMileageJson = json_encode($serviceMileageMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$fullName = trim($data['last_name'] . ' ' . $data['first_name'] . ' ' . $data['middle_name']);
$formattedDate = $data['valid_until'] !== '' ? date('d.m.Y', strtotime($data['valid_until'])) : '';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Сертификат на техническое обслуживание</title>
    <style>
        :root {
            --ink: #142033;
            --muted: #66758a;
            --line: #9eb9d0;
            --blue: #2d699f;
            --paper: #f8fbfe;
            --panel: #ffffff;
            --accent: #1f557f;
            --danger: #a53535;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: var(--ink);
            background: #eef3f7;
            font-family: Arial, Helvetica, sans-serif;
        }

        .app {
            display: grid;
            grid-template-columns: minmax(320px, 420px) minmax(620px, 1fr);
            gap: 24px;
            min-height: 100vh;
            padding: 24px;
        }

        .controls {
            align-self: start;
            background: var(--panel);
            border: 1px solid #d7e0ea;
            border-radius: 8px;
            box-shadow: 0 16px 36px rgba(20, 32, 51, 0.08);
            padding: 22px;
            position: sticky;
            top: 24px;
        }

        .controls h1 {
            font-size: 22px;
            line-height: 1.2;
            margin: 0 0 6px;
        }

        .controls p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.45;
            margin: 0 0 18px;
        }

        .form-grid {
            display: grid;
            gap: 13px;
        }

        .field {
            display: grid;
            gap: 6px;
        }

        .field span,
        .brand-title {
            color: #34445a;
            font-size: 13px;
            font-weight: 700;
        }

        input,
        select {
            width: 100%;
            min-height: 42px;
            border: 1px solid #c8d5df;
            border-radius: 6px;
            color: var(--ink);
            font: inherit;
            padding: 9px 11px;
            background: #fff;
        }

        input:focus,
        select:focus {
            border-color: var(--blue);
            outline: 2px solid rgba(45, 105, 159, 0.16);
        }

        .brand-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .brand-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .brand-option span {
            align-items: center;
            border: 1px solid #c8d5df;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            font-weight: 700;
            justify-content: center;
            min-height: 42px;
            padding: 8px 10px;
            transition: 0.16s ease;
        }

        .brand-option input:checked + span {
            background: #eaf3fb;
            border-color: var(--blue);
            color: #174f83;
            box-shadow: inset 0 0 0 1px var(--blue);
        }

        .errors {
            background: #fff2f2;
            border: 1px solid #e5bcbc;
            border-radius: 6px;
            color: var(--danger);
            font-size: 14px;
            line-height: 1.35;
            padding: 10px 12px;
        }

        .errors ul {
            margin: 0;
            padding-left: 18px;
        }

        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 5px;
        }

        button {
            border: 0;
            border-radius: 6px;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            min-height: 44px;
            padding: 10px 12px;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
        }

        .btn-secondary {
            background: #dde8f1;
            color: #18344f;
        }

        .note {
            border-top: 1px solid #e2e9ef;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
            margin-top: 17px;
            padding-top: 13px;
        }

        .preview-wrap {
            align-items: flex-start;
            display: flex;
            justify-content: center;
            min-width: 0;
        }

        .certificate {
            position: relative;
            width: 794px;
            min-height: 1123px;
            overflow: hidden;
            background: #f8fbfe url("background.png") center bottom / cover no-repeat;
            border: 1px solid #bfd1df;
            box-shadow: 0 18px 50px rgba(19, 42, 66, 0.18);
            color: var(--ink);
            padding: 28px 34px 28px;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        .certificate::before {
            content: "";
            position: absolute;
            inset: 12px;
            border: 1px solid #9ebbd2;
            pointer-events: none;
            z-index: 4;
        }

        .certificate::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0.16) 0%, rgba(255,255,255,0.2) 45%, rgba(255,255,255,0.1) 100%);
            z-index: 0;
        }

        .fineprint {
            color: #263244;
            font-family: "Arial Narrow", Arial, Helvetica, sans-serif;
            font-size: 14px;
            line-height: 1.25;
            margin: 18px auto 54px;
            max-width: 560px;
            position: relative;
            text-align: center;
            z-index: 2;
        }

        .brand-block {
            align-items: center;
            display: grid;
            justify-items: center;
            min-height: 160px;
            position: relative;
            z-index: 2;
        }

        .brand-logo {
            align-items: center;
            display: flex;
            height: 92px;
            justify-content: center;
            margin-bottom: 8px;
            width: 300px;
        }

        .brand-logo img {
            max-height: 92px;
            width: 300px;
            object-fit: contain;
        }

        .logo-fallback {
            font-size: 54px;
            font-weight: 800;
            letter-spacing: 4px;
            text-transform: uppercase;
        }

        .dealer {
            font-size: 25px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .main {
            margin: 54px auto 0;
            max-width: 620px;
            position: relative;
            text-align: center;
            z-index: 2;
        }

        .cert-title {
            align-items: end;
            display: flex;
            gap: 14px;
            justify-content: center;
            margin-bottom: 34px;
        }

        .cert-title strong {
            font-size: 47px;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .cert-number {
            border-bottom: 2px solid #2b3341;
            display: inline-block;
            font-size: 29px;
            font-weight: 700;
            min-width: 190px;
            padding: 0 8px 4px;
            text-align: left;
        }

        .separator {
            align-items: center;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 14px;
            margin: 0 auto 30px;
            max-width: 560px;
        }

        .separator::before,
        .separator::after {
            background: #a7bfd4;
            content: "";
            height: 1px;
        }

        .separator span {
            background: var(--blue);
            height: 10px;
            transform: rotate(45deg);
            width: 10px;
        }

        .recipient {
            font-size: 26px;
            line-height: 1.25;
            margin: 0 0 26px;
            min-height: 34px;
        }

        .service-line,
        .discount-line {
            font-size: 31px;
            line-height: 1.35;
            margin: 14px 0;
        }

        .fill {
            border-bottom: 2px solid #293445;
            display: inline-block;
            font-weight: 500;
            min-width: 155px;
            padding: 0 8px 2px;
            text-align: center;
        }

        .discount-line .fill {
            min-width: 230px;
        }

        .showroom {
            bottom: 176px;
            display: none;
            left: 0;
            position: absolute;
            right: 0;
            z-index: 1;
        }

        .showroom .building {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.95), rgba(222,235,246,0.88)),
                repeating-linear-gradient(90deg, rgba(49,89,122,0.22) 0 2px, transparent 2px 52px);
            border-top: 2px solid rgba(255,255,255,0.9);
            height: 124px;
            margin-top: 58px;
        }

        .cars {
            align-items: end;
            display: flex;
            gap: 22px;
            justify-content: center;
            left: 50%;
            position: absolute;
            top: 0;
            transform: translateX(-50%);
            width: 720px;
        }

        .car {
            background: linear-gradient(180deg, #eef3f7, #8b99a5 55%, #263241 56%, #0d1520);
            border: 2px solid rgba(255,255,255,0.85);
            border-radius: 46px 46px 14px 14px;
            box-shadow: 0 18px 20px rgba(29, 45, 61, 0.24);
            height: 76px;
            position: relative;
            width: 145px;
        }

        .car::before {
            background: linear-gradient(180deg, #dbe8f3, #172231);
            border: 2px solid #101925;
            border-radius: 18px 18px 8px 8px;
            content: "";
            height: 28px;
            left: 23px;
            position: absolute;
            top: 14px;
            width: 95px;
        }

        .car::after {
            background: radial-gradient(circle, #080d13 0 48%, #c7d4df 49% 62%, transparent 63%);
            bottom: -11px;
            content: "";
            height: 30px;
            left: 15px;
            position: absolute;
            width: 116px;
        }

        .car.big {
            height: 88px;
            width: 170px;
        }

        .car.red {
            background: linear-gradient(180deg, #ff6c61, #d92928 55%, #263241 56%, #0d1520);
        }

        .car.dark {
            background: linear-gradient(180deg, #344150, #0d1219 55%, #1e2835 56%, #070b10);
        }

        .bottom {
            bottom: 44px;
            left: 68px;
            position: absolute;
            right: 68px;
            text-align: center;
            z-index: 3;
        }

        .address {
            align-items: center;
            display: flex;
            gap: 14px;
            justify-content: center;
            margin-bottom: 8px;
        }

        .pin {
            background: #204e77;
            border-radius: 50% 50% 50% 0;
            display: inline-block;
            height: 33px;
            position: relative;
            transform: rotate(-45deg);
            width: 33px;
        }

        .pin::after {
            background: #fff;
            border-radius: 50%;
            content: "";
            height: 10px;
            left: 11px;
            position: absolute;
            top: 11px;
            width: 10px;
        }

        .address strong {
            font-size: 39px;
            letter-spacing: 1px;
        }

        .phone {
            font-size: 29px;
            margin-bottom: 34px;
        }

        .meta {
            align-items: center;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 20px;
            font-family: "Arial Narrow", Arial, Helvetica, sans-serif;
            font-size: 18px;
            text-align: left;
        }

        .meta .fill {
            min-width: 210px;
            text-align: left;
        }

        .divider {
            background: #b7c9d8;
            height: 28px;
            width: 1px;
        }

        .placeholder {
            color: #8a98a8;
        }

        @media (max-width: 1120px) {
            .app {
                grid-template-columns: 1fr;
            }

            .controls {
                position: static;
            }

            .preview-wrap {
                overflow-x: auto;
                justify-content: flex-start;
                padding-bottom: 16px;
            }
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        @media print {
            html,
            body {
                background: #fff;
                margin: 0;
                width: 210mm;
            }

            .controls {
                display: none;
            }

            .app {
                display: block;
                min-height: 0;
                padding: 0;
            }

            .preview-wrap {
                display: block;
                overflow: visible;
                padding: 0;
            }

            .certificate {
                border: 0;
                box-shadow: none;
                height: 297mm;
                min-height: 297mm;
                width: 210mm;
            }
        }
    </style>
</head>
<body class="<?= $autoPrint ? 'auto-print' : '' ?>">
    <main class="app">
        <aside class="controls">
            <h1>Сертификат на ТО</h1>
            <p>Заполните данные, выберите бренд и сформируйте сертификат. Логотип берется из файла рядом с этим PHP-файлом.</p>

            <?php if ($errors): ?>
                <div class="errors">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <br>
            <?php endif; ?>

            <form method="post" class="form-grid" autocomplete="off">
                <label class="field">
                    <span>Фамилия</span>
                    <input name="last_name" value="<?= h($data['last_name']) ?>" required>
                </label>

                <label class="field">
                    <span>Имя</span>
                    <input name="first_name" value="<?= h($data['first_name']) ?>" required>
                </label>

                <label class="field">
                    <span>Отчество</span>
                    <input name="middle_name" value="<?= h($data['middle_name']) ?>">
                </label>

                <div class="field">
                    <div class="brand-title">Бренд автомобиля</div>
                    <div class="brand-options">
                        <?php foreach ($brands as $key => $brand): ?>
                            <label class="brand-option">
                                <input type="radio" name="brand" value="<?= h($key) ?>" <?= $data['brand'] === $key ? 'checked' : '' ?>>
                                <span><?= h($brand['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <label class="field">
                    <span>ТО</span>
                    <select name="service_to">
                        <?php foreach (array_keys($serviceMileageMap) as $service): ?>
                            <option value="<?= h($service) ?>" <?= $data['service_to'] === $service ? 'selected' : '' ?>><?= h($service) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span>Пробег до</span>
                    <input name="mileage" value="<?= h($data['mileage']) ?>" placeholder="Например: 15 000 км" readonly required>
                </label>

                <label class="field">
                    <span>Скидка</span>
                    <input name="discount" value="<?= h($data['discount']) ?>" placeholder="Например: 20%" required>
                </label>

                <label class="field">
                    <span>VIN</span>
                    <input name="vin" value="<?= h($data['vin']) ?>" maxlength="17" pattern="[A-HJ-NPR-Za-hj-npr-z0-9]{6,17}" placeholder="17 символов" required>
                </label>

                <label class="field">
                    <span>Срок действия до</span>
                    <input type="date" name="valid_until" value="<?= h($data['valid_until']) ?>" required>
                </label>

                <input type="hidden" name="certificate_number" value="<?= h($data['certificate_number']) ?>">

                <div class="actions">
                    <button class="btn-secondary" type="submit" name="action" value="generate">Сгенерировать</button>
                    <button class="btn-primary" type="button" id="printButton">Печать</button>
                </div>
            </form>

            <div class="note">
                Поддерживаемые имена логотипов: logo-chery.svg/png/webp/jpg, chery.svg/png/webp/jpg, logo-tenet.svg/png/webp/jpg, tenet.svg/png/webp/jpg. Номер создается автоматически и хранится в файле certificate-counter.json рядом с формой.
            </div>
        </aside>

        <section class="preview-wrap" aria-label="Предпросмотр сертификата">
            <article class="certificate" id="certificate">
                <div class="fineprint">*сертификат действителен при наличии печати организации, подписи должностного лица, номера сертификата, пробега, VIN, срока действия.</div>

                <div class="brand-block">
                    <div class="brand-logo" data-brand-logo>
                        <?php if ($logo): ?>
                            <img src="<?= h($logo) ?>" alt="<?= h($selectedBrand['label']) ?>">
                        <?php else: ?>
                            <div class="logo-fallback"><?= h($selectedBrand['label']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="dealer" data-brand-dealer><?= h($selectedBrand['dealer']) ?></div>
                </div>

                <div class="main">
                    <div class="cert-title">
                        <strong>*Сертификат №</strong>
                        <span class="cert-number <?= $data['certificate_number'] === '' ? 'placeholder' : '' ?>" data-preview="certificate_number"><?= h($data['certificate_number'] !== '' ? $data['certificate_number'] : 'авто') ?></span>
                    </div>

                    <div class="separator"><span></span></div>

                    <p class="recipient <?= $fullName === '' ? 'placeholder' : '' ?>" data-preview="full_name"><?= h($fullName !== '' ? $fullName : 'Фамилия Имя Отчество') ?></p>
                    <div class="service-line">на техническое обслуживание</div>
                    <div class="service-line">«<span class="fill" data-preview="service_to"><?= h($data['service_to']) ?></span>» пробег до <span class="fill <?= $data['mileage'] === '' ? 'placeholder' : '' ?>" data-preview="mileage"><?= h($data['mileage'] !== '' ? $data['mileage'] : ' ') ?></span></div>
                    <div class="discount-line">Скидка в размере <span class="fill <?= $data['discount'] === '' ? 'placeholder' : '' ?>" data-preview="discount"><?= h($data['discount'] !== '' ? $data['discount'] : ' ') ?></span></div>
                </div>

                <div class="showroom" aria-hidden="true">
                    <div class="cars">
                        <div class="car"></div>
                        <div class="car big dark"></div>
                        <div class="car"></div>
                        <div class="car red"></div>
                    </div>
                    <div class="building"></div>
                </div>

                <div class="bottom">
                    <div class="address">
                        <span class="pin"></span>
                        <strong>ЛЕСНАЯ, 2</strong>
                    </div>
                    <div class="phone">8 (8352) 201-230</div>

                    <div class="meta">
                        <div>VIN: <span class="fill <?= $data['vin'] === '' ? 'placeholder' : '' ?>" data-preview="vin"><?= h($data['vin'] !== '' ? $data['vin'] : ' ') ?></span></div>
                        <div class="divider"></div>
                        <div>Срок действия до <span class="fill <?= $formattedDate === '' ? 'placeholder' : '' ?>" data-preview="valid_until"><?= h($formattedDate !== '' ? $formattedDate : ' ') ?></span></div>
                    </div>
                </div>
            </article>
        </section>
    </main>

    <?php if ($autoPrint): ?>
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    <?php endif; ?>
    <script>
        (function () {
            const brandAssets = <?= json_encode($brandAssets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const serviceMileage = <?= $serviceMileageJson ?>;
            const form = document.querySelector('form.form-grid');
            const logoBox = document.querySelector('[data-brand-logo]');
            const dealerBox = document.querySelector('[data-brand-dealer]');
            const printButton = document.getElementById('printButton');
            const certificateNumberInput = document.querySelector('input[name="certificate_number"]');

            function escapeHtml(value) {
                return String(value).replace(/[&<>"']/g, function (char) {
                    return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
                });
            }

            function renderBrand(brandKey) {
                const brand = brandAssets[brandKey];
                if (!brand || !logoBox || !dealerBox) {
                    return;
                }

                dealerBox.textContent = brand.dealer;
                if (brand.logo) {
                    logoBox.innerHTML = '<img src="' + encodeURI(brand.logo) + '" alt="' + escapeHtml(brand.label) + '">';
                } else {
                    logoBox.innerHTML = '<div class="logo-fallback">' + escapeHtml(brand.label) + '</div>';
                }
            }

            document.querySelectorAll('input[name="brand"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    if (input.checked) {
                        renderBrand(input.value);
                    }
                });
            });

            function getField(name) {
                return form ? form.elements[name] : null;
            }

            function setPreview(name, value, placeholder) {
                const element = document.querySelector('[data-preview="' + name + '"]');
                if (!element) {
                    return;
                }

                const text = String(value || '').trim();
                element.textContent = text || placeholder || ' ';
                element.classList.toggle('placeholder', !text);
            }

            function formatDate(value) {
                if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                    return '';
                }

                const parts = value.split('-');
                return parts[2] + '.' + parts[1] + '.' + parts[0];
            }

            function updatePreview() {
                const lastName = getField('last_name') ? getField('last_name').value : '';
                const firstName = getField('first_name') ? getField('first_name').value : '';
                const middleName = getField('middle_name') ? getField('middle_name').value : '';
                const serviceField = getField('service_to');
                const mileageField = getField('mileage');
                const vinField = getField('vin');
                if (serviceField && mileageField && serviceMileage[serviceField.value]) {
                    mileageField.value = serviceMileage[serviceField.value];
                }
                if (vinField) {
                    vinField.value = vinField.value.toUpperCase();
                }

                setPreview('certificate_number', certificateNumberInput ? certificateNumberInput.value : '', 'авто');
                setPreview('full_name', [lastName, firstName, middleName].map(function (value) {
                    return value.trim();
                }).filter(Boolean).join(' '), 'Фамилия Имя Отчество');
                setPreview('service_to', serviceField ? serviceField.value : '', 'ТО-0');
                setPreview('mileage', mileageField ? mileageField.value : '', ' ');
                setPreview('discount', getField('discount') ? getField('discount').value : '', ' ');
                setPreview('vin', vinField ? vinField.value : '', ' ');
                setPreview('valid_until', formatDate(getField('valid_until') ? getField('valid_until').value : ''), ' ');
            }

            async function ensureCertificateNumber() {
                if (certificateNumberInput && certificateNumberInput.value.trim()) {
                    return certificateNumberInput.value.trim();
                }

                const response = await fetch('?action=next-number', {
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json',
                    },
                });
                const result = await response.json();

                if (!response.ok || !result.ok || !result.number) {
                    throw new Error(result.error || 'Не удалось получить номер сертификата.');
                }

                certificateNumberInput.value = result.number;
                setPreview('certificate_number', result.number, 'авто');
                return result.number;
            }

            if (form) {
                form.querySelectorAll('input, select').forEach(function (input) {
                    input.addEventListener('input', updatePreview);
                    input.addEventListener('change', updatePreview);
                });
            }

            if (printButton && form) {
                printButton.addEventListener('click', async function () {
                    updatePreview();

                    if (!form.reportValidity()) {
                        return;
                    }

                    printButton.disabled = true;
                    const oldText = printButton.textContent;
                    printButton.textContent = 'Готовим...';

                    try {
                        await ensureCertificateNumber();
                        updatePreview();
                        printButton.textContent = oldText;
                        printButton.disabled = false;
                        window.print();
                    } catch (error) {
                        printButton.textContent = oldText;
                        printButton.disabled = false;
                        alert(error.message || 'Не удалось подготовить сертификат к печати.');
                    }
                });
            }

            updatePreview();
        })();
    </script>
</body>
</html>
