# 03 — Regras de negócio (fonte da verdade)

## 1. Papéis
| Papel | Descrição |
|---|---|
| Visitante | não autenticado |
| Colaborador (`user`) | cadastrado; publica, que passa por revisão; favorita |
| Administrador (`admin`) | moderação e gestão completa |

Middleware `admin` (`EnsureUserIsAdmin`) protege o grupo `/admin`. A autorização fina fica nas Policies.

## 2. Policies

### `ArticlePolicy`
| Método | Regra |
|---|---|
| `view` | publicado → todos. Outro status → **somente o autor ou um admin** (bug corrigido: pendente era público) → senão 404 |
| `create` | usuário autenticado **com e-mail verificado** |
| `update` | admin **ou** autor (qualquer status, exceto quando estiver na lixeira) |
| `delete` (lixeira) | admin **ou** autor |
| `restore` | admin |
| `forceDelete` | admin |
| `publish` / `reject` / `feature` / `moderate` (categoria, coleções, status, zerar views) | admin |
| `assignCollections` | admin |

### `UserPolicy`
`viewAny` (lista de usuários) e `updateRole` → admin. Um admin não pode rebaixar a si mesmo nem remover o último admin.

## 3. Cadastro, login e conta
1. **Cadastro** (`/cadastro`): nome, e-mail, senha (mín. **8**, com confirmação), aceite dos Termos de Uso e da Política de Privacidade.
   - E-mail único (mensagem: "Este e-mail já está cadastrado.").
   - Cria com `role = user` e **envia a verificação de e-mail** (melhoria).
   - Faz login automático e redireciona para `/minha-conta` com o aviso "Confirme seu e-mail para publicar".
2. **Login** (`/login`): e-mail **ou** username + senha + "lembrar-me". Throttle de 5 tentativas/min por e-mail+IP (Fortify).
   - Senhas legadas: ver o *WordpressHasher* em `06` §5; no primeiro login bem-sucedido, gravar em bcrypt e `password_legacy = false`.
   - **Esqueci minha senha** (melhoria; antes não existia).
3. **Login com Google** (`/auth/google`):
   - `social_accounts` já existe → login.
   - Senão, existe um usuário com o mesmo e-mail → vincula e faz login.
   - Senão, cria o usuário (`role = user`, `email_verified_at = now()`, nome e avatar do Google) e faz login.
   - Após o login → URL pretendida ou `/minha-conta`.
   - Erro ou cancelamento → volta para `/login` com a mensagem "Não foi possível entrar com o Google."
4. **Último acesso**: o listener de `Illuminate\Auth\Events\Login` grava `last_login_at = now()` (bug corrigido).
5. **Meus dados** (melhoria): editar nome, bio e avatar; trocar a senha; definir uma senha para a conta Google; ver as contas vinculadas; **excluir a conta** (LGPD: soft delete do usuário, os artigos publicados passam a exibir "Autor removido", os favoritos são apagados).
6. Usuário logado que acessa `/login` ou `/cadastro` → redireciona para `/minha-conta`.

## 4. Fluxo editorial

```
          (salvar rascunho)                (enviar)                 (admin publica)
 [novo] ──────────────────▶ draft ─────────────────▶ pending ───────────────────▶ published
    │                         ▲                         │                             │
    │ (enviar)                │ (autor edita)            │ (admin rejeita c/ motivo)  │ (colaborador edita e
    └────────────────────────┐│                         ▼                             │  resubmit_on_author_edit = true)
                             └┴──────────────────── rejected ◀────────────────────────┘ → pending
 admin criando ou editando: vai direto para published (pode escolher rascunho)
```

