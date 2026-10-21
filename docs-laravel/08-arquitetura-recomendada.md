# 08 — Arquitetura recomendada (back-end, front-end e banco)

> Este documento define **como** a nova plataforma deve ser estruturada, sem nenhum vínculo com o
> modelo do WordPress. Ele **tem prioridade** sobre os documentos 01–05 nas questões de estrutura;
> a §9 lista exatamente o que muda neles.

## Princípios

1. **O domínio primeiro.** O sistema é um *acervo de memória*, não um blog. As entidades refletem isso: itens do acervo, mídias com proveniência, contribuições, curadoria.
2. **Módulos com fronteiras claras.** Cada contexto (Acervo, Contribuições, Curadoria, Contas, Mídia, Engajamento) tem seus models, ações, eventos e políticas. Um módulo conversa com outro por **eventos** e **contratos**, nunca mexendo nas tabelas alheias.
3. **Escrita consistente, leitura rápida.** Transações e invariantes na escrita; caches, índice de busca e contadores agregados na leitura.
4. **Tudo que é lento vai para a fila:** conversões de imagem, indexação, e-mails, extração de metadados, geração de sitemap.
5. **Tipos ponta a ponta.** O contrato PHP gera os tipos TypeScript; o front nunca "adivinha" o formato dos dados.
6. **Observável e reversível.** Logs estruturados, métricas, rastreamento de erros, auditoria das ações administrativas, histórico de versões dos conteúdos, migrations reversíveis.
7. **Escalar na horizontal sem reescrever.** Aplicação *stateless* (sessão, cache e fila no Redis; arquivos em object storage), pronta para mais de uma instância atrás de um balanceador.

---

## 1. Infraestrutura alvo

| Componente | Recomendação | Por quê |
|---|---|---|
| Aplicação | Laravel 12 em PHP-FPM 8.3 (Octane/FrankenPHP como evolução) | Stateless, várias instâncias |
| Banco | **MySQL 8** (InnoDB), com réplica de leitura quando houver volume | Requisito do projeto; réplica via `read`/`write` no `config/database.php` |
| Cache, sessão, filas, locks | **Redis** | Remove o estado da aplicação; permite `Cache::tags`, locks atômicos e rate limit distribuído |
| Fila | Redis + **Laravel Horizon** (filas `default`, `media`, `search`, `mail`) | Visibilidade, retries, prioridades |
| Busca | **Laravel Scout + Meilisearch** | Busca tolerante a erros de digitação, com acentos, facetas (categoria/coleção/mídia/década) e destaque do trecho encontrado — muito superior ao FULLTEXT |
| Arquivos | **Object storage S3-compatível** (AWS S3, Cloudflare R2, MinIO) + **CDN** | Escala, durabilidade, desacopla os arquivos do servidor |
| Imagens | Conversões na fila (WebP/AVIF) + CDN; opcional: imgproxy | Thumbnails sob demanda, cache na borda |
| SSR | Processo Inertia SSR (Node) | SEO e preview dos links |
| E-mail | Provedor transacional (SES, Postmark, Resend) | Entregabilidade |
| Observabilidade | **Sentry** (erros, back e front), **Laravel Pulse** (saúde), logs JSON em stdout | Diagnóstico em produção |
| Ambientes | local (Docker/Sail), homologação, produção — **paridade** de serviços | Evita surpresas |
| Deploy | CI/CD com *zero downtime* (atomic symlink: Deployer/Envoyer, ou containers) | Rollback em 1 comando |

Desenvolvimento local com **Laravel Sail** (`mysql`, `redis`, `meilisearch`, `minio`, `mailpit`), para os mesmos serviços de produção.

---

## 2. Back-end

### 2.1 Organização em módulos (*modular monolith*)

