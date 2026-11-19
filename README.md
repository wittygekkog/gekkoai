<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## GekkoAi database

The central platform uses MySQL. Set `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env` (see `.env.example` for the names). Create the database, then run:

```bash
php artisan migrate --seed
```

The seeder loads the bootstrap administrators, settings, the six business units, and example logs, flags, data, and logic rules. Automated tests keep using an in-memory SQLite database.

## Mobile API

Native clients use `/api/v1` (`routes/api.php`). These routes are stateless. They do not use the admin session or CSRF. Web administration routes are unchanged.

Public:

- `GET /api/v1/branding` — `app_name`, `logo_url`, `primary_color`, `logo_version` from settings. This endpoint was added because the platform had no branding contract for mobile. Logos are HTTPS or public storage URLs. An `http://` value is not exposed.
- `POST /api/v1/auth/login` — email or username, password, optional `remember` and `device_name`.
- `POST /api/v1/auth/refresh` — rotates the refresh token and revokes the previous pair.

Authenticated with `Authorization: Bearer`:

- `POST /api/v1/auth/logout`, `GET /api/v1/profile`
- `GET /api/v1/tenants` — authorized SBUs only
- Conversations: list, create, show, logic, `POST .../messages`, `POST .../audio`
- `GET /api/v1/operations` and `GET /api/v1/operations/{id}`
- Approvals: list, approve, reject
- Escalations: list, resolve
- `GET /api/v1/rules`, `POST /api/v1/rules/chat`, `POST /api/v1/rules`, `PATCH /api/v1/rules/{id}` — standing rules for the selected SBU. Chat drafts a title and statement. Saving requires `ai.memory.manage`.
- `GET /api/v1/objectives`, `POST /api/v1/objectives/chat`, `POST /api/v1/objectives`, `PATCH /api/v1/objectives/{id}` — one outcome sentence for the selected SBU. Chat tightens the wording and removes anything that would skip Rules or approval. Saving requires `ai.memory.manage`. An objective does not grant a tool or override Rules.
- `GET /api/v1/documents`, `GET /api/v1/documents/{id}`, `POST`, `PATCH`, `POST .../approve`, `DELETE` — knowledge documents. A new or edited document stays `pending` until approval. File storage paths are not returned.
- `GET /api/v1/flags`, `GET /api/v1/access`, `POST /api/v1/feedback`

The JSON envelope is `{ "success", "message", "data" }`. Validation is HTTP 422. A missing or revoked token is HTTP 401. A tenant the administrator cannot access is HTTP 403.

Tokens are returned once. `admin_api_tokens` stores SHA-256 hashes, device name, and expiry. Access tokens last 2 hours, or 14 days when `remember` is true. Refresh tokens last 30 days, or 90 days with remember. Logout sets `revoked_at`.

Accounts with `must_change_password` cannot call the mobile API until the password is changed in the web admin. The bootstrap administrators are created in that state. Login failures for unknown, inactive, and wrong-password accounts share one message.

`GET` conversation and operation routes only read state. Creating a booking, approval, or rejection requires the matching `POST`. Audio upload stores the file and an `AiTranscription` row. With no speech provider configured, `transcript` is null and `provider_configured` is false. The API does not invent a transcript. Supported language codes are `en` and `tl` (`fil` is stored as `tl`).

Connector secrets, base URLs, and key ids are omitted from tenant and access payloads. Logic payloads include intent, parameters, confidence, calibration, missing fields, and eligibility. They do not include chain-of-thought.