Regras:
- **Colaborador** ao enviar → `pending` (`submitted_at = now()`); ao salvar como rascunho → `draft`.
- **Admin** ao criar → `published` por padrão (opção "salvar como rascunho").
- **Publicar** (admin): `status = published`, `published_at` = (mantém, se já existia) ou `now()`, `reviewed_by`, `reviewed_at`; notifica o autor ("Sua publicação foi aprovada").
- **Rejeitar** (admin): o motivo é obrigatório (mín. 10 caracteres); `status = rejected`; notifica o autor com o motivo. O autor edita e reenvia → `pending`.
- **Colaborador edita um artigo `published`**: se `config('museu.editorial.resubmit_on_author_edit')` → `pending`, e o artigo **sai do ar** até ser aprovado de novo. O formulário avisa antes de salvar: "Ao salvar, sua publicação voltará para revisão."
- **Admin edita** → não muda o status.
- **Novo pendente** → notificação (database + e-mail) para todos os admins: "Nova publicação aguardando revisão".
- **Excluir**: o autor e o admin enviam para a **lixeira** (soft delete). Só o admin restaura ou exclui de vez. A exclusão definitiva apaga as mídias e os arquivos (medialibrary faz isso em cascata) e os pivôs.
- Artigos na lixeira não aparecem em nenhuma listagem pública nem nos favoritos.

## 5. Formulário de artigo — validação (servidor **e** cliente)

| Campo | Regras (`StoreArticleRequest` / `UpdateArticleRequest`) |
|---|---|
| `title` | required, string, max:100 |
| `summary` | nullable, string, max:250 |
| `content` | nullable, string, max:200000 → `clean()` (Purifier, perfil `article`) |
| `category_id` | required, exists:categories,id |
| `tags` | nullable, array, max:20; cada item string max:50; normalizar (trim, sem duplicatas, sem vazios) |
| `collection_ids` | nullable, array; cada `exists:collections,id`. **Ignorado se não for admin**. Quando enviado por um admin, faz `sync()` **inclusive com array vazio** (bug corrigido) |
| `cover` | **required na criação**; na edição, required se não houver capa atual (`required_without:keep_cover`); image, mimes jpg,jpeg,png,webp, max:`image_mb` |
| `gallery_new[]` | array, max:`max_images`; cada item: `file` image ≤ `image_mb`, `description` ≤ 300 |
| `gallery_existing[]` | `id` (pertence ao artigo), `description`, `remove` bool, `position` int |
| `pdfs_new[]` / `pdfs_existing[]` | idem, `mimetypes:application/pdf`, ≤ `pdf_mb` |
| `audios_new[]` / `audios_existing[]` | idem, mimes de `audio_mimes`, ≤ **`audio_mb` único** (bug corrigido) |
| `videos[]` | array, max:`max_videos`; `url` required, url, e **deve resultar em um `youtube_id` válido** (regra `ValidYoutubeUrl`); `description` ≤ 500; `id` opcional (existente); `position` |
| `consent` | **accepted** (bug corrigido: antes só no navegador); grava `consent_accepted_at` e `consent_ip` |
| `intent` | `in:draft,submit` (padrão `submit`) |

Mensagens em pt-BR (`lang/pt_BR/validation.php` + `attributes`): "O título é obrigatório.", "Adicione uma foto de capa.", "Selecione uma categoria.", "Link do YouTube inválido." etc.

**Persistência** (`SaveArticleAction`, dentro de uma transação):
1. Cria ou atualiza o artigo com os campos validados e o status conforme §4.
2. `tags`: `firstOrCreate` por slug → `sync`.
3. `collections`: `sync` (admin).
4. Capa: se veio um arquivo, `addMedia(...)->toMediaCollection('cover')` (substitui).
5. Galeria, PDFs e áudios existentes: atualiza a descrição e a ordem; apaga os marcados com `remove`.
6. Novos: `addMedia` com `custom_properties.description`; ordem = `position`.
7. Vídeos: *sync* pela lista recebida (atualiza os que têm `id`, cria os novos, apaga os ausentes); `youtube_id` extraído por `YoutubeId::extract()`.
8. Dispara os eventos `ArticleSubmitted` / `ArticleUpdated`.

**YoutubeId::extract** aceita: `youtu.be/ID`, `youtube.com/watch?v=ID` (com outros parâmetros em qualquer ordem), `/embed/ID`, `/shorts/ID`, `/v/ID`, `/live/ID`, `m.youtube.com`, `youtube-nocookie.com`. O ID tem 11 caracteres `[A-Za-z0-9_-]`. A embed usa `https://www.youtube-nocookie.com/embed/{id}` (privacidade).