```
app/
  Modules/
    Archive/            # Acervo público: Item, Category, Collection, Tag, Person, Place, busca, relacionados
      Models/  Actions/  Queries/  Data/  Events/  Listeners/  Policies/  Http/{Controllers,Requests}  Search/
    Contributions/      # Envio pelo colaborador: rascunho, submissão, edição, consentimento
    Curation/           # Revisão, publicação, rejeição, destaques, auditoria editorial
    Media/              # Asset, upload direto, conversões, metadados técnicos, antivírus
    Engagement/         # Favoritos, visualizações, compartilhamentos, estatísticas
    Accounts/           # Usuários, perfis, login social, papéis e permissões, LGPD
    Site/               # Páginas institucionais, configurações, home, navegação, doações, contato
  Shared/               # ValueObjects (Slug, YoutubeId, Year), enums comuns, traits, helpers de cache
  Providers/ModuleServiceProvider.php   # registra rotas, policies, eventos e observers por módulo
routes/
  web.php               # inclui routes/modules/*.php
```

Regras:
- Um controller faz **só** isto: recebe o Request validado → chama uma Action/Query → devolve uma resposta Inertia ou um redirect.
- **Actions** (escrita): uma classe por caso de uso, com `handle()`, transacional e disparando eventos de domínio. Ex.: `SubmitContribution`, `PublishItem`, `RejectItem`, `AttachMediaToItem`, `ToggleFavorite`.
- **Queries** (leitura): classes de consulta reutilizáveis, que retornam DTOs paginados. Ex.: `SearchArchive`, `HomeSections`, `RelatedItems`, `ItemForDisplay`.
- **DTOs** com **`spatie/laravel-data`**: entrada (substitui os arrays soltos) e saída (props do Inertia).
- **Tipos TS gerados** com `spatie/laravel-typescript-transformer` (`php artisan typescript:transform` → `resources/js/types/generated.d.ts`). Os enums PHP viram *unions* em TS automaticamente.
- **Máquina de estados** do fluxo editorial com **`spatie/laravel-model-states`**: cada transição é uma classe que valida a origem e o destino e dispara eventos. Uma transição inválida gera uma exceção, não um `if` esquecido.
- **Permissões** com **`spatie/laravel-permission`** (papéis + permissões finas), em vez de `role` como string. Papéis iniciais: `admin`, `curator` (revisa e publica, sem gerenciar usuários), `contributor`. As permissões são verificadas nas Policies.
- **Auditoria** com **`spatie/laravel-activitylog`**: toda ação de curadoria e de admin (publicar, rejeitar, mudar papel, excluir) gera um registro com autor, antes e depois.
- **Versões dos conteúdos**: cada submissão ou edição de um item gera uma `item_revision` (snapshot JSON). O curador vê o *diff* entre a versão publicada e a proposta.

### 2.2 Fluxo editorial (separando o publicado do proposto)

Problema do modelo ingênuo: quando o colaborador edita um item publicado, ou o item sai do ar, ou a alteração vai ao ar sem revisão.
**Solução profissional:** a versão **publicada** e a versão **em revisão** coexistem.

```
items.published_revision_id  → versão no ar (o público vê esta)
items.draft_revision_id      → versão em edição ou revisão (o autor e o curador veem esta)
```
- Primeira submissão: cria a revisão `pending`; o item ainda não está público.
- Aprovação: a revisão passa a `published`; `items.published_revision_id` aponta para ela; os campos de leitura do item são **materializados** a partir da revisão (para consultas rápidas).
- Colaborador edita um item já publicado: cria uma nova revisão `pending`, enquanto **o item continua no ar com a versão anterior**. O curador aprova ou rejeita a alteração.
- Rejeição: a revisão passa a `changes_requested`, com o motivo; o autor ajusta e reenvia.
- O histórico completo fica disponível (quem, quando, o que mudou).

Estados do **item**: `draft → in_review → published ⇄ unpublished`, `archived` (soft delete).
Estados da **revisão**: `draft → pending → approved | changes_requested | superseded`.

> Isto substitui a decisão "edição de um publicado volta para revisão e sai do ar" (03 §4).

### 2.3 Mídia como entidade própria (Asset)

Em um acervo, a mídia **é o próprio objeto de memória**: tem autoria, data, local e direitos.

