# 01 — Stack e setup

## 1. Versões

| Camada | Tecnologia | Versão mínima |
|---|---|---|
| Linguagem | PHP | 8.3 (extensões: `gd` ou `imagick`, `intl`, `mbstring`, `pdo_mysql`, `exif`, `fileinfo`, `zip`) |
| Framework | Laravel | 12.x (use a estável mais recente) |
| Banco | MySQL | 8.0 (InnoDB, `utf8mb4_unicode_ci`, índice FULLTEXT) |
| Front | **Inertia.js v2 + React 18/19 + TypeScript** | — |
| UI | **MUI v7** (`@mui/material`, `@mui/icons-material`, `@emotion/react`, `@emotion/styled`) | — |
| Build | Vite + `laravel-vite-plugin` + `@vitejs/plugin-react` | — |
| Node | Node.js | 20 LTS+ |

## 2. Pacotes

### Composer
| Pacote | Uso |
|---|---|
| `inertiajs/inertia-laravel` | Adaptador Inertia |
| `tightenco/ziggy` | `route()` no React |
| `laravel/fortify` | Login, cadastro, reset de senha, verificação de e-mail (sem views) |
| `laravel/socialite` | Login com Google |
| `spatie/laravel-medialibrary` | Upload, conversões (thumb/medium/large WebP), ordenação de mídia |
| `spatie/laravel-sluggable` | Slugs únicos para artigos, categorias, coleções e tags |
| `spatie/laravel-sitemap` | `sitemap.xml` |
| `mews/purifier` | Sanitização do HTML do editor |
| `jaybizzle/crawler-detect` | Detecção de robôs no contador de visualizações |
| `hautelook/phpass` *(ou implementação própria)* | Verificar senhas antigas `$P$` do WP, se houver |
| **dev:** `pestphp/pest`, `pestphp/pest-plugin-laravel`, `larastan/larastan`, `laravel/pint`, `barryvdh/laravel-debugbar` | Qualidade |

### NPM
| Pacote | Uso |
|---|---|
| `@inertiajs/react` | Inertia React |
| `react`, `react-dom`, `typescript`, `@types/react`, `@types/react-dom` | Base |
| `@mui/material`, `@mui/icons-material`, `@emotion/react`, `@emotion/styled`, `@emotion/server` (SSR) | UI |
| `mui-tiptap`, `@tiptap/react`, `@tiptap/starter-kit`, `@tiptap/extension-link`, `@tiptap/extension-underline` | Editor rico |
| `@dnd-kit/core`, `@dnd-kit/sortable` | Reordenar mídias no formulário |
| `yet-another-react-lightbox` (+ plugins zoom, captions, download, counter) | Lightbox da galeria |
| `embla-carousel-react` | Carrosséis de artigos |
| `plyr-react` | Player de áudio |
| `notistack` | Toasts (sucesso/erro) |
| `ziggy-js` | Tipos do `route()` |
| `dayjs` (locale pt-br) | Datas no front, quando necessário |
| **dev:** `eslint`, `@typescript-eslint/*`, `eslint-plugin-react-hooks`, `prettier` | Qualidade |

> Não use Tailwind: toda a estilização vem do tema MUI (`sx`, `styled`).

## 3. Criação do projeto

```bash
composer create-project laravel/laravel museu-franca
cd museu-franca
composer require inertiajs/inertia-laravel tightenco/ziggy laravel/fortify laravel/socialite \
  spatie/laravel-medialibrary spatie/laravel-sluggable spatie/laravel-sitemap mews/purifier \
  jaybizzle/crawler-detect
composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan laravel/pint
php artisan inertia:middleware
php artisan fortify:install
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan vendor:publish --provider="Mews\Purifier\PurifierServiceProvider"
php artisan storage:link

npm i @inertiajs/react react react-dom @mui/material @mui/icons-material @emotion/react @emotion/styled \
  @emotion/server mui-tiptap @tiptap/react @tiptap/starter-kit @tiptap/extension-link @tiptap/extension-underline \
  @dnd-kit/core @dnd-kit/sortable yet-another-react-lightbox embla-carousel-react plyr-react notistack dayjs
npm i -D typescript @types/react @types/react-dom @vitejs/plugin-react eslint prettier \
  @typescript-eslint/parser @typescript-eslint/eslint-plugin eslint-plugin-react-hooks
```

Scripts em `package.json`: `dev`, `build` (`tsc --noEmit && vite build`), `build:ssr` (`vite build --ssr`),
`lint` (`eslint resources/js --ext .ts,.tsx`), `typecheck` (`tsc --noEmit`).