**Upload com progresso**: enviar via `router.post(..., { forceFormData: true, onProgress })` e mostrar a % num overlay. Tamanho máximo do POST no servidor: `upload_max_filesize` e `post_max_size` ≥ 256M.

## 6. Acervo — busca e filtros

`GET /acervo?q=&categoria=slug&colecao=slug&midia=video|pdf|galeria|audio&ordem=recentes|antigos|mais-vistos|az&page=`

- Só `published`.
- `q`: FULLTEXT em título, resumo e conteúdo **+** nome das tags. Com `q`, a ordem padrão é relevância.
- `categoria`: inclui os descendentes.
- `colecao`: filtra a coleção.
- **Melhoria:** categoria **e** coleção são combináveis (AND). Antes, uma anulava a outra.
- `midia` (melhoria): só os artigos que têm esse tipo de anexo.
- Paginação: 12; a URL preserva todos os filtros; `preserveScroll` ao paginar.
- Chips de filtros ativos, cada um com "×" para remover, e o botão "Limpar filtros".
- Resultado vazio: "Nenhum artigo encontrado." + sugestão de limpar os filtros.
- Os contadores dos menus laterais (categorias com os descendentes, coleções) contam só os publicados. Ficam em cache (`TaxonomyCounts`) por 10 min e são invalidados pelo `ArticleObserver` (saved/deleted/restored) e pelos observers de Category/Collection.

## 7. Página do artigo
Ordem dos blocos: selo de status (se não publicado) → barra de moderação (admin) → título → autor (link para o perfil) e data → favoritar → compartilhar → capa → resumo → metadados (categoria como breadcrumb clicável, coleções clicáveis, tags clicáveis `#tag` → `/acervo?q=tag`) → conteúdo → galeria → vídeos → áudios → PDFs → "Sugeridos para você" → aviso de responsabilidade.

- Cada seção de mídia só aparece se tiver itens. As descrições são exibidas como legenda.
- O selo "Coleção" lista os nomes separados por vírgula (bug corrigido).
- **Compartilhar:** WhatsApp, Telegram, Facebook, X, e-mail e copiar link; no mobile usa `navigator.share` quando disponível.
- **SEO** (melhoria importante para o preview no WhatsApp): `<title>`, `meta description` (resumo), Open Graph (`og:title`, `og:description`, `og:image` = capa *large*, `og:type=article`), Twitter Card, `link rel=canonical` e JSON-LD `Article`. Renderizado via **SSR**.
- 404 para slug inexistente. Se o slug mudou, a tabela `redirects` faz 301.

## 8. Relacionados ("Sugeridos para você") — `RelatedArticlesService`
Entrada: artigo base, `limit` (≤ 20), `flavor` ∈ `category | parent | tags | collections | mix | auto`.
Sempre: só os publicados, exclui o próprio artigo, sem repetição, ordem por data desc dentro de cada etapa.
- `category`: mesma categoria.
- `parent`: categoria pai + os descendentes do pai.
- `tags` / `collections`: qualquer tag ou coleção em comum.
- `mix`: primeiro as interseções (categoria∩tag, categoria∩coleção, tag∩coleção), depois a cascata categoria → pai → tags → coleções.
- `auto`: cascata categoria → pai → tags → coleções.
- Se faltarem itens, completa com os mais recentes.
- A página do artigo usa `mix` (melhoria; antes era só categoria). Resultado em cache por 1h por artigo, invalidado quando o artigo é salvo.

## 9. Carrosséis da home (`HomeController`)
Seções configuráveis (melhoria: definidas na tabela `settings`/admin; padrão abaixo):
1. Destaques (`is_featured`, até 6, ordem: `featured_at` desc, depois `views_count` desc)
2. Mais acessados (`views_count` desc, 12)
3. Publicações recentes (12)
4. Coleções: DOPS, Memórias da Resistência, Movimento Operário e Sindical (8 cada)
5. Categorias: Cultura e Artes, Movimento Negro (12 cada)
6. Blocos institucionais (Sobre, IPRA, Apoie, Contato)