- `assets`: um arquivo único com metadados técnicos (mime, tamanho, dimensões, duração, hash SHA-256 para deduplicação, EXIF filtrado) e **metadados de acervo**: título, descrição/legenda, texto alternativo, **créditos/autoria**, **data do original** (aceita datas aproximadas: "c. 1972", "década de 1970"), **local**, **licença/direitos de uso**, **fonte/proveniência** (ex.: "Arquivo DOPS — Delegacia de Franca").
- `item_assets`: vínculo item ↔ asset com `role` (`cover`, `gallery`, `document`, `audio`, `video_file`) e `position`. O mesmo asset pode aparecer em mais de um item, sem duplicar o arquivo.
- **Upload direto ao storage** (URL pré-assinada S3): o navegador envia o arquivo direto ao bucket, com progresso real e retomada para arquivos grandes; o servidor só registra o asset e despacha o processamento. A aplicação não trafega gigabytes e não estoura timeouts nem o `post_max_size`.
- Pipeline na fila `media`: validação do mime real → antivírus (ClamAV, opcional) → extração de metadados → remoção de EXIF sensível (GPS) → conversões (thumb, card, large, full em WebP/AVIF) → miniatura da 1ª página do PDF → forma de onda e duração do áudio → `asset.status = ready`.
- Vídeos do YouTube como `item_embeds` (provider, external_id, url, título, duração e thumbnail obtidos via oEmbed na fila). Fica pronto para Vimeo ou SoundCloud no futuro.
- **Transcrições** de áudio e vídeo (campo `transcript` no asset ou embed): acessibilidade e busca. Pode ser gerada automaticamente depois (Whisper) e revisada por um curador.

### 2.4 Leitura e desempenho

- **Contadores fora da linha principal:** `item_stats (item_id PK, views, favorites, shares, updated_at)`. Incremento em lote: as views vão primeiro para o **Redis** (`HINCRBY`) e um job a cada minuto descarrega no MySQL. Isso evita a disputa de *lock* na linha do item em picos.
- **Analytics diário:** `item_daily_stats (item_id, date, views, unique_visitors)` para os gráficos do painel e o ranking "mais vistos da semana/mês" (melhor do que um total histórico).
- Deduplicação das views no Redis (`SET NX` com TTL de 24h, chave `view:{item}:{hash}`), não em tabela.
- **Cache em camadas:** página pública (resposta HTTP com `Cache-Control` + CDN para visitantes anônimos), fragmentos (seções da home, árvore de categorias, contadores) com `Cache::tags` e invalidação por evento; *query cache* nunca.
- **Busca** pelo índice Meilisearch: documento por item publicado com título, resumo, texto puro, tags, categorias (com os ancestrais), coleções, pessoas, locais, ano/década e tipos de mídia. Facetas e filtros saem do índice; o MySQL só hidrata os IDs da página.
- Paginação por cursor nas listagens infinitas (admin, notificações); paginação por página no acervo (SEO).
- `Model::preventLazyLoading()`, `preventSilentlyDiscardingAttributes()` e `preventAccessingMissingAttributes()` fora de produção.

### 2.5 API e integrações
- Rotas web via Inertia para a aplicação.
- **API pública somente leitura**, versionada (`/api/v1/items`, `/collections`, `/categories`) com Sanctum/tokens e rate limit: abre portas para apps, parceiros e pesquisadores.
- Opcional, em evolução: um endpoint **OAI-PMH** e exportação **Dublin Core**, o padrão de interoperabilidade de acervos (agregadores como a Europeana e repositórios acadêmicos).
- Webhooks internos com eventos de domínio (`ItemPublished`) consumidos por listeners: indexação, sitemap, invalidação de CDN e notificações.

