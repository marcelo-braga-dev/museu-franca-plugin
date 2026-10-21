# 05 — Front-end: Inertia + React + TypeScript + MUI

## 1. Bootstrap

`resources/js/app.tsx`
- `createInertiaApp` com `resolve` via `import.meta.glob('./Pages/**/*.tsx')`.
- Layout padrão por página (`Page.layout = page => <PublicLayout>{page}</PublicLayout>`).
- Provedores: `ThemeProvider` (com `CssBaseline`), `SnackbarProvider` (notistack), `FavoritesProvider`.
- `title: t => t ? \`${t} · Museu da Pessoa de Franca\` : 'Museu da Pessoa de Franca'`.
- Barra de progresso do Inertia na cor primária.

`resources/js/ssr.tsx`: SSR com `@emotion/server` (`createEmotionCache` + `extractCriticalToChunks`), para que o MUI renderize no servidor. Código que acessa `window`, `localStorage` ou VLibras roda só em `useEffect`.

Tipos: `resources/js/types/index.d.ts` com `PageProps` (auth, flash, counts, notifications, ziggy, app) e os tipos `ArticleCard`, `Article`, `CategoryNode`, `Collection`, `Paginated<T>`. Use `usePage<PageProps>()`.

## 2. Tema (`theme.ts`)

Identidade atual do site:

| Token | Valor |
|---|---|
| `primary.main` | `#9E2B19` (vermelho-tijolo do museu) |
| `primary.contrastText` | `#ffffff` |
| `secondary.main` | `#0f172a` |
| `text.primary` | `#0f172a` · `text.secondary` `#374151` |
| `divider` | `#e5e7eb` |
| `background.default` | `#ffffff` · `background.paper` `#ffffff` (claro) |
| Ícones de mídia | YouTube `#ef4444`, PDF `#b91c1c`, imagem `#16a34a`, áudio `#111827` |

- `createTheme({ cssVariables: true, colorSchemes: { light, dark } })`. No modo escuro: `background.default #0b1120`, `paper #111827`, `primary.main #e0634f` (contraste AA).
- Tipografia: títulos em **"Merriweather"** (serifada, tom de memória/arquivo) e texto em **"Inter"**, via Google Fonts ou `@fontsource`. `h1` clamp(1.8rem, 4vw, 2.6rem).
- `shape.borderRadius: 12`; cards com `variant="outlined"` e hover com elevação leve; botões `disableElevation`, `textTransform: 'none'`, `fontWeight: 600`.
- Breakpoints padrão do MUI. Layout fluido de 360px a 1440px; conteúdo de leitura no máx. 1100px (`Container maxWidth="lg"`).
- Respeitar `prefers-reduced-motion` (desligar as transições do carrossel e do lightbox).

## 3. Layouts

### `PublicLayout`
- **AppBar** (fixa no topo, fundo branco, borda inferior):
  - Logo (link para a home) · menu: **Acervo**, **Coleções**, **O Museu**, **Apoie**, botão de destaque **Publicar história** (vai ao login se for visitante).
  - Campo de busca compacto (desktop), que envia para `/acervo?q=`.
  - Visitante: botões **Entrar** e **Cadastrar**. Logado: sino de notificações (Badge) + avatar com menu (Minha conta, Minhas publicações, Favoritos, Admin (se admin, com o badge de pendentes), Sair).
  - Mobile: `IconButton` de menu → `Drawer` com os mesmos itens.
- *Skip link* "Pular para o conteúdo".
- `<main id="conteudo">`.
- **Footer**: contatos, redes, links institucionais (Quem somos, Apoie, Contato, Política de Privacidade, Termos), "Mantido pelo IPRA".
- `CookieConsent` (Snackbar persistente) e `VLibrasWidget` (client-only).
- `FlashMessages`: lê `flash.success`/`flash.error` e mostra com notistack.

### `AccountLayout` (área logada e admin)
- `Drawer` lateral permanente no desktop e temporário no mobile, com o item ativo destacado.
- Itens: Publicar história · Minhas publicações · Favoritos · Notificações · Meus dados. Seção **Administração** (se admin): Painel · Revisão (badge) · Artigos · Categorias · Coleções · Tags · Usuários · Configurações.
- Conteúdo com `Breadcrumbs` e o título da página.

