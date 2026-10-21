# 04 — Rotas e back-end

## 1. Rotas (`routes/web.php`)

```php
// ---------- Público ----------
Route::get('/', HomeController::class)->name('home');
Route::get('/acervo', [AcervoController::class, 'index'])->name('acervo');
Route::get('/artigos/{article:slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::post('/artigos/{article}/visualizacao', ArticleViewController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])->middleware('throttle:60,1')->name('articles.view');
Route::get('/colecoes', [CollectionController::class, 'index'])->name('collections.index');
Route::get('/colecoes/{collection:slug}', [CollectionController::class, 'show'])->name('collections.show');
Route::get('/autores/{user}-{slug?}', AuthorController::class)->name('authors.show');
Route::get('/quem-somos', [PageController::class, 'about'])->name('pages.about');
Route::get('/apoie', [PageController::class, 'donate'])->name('pages.donate');
Route::get('/politica-de-privacidade', [PageController::class, 'privacy'])->name('pages.privacy');
Route::get('/termos-de-uso', [PageController::class, 'terms'])->name('pages.terms');
Route::get('/contato', [ContactController::class, 'create'])->name('contact');
Route::post('/contato', [ContactController::class, 'store'])->middleware('throttle:3,10');

// ---------- Auth (Fortify registra login/cadastro/reset/verify) ----------
// Fortify: GET/POST /login, /cadastro (configurar 'register' path), /esqueci-senha, /redefinir-senha/{token},
//          /email/verificar ; views apontam para páginas Inertia (Fortify::loginView etc.)
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);

// ---------- Conta (auth) ----------
Route::middleware('auth')->prefix('minha-conta')->name('account.')->group(function () {
    Route::get('/', AccountHomeController::class)->name('home');                 // redireciona para publicações
    Route::get('/publicar', [Account\ArticleController::class, 'create'])->middleware('verified')->name('articles.create');
    Route::post('/artigos', [Account\ArticleController::class, 'store'])->middleware('verified')->name('articles.store');
    Route::get('/publicacoes', [Account\ArticleController::class, 'index'])->name('articles.index');
    Route::get('/artigos/{article}/editar', [Account\ArticleController::class, 'edit'])->name('articles.edit');
    Route::post('/artigos/{article}', [Account\ArticleController::class, 'update'])->name('articles.update'); // POST + _method=PUT (multipart)
    Route::delete('/artigos/{article}', [Account\ArticleController::class, 'destroy'])->name('articles.destroy');
    Route::get('/favoritos', [FavoriteController::class, 'index'])->name('favorites');
    Route::get('/notificacoes', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notificacoes/lidas', [NotificationController::class, 'markAllRead'])->name('notifications.read');
    Route::get('/meus-dados', [ProfileController::class, 'edit'])->name('profile');
    Route::put('/meus-dados', [ProfileController::class, 'update']);
    Route::put('/meus-dados/senha', [ProfileController::class, 'password'])->name('profile.password');
    Route::get('/meus-dados/exportar', [ProfileController::class, 'export'])->name('profile.export');
    Route::delete('/meus-dados', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
Route::post('/artigos/{article}/favorito', [FavoriteController::class, 'toggle'])
    ->middleware(['auth', 'throttle:30,1'])->name('favorites.toggle');

// ---------- Admin ----------
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('/revisao', [Admin\ReviewController::class, 'index'])->name('review');
    Route::get('/artigos', [Admin\ArticleController::class, 'index'])->name('articles.index'); // ?status=…&lixeira=1
    Route::post('/artigos/{article}/publicar', [Admin\ModerationController::class, 'publish'])->name('articles.publish');
    Route::post('/artigos/{article}/rejeitar', [Admin\ModerationController::class, 'reject'])->name('articles.reject');
    Route::post('/artigos/{article}/destaque', [Admin\ModerationController::class, 'toggleFeatured'])->name('articles.feature');
    Route::put('/artigos/{article}/classificacao', [Admin\ModerationController::class, 'classify'])->name('articles.classify'); // categoria + coleções
    Route::post('/artigos/{article}/zerar-visualizacoes', [Admin\ModerationController::class, 'resetViews'])->name('articles.reset-views');
    Route::post('/artigos/{id}/restaurar', [Admin\ModerationController::class, 'restore'])->name('articles.restore');
    Route::delete('/artigos/{id}/definitivo', [Admin\ModerationController::class, 'forceDelete'])->name('articles.force-delete');
    Route::post('/artigos/lote', [Admin\BulkController::class, 'handle'])->name('articles.bulk');
    Route::resource('categorias', Admin\CategoryController::class)->except('show')->names('categories');
    Route::resource('colecoes', Admin\CollectionController::class)->except('show')->names('collections')->parameters(['colecoes' => 'collection']);
    Route::resource('tags', Admin\TagController::class)->except(['show', 'create'])->names('tags');
    Route::post('/tags/mesclar', [Admin\TagController::class, 'merge'])->name('tags.merge');
    Route::get('/usuarios', [Admin\UserController::class, 'index'])->name('users.index');
    Route::put('/usuarios/{user}/papel', [Admin\UserController::class, 'updateRole'])->name('users.role');
    Route::get('/configuracoes', [Admin\SettingsController::class, 'edit'])->name('settings');
    Route::put('/configuracoes', [Admin\SettingsController::class, 'update']);
});

// ---------- SEO / legado ----------
Route::get('/sitemap.xml', SitemapController::class);
// Middleware global LegacyRedirects (antes do 404): consulta a tabela `redirects`; trata também
//   /minha-conta/?aba=publicar → /minha-conta/publicar, ?aba=historico → /minha-conta/publicacoes,
//   ?aba=favoritos → /minha-conta/favoritos, ?aba=revisao → /admin/revisao, ?aba=usuarios → /admin/usuarios,
//   /minha-conta/login → /login, /minha-conta/cadastro → /cadastro, /acervo/?categoria=x (mantido),
//   /colecao/{slug} → /colecoes/{slug}, /?p={id} e /publicacoes/artigo/?id={id} → /artigos/{slug},
//   /wp-login.php, /wp-admin → /login
```