### 2.6 Segurança
- Policies + permissões em todas as ações; testes da matriz de permissões.
- Validação via DTO/FormRequest; HTML sanitizado (Purifier) **e** CSP rígida no front.
- Uploads: URL pré-assinada com tamanho e mime limitados na assinatura; bucket privado com originais servidos por URL assinada quando a licença exigir; conversões públicas via CDN.
- Rate limit por rota (login, cadastro, contato, favoritos, views, API).
- 2FA para admins e curadores (Fortify suporta).
- Segredos só em variáveis de ambiente/secret manager; `APP_DEBUG=false`; os cabeçalhos HSTS, CSP, `Referrer-Policy` e `Permissions-Policy`.
- Dependências: `composer audit` e `npm audit` no CI; Dependabot/Renovate.
- Backups automáticos (banco a cada hora com retenção de 30 dias; versionamento do bucket) **com teste de restauração** mensal.

---

## 3. Banco de dados

### 3.1 Convenções
- `id` bigint interno (FKs e joins) + **`public_id` ULID** (único, exposto em URLs e APIs que não usam slug). Nunca exponha IDs sequenciais em rotas mutáveis (`/minha-conta/itens/{public_id}/editar`).
- Nomes em inglês, plural, `snake_case`; pivôs no singular e em ordem alfabética (`collection_item`).
- `timestamps` em tudo; `softDeletes` nas entidades de conteúdo e em usuários.
- FKs **sempre** declaradas, com `ON DELETE` explícito; sem `cascade` em entidades com valor histórico (use `restrict` + soft delete).
- Enums como `string` validada por enum PHP (fácil de evoluir), não como `ENUM` do MySQL.
- Índices pensados a partir das queries reais (§3.3); toda listagem pública tem índice composto que começa por `status`.
- JSON só para dados realmente flexíveis (snapshots de revisão, metadados técnicos, configurações); campos consultados viram colunas.
- Colunas geradas + índice quando precisar filtrar por um valor dentro de um JSON.
- Migrations pequenas, reversíveis, **sem lógica de dados misturada** (dados = seeders/commands). Mudanças destrutivas em duas etapas: *expand → migrate → contract*.

### 3.2 Esquema por módulo

**Accounts**
```
users(id, public_id, name, email UQ, email_verified_at, password NULL, avatar_asset_id NULL,
      bio, locale, last_login_at, two_factor_*, timestamps, deleted_at)
social_accounts(id, user_id FK, provider, provider_user_id, UQ(provider, provider_user_id), timestamps)
roles / permissions / model_has_roles ...   (spatie/laravel-permission)
consents(id, user_id FK, type [terms|privacy|contribution], version, accepted_at, ip_hash, user_agent)
```

**Archive / Contributions / Curation**
```
items(id, public_id, slug UQ, status, author_id FK users,
      published_revision_id FK NULL, draft_revision_id FK NULL,
      -- campos materializados da revisão publicada (leitura rápida):
      title, summary, body_html, body_text, cover_asset_id FK NULL, primary_category_id FK,
      date_start DATE NULL, date_end DATE NULL, date_label VARCHAR NULL,   -- "c. 1972"
      place_id FK NULL, language CHAR(5) DEFAULT 'pt-BR', rights_statement_id FK NULL,
      is_featured, featured_at, published_at, first_published_at,
      timestamps, deleted_at)
  IDX(status, published_at), IDX(status, is_featured, featured_at), IDX(author_id, status),
  IDX(primary_category_id, status, published_at)

item_revisions(id, item_id FK, number, status, author_id FK, data JSON,   -- snapshot completo
               reviewer_id FK NULL, reviewed_at, review_note, submitted_at, timestamps)
  UQ(item_id, number), IDX(status, submitted_at)

categories(id, parent_id FK NULL, name, slug UQ, description, position,
           _lft, _rgt)            -- nested set (kalnoy/nestedset): descendentes/ancestrais em 1 query
collections(id, public_id, name, slug UQ, description, cover_asset_id, curator_note,
            is_visible, position, timestamps)
tags(id, name, slug UQ, timestamps)
people(id, name, slug UQ, bio, birth_label, death_label)          -- pessoas retratadas/mencionadas
places(id, name, slug UQ, city, state, lat NULL, lng NULL)        -- locais (Franca, bairros...)
rights_statements(id, code, label, url)                            -- ex.: "Uso educacional", CC BY-NC 4.0

category_item(category_id, item_id, PK)   -- categorias secundárias (opcional); a primária fica em items.primary_category_id
collection_item(collection_id, item_id, position, PK(collection_id, item_id))
item_tag(item_id, tag_id, PK)
item_person(item_id, person_id, role [retratado|entrevistado|autor], PK(item_id, person_id, role))
```