## 4. Páginas

| Página | Arquivo | Conteúdo / comportamento |
|---|---|---|
| Home | `Pages/Home.tsx` | Hero (título "Cada pessoa é um museu vivo", subtítulo, CTAs Acessar o acervo / Publicar história), `FeaturedGrid`, seções `ArticleCarousel` (03 §9), blocos institucionais (Sobre, IPRA 20 anos, Apoie, Contato), atalhos para as coleções (cards clicáveis) |
| Acervo | `Pages/Acervo/Index.tsx` | Grid de 2 colunas: lateral (`CategoryTree`, `CollectionList`, filtro de mídia, ordenação) + resultados (`SearchBar`, chips dos filtros ativos, contagem "N resultados", grade `ArticleCard`, `Pagination`). Mobile: filtros num `Drawer` (botão "Filtros (n)"). Navegação com `router.get(route('acervo'), filtros, { preserveState: true, preserveScroll: true, replace: true })`; busca com *debounce* de 400 ms |
| Artigo | `Pages/Articles/Show.tsx` | Ordem da 03 §7. `<Head>` com SEO/OG. `ModerationBar` (admin). `StatusBanner` (Alert) quando não publicado, com o motivo da rejeição para o autor. `Gallery`, `VideoList`, `AudioList`, `PdfList`, `ShareBar`, `FavoriteButton variant="pill"`, `ArticleCarousel` "Sugeridos para você", aviso de responsabilidade. Hook `useArticleView(article.id)` |
| Coleções | `Pages/Collections/Index.tsx`, `Show.tsx` | Cards de coleção (capa, nome, descrição, "N artigos") / cabeçalho da coleção + grade paginada |
| Autor | `Pages/Authors/Show.tsx` | Avatar, nome, bio, grade das publicações |
| Institucionais | `Pages/Pages/{About,Donate,Privacy,Terms,Contact}.tsx` | Donate: hero, cards de impacto, `PixCard` (chave + copiar + QR), dados bancários, Transparência, texto IPRA |
| Login | `Pages/Auth/Login.tsx` | Card centralizado: botão **"Continuar com Google"** (destaque), divisor "ou", e-mail/usuário, senha (mostrar/ocultar), lembrar-me, "Esqueci minha senha", link "Criar conta" |
| Cadastro | `Pages/Auth/Register.tsx` | Google + nome, e-mail, senha, confirmação, aceite dos termos (links) |
| Reset / Verificação | `Pages/Auth/{ForgotPassword,ResetPassword,VerifyEmail}.tsx` | Fluxos do Fortify |
| Minhas publicações | `Pages/Account/Articles/Index.tsx` | `Tabs` por status (Todas, Publicadas, Em revisão, Rascunhos, Ajustes solicitados) + grade `ManageArticleCard` |
| Formulário | `Pages/Account/Articles/Form.tsx` | §6 abaixo |
| Favoritos | `Pages/Account/Favorites.tsx` | Grade de `ArticleCard` com busca; estado vazio com ilustração e CTA para o acervo |
| Notificações | `Pages/Account/Notifications.tsx` | Lista com link e "marcar todas como lidas" |
| Meus dados | `Pages/Account/Profile.tsx` | Seções: Perfil (nome, bio, avatar com crop simples), Senha, Contas vinculadas (Google), Privacidade (exportar dados, excluir conta com Dialog de confirmação) |
| Admin Painel | `Pages/Admin/Dashboard.tsx` | Cards de métricas, tabela de pendentes recentes, top mais vistos |
| Revisão | `Pages/Admin/Review.tsx` | `Table` com pré-visualização em Dialog e Publicar/Rejeitar (Dialog com o motivo) |
| Artigos | `Pages/Admin/Articles/Index.tsx` | Tabela com seleção, ações em lote, filtros, aba Lixeira (restaurar / excluir definitivo) |
| Taxonomias | `Pages/Admin/Taxonomies/{Categories,Collections,Tags}.tsx` | Categorias: árvore com pai (Autocomplete), reordenar; Coleções: nome, slug, descrição, capa, visível; Tags: lista, renomear, mesclar |
| Usuários | `Pages/Admin/Users/Index.tsx` | Tabela (avatar, nome, e-mail, papel com Select, publicações, último acesso, cadastro) com busca e paginação no servidor |
| Configurações | `Pages/Admin/Settings.tsx` | Dados de doação, textos institucionais, seções da home |
| Erros | `Pages/Errors/{404,403,500}.tsx` | Mensagem amigável + busca + voltar para a home |

