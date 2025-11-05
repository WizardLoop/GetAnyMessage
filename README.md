# 📨🔓 GetAnyMessage
### get any message from any chat!
### Save Restricted Content Easily.
**GetAnyMessage** is a powerful Telegram bot that lets you retrieve **restricted messages** (those that cannot be forwarded or copied) from any chat.

> 📨🔓 [@GetAnyMessageRobot](https://GetAnyMessageRobot.t.me)

[![AGPL License](https://img.shields.io/badge/license-AGPL--3.0-blue.svg)](LICENSE)
[![Made with ❤️](https://img.shields.io/badge/Made%20with-%E2%9D%A4%EF%B8%8F%20-blue)](https://github.com/WizardLoop/GetAnyMessage)
[![Docker Ready](https://img.shields.io/badge/docker-ready-blue.svg)](https://www.docker.com/)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-blue)](https://www.php.net/)

---

## 📦 Features

- 🔓 Retrieve restricted messages from any chat
- 📁 file support up to 4GB
- 🧩 Supports groups, channels, private messages, bots
- ⭐️ animated emoji's support
- 💬 All types of messages
- 🔁 Bypass Telegram forward/copy restrictions
- ⚙️ Built with `MadelineProto` & PHP Coroutine Engine

---

### 🛠 Installation Setup

#### 1️⃣ Clone the repository

```bash
git clone https://github.com/WizardLoop/GetAnyMessage.git
cd GetAnyMessage
```

#### 2️⃣ Install dependencies

Install PHP dependencies using Docker:
```bash
docker compose run --rm composer install
```

#### 3️⃣ Launch the bot

```bash
docker compose up --pull always -d
```

The bot will start running in the background.

#### 🔍 View logs

```bash
docker compose logs
```
Live log output of your bot.

---

## ⚙️ Common Commands

| Command                        | Description                                      |
|--------------------------------|--------------------------------------------------|
| `docker compose build`         | Build the Docker image                          |
| `docker compose up --pull always -d`         | Start the bot in the background                |
| `docker compose down`          | Stop and remove the bot container              |
| `docker compose restart`       | Restart the bot quickly                        |
| `docker compose logs`       | View real-time bot logs                        |
| `docker compose exec bot composer dump-autoload` | Reload Composer autoload |
| `docker compose ps`            | Show the status of Docker containers           |

---

## 🔐 Environment Configuration

Copy `.env.example` to `.env` and fill in:

```bash
cp .env.example .env
```

- `API_ID`
- `API_HASH`
- `BOT_TOKEN`
- `ADMIN_ID`

---

## 🧪 Testing & Code Quality

This project supports **PHPUnit**, **PHPCS**, and **PHP-CS-Fixer**.

### ✅ Run tests

```bash
docker compose exec bot vendor/bin/phpunit
```

### 🎨 Code style

```bash
docker compose exec bot vendor/bin/phpcs
docker compose exec bot vendor/bin/php-cs-fixer fix
```

---

## 🤝 Contributing

Pull requests are welcome!

1. Fork the repo
2. Create a branch: `git checkout -b fix/my-fix`
3. Commit: `git commit -m 'Fix something'`
4. Push: `git push origin fix/my-fix`
5. Open a PR 🙌

---

## 📄 License

Licensed under the **GNU AGPL-3.0** — see [`LICENSE`](LICENSE).

---

Questions? Suggestions? Contact [@WizardLoop](https://t.me/WizardLoop).