Todas as rotas mutáveis do admin usam `$this->authorize(...)` mesmo dentro do grupo `admin`.

## 2. Props compartilhados (`HandleInertiaRequests::share`)

```php
'auth' => ['user' => $user ? [id, name, email, role, avatar_url, email_verified] : null],
'flash' => ['success' => session('success'), 'error' => session('error')],
'counts' => fn () => $user?->isAdmin() ? ['pending' => Article::pending()->count()] : null, // lazy
'notifications' => fn () => $user ? ['unread' => $user->unreadNotifications()->count()] : null,
'ziggy' => fn () => [...(new Ziggy)->toArray(), 'location' => $request->url()],
'app' => ['name' => config('app.name'), 'ga4' => config('services.ga4.id')],
```

## 3. Controllers — responsabilidades e props

| Controller | Página Inertia | Props principais |
|---|---|---|
| `HomeController` | `Home` | `sections[]` = `{ key, title, type: featured\|carousel, link, items: ArticleCard[] }` |
| `AcervoController@index` | `Acervo/Index` | `articles` (paginado, `ArticleCardResource`), `filters`, `categories` (árvore com `count`), `collections` (com `count`), `totals` |
| `ArticleController@show` | `Articles/Show` | `article` (`ArticleResource` completo), `related` (cards), `can` (`update, moderate, delete`), `isFavorited`, `seo` |
| `CollectionController@index/show` | `Collections/Index`, `Collections/Show` | lista de coleções / coleção + artigos paginados |
| `AuthorController` | `Authors/Show` | autor público (sem e-mail) + publicações |
| `Account\ArticleController@index` | `Account/Articles/Index` | artigos do usuário (todos os status exceto lixeira), filtros por status |
| `@create` / `@edit` | `Account/Articles/Form` | `article?`, `categories` (árvore), `collections` (se admin), `limits`, `audioMimes`, `canAssignCollections`, `willResubmit` (bool) |
| `@store` / `@update` | — | `SaveArticleAction`; redireciona para `articles.show` (publicado) ou `account.articles.index` com flash |
| `@destroy` | — | soft delete; flash "Publicação enviada para a lixeira." |
| `FavoriteController@index` / `@toggle` | `Account/Favorites` / JSON | |
| `ProfileController` | `Account/Profile` | user, contas sociais |
| `Admin\DashboardController` | `Admin/Dashboard` | métricas |
| `Admin\ReviewController` | `Admin/Review` | pendentes paginados |
| `Admin\ArticleController@index` | `Admin/Articles/Index` | tabela paginada no servidor (filtros, ordenação, lixeira) |
| `Admin\Category/Collection/Tag` | `Admin/Taxonomies/*` | CRUD |
| `Admin\UserController` | `Admin/Users/Index` | tabela de usuários |

