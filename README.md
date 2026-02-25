# Laravel Real-Time Chat

A real-time chat application built with Laravel 12, Vue 3, and WebSockets. This is a hobby project I built to sharpen my full-stack skills and showcase clean Laravel architecture on my GitHub profile.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.3 |
| Frontend | Vue 3, Inertia.js, Tailwind CSS 4 |
| WebSockets | Laravel Reverb, Laravel Echo |
| Database | MySQL 8 |
| Queue & Cache | Redis 7 |
| Testing | PHPUnit (46 tests), Vitest (38 tests) |
| CI/CD | GitHub Actions |
| DevOps | Docker Compose (7 services) |

## Architecture

The project follows **Domain-Driven Design** with a strict **Repository Pattern** and **Service Layer**:

```
app/
  Domains/Chat/
    Models/          Eloquent models (Channel, Message)
    Repositories/    Interfaces (contracts)
    Services/        Business logic (SendMessage, CreateChannel)
    Events/          Broadcastable events (MessageSent)
    Policies/        Authorization
    DTOs/            Data Transfer Objects

  Http/
    Controllers/     Thin controllers — validate, delegate, respond
    Requests/        Form Request validation

  Infrastructure/
    Repositories/    Concrete implementations
```

**Key principles:**
- Controllers never touch the database directly
- Services coordinate repositories, events, and authorization
- All dependencies injected via interfaces, bound in ServiceProvider
- TDD workflow — tests written first

## Features

- User registration and authentication
- Public and private channels
- Real-time messaging via WebSockets (Laravel Reverb)
- Channel creation, browsing, and joining
- Presence channels (online users)
- Optimistic UI with server-confirmed messages

## Getting Started

### Prerequisites

- Docker & Docker Compose

### Setup

```bash
# Clone the repo
git clone https://github.com/your-username/chat.git
cd chat

# First-time setup (builds images, installs deps, runs migrations)
make setup

# Start all containers
make up
```

The app will be available at `http://localhost`.

### Daily Workflow

```bash
make up                  # start containers
make down                # stop containers
make bash                # shell into app container
make tinker              # Laravel Tinker

make test                # run PHP tests
make npm cmd="test"      # run Vue tests

make migrate             # run migrations
make fresh               # migrate:fresh --seed
make logs                # view all logs
```

## Testing

**84 automated tests** across backend and frontend:

```bash
# PHP — 46 tests (unit + feature)
make test

# Vue — 38 component tests
make npm cmd="test"
```

### PHP Test Coverage

| Suite | Tests | What's Covered |
|-------|-------|---------------|
| Unit/Repositories | 13 | MessageRepository, ChannelRepository |
| Unit/Services | 7 | MessageService, ChannelService |
| Feature/Auth | 9 | Registration, login, logout |
| Feature/Chat | 16 | Channels (CRUD, join, auth), Messages (send, broadcast, load) |
| Feature/General | 1 | Root redirect |

### Vue Test Coverage

| Suite | Tests | What's Covered |
|-------|-------|---------------|
| Auth/Login | 6 | Form rendering, inputs, labels, links |
| Auth/Register | 7 | All fields, validation attributes, navigation |
| Chat/Channel | 14 | Messages, input, sidebar, avatars, own vs other |
| Chat/Index | 11 | Channel list, join/open, empty states, sidebar |

## CI/CD

GitHub Actions runs three parallel jobs on every push and PR:

1. **PHP Tests** — MySQL migration verification + SQLite test suite
2. **JS Tests & Build** — Vitest + Vite production build
3. **Code Quality** — Laravel Pint (PSR-12)

## Docker Services

| Service | Image | Port |
|---------|-------|------|
| app | PHP 8.3-FPM | 9000 (internal) |
| nginx | Nginx 1.25 | 80, 8080 |
| mysql | MySQL 8.0 | 3306 |
| redis | Redis 7 | 6379 |
| queue | PHP 8.3-FPM | — |
| reverb | PHP 8.3-FPM | 8080 (WebSocket) |
| node | Node 20 | 5173 (Vite HMR, dev only) |

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