Cards dos carrosséis: capa *thumb*, ícones de mídia, título e meta (primeira coleção, senão a categoria).
Cache de 5 min por seção, invalidado pelo `ArticleObserver`.

## 10. Favoritos
- `POST /artigos/{article}/favorito` (auth, throttle 30/min) faz o toggle e responde `{ favorited: bool, count: int }`.
- Só artigos `published`. Visitante → o botão leva ao login, com retorno para a página.
- O front atualiza **todos** os botões daquele artigo na página (estado compartilhado num contexto ou store).
- Melhoria: mostrar a contagem de favoritos no artigo; "Meus favoritos" com busca e paginação.

## 11. Contador de visualizações
- O front (`useArticleView`), na página do artigo publicado, quando `document.visibilityState === 'visible'` por **3 s** seguidos (o timer pausa quando a aba fica oculta), envia `POST /artigos/{id}/visualizacao` com `keepalive`. Não envia se `navigator.webdriver` for true ou se o `localStorage` indicar uma visita nas últimas 24h. Todos os acessos a `localStorage` ficam em try/catch.
- Back-end (`ViewCounter::record`):
  1. artigo `published`, senão 204 sem contar;
  2. `CrawlerDetect` ou user-agent vazio → não conta;
  3. usuário admin → não conta;
  4. `insertOrIgnore` em `article_views (article_id, visitor_hash)` com janela de 24h (apaga os registros antigos do mesmo hash antes, ou use a chave `(article_id, visitor_hash, date)`);
  5. inseriu → `increment('views_count')` atômico.
- Rota **sem CSRF** (a página pode vir de cache), com `throttle:60,1`. Resposta `204`.
- Admin: "Zerar visualizações" põe o contador em 0 e apaga o `article_views` do artigo.

## 12. Moderação (admin)
- **Fila de revisão** `/admin/revisao`: tabela (MUI DataGrid ou Table) com título, autor, categoria, data de envio e ações (Ver, Publicar, Rejeitar). Contador de pendentes como badge no menu.
- **Barra de moderação no artigo** (só admin): chips de status, autor, visualizações e destaque; ações Publicar/Rejeitar (se pendente), Tornar/remover destaque, Editar, Alterar categoria, Alterar coleções, Zerar visualizações, Lixeira, Excluir definitivamente (com diálogo de confirmação digitando "EXCLUIR"). Mensagens via snackbar. Após excluir definitivamente → `/admin/artigos` (bug corrigido: redirecionava para uma aba inexistente).
- **Todos os artigos** `/admin/artigos`: tabela com busca, filtros (status, categoria, coleção, autor, destaque), ordenação, ações em lote (publicar, mover para a lixeira, definir coleção) e a aba **Lixeira** (restaurar, excluir definitivamente).
- **Taxonomias** `/admin/categorias`, `/admin/colecoes`, `/admin/tags`: CRUD. A categoria tem um pai, sem permitir ciclos. Não excluir uma categoria com artigos (oferecer "mover os artigos para…"). Excluir uma tag remove o vínculo. Mesclar tags (melhoria).
- **Usuários** `/admin/usuarios`: tabela com avatar, nome, e-mail, papel, nº de publicações, último acesso e data de cadastro; busca; alterar o papel (respeitando a `UserPolicy`); desativar a conta.
- **Painel inicial do admin** (melhoria): cards com o total de artigos por status, pendentes, usuários novos em 30 dias, top 10 mais vistos e últimos envios.
- "Fixar no topo" **não** será implementado: não tinha efeito no legado (bug/feature morta removida).