## 5. Componentes

| Componente | Descrição |
|---|---|
| `ArticleCard` | Card MUI: capa 16:10 (`<img srcSet>` thumb/medium, lazy, fallback local), overlay com `FavoriteButton variant="icon"` (canto superior direito) e `MediaBadges` (canto inferior esquerdo), título (2 linhas com *ellipsis* e tooltip), chips de categoria `Pai › Filha`, "Por {autor}". O card inteiro é clicável (Inertia `Link`), com foco acessível |
| `ManageArticleCard` | `ArticleCard` + `StatusChip` + ações Ver / Editar / Lixeira (Dialog de confirmação) |
| `MediaBadges` | Ícones com Tooltip: `YouTube`, `PictureAsPdf`, `Collections`, `Headphones` |
| `FeaturedGrid` | Grade de destaques: 1 card grande + 2-5 menores (desktop), lista no mobile |
| `ArticleCarousel` | Embla: título da seção + link "Ver todos" (para o acervo filtrado), setas (desabilitadas nos limites), rolagem por arraste e por teclado (←/→), `aria-roledescription="carousel"`; slides de 260 a 300 px |
| `FavoriteButton` | `variant: icon \| pill`. Visitante → `Link` para o login com `?redirect=`. Logado → `axios.post(route('favorites.toggle'))` com atualização otimista; o estado vem do `FavoritesProvider` (`Set<number>`), sincronizando todos os botões do mesmo artigo; erro → reverte + snackbar |
| `CategoryTree` | `List` aninhada (Collapse) com contadores (`Chip size="small"`), item ativo, "Todas as categorias (N)"; oculta as vazias |
| `CollectionList` | `List` com contadores e o item ativo |
| `SearchBar` | `TextField` com ícone, limpar, submit no Enter e debounce no acervo |
| `ActiveFilters` | Chips removíveis + "Limpar filtros" |
| `Gallery` | Grade responsiva (masonry ou `ImageList`) com legenda; clique ou Enter abre o `Lightbox` (yet-another-react-lightbox com zoom, legenda, contador e download; teclado ←/→/Esc) |
| `VideoList` | Cards com iframe `youtube-nocookie` *lazy* (fachada: thumbnail `i.ytimg.com/vi/{id}/hqdefault.jpg` + botão play; o iframe só carrega no clique — performance) + descrição |
| `AudioList` | Cards com Plyr, título, tamanho, descrição e baixar |
| `PdfList` | Lista com ícone, nome, tamanho, descrição, botões "Ver" (nova aba) e "Baixar" |
| `ShareBar` | WhatsApp, Telegram, Facebook, X, e-mail, copiar link (snackbar "Link copiado!"), `navigator.share` no mobile |
| `ModerationBar` | Accordion "Painel de gestão" (estado lembrado no `localStorage` por artigo, em try/catch): chips (status, autor, views, destaque) + ações da 03 §12; cada ação com `router.post(..., { preserveScroll: true })` e Dialog de confirmação nas destrutivas |
| `StatusChip` | Cores: publicado = success, em revisão = warning, rascunho = default, ajustes solicitados = error |
| `RichTextEditor` | `mui-tiptap` (`RichTextEditor` + `MenuControlsContainer`): títulos H2/H3, negrito, itálico, sublinhado, link, citação, listas, desfazer/refazer; contador de caracteres; o valor é HTML |
| `FileDropZone` | Área de arrastar/clicar (`Paper` com borda tracejada), aceita `accept`, valida o tamanho no cliente com mensagem, mostra a pré-visualização |
| `SortableMediaList` | dnd-kit: itens (miniatura ou ícone, nome, campo de descrição, botão remover), arrastáveis para reordenar; usado em galeria, PDFs e áudios |
| `VideoFieldList` | Lista de campos (URL + descrição) com a pré-visualização da miniatura ao detectar um ID válido e reordenação; valida com o mesmo extrator do back-end (`lib/youtube.ts`) |
| `UploadProgressDialog` | Dialog modal com `LinearProgress` determinado + % ("Enviando sua publicação…"), sem fechar durante o envio |
| `ConfirmDialog` | Diálogo genérico; variante "digite EXCLUIR para confirmar" |
| `PixCard` | Chave + botão copiar + QR |
| `VLibrasWidget` | Injeta o script e a marcação do VLibras no `useEffect` (uma única vez) |
| `CookieConsent` | Aceitar/recusar; carrega o GA4 após o aceite |
| `SeoHead` | `<Head>` com title, description, canonical, OG, Twitter e JSON-LD (props `seo` do servidor) |
| `EmptyState` | Ícone + texto + ação |