**Media**
```
assets(id, public_id, disk, path, original_name, mime, size, checksum_sha256 IDX,
       width, height, duration_ms, pages, status [uploading|processing|ready|failed],
       title, caption, alt_text, credit, date_label, place_id, rights_statement_id,
       transcript TEXT NULL, technical JSON, uploaded_by FK users, timestamps, deleted_at)
asset_conversions(id, asset_id FK, name [thumb|card|large|full|poster|waveform], disk, path,
                  width, height, size, format)
item_assets(id, item_id FK, asset_id FK, role, position, caption_override NULL, UQ(item_id, asset_id, role))
item_embeds(id, item_id FK, provider [youtube|vimeo], external_id, url, title, description,
            duration_s, thumbnail_url, position, timestamps)
```

**Engagement**
```
favorites(user_id, item_id, created_at, PK(user_id, item_id))
item_stats(item_id PK, views, favorites, shares, updated_at)
item_daily_stats(item_id, date, views, unique_visitors, PK(item_id, date))
```

**Site e infraestrutura**
```
pages(id, slug UQ, title, body_json/blocks, seo JSON, status, timestamps)   -- institucionais editáveis
home_sections(id, type [featured|latest|most_viewed|collection|category|custom], title,
              source_id NULL, limit, position, is_visible)
settings(key PK, value JSON)            -- doação, contatos, redes
redirects(id, from_path UQ, to_path, status_code, hits, last_hit_at)
contact_messages(id, name, email, message, ip_hash, handled_at, timestamps)
activity_log (spatie), notifications, jobs, failed_jobs, job_batches, cache, sessions*
```
\* com Redis, as sessões e o cache saem do banco.

### 3.3 Índices × consultas principais

| Consulta | Índice |
|---|---|
| Acervo sem busca, por data | `items(status, published_at)` |
| Acervo por categoria (com descendentes) | `items(primary_category_id, status, published_at)` + nested set |
| Destaques da home | `items(status, is_featured, featured_at)` |
| Minhas publicações | `items(author_id, status)` |
| Fila de revisão | `item_revisions(status, submitted_at)` |
| Mais vistos (período) | `item_daily_stats(date, views)` + agregação |
| Busca textual e facetas | **Meilisearch** (não usa o MySQL) |

### 3.4 Integridade e regras no banco
- `CHECK`s simples onde ajudam (MySQL 8.0.16+): `date_end >= date_start`, `position >= 0`.
- Unicidade de slug garantida no banco **e** no gerador (retentativas com sufixo).
- Exclusão de categoria com itens: `RESTRICT` (a aplicação oferece "mover itens antes").
- Usuário excluído (LGPD): anonimizar (`name = 'Colaborador removido'`, e-mail com hash) mantendo as FKs; os itens publicados continuam, com o autor anonimizado.

---

## 4. Front-end

### 4.1 Organização por funcionalidade

```
resources/js/
  app.tsx  ssr.tsx
  design-system/          # camada sobre o MUI: tokens, tema, componentes base (Button, Card, Dialog, EmptyState...)
    theme/  components/  icons/
  features/
    archive/              # ItemCard, ItemGrid, SearchBar, FacetPanel, CategoryTree...
    item/                 # ItemHeader, Gallery, Lightbox, EmbedList, AudioPlayer, DocumentList, ShareBar...
    contribution/         # ContributionForm (wizard), steps, MediaUploader, useDirectUpload...
    curation/             # ReviewQueue, RevisionDiff, ModerationBar...
    engagement/           # FavoriteButton, useViewTracker...
    account/  admin/  site/
  layouts/                # PublicLayout, AccountLayout, AdminLayout
  pages/                  # SÓ composição: cada página importa as features e recebe os props tipados
  lib/                    # http (axios com interceptors), youtube, format (datas, bytes), storage seguro
  hooks/
  types/generated.d.ts    # gerado pelo typescript-transformer — nunca editar à mão
```