## 4. Estrutura de pastas

```
app/
  Actions/            # casos de uso: PublishArticle, RejectArticle, ToggleFavorite...
  Enums/              # ArticleStatus, UserRole, MediaCollection
  Http/
    Controllers/{Public,Account,Admin,Auth}/
    Requests/{Account,Admin}/
    Resources/        # ArticleCardResource, ArticleResource, UserResource...
    Middleware/       # HandleInertiaRequests, EnsureUserIsAdmin, TrackLastLogin
  Models/
  Notifications/
  Observers/          # ArticleObserver (cache), ...
  Policies/
  Services/           # RelatedArticlesService, ViewCounter, YoutubeId, TaxonomyCounts, SeoService
  Support/Hashing/    # WordpressHasher
  Console/Commands/   # ImportWordpress, GenerateSitemap, PruneArticleViews
config/museu.php      # configurações do domínio (ver §6)
resources/js/
  app.tsx  ssr.tsx  theme.ts
  Layouts/{PublicLayout,AccountLayout}.tsx
  Pages/{Home,Acervo,Artigos,Colecoes,Paginas,Auth,Conta,Admin}/...
  Components/{articles,media,forms,navigation,feedback}/...
  hooks/  lib/  types/
tests/{Feature,Unit}/
```

## 5. `.env` (chaves relevantes)

```dotenv
APP_NAME="Museu da Pessoa de Franca"
APP_URL=https://museudapessoadefranca.org.br
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_TIMEZONE=UTC            # exibição em America/Sao_Paulo via config/museu.php

DB_CONNECTION=mysql
DB_DATABASE=museu
DB_USERNAME=...
DB_PASSWORD=...

# Banco WordPress antigo (somente leitura, para o importador)
WP_DB_HOST=127.0.0.1
WP_DB_DATABASE=museu_site
WP_DB_USERNAME=...
WP_DB_PASSWORD=...
WP_DB_PREFIX=wp_
WP_UPLOADS_PATH=/www/wwwroot/museudapessoadefranca.org.br/wp-content/uploads

QUEUE_CONNECTION=database
CACHE_STORE=database        # ou redis
MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=museudapessoadefranca@gmail.com

GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

GA4_MEASUREMENT_ID=G-XXXXXXX
```

> O redirect URI do Google muda (antes: `/wp-login.php?loginSocial=google`). Cadastre o novo no Google Cloud Console **antes** do go-live, mantendo o antigo até a virada.

## 6. `config/museu.php`

```php
return [
    'display_timezone' => 'America/Sao_Paulo',
    'per_page' => ['acervo' => 12, 'account' => 12, 'collection' => 12, 'users' => 24],
    'limits' => [
        'title' => 100,
        'summary' => 250,
        'image_mb' => 10,
        'pdf_mb' => 20,
        'audio_mb' => 50,          // limite ÚNICO (bug corrigido: antes 5 MB × 50 MB)
        'max_images' => 200,
        'max_pdfs' => 20,
        'max_audios' => 20,
        'max_videos' => 20,
    ],
    'audio_mimes' => ['mp3','m4a','wav','ogg','oga','aac','opus','flac','webm'],
    'editorial' => [
        'resubmit_on_author_edit' => true,  // colaborador editou um publicado → volta para revisão
    ],
    'views' => [
        'dedupe_hours' => 24,
        'min_visible_seconds' => 3,
    ],
    'carousel_max' => 20,
    'donation' => [
        'pix_key' => 'museudapessoadefranca@gmail.com',
        'pix_qr' => '/images/pix-qrcode.png',
        'bank' => 'Banco do Brasil',
        'agency' => '0053-1',
        'account' => '103.585-1',
        'cnpj' => '07.464.521-0001/49',
        'contact_email' => 'museudapessoadefranca@gmail.com',
    ],
];
```

## 7. Produção
- Servidor: Nginx/OpenLiteSpeed + PHP-FPM; `php artisan optimize`; `queue:work` sob supervisor; processo SSR (`inertia:start-ssr`) sob supervisor.
- Agendador (`schedule:run` a cada minuto): sitemap diário, limpeza de `article_views` com mais de 48h, `queue:prune-failed`.
- Uploads em `storage/app/public` (ou S3 compatível, via `MEDIA_DISK`).
- Backups diários do banco e dos arquivos (`spatie/laravel-backup`, opcional).
