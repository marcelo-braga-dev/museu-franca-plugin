# 02 — Banco de dados

MySQL 8, `utf8mb4_unicode_ci`. Todas as migrations são reversíveis. Os IDs dos artigos, usuários e
termos importados do WordPress **são preservados** (ver `06`), então as tabelas aceitam inserção com ID explícito.

## 1. Enums (`app/Enums`)

```php
enum UserRole: string { case Admin = 'admin'; case User = 'user'; }

enum ArticleStatus: string {
    case Draft = 'draft';         // melhoria: rascunho salvo pelo autor
    case Pending = 'pending';     // aguardando revisão
    case Published = 'published';
    case Rejected = 'rejected';   // melhoria: rejeitado, com motivo
}

enum MediaCollection: string {   // coleções da medialibrary no Article
    case Cover = 'cover';         // singleFile
    case Gallery = 'gallery';
    case Pdfs = 'pdfs';
    case Audios = 'audios';
}
```
Cada enum expõe `label(): string` em pt-BR (ex.: `Pending` → "Pendente de revisão").

## 2. Tabelas

### `users`
| Coluna | Tipo | Observações |
|---|---|---|
| id | bigint PK | preserva o ID do WP |
| name | string(120) | nome de exibição |
| username | string(60) unique nullable | login legado; o cadastro novo usa e-mail |
| email | string unique | |
| email_verified_at | timestamp nullable | contas importadas e do Google já ficam verificadas |
| password | string nullable | null para contas apenas Google |
| password_legacy | boolean default false | true = hash no formato WP, rehash no primeiro login |
| role | string(20) default `user` | `UserRole` |
| avatar_path | string nullable | upload próprio; senão foto do Google; senão Gravatar |
| bio | text nullable | melhoria: perfil público do autor |
| last_login_at | timestamp nullable | **bug corrigido**: um campo único |
| remember_token, timestamps, softDeletes | | soft delete para a exclusão de conta (LGPD) |

### `social_accounts`
`id, user_id FK cascade, provider string(30), provider_id string(191), avatar_url nullable, timestamps`
· unique(`provider`, `provider_id`).

### `categories` (hierárquica)
`id, parent_id FK nullable → categories (nullOnDelete), name string(120), slug string unique, description text nullable, position int default 0, timestamps`
· index(`parent_id`).

### `collections`
`id, name string(120), slug string unique, description text nullable, cover_path nullable, position int default 0, is_visible bool default true, timestamps`.

### `tags`
`id, name string(80), slug string unique, timestamps`.

### `articles`
| Coluna | Tipo | Observações |
|---|---|---|
| id | bigint PK | |
| user_id | FK users (restrictOnDelete) | autor |
| category_id | FK categories (restrictOnDelete) | **obrigatória** |
| title | string(100) | |
| slug | string(191) unique | preservar os slugs do WP |
| summary | string(250) nullable | |
| content | longText nullable | HTML sanitizado |
| status | string(20) index | `ArticleStatus` |
| rejection_reason | text nullable | preenchido ao rejeitar |
| is_featured | boolean default false index | |
| featured_at | timestamp nullable | ordena os destaques |
| views_count | unsignedInteger default 0 index | |
| consent_accepted_at | timestamp | **termo de autorização** (antes não era gravado) |
| consent_ip | string(45) nullable | evidência do aceite |
| published_at | timestamp nullable index | |
| submitted_at | timestamp nullable | última submissão para revisão |
| reviewed_by | FK users nullable | quem publicou/rejeitou |
| reviewed_at | timestamp nullable | |
| legacy_wp_id | bigint nullable unique | rastreabilidade |
| timestamps, softDeletes | | soft delete = lixeira |

Índices: `(status, published_at)`, `(user_id, status)`, **FULLTEXT** `(title, summary, content)`.

### Pivôs
- `article_collection (article_id, collection_id)` — PK composta, FKs cascade.
- `article_tag (article_id, tag_id)` — PK composta, FKs cascade.