Regras:
- **Páginas finas:** recebem os props do servidor e compõem as features. A lógica de UI fica nos hooks da feature.
- **Design system próprio sobre o MUI:** a aplicação importa `@/design-system`, não o `@mui/material` direto (exceto os primitivos de layout). Trocar o visual vira um ajuste de tema, não uma caça em 200 arquivos.
- **Tema com tokens** (cores, espaçamento, raios, tipografia) e `colorSchemes` claro/escuro; `cssVariables: true`.
- **Formulários:** `react-hook-form` + **`zod`** (com schemas espelhando as regras do back, usando os enums e limites gerados) para validação instantânea; envio via Inertia `router` ou axios; os erros do servidor são mapeados para os campos. O `useForm` do Inertia fica para formulários simples.
- **Estado de servidor:** os props do Inertia + *partial reloads* (`only`) e `router.reload`. Para widgets interativos fora da navegação (sugestões de tags, status de upload, notificações) use **TanStack Query** com endpoints JSON dedicados.
- **Upload direto:** hook `useDirectUpload` (pede a URL pré-assinada → PUT com progresso → confirma), com **uploads em paralelo, retomada e cancelamento**; o formulário de contribuição salva o rascunho no servidor automaticamente (não só no localStorage).
- **Wizard de contribuição** em passos (Sobre a história → Mídias → Contexto: data, local, pessoas → Direitos e consentimento → Revisão), com validação por passo e salvamento automático.
- **Erros:** um *Error Boundary* por área e páginas de erro do Inertia (403/404/419/500/503) com o layout.
- **i18n preparado:** textos em `lang/*.json`, servidos ao front (`laravel-react-i18n` ou similar), mesmo que hoje só exista pt-BR.

### 4.2 UX para um acervo
- **Busca como protagonista:** barra global, sugestões instantâneas (Meilisearch), facetas com contagem (categoria, coleção, década, tipo de mídia, pessoa, local), trecho destacado nos resultados.
- **Linha do tempo** (navegar por década/ano) e **mapa** (itens por local) como formas alternativas de explorar o acervo.
- Página do item com **visualizador de documentos** (PDF inline com miniaturas das páginas), galeria com zoom profundo (opcional: OpenSeadragon para documentos de alta resolução), áudio com transcrição sincronizada.
- **Citação do item** ("Como citar": ABNT gerada a partir dos metadados) e **direitos de uso** visíveis.
- Coleções como **exposições curadas**: texto do curador, ordem definida, capa.

### 4.3 Qualidade no front
- **Vitest + Testing Library** (componentes e hooks), **Playwright** (E2E dos fluxos críticos: buscar, ver item, contribuir, revisar e publicar, login com Google mockado), **axe** nos testes E2E (acessibilidade automatizada).
- **Storybook** para o design system e as features visuais.
- ESLint (regras de hooks, a11y com `jsx-a11y`), Prettier, `tsc --noEmit` estrito, `import/no-cycle`.
- Orçamento de performance no CI (Lighthouse CI): LCP < 2,5 s, CLS < 0,1, JS inicial < 200 kB gz.

---

## 5. Testes e qualidade (back)
- Pirâmide: **unit** (value objects, transições de estado, serviços), **feature** (rotas, policies, actions), **contract** (os DTOs geram exatamente os tipos esperados), **E2E** (Playwright).
- Testes de **arquitetura** com Pest Arch: módulos não importam models de outros módulos; controllers não usam `DB::`; páginas não importam `@mui/material` direto.
- Cobertura mínima de 80% nas Actions e Policies.
- Banco de testes MySQL real (não SQLite: FULLTEXT, JSON e CHECK se comportam diferente).
- Mutation testing (Pest `--mutate`) nas regras editoriais.