**Recursos JSON** (`app/Http/Resources`):
- `ArticleCardResource`: `id, title, slug, url, cover {thumb, medium, alt}, author {id, name}, category_path, collections[{name,slug}], badges {video,pdf,gallery,audio}, status, status_label, published_at_human, views_count`.
- `ArticleResource`: card + `summary, content (HTML sanitizado), category {id,name,slug,breadcrumb[]}, tags[], cover {large, full}, gallery[{id, thumb, large, full, description, alt, download_url}], videos[{id, youtube_id, embed_url, description}], audios[{id, url, mime, size_human, title, description}], pdfs[{id, url, size_human, title, description}], favorites_count, rejection_reason (só autor/admin)`.

## 4. Serviços e Actions

| Classe | Função |
|---|---|
| `Actions\SaveArticleAction` | Cria/atualiza o artigo com taxonomias, mídias e vídeos (03 §5), em transação |
| `Actions\PublishArticle`, `RejectArticle`, `ToggleFeatured`, `ResetViews`, `ToggleFavorite` | Casos de uso unitários + eventos |
| `Services\RelatedArticlesService` | 03 §8 |
| `Services\ViewCounter` | 03 §11 |
| `Services\YoutubeId` | `extract(string $url): ?string` |
| `Services\TaxonomyCounts` | Árvore de categorias com contagem dos descendentes + contagem das coleções (cache 10 min) |
| `Services\HomeSections` | Monta as seções da home (cache 5 min) |
| `Services\SeoService` | Gera os dados SEO/OG/JSON-LD por página |
| `Support\Hashing\WordpressHasher` | Verifica `$wp$2y$`, `$P$` e bcrypt puro (ver 06 §5) |

## 5. Eventos, observers e jobs
- `ArticleObserver`: `saved/deleted/restored/forceDeleted` → `Cache::tags`/chaves: `home.*`, `taxonomy.counts`, `related.{id}`. Se o cache não suportar tags, use chaves versionadas (`Cache::increment('cache.version.articles')`).
- `CategoryObserver`, `CollectionObserver` → `taxonomy.counts`.
- Eventos `ArticleSubmitted`, `ArticlePublished`, `ArticleRejected` → listeners que enviam as notificações (fila).
- `Login` → `UpdateLastLoginAt`.
- Conversões da medialibrary em fila (`queue: media`).

## 6. Agendador (`routes/console.php`)
```php
Schedule::command('sitemap:generate')->daily();
Schedule::command('museu:prune-article-views')->hourly();   // apaga article_views > 48h
Schedule::command('queue:prune-failed --hours=168')->daily();
```

## 7. Segurança
- CSRF em tudo, exceto `articles.view`.
- Rate limits: login (Fortify), cadastro 5/h por IP, contato 3/10min, favoritos 30/min, views 60/min.
- Upload: validar mime **real** (`mimetypes`), renomear arquivos, servir pelo disco público sem execução (`.htaccess`/nginx sem PHP em `storage`).
- Purifier: perfil `article` permitindo `p, br, h1-h3, strong, em, u, a[href|target|rel], blockquote, pre, code, ul, ol, li`; links externos recebem `rel="noopener nofollow"`.
- Cabeçalhos: CSP básica (permitir youtube-nocookie, vlibras.gov.br, googletagmanager), `X-Frame-Options: SAMEORIGIN`, HSTS.
- Policies testadas em feature tests (cada linha da matriz da 03 §2).
- Nunca expor o e-mail de usuários fora do admin.

## 8. Testes mínimos (Pest)
- `Auth`: cadastro, verificação, login, login legado com rehash, Google (mock do Socialite: vincular, criar, logar), reset de senha.
- `Articles`: criação por colaborador → pending; por admin → published; validações (capa, termo, vídeo inválido, limites); edição de publicado por colaborador → pending; admin remove todas as coleções; soft delete pelo autor; force delete só pelo admin (e mídias apagadas); pendente → 404 para terceiros.
- `Acervo`: busca, filtro por categoria com descendentes, coleção + categoria, mídia, paginação preservando os filtros.
- `Related`: exclusão do próprio artigo, completar com recentes, sem repetição.
- `Views`: robô não conta, admin não conta, dedupe 24h, não publicado não conta.
- `Favorites`: toggle, visitante 401/redirect, artigo não publicado rejeitado.
- `Legacy`: redirects (`/?p=`, `?aba=`), slugs preservados.
