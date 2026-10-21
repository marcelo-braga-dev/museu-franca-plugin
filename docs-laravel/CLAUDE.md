# CLAUDE.md — Museu da Pessoa de Franca

Plataforma de acervo digital colaborativo (histórias, documentos, fotos, vídeos e áudios) do
Museu da Pessoa de Franca / IPRA. Esta é a reescrita em Laravel da antiga versão WordPress.

## Leia antes de qualquer tarefa
- `docs/08-arquitetura-recomendada.md` — **arquitetura oficial** (módulos, banco, front, infraestrutura). Em questões de estrutura, ele **prevalece** sobre os docs 01–05; a §9 dele lista o que muda em cada um.
- `docs/03-regras-de-negocio.md` — **fonte da verdade** das regras de negócio (ajustadas pelo 08 §2.2 no fluxo editorial). Em caso de conflito com o legado, vale este arquivo.
- `docs/07-plano-de-implementacao.md` — fases; trabalhe **uma fase por vez** e só dentro do escopo pedido.
- Os outros arquivos de `docs/` conforme a área: banco (02), back-end (04), front (05), importação (06).
- `docs/00-documentacao-funcional-legado.md` — comportamento do sistema antigo (apenas referência; **não** replique os bugs da §13).

## Stack
- PHP 8.3+, **Laravel 12** (ou a versão estável mais recente), MySQL 8 (utf8mb4_unicode_ci)
- **Inertia.js v2** + **React 18+** + **TypeScript** (strict) + **MUI v7** (+ `@mui/icons-material`, `@mui/x-date-pickers` se necessário)
- Vite, Ziggy (`route()` no front), Inertia SSR para as páginas públicas
- Auth: Laravel Fortify (back-end) + páginas MUI próprias; Socialite (Google)
- Mídia: spatie/laravel-medialibrary; filas no driver `database` (Redis opcional)
- Testes: **Pest**; Qualidade: **Larastan** (nível 6+), **Pint**, ESLint + Prettier, `tsc --noEmit`

Infra (ver 08 §1): Redis (cache, sessão, fila, locks) + Horizon, Scout + Meilisearch, storage S3-compatível (MinIO local), Sentry, Pulse. Ambiente local via Laravel Sail.
Pacotes de domínio: spatie/laravel-data + typescript-transformer (tipos gerados), laravel-model-states, laravel-permission, laravel-activitylog, kalnoy/nestedset.

## Convenções
- **Monólito modular:** código em `app/Modules/{Archive,Contributions,Curation,Media,Engagement,Accounts,Site}`; módulos se comunicam por eventos e contratos (08 §2.1). Testes Pest Arch garantem as fronteiras.
- **Tipos gerados:** depois de alterar DTOs ou enums, rode `php artisan typescript:transform`; nunca edite `resources/js/types/generated.d.ts` à mão.
- Front: importe componentes de `@/design-system`, não de `@mui/material` direto (exceto os primitivos de layout).
- **Idioma:** textos de UI, mensagens de validação e e-mails em **pt-BR**; código (classes, métodos, colunas, rotas nomeadas) em **inglês**. URLs públicas em português (`/acervo`, `/artigos/{slug}`, `/minha-conta`).
- **Controllers finos:** validação em `FormRequest`, autorização em `Policy`, lógica em `app/Services` ou `app/Actions`.
- Enums PHP *backed* em `app/Enums` (ex.: `ArticleStatus`, `UserRole`, `MediaKind`).
- Respostas Inertia tipadas: os props saem de `JsonResource` / DTOs; crie tipos TS correspondentes em `resources/js/types`.
- **Nunca** use `$request->all()` em `create`/`update`; use `$request->validated()`.
- Toda query de listagem pública filtra `status = published` com o *scope* `Article::published()`.
- Contadores e caches são invalidados por *observers/events*, não espalhados pelo código.
- HTML vindo do editor é sanitizado no servidor (`mews/purifier`) antes de salvar.
- Datas: armazenar em UTC e exibir em `America/Sao_Paulo`, no formato `21 de outubro de 2026` (`Carbon::translatedFormat('j \d\e F \d\e Y')`).
- Front: componentes funcionais com hooks; páginas em `resources/js/Pages/**`, componentes em `resources/js/Components/**`; sem CSS solto — use `sx`/`styled` e o tema.
- Acessibilidade (WCAG 2.1 AA) é requisito: labels, `aria-*`, foco visível, navegação por teclado, contraste.

## Comandos
```bash
composer install && npm ci
cp .env.example .env && php artisan key:generate
php artisan migrate --seed            # cria admin padrão e dados de exemplo
npm run dev                           # Vite
php artisan serve                     # ou Herd/Valet/Sail
php artisan queue:work                # conversões de imagem e e-mails
npm run build && npm run build:ssr    # produção
php artisan inertia:start-ssr         # servidor SSR
./vendor/bin/pest                     # testes
./vendor/bin/pint && ./vendor/bin/phpstan analyse
npm run lint && npm run typecheck
php artisan museu:import-wordpress    # importação do legado (ver docs/06)
```

## Definição de pronto (toda tarefa)
1. Testes Pest cobrindo a regra nova (feature test para rotas e policies; unit para serviços).
2. `pest`, `phpstan`, `pint --test`, `npm run lint`, `npm run typecheck` e `npm run build` passando.
3. Migrations reversíveis (`down`).
4. Sem N+1: use `with()` nas listagens (ative `Model::preventLazyLoading()` fora de produção).
5. Textos em pt-BR e fluxo verificado no navegador (desktop e 375px).
6. Informe o que foi feito, os arquivos alterados e o que ficou pendente.

## Não faça
- Não instale pacotes fora da lista de `docs/01` sem justificar.
- Não crie migrations que apaguem dados sem pedir confirmação.
- Não exponha e-mails de usuários em páginas públicas.
- Não commite `.env`, credenciais ou dumps do banco.