## 6. CI/CD
1. PR → lint (Pint, ESLint), análise estática (Larastan nível 8, `tsc`), testes (Pest em paralelo, Vitest), build do front e do SSR, `composer/npm audit`, Lighthouse CI na homologação.
2. Merge na `main` → deploy automático em homologação (migrations + `typescript:transform` + `scout:sync-index-settings`).
3. Tag de release → deploy em produção com *zero downtime*, migrations *expand-only* antes da troca, *health check* e rollback automático se falhar.
4. Versionamento semântico + CHANGELOG gerado dos commits convencionais.

## 7. Observabilidade e operação
- Sentry (back e front, com *release tracking* e *source maps*), Pulse (filas, slow queries, cache), Horizon (jobs).
- Logs estruturados JSON com `request_id` correlacionando back, front e jobs.
- Alertas: taxa de erro, fila acumulada, falha no processamento de mídia, disco/bucket, certificado expirando.
- Página `/up` (health check) + monitor externo de uptime.
- Painel de admin com métricas de negócio (itens por status, contribuições por mês, tempo médio de revisão, mais vistos).

## 8. Roadmap de evolução (após o MVP)
1. Transcrição automática de áudio e vídeo (fila + revisão do curador).
2. Linha do tempo e mapa.
3. API pública + OAI-PMH/Dublin Core.
4. Exposições virtuais (coleções com narrativa e blocos).
5. Programa de voluntários (curadores convidados com permissões limitadas por coleção).
6. Multilíngue (ES/EN) para os textos de interface e as coleções principais.

---

## 9. Ajustes aos documentos 01–07

| Documento | Mudança |
|---|---|
| 01 | Adicionar: Redis, Horizon, Scout + Meilisearch, S3/MinIO, Sentry, Pulse, `spatie/laravel-data`, `spatie/laravel-typescript-transformer`, `spatie/laravel-model-states`, `spatie/laravel-permission`, `spatie/laravel-activitylog`, `kalnoy/nestedset`; front: `react-hook-form`, `zod`, `@tanstack/react-query`, Vitest, Playwright, Storybook. `jaybizzle/crawler-detect` continua. `spatie/laravel-medialibrary` **é substituído** pelo módulo Media próprio (assets + conversões), ou pode ser mantido apenas como motor de conversão |
| 02 | `articles` → `items` + `item_revisions`; mídias → `assets` + `item_assets` + `item_embeds`; `views_count` → `item_stats`/`item_daily_stats`; `role` → spatie/permission; `article_views` → Redis; IDs públicos ULID; categorias em nested set; novas tabelas `people`, `places`, `rights_statements`, `consents`, `pages`, `home_sections` |
| 03 §4 | Fluxo editorial com revisões (§2.2): a edição de um publicado **não tira o item do ar** |
| 03 §6 | Busca e facetas pelo Meilisearch (filtros por década, pessoa, local) |
| 03 §11 | Deduplicação e contagem no Redis com *flush* periódico |
| 04 | Rotas organizadas por módulo (`routes/modules/*.php`); upload direto (`POST /uploads/sign`, `POST /uploads/{asset}/complete`); controllers chamam Actions/Queries; props via DTOs `laravel-data` |
| 05 | Estrutura `design-system/` + `features/` + `pages/`; wizard de contribuição; formulários com react-hook-form + zod; testes Vitest/Playwright/Storybook |
| 06 | O importador mapeia para `items` + uma revisão inicial aprovada, `assets` (calculando o checksum, sem duplicar arquivos repetidos) e `item_embeds`; **não precisa preservar IDs** (usa `legacy_wp_id` + tabela `redirects`) |
| 07 | Inserir o módulo Media (upload direto + pipeline) e a busca (Meilisearch) antes da Fase 3; revisões e o diff na Fase 5 |
