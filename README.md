# B24 Interconnector

Микросервис-шина для обмена данными (сделками) между двумя независимыми облачными инстансами Битрикс24.

## Почему это решение необходимо (Key Features)

- **Гарантированная доставка**: Если один из Битрикс24 временно недоступен или тормозит, шина удерживает сделку в очереди и повторяет попытку позже. Данные не потеряются.
- **Защита от блокировок (Rate Limiting)**: При массовом поступлении сделок (до 10 000 одновременно) шина распределяет нагрузку, отправляя в Битрикс24 по 10–20 запросов в минуту, чтобы избежать бана со стороны API.
- **Обход лимитов URL (GET -> POST)**: Микросервис получает только ID сделки, а затем сам выкачивает все тяжелые данные (длинные комментарии, файлы) через POST-запросы, предотвращая обрезку данных сервером.
- **Связка сущностей**: Сервис хранит карту соответствий ID сделки в Системе А и Системе Б, что позволяет корректно возвращать обновления и комментарии в нужную карточку.

## Стек технологий
![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php)
![Slim Framework](https://img.shields.io/badge/Slim_4-5B5B5B?style=flat-square&logo=slim)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Eloquent](https://img.shields.io/badge/Eloquent_ORM-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![Monolog](https://img.shields.io/badge/Logging-Monolog-D291BC?style=flat-square)
![Guzzle](https://img.shields.io/badge/HTTP_Client-Guzzle-FF9E00?style=flat-square)
![Composer](https://img.shields.io/badge/Composer-885630?style=flat-square&logo=composer&logoColor=white)
- **PHP 8.0+** (Slim 4 Framework)
- **MySQL** (Очередь задач)
- **Phinx** (Миграции базы данных)
- **Eloquent ORM (Illuminate Database)** (Управление сущностями и БД)
- **Monolog** (Продвинутое логирование событий и ошибок (PSR-3))
- **PHP-DI** (Внедрение зависимостей (Dependency Injection))
- **Guzzle** (HTTP-клиент для API Битрикс24)
- **vlucas/phpdotenv** (Управление конфигурацией)
- **Composer** (Управление зависимостями)

## Архитектура и структура проекта

- `public/index.php` — **Endpoint (Приёмник)**. Принимает вебхуки от Битрикс24 и быстро записывает их в очередь (MySQL), не заставляя Битрикс ждать.
- `bin/worker.php` — **Worker (Обработчик)**. Скрипт, который запускается по Cron, выгребает сделки из базы и выполняет тяжелую работу: запрашивает детали, создает сделки в другой системе, пишет логи.
- `src/` — **Logic (Ядро)**. Здесь будут лежать классы для работы с API Битрикс24 и логика трансформации данных.
- `migrations/` — **Database (Схема)**. Описание таблиц для Phinx, что позволяет развернуть БД одной командой.
- `logs/` — **Monitoring**. Журналы работы воркера и ошибок API.

### Архитектурный паттерн ADR (Action-Domain-Responder)
Проект реализован с разделением ответственности по принципу ADR:

* **Action (Controllers)**: Принимает HTTP-запрос, делегирует задачу сервису и передает результат в Responder.
* **Domain (Services/Models)**: Содержит бизнес-логику. Включает `QueueService` для управления очередью и Eloquent-модели для работы с БД.
* **Responder**: Унифицированный формат вывода через `App\Responses\ApiResponse`, обеспечивающий консистентность JSON-ответов.

### Middleware Pipeline (Цепочка фильтрации)
Каждый входящий запрос проходит через следующие слои:

1.  **ErrorHandling**: Перехватывает любые исключения и возвращает чистый JSON-ответ.
2.  **TokenAuth**: Проверяет наличие и валидность `application_token` (безопасность).
3.  **BitrixValidation**: Проверяет наличие обязательных полей (например, id сделки).

### Логирование и Мониторинг
Система использует Monolog (PSR-3) для записи событий в `logs/app.log`:
- `INFO`: Успешная постановка сделок в очередь.
- `WARNING`: Попытки несанкционированного доступа (неверный токен).
- `ERROR`: Критические ошибки базы данных или API.

## Установка и развертывание

### 1. Клонирование репозитория
```bash
git clone https://github.com/GreatGleb/b24-interconnector.git
cd b24-interconnector
```

### 2. Установка зависимостей
Проект использует **Composer** для управления библиотеками (Slim, Guzzle, Phinx, Dotenv).
```bash
composer install
```

### 3. Настройка окружения
Создайте файл `.env` в корне проекта на основе примера:
```bash
cp .env.example .env
```
Отредактируйте .env, указав доступы к вашей MySQL и Webhook-ссылки от Битрикс24:

- DB_HOST, DB_NAME, DB_USER, DB_PASS — параметры подключения к БД.
- BITRIX_SOURCE_URL — вебхук первой системы.
- BITRIX_TARGET_URL — вебхук второй системы.

### 4. Подготовка базы данных
Миграции управляются через **Phinx**. Для автоматического создания таблиц выполните:
```bash
vendor/bin/phinx migrate
```

### 5. Настройка веб-сервера
На сервере (Nginx) необходимо указать, что корневой директорией проекта является папка `public/`, а не корень репозитория.

Пример настройки `server` блока в Nginx:
```nginx
server {
    listen 80;
    server_name your-domain.ru;
    root /var/www/b24-interconnector/public; # Важно: путь до папки public

    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }
}
```
#### Запуск обработчика очереди (Cron)
Чтобы шина начала пересылать сделки, необходимо настроить запуск воркера каждую минуту.

Пример добавления записи в crontab (`crontab -e`):
```bash
* * * * * /usr/bin/php /var/www/b24-interconnector/bin/worker.php >> /var/www/b24-interconnector/logs/worker.log 2>&1
```

### 6. Эндпоинты (Webhooks API)

Микросервис предоставляет следующие точки входа для систем Битрикс24:

1. **Регистрация сделки от Источника**:
    - `GET https://ваш-домен.org/inbound/from-source`
    - Назначение: Сюда Битрикс-отправитель присылает сигнал при создании сделки или смене стадии.

2. **Обратная связь от Дилера (Vinipol)**:
    - `GET https://ваш-домен.org/inbound/from-vinipol`
    - Назначение: Сюда Битрикс-получатель присылает обновления (например, когда менеджер Vinipol изменил статус или добавил комментарий), чтобы данные вернулись в исходную систему.