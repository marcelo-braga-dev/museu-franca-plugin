# 07 — Plano de implementação (fases)

Execute **uma fase por vez**. Ao final de cada fase: rode a "definição de pronto" do `CLAUDE.md`,
liste os arquivos criados e alterados e marque o checklist de aceite. Não avance sem aprovação.

---

## Fase 0 — Fundação
**Tarefas**
- Criar o projeto Laravel e instalar os pacotes (01 §3); configurar o TypeScript, ESLint, Prettier, Pint, Larastan e Pest.
- Inertia + React + SSR com Emotion; Ziggy; `theme.ts` (05 §2); `PublicLayout` e `AccountLayout` com dados fictícios.
- `config/museu.php`, locale pt_BR (`lang/pt_BR` com validation.php + attributes), timezone de exibição.
- Página `Errors/404`; `FlashMessages` (notistack); `VLibrasWidget`; `CookieConsent`.
- CI (GitHub Actions): pest, phpstan, pint --test, lint, typecheck e build.

**Aceite**
- [ ] `npm run build && npm run build:ssr` sem erros; a página inicial renderiza via SSR (ver o HTML no código-fonte).
- [ ] O layout funciona a 375px e a 1440px; o VLibras aparece; o skip link funciona.
- [ ] O pipeline de CI fica verde.

## Fase 1 — Banco de dados e domínio
**Tarefas**
- Migrations, enums, models, relações, scopes e casts (02).
- Factories e seeders (Admin, Taxonomy, Demo).
- Medialibrary: coleções e conversões WebP em fila.
- Policies `ArticlePolicy` e `UserPolicy` + middleware `admin`.
- Services `YoutubeId`, `TaxonomyCounts`, `RelatedArticlesService` + observers de cache.

**Aceite**
- [ ] `migrate:fresh --seed` cria o admin, as taxonomias e 30 artigos demo com mídias.
- [ ] Testes unitários: `YoutubeId` (todos os formatos da 03 §5), `RelatedArticlesService` (03 §8), contagem de categorias com descendentes.
- [ ] Testes das policies cobrindo cada linha da 03 §2.

## Fase 2 — Autenticação e conta
**Tarefas**
- Fortify com as páginas Inertia: login (e-mail ou username), cadastro, reset de senha, verificação de e-mail.
- `WordpressHasher` + `authenticateUsing` com rehash (06 §5).
- Socialite Google: redirect/callback, vínculo por e-mail, criação de conta (03 §3).
- Listener `last_login_at`.
- "Meus dados": perfil, senha, contas vinculadas, exportar e excluir conta.
- Redirecionamento de logado em `/login` e `/cadastro`.

**Aceite**
- [ ] Os fluxos de cadastro → e-mail de verificação → login funcionam (Mailpit/log).
- [ ] O login com Google (testado com o Socialite mockado) vincula pelo e-mail e cria contas.
- [ ] O login com hash `$wp$2y$` funciona e faz o rehash para bcrypt.
- [ ] O throttle de login fica ativo.

## Fase 3 — Área pública
**Tarefas**
- Home (seções da 03 §9) com `FeaturedGrid` e `ArticleCarousel`.
- Acervo: busca FULLTEXT + tags, filtros combináveis (categoria com descendentes, coleção, mídia), ordenação, paginação, chips, drawer mobile (03 §6, 05 §4).
- Página do artigo completa (03 §7): galeria + lightbox, vídeos (fachada), áudios, PDFs, compartilhar, relacionados, SEO/OG/JSON-LD.
- Coleções (índice e detalhe), perfil do autor, páginas institucionais (Apoie com PIX), Contato.
- Sitemap e robots.

**Aceite**
- [ ] Pendente ou rascunho retorna 404 para visitantes e terceiros, e é visível para o autor e o admin.
- [ ] Filtro por categoria inclui as subcategorias; categoria + coleção combinadas; a paginação preserva os filtros.
- [ ] O preview do link no WhatsApp/Facebook mostra título, resumo e capa (validar com o debugger do Facebook / OG).
- [ ] Lighthouse mobile: Performance ≥ 85, Acessibilidade ≥ 95, SEO ≥ 95 na Home, no Acervo e no Artigo.
- [ ] A galeria funciona por teclado (Enter abre, setas navegam, Esc fecha) e com 143 imagens sem travar.

## Fase 4 — Publicação (colaborador)
**Tarefas**
- Formulário completo (05 §6) com `RichTextEditor`, `FileDropZone`, `SortableMediaList`, `VideoFieldList`, upload com progresso.
- `StoreArticleRequest` / `UpdateArticleRequest` (03 §5) e `SaveArticleAction`.
- Fluxo editorial: rascunho, envio, volta para revisão ao editar um publicado (03 §4).
- Minhas publicações (abas por status), lixeira (soft delete) pelo autor.
- Favoritos: botão, provider, página "Favoritos".
- Contador de visualizações (03 §11) + `useArticleView`.
- Notificações (database + e-mail) e o sino no cabeçalho.

**Aceite**
- [ ] O servidor rejeita um envio sem capa, sem termo, com vídeo inválido ou com um áudio acima do limite (testes).
- [ ] Um colaborador cria → `pending`; os admins recebem a notificação; um admin cria → `published`.
- [ ] Editar um publicado como colaborador → `pending`, com o aviso exibido antes de salvar.
- [ ] Reordenar e remover mídias e editar as legendas persiste corretamente.
- [ ] Favoritar sincroniza todos os botões do artigo na página; o visitante é levado ao login.
- [ ] Views: robô, admin e repetição em 24h não contam (testes).

## Fase 5 — Administração
**Tarefas**
- Painel com métricas; Revisão (publicar, rejeitar com motivo); Artigos (tabela, filtros, lote, lixeira, restaurar, excluir definitivo).
- `ModerationBar` na página do artigo.
- CRUD de categorias (árvore, sem ciclos, mover os artigos ao excluir), coleções (capa, visível) e tags (mesclar).
- Usuários (papel, desativar, com proteção do último admin).
- Configurações (doação, textos institucionais, seções da home).

**Aceite**
- [ ] Rejeitar exige um motivo; o autor vê o motivo e consegue reenviar.
- [ ] Remover todas as coleções de um artigo funciona (teste de regressão do bug legado #4).
- [ ] A exclusão definitiva apaga os arquivos do disco (teste com `Storage::fake`).
- [ ] Um não admin recebe 403 em todas as rotas `/admin` (teste).

## Fase 6 — Importação do legado
**Tarefas**
- Comando `museu:import-wordpress` completo (06), com `--dry-run`, `--only`, `--verify` e `--confirm-admins`.
- Middleware `LegacyRedirects` + tabela `redirects`.

**Aceite**
- [ ] `--verify` sem divergências contra o banco WP (66 artigos, 38 vídeos, ~527 arquivos, 5 coleções, 134 tags, 5 vínculos Google).
- [ ] O artigo "Fichas do DOPS" (legado ID 684) exibe as 143 imagens com as legendas, na ordem original.
- [ ] `/?p=684`, `/minha-conta/?aba=publicar` e `/colecao/dops/` redirecionam corretamente (301).
- [ ] Um usuário importado consegue entrar com a senha antiga.

## Fase 7 — Produção
**Tarefas**
- Deploy (supervisor para a fila e o SSR, agendador), cabeçalhos de segurança/CSP, backups, monitoramento de erros (Sentry opcional), GA4 após o consentimento.
- Roteiro de virada (06 §11).

**Aceite**
- [ ] Ensaio completo em homologação aprovado pelo responsável do museu.
- [ ] O monitoramento de 404 fica ativo por 30 dias após a virada.