## 13. Notificações
| Evento | Para | Canais | Texto |
|---|---|---|---|
| `ArticleSubmitted` (pending) | admins | database + mail | "Nova publicação aguardando revisão: {título}" |
| `ArticlePublished` | autor (se não for o próprio admin) | database + mail | "Sua publicação foi aprovada!" + link |
| `ArticleRejected` | autor | database + mail | "Sua publicação precisa de ajustes" + motivo + link para editar |
| Cadastro | usuário | mail | verificação de e-mail |

Sino de notificações no cabeçalho (lista as não lidas e marca como lidas). Os e-mails vão em fila.

## 14. Páginas institucionais
`/quem-somos`, `/apoie`, `/politica-de-privacidade`, `/termos-de-uso`, `/contato` (melhoria: formulário com honeypot + throttle, envia e-mail ao museu).
O conteúdo de "Quem somos" e dos termos vem de `settings` (editável no admin) ou de arquivos Markdown em `resources/content`.
A página Apoie usa os dados de `config('museu.donation')`/settings, com o botão "Copiar chave" e o QR Code.

## 15. Acessibilidade e LGPD
- **VLibras** em todas as páginas (`<script src="https://vlibras.gov.br/app/vlibras-plugin.js">` + `new window.VLibras.Widget('https://vlibras.gov.br/app')`), carregado só no cliente.
- WCAG 2.1 AA: foco visível, *skip link*, `alt` em todas as imagens (o alt da galeria vem da descrição ou do título do artigo), navegação por teclado na galeria e nos carrosséis, `prefers-reduced-motion`.
- Banner de consentimento de cookies; o GA4 só carrega após o aceite.
- Exclusão de conta e exportação dos dados do usuário (JSON) em "Meus dados".

## 16. Bugs do legado → correção obrigatória

| # (doc legado §13) | Bug | Correção nesta versão |
|---|---|---|
| 1 | Último acesso em um campo diferente do lido | `users.last_login_at` via listener de Login |
| 2 | Limite de áudio 5 MB × 50 MB | `config('museu.limits.audio_mb')` único |
| 3 | Capa, título e termo só no front | FormRequest no servidor + `consent_accepted_at` |
| 4 | Remover todas as coleções não funcionava | `sync()` sempre que um admin envia o campo |
| 5 | Redirecionamento para uma aba inexistente | Rotas nomeadas, redirecionamento para `/admin/artigos` |
| 6 | "Fixar no topo" sem efeito | Removido |
| 7 | Pendente visível publicamente | `ArticlePolicy::view` |
| 8 | Nomes de coleção concatenados | Lista com separador / chips |
| 9 | Placeholder externo fora do ar | Imagem local `public/images/placeholder-cover.webp` |
| 10 | Detecção de mídia inconsistente | `withExists`/`withCount` nas relações |
| 11 | Dois formatos de vídeo | Tabela `article_videos` |
| 12 | Metadados renomeados sem migrar | Migrations versionadas + testes de regressão das mídias |
| 13 | Código duplicado | — |
| 14 | Comentários sem UI | Não implementar comentários (fora do escopo) |
| 15 | Sem reset de senha | Fortify reset |
| 16 | Sem verificação de e-mail | `MustVerifyEmail` (exigida para publicar) |
| 17 | Colaborador apagava publicado em definitivo | Lixeira (soft delete); definitivo só pelo admin |
| — | Contador inflado por robôs | §11 |
| — | Contadores parados por campos diferentes | Um único `views_count` |

## 17. Outras melhorias incluídas
- Rascunhos e status "rejeitado" com motivo; notificações.
- Reordenação de mídias por arrastar (dnd-kit); edição de legenda e alt.
- Imagens WebP responsivas (`srcset`), *lazy loading* e *blur placeholder*.
- Busca nas tags; filtro por tipo de mídia; ordenação; filtros combináveis.
- Perfil público do autor `/autores/{id}-{slug}` com bio e as publicações dele.
- `sitemap.xml`, `robots.txt`, Open Graph, JSON-LD, SSR.
- Painel do admin com métricas; ações em lote; lixeira com restauração.
- Formulário de contato; páginas institucionais editáveis.
- Modo escuro respeitando `prefers-color-scheme` (tema MUI com `colorSchemes`).