Hooks: `useArticleView(id)`, `useFavorites()`, `useDebounce`, `useLocalStorage` (seguro, com try/catch), `useConfirm`.

## 6. Formulário de publicação (`Account/Articles/Form.tsx`)

Usa `useForm` do Inertia, com envio em `forceFormData`. Layout em seções (`Card`s) com um `Stepper` lateral fixo no desktop (Informações · Capa e classificação · Mídias · Revisão e envio):

1. **Informações:** Título (contador `n/100`), Resumo (multiline, `n/250`), Conteúdo (`RichTextEditor`).
2. **Capa e classificação:** `FileDropZone` da capa (obrigatória, com pré-visualização e "trocar"), Categoria (`Autocomplete` com a árvore indentada, obrigatória), Palavras-chave (`Autocomplete freeSolo multiple` com sugestões das tags existentes, chips), Coleções (só admin: `Autocomplete multiple`).
3. **Mídias:** abas ou acordeões Galeria / PDFs / Áudios / Vídeos. Cada um com `FileDropZone multiple` + `SortableMediaList`. Os limites são exibidos ("Áudio: mp3, m4a, wav, ogg, aac, opus, flac — máx. 50 MB").
4. **Revisão e envio:** resumo do que será enviado, checkbox do **Termo de Autorização para Uso de Imagem, Áudio e Conteúdos** (texto legado: "Autorizo a utilização da minha imagem, voz, fotografias, vídeos e materiais fornecidos no âmbito da Plataforma Museu da Pessoa Franca."), e os botões **Salvar rascunho** (`intent=draft`) e **Enviar para revisão** (colaborador) / **Publicar** (admin).
   - Na edição de um publicado por colaborador, um `Alert` informa: "Ao salvar, sua publicação voltará para revisão."

Comportamento:
- Erros do servidor aparecem em cada campo (`helperText`) e num `Alert` no topo com links que rolam até cada campo.
- Ao trocar de página com alterações não salvas → confirmação (`router.on('before')`).
- Rascunho local automático (localStorage, só os campos de texto) a cada 5 s, com a opção "Restaurar rascunho".
- Progresso de upload no `UploadProgressDialog` (`onProgress`).
- Sucesso → redireciona com a snackbar "Publicação enviada para revisão!" / "Publicação atualizada!".

## 7. Acessibilidade (checklist por componente)
- Todo `IconButton` com `aria-label`; imagens com `alt` (a capa usa o título; a galeria, a descrição ou "Imagem N de {título}").
- Ordem de foco lógica; o lightbox e os Dialogs prendem o foco (MUI já faz) e devolvem o foco ao fechar.
- Contraste AA nos chips e badges sobre as imagens (fundo semitransparente escuro).
- Carrossel operável por teclado; não usar autoplay.
- Formulários: `label` visível, `required` sinalizado, erros com `aria-describedby`.
- Testar com teclado + leitor de tela (NVDA/VoiceOver) nas páginas Home, Acervo, Artigo e Formulário.

## 8. Performance
- Code splitting por página (glob dinâmico).
- Imagens responsivas (`srcSet` com as conversões WebP) e `loading="lazy"`; a capa do artigo com `fetchpriority="high"`.
- Fachada do YouTube (o iframe só carrega no clique).
- Plyr, o editor e o lightbox carregados sob demanda (`React.lazy`).
- Props parciais do Inertia (`only: ['articles']`) ao filtrar o acervo.
- Meta: LCP < 2,5 s no 4G, CLS < 0,1 (aspect-ratio fixo nas capas).
