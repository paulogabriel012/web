# AGENTS.md — Invisiboll Web

Guia para agentes de IA (opencode, codex, etc.) trabalharem neste repositório.

## Visão geral

`web` é o backend do ecossistema Invisiboll (browser antidect multi-repo). É a **fonte da verdade**: autenticação, billing, planos, quotas e API REST consumida pelo desktop (repo `electron`).

Arquitetura completa em `../ARCHITECTURE.md`.

## Stack

- **Laravel 13** (PHP 8.4) + Fortify (auth)
- **React starter kit**: Inertia + React 19 + TypeScript + Tailwind v4 + shadcn/ui
- **Laravel AI SDK** (`laravel/ai`) — agentes, tools, providers
- **SQLite** no dev local (trocar por Postgres em prod)
- Vite + laravel-vite-plugin (build do frontend)

## Estrutura principal

```
app/
├── Http/Controllers/        # Controllers (dashboard + API)
├── Models/
└── ...
config/ai.php                # Providers de IA (OpenAI, Anthropic, ...)
resources/js/                # Frontend React (Inertia)
routes/web.php               # Rotas do dashboard + Inertia
routes/api.php               # API consumida pelo electron
database/migrations/
```

## Comandos

```bash
composer run dev             # Servidor Laravel + Vite (dev)
composer test                # Testes PHPUnit
pnpm lint / typecheck        # ESLint + TypeScript no frontend (pnpm é o gerenciador)
php artisan make:agent       # Criar agente de IA (Laravel AI)
php artisan make:tool        # Criar tool para agentes
```

## Regras

- Toda regra de negócio (planos, quotas, perfis) vive **aqui**, nunca no desktop
- A API (`/api/*`) é o contrato com o `electron` — respostas devem seguir os schemas de `@invisiboll/contracts` (repo `packages`); o OpenAPI gerado aqui é a fonte da verdade dos tipos TS
- Auth: JWT curto + refresh; Fortify cuida de login/registro/2FA
- Billing: Stripe; webhooks atualizam quotas (`GET /api/me` → `subscription.limits`)
- Não criar camadas intermediárias (ex.: hub Fastify) — o desktop consome esta API direto
- Comunicar com o usuário em **pt-BR**; código e commits em **inglês**

## IA (Laravel AI)

- Providers configurados em `config/ai.php` (driver default: `AI_DRIVER`)
- Chaves de API vão no `.env`: `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`
- Criar agentes com `php artisan make:agent` — implementam o contrato `Laravel\Ai\Contracts\Agent` com o trait `Promptable`
- Não commitar chaves de API nem expor secrets