### `article_videos`
`id, article_id FK cascade, url string(500), youtube_id string(20), description string(500) nullable, position int default 0, timestamps`.

### `media` (spatie/laravel-medialibrary)
Tabela padrão do pacote. No `Article`:
- `cover`: `singleFile()`, só imagens; conversões `thumb` (400×250 crop), `medium` (800w), `large` (1600w), em WebP.
- `gallery`: imagens; mesmas conversões; `custom_properties.description` = legenda; `order_column` = ordem; `name` = título; `custom_properties.alt` = texto alternativo.
- `pdfs`: `application/pdf`; `custom_properties.description`.
- `audios`: os mimes de `config('museu.audio_mimes')`; `custom_properties.description`.

Conversões **em fila** (`->queued()`); a original fica preservada para download.

### `favorites`
`user_id FK cascade, article_id FK cascade, created_at` · PK (`user_id`, `article_id`).

### `article_views` (deduplicação do contador)
`id, article_id FK cascade, visitor_hash char(64), viewed_at timestamp` · unique(`article_id`, `visitor_hash`) · index(`viewed_at`).
`visitor_hash = hash('sha256', ip . '|' . user_agent . '|' . config('app.key'))` (não guarda o IP puro — LGPD).
Limpeza: o agendador apaga os registros com mais de 48h.

### `redirects` (SEO / legado)
`id, from_path string(500) unique, to_path string(500), status_code smallint default 301, hits int default 0, timestamps`.
Preenchida pelo importador (`/?p=123`, `/publicacoes/artigo/?id=123`, slugs alterados).

### `settings` (opcional)
`key string PK, value json` — dados de doação e textos institucionais editáveis no admin
(sobrescrevem `config/museu.php`).

### Tabelas de infraestrutura
`sessions`, `cache`, `jobs`, `failed_jobs`, `password_reset_tokens`, `notifications` (canal database).

## 3. Models e relações

```php
User:       hasMany(Article), hasMany(SocialAccount), belongsToMany(Article, 'favorites')->withPivot('created_at')
            isAdmin(): bool
Article:    belongsTo(User, 'user_id') as author, belongsTo(Category), belongsToMany(Collection),
            belongsToMany(Tag), hasMany(ArticleVideo)->orderBy('position'),
            belongsToMany(User, 'favorites') as favoritedBy, HasMedia (cover, gallery, pdfs, audios)
            casts: status => ArticleStatus, is_featured => bool, datas => datetime
            scopes: published(), pending(), visibleTo(?User), search(string), inCategoryTree(Category), inCollection(Collection)
            accessors: url, has_video, has_pdf, has_gallery, has_audio
Category:   belongsTo(self, 'parent_id') as parent, hasMany(self) as children, hasMany(Article)
            descendantIds(): array (cacheado), breadcrumb(): string "Pai > Filha"
Collection: belongsToMany(Article)
Tag:        belongsToMany(Article)
ArticleVideo: belongsTo(Article); embedUrl accessor
```

`scopeInCategoryTree` filtra por `category_id IN (id + descendentes)`.
`scopeSearch` usa `MATCH(title, summary, content) AGAINST(? IN BOOLEAN MODE)` e também casa nomes de tags (`orWhereHas('tags')`).

Os *accessors* `has_*` devem vir de `withCount`/`withExists` nas listagens, **sem N+1**:
```php
Article::published()->with(['author:id,name', 'category.parent', 'collections:id,name,slug', 'media'])
    ->withExists(['videos as has_video'])
```

## 4. Seeders
- `AdminSeeder`: admin a partir de `ADMIN_EMAIL` / `ADMIN_PASSWORD` do `.env`.
- `TaxonomySeeder`: as 14 categorias e as 5 coleções atuais (lista em `00-documentacao-funcional-legado.md` §3), só em ambiente local.
- `DemoSeeder` (local): 30 artigos com factories, imagens de exemplo, vídeos e favoritos.

Factories para todos os models (estados `published()`, `pending()`, `featured()`).
