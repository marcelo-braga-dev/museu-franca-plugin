# Museu da Pessoa de Franca — Documentação Funcional

> Objetivo: descrever **o que o sistema faz** (regras de negócio, fluxos, dados e telas),
> independente do WordPress, para servir de base à migração para **Laravel + Inertia**.
> Levantamento feito em 21/10/2026 a partir do código do plugin `museu-franca` (v2.0.0),
> das páginas montadas no construtor visual e do banco de dados em produção.

---

## Sumário

1. [Visão geral](#1-visão-geral)
2. [Perfis de usuário e permissões](#2-perfis-de-usuário-e-permissões)
3. [Modelo de dados](#3-modelo-de-dados)
4. [Mapa de páginas e rotas](#4-mapa-de-páginas-e-rotas)
5. [Funcionalidades públicas](#5-funcionalidades-públicas)
6. [Autenticação e conta](#6-autenticação-e-conta)
7. [Painel do usuário (Minha Conta)](#7-painel-do-usuário-minha-conta)
8. [Publicação e edição de artigos](#8-publicação-e-edição-de-artigos)
9. [Moderação e gestão (administradores)](#9-moderação-e-gestão-administradores)
10. [Componentes reutilizáveis](#10-componentes-reutilizáveis)
11. [Contador de visualizações](#11-contador-de-visualizações)
12. [Integrações externas](#12-integrações-externas)
13. [Bugs e inconsistências atuais (não replicar)](#13-bugs-e-inconsistências-atuais-não-replicar)
14. [Migração de dados](#14-migração-de-dados)
15. [Sugestão de arquitetura Laravel + Inertia](#15-sugestão-de-arquitetura-laravel--inertia)

---

## 1. Visão geral

Plataforma de **acervo digital colaborativo** de memórias, histórias, documentos, fotos, vídeos e áudios
da comunidade de Franca/SP, mantida pelo **Instituto Práxis de Educação e Cultura (IPRA)**.

- Visitantes navegam, pesquisam e filtram o acervo.
- Usuários cadastrados **publicam conteúdos** ("artigos"), que vão para **revisão** antes de serem publicados.
- Usuários cadastrados podem **favoritar** artigos.
- Administradores **moderam** (publicam, editam, excluem), marcam **destaques**, organizam **coleções** e **categorias**, e veem a **lista de usuários**.

Idioma: pt-BR · Fuso: `America/Sao_Paulo` · Formato de data exibido: `21 de outubro de 2026`.

### Volume atual (produção)

| Item | Quantidade |
|---|---|
| Artigos publicados | 66 (nenhum pendente no momento) |
| Usuários | 14 (5 administradores, 9 assinantes) |
| Arquivos de mídia | 527 (513 imagens, 5 PDFs, 0 áudios) |
| Vídeos do YouTube vinculados | 38 |
| Categorias | 14 (todas de nível raiz hoje, mas o modelo é hierárquico) |
| Coleções | 5 |
| Palavras-chave (tags) | 134 |
| Comentários | 0 (sem UI de comentários) |

---

## 2. Perfis de usuário e permissões

Existem, na prática, **3 perfis**:

| Perfil | Origem no WP | Descrição |
|---|---|---|
| **Visitante** | não autenticado | Navega no acervo |
| **Colaborador** | `subscriber` | Usuário cadastrado comum |
| **Administrador** | `administrator` | Gestão completa |

> O código também verifica `editor` / `edit_others_posts` / `publish_posts` em alguns pontos, mas
> nenhum usuário tem esses papéis. Na migração basta `role = admin | user`.

### Matriz de permissões

| Ação | Visitante | Colaborador | Admin |
|---|:-:|:-:|:-:|
| Ver home, acervo, coleções e artigos publicados | ✅ | ✅ | ✅ |
| Pesquisar / filtrar acervo | ✅ | ✅ | ✅ |
| Compartilhar artigo (WhatsApp, Telegram, copiar link) | ✅ | ✅ | ✅ |
| Favoritar artigo | ❌ (vai para o login) | ✅ | ✅ |
| Cadastrar-se / entrar com Google | ✅ | — | — |
| Publicar artigo | ❌ | ✅ → fica **pendente** | ✅ → **publicado direto** |
| Editar / excluir **os próprios** artigos | ❌ | ✅ | ✅ |
| Ver os próprios artigos pendentes | ❌ | ✅ | ✅ |
| Escolher coleções ao publicar/editar | ❌ | ❌ | ✅ |
| Editar / excluir artigos de outros | ❌ | ❌ | ✅ |
| Fila de revisão / publicar pendentes | ❌ | ❌ | ✅ |
| Painel de gestão dentro do artigo | ❌ | ❌ | ✅ |
| Lista "Todas as publicações" | ❌ | ❌ | ✅ |
| Lista de usuários | ❌ | ❌ | ✅ |
| Gerenciar categorias e coleções (CRUD) | ❌ | ❌ | ✅ (hoje pelo admin do WP) |

---

## 3. Modelo de dados

### 3.1 Artigo (entidade central)

| Campo | Tipo | Regras |
|---|---|---|
| `id` | int | |
| `title` | string(100) | Obrigatório. Limite de 100 caracteres no formulário, com contador |
| `slug` | string | Gerado do título, único. URL pública: `/artigos/{slug}/` |
| `summary` | string(250) | Opcional ("Resumo"). Máximo de 250 caracteres |
| `content` | HTML | Opcional. Texto rico (editor Quill), sanitizado no servidor |
| `status` | enum | `pending` (revisão) · `published` · (lixeira/excluído) |
| `author_id` | FK users | Quem publicou |
| `cover_image` | FK media | **Obrigatória** ("Foto de capa") |
| `category_id` | FK categories | **Obrigatória**. Uma única categoria por artigo (regra da UI) |
| `is_featured` | bool | "Destaque" (marcado por admin) |
| `views_count` | int | Ver [§11](#11-contador-de-visualizações) |
| `published_at` / `created_at` / `updated_at` | datetime | Data exibida: data de publicação |

Relações N:N:
- **Coleções** (`collections`): 0..N. Só administradores atribuem.
- **Palavras-chave** (`tags`): 0..N. Digitadas separadas por vírgula; tags novas são criadas automaticamente.

Relações 1:N (anexos do artigo, **ordenados**):
- **Imagens da galeria**: arquivo + **descrição/legenda** opcional.
- **PDFs**: arquivo + descrição opcional.
- **Áudios**: arquivo + descrição opcional.
- **Vídeos do YouTube**: URL + `youtube_id` extraído + descrição opcional.

> Hoje a descrição de cada anexo é guardada no artigo com a chave `{tipo}_descricao_{idAnexo}`.
> No Laravel ela deve ser uma coluna da própria tabela de mídia.

### 3.2 Categoria (hierárquica)

`id, name, slug, parent_id, description`. Exibida como caminho `Pai > Filha`. Ao filtrar por uma
categoria, os artigos das **subcategorias também entram**.

Categorias atuais: Cultura e Artes · Cultura HipHop · Diversidade e Identidades · DOPS · Educação ·
Esporte e Lazer · Movimento Negro · Movimentos Sindicais e Trabalhadores · Movimentos Sociais ·
Personalidades e Protagonistas · Política e Lideranças · Religião e Espiritualidade · Trabalho e
Profissões · Sem categoria.

### 3.3 Coleção (plana)

`id, name, slug, description`. Agrupamento curatorial, independente da categoria.
Atuais: **DOPS**, **Memórias da Resistência**, **Movimento Operário e Sindical**, **Movimento Negro**, **Laboratório das Artes**.

### 3.4 Palavra-chave (tag)

`id, name, slug`. Exibida como `#palavra`.

### 3.5 Usuário

| Campo | Observação |
|---|---|
| `username` | Único (cadastro manual) |
| `name` (display name) | Exibido como autor |
| `email` | Único |
| `password` | Mínimo de 6 caracteres |
| `role` | `admin` / `user` |
| `avatar` | Hoje vem do Gravatar (pelo e-mail) ou da foto do Google |
| `last_login_at` | Atualizado a cada login |
| `google_id` | Conta Google vinculada (login social) |

### 3.6 Favorito

Par `(user_id, article_id)`. Hoje fica num array no usuário; no Laravel deve ser uma **tabela pivô**.
Só artigos **publicados** contam como favoritos válidos.

### 3.7 Mídia (arquivos)

Arquivo enviado + `mime`, `size`, `original_name`, `title`, `alt`. Imagens precisam de versões
redimensionadas: a capa usa *medium* nos cards e *large* no artigo; a galeria usa *large* na grade e
*full* para ampliar e baixar.

### Diagrama (proposto)

```
users ─┬─< articles >── categories (parent_id → categories)
       │      │ ├──< article_media (type: image|pdf|audio, description, position)
       │      │ ├──< article_videos (url, youtube_id, description, position)
       │      │ ├──< article_collection >── collections
       │      │ └──< article_tag >── tags
       └──< favorites >── articles
article_views (article_id, ip_hash, viewed_at)   ← deduplicação do contador
social_accounts (user_id, provider, provider_id) ← login Google
```

---

## 4. Mapa de páginas e rotas

| URL atual | Página | Acesso | Rota Laravel sugerida | Página Inertia |
|---|---|---|---|---|
| `/` | Home | público | `GET /` | `Home` |
| `/acervo/` | Acervo (busca + filtros) | público | `GET /acervo` | `Acervo/Index` |
| `/artigos/{slug}/` | Artigo | público (pendente: só autor/admin) | `GET /artigos/{slug}` | `Artigos/Show` |
| `/colecao/{slug}/` | Arquivo da coleção | público | `GET /colecoes/{slug}` (ou redirecionar para `/acervo?colecao=`) | `Colecoes/Show` |
| `/quem-somos/` | Institucional (estático) | público | `GET /quem-somos` | `Paginas/QuemSomos` |
| `/apoie/` | Doações | público | `GET /apoie` | `Paginas/Apoie` |
| `/politica-de-privacidade/`, `/termos-de-uso/` | Estáticas | público | idem | `Paginas/*` |
| `/minha-conta/login/` | Login | visitante | `GET/POST /login` | `Auth/Login` |
| `/minha-conta/cadastro/` | Cadastro | visitante | `GET/POST /cadastro` | `Auth/Register` |
| `/minha-conta/?aba=…` | Painel do usuário | logado | `/minha-conta/{aba}` | `Conta/*` |
| `/publicacoes/artigo/?id=N` | Visualização antiga por ID | público | redirecionar 301 → `/artigos/{slug}` | — |
| `/?p=N` | Link curto do WP | público | redirecionar 301 → `/artigos/{slug}` | — |

**SEO:** manter exatamente os mesmos slugs de artigos (ex.: `/artigos/fichas-do-dops-delegacia-de-ordem-social-4/`), com e sem barra final.

### Layout global
- **Cabeçalho:** logo, menu (Acervo · O Museu · Apoie · Publicar História → `/minha-conta/publicar`) e
  menu de conta: logado → "Minha Conta | Sair"; visitante → "Entrar | Cadastrar".
- **Rodapé:** lista de contatos/ícones e imagem institucional.
- **VLibras** (tradutor de Libras do Governo Federal) em **todas** as páginas — ver [§12](#12-integrações-externas).
- Ícones: Font Awesome 6.

---

## 5. Funcionalidades públicas

### 5.1 Home

Seções, em ordem:
1. Hero com os botões "Acesse o Acervo" e "Publicar História".
2. **Destaques** — até 6 artigos com `is_featured = true` ([§10.3](#103-grade-de-destaques)).
3. Frase "Cada pessoa é um museu vivo. Descubra, compartilhe e preserve memórias."
4. **Mais acessados** — carrossel, ordenado por `views_count` desc.
5. **Publicações recentes** — carrossel, ordenado por data desc.
6. Bloco **Memórias da Resistência** (texto + link).
7. "Acesse alguns desses conteúdos organizados por coleções" — botões para as 5 coleções.
8. Carrosséis por coleção: **DOPS**, **Memórias da Resistência**, **Movimento Operário e Sindical** (8 itens cada).
9. "Nosso Acervo" (CTA), "Sobre o Museu", "20 anos do IPRA" (link para o IPRA).
10. Carrosséis por categoria: **Arte e Cultura** (`cultura-artes`) e **Movimento Negro** (`movimento-negro`).
11. "Apoie o Museu" (CTA para `/apoie`) e "Entre em contato".

Os carrosséis são instâncias do componente [§10.2](#102-carrossel-de-artigos-widgets_artigos).

### 5.2 Acervo (`/acervo`)

Layout: **barra lateral** com o menu de Categorias e o menu de Coleções, e **grade de artigos** com busca.

**Parâmetros de URL (query string):**

| Param | Efeito |
|---|---|
| `q` | Busca textual (título/conteúdo/resumo); ordena por relevância |
| `colecao` | slug (ou id) da coleção |
| `categoria` | slug (ou id) da categoria |
| `cat` | id da categoria (forma alternativa) |
| `page` | Paginação |

**Regras de filtro:**
- **Coleção e categoria são mutuamente exclusivas.** Se houver `colecao`, a categoria é ignorada.
- Filtro por categoria **inclui as subcategorias**.
- A busca `q` combina com o filtro ativo.
- Um selo mostra o filtro ativo: "Coleção: X" ou "Categoria: Y".
- Paginação de **12 por página**, ordenada por data desc; os links preservam `q` e o filtro ativo.
- Sem resultados: "Nenhum artigo encontrado."

**Card do artigo na grade:**
- Capa (link para o artigo), com placeholder quando não houver capa.
- Botão de **favoritar** (ícone de coração) sobre a capa.
- **Selos de mídia** sobre a capa: 🎬 YouTube · 📄 PDF · 🖼️ Galeria.
  - YouTube: tem vídeo vinculado **ou** o conteúdo contém um link `youtube.com`/`youtu.be`.
  - PDF: tem PDF vinculado **ou** um arquivo PDF anexado **ou** o conteúdo contém um link `.pdf`.
  - Galeria: tem pelo menos uma imagem de galeria.
- Título (truncado, com o título completo no tooltip).
- Categorias como chips `Pai > Filha`.
- "Por: {autor}".

**Menu de Categorias (lateral):**
- Item "Todas as Categorias" com o total de artigos publicados.
- Árvore hierárquica por nome, **contador por categoria** (inclui as subcategorias; conta só publicados).
- Esconde as categorias vazias (configuração usada no acervo).
- Destaca a categoria atual e as ancestrais.
- Os links preservam `q`; clicar numa categoria troca o filtro.
- O contador fica em cache por 10 min e é invalidado ao salvar artigo ou alterar categoria.

**Menu de Coleções (lateral):**
- Lista por nome, com contador (publicados) e destaque da coleção atual.
- O link de uma coleção **remove** o filtro de categoria e preserva `q`.
- O contador fica em cache por 10 min, invalidado ao salvar artigo ou alterar coleção.

### 5.3 Página do artigo (`/artigos/{slug}`)

Ordem dos blocos:
1. Se estiver pendente: selo "Pendente de revisão". Para o admin, também o botão **"Publicar agora"** ([§9.2](#92-publicar-agora)).
2. Para o admin: **Painel de gestão** recolhível ([§9.3](#93-painel-de-gestão-no-artigo)).
3. **Título**.
4. "Por: {autor}, {data de publicação}" e o botão **Favoritar** (variante com texto).
5. **Compartilhar:** WhatsApp (`api.whatsapp.com/send?text=título url`), Telegram (`t.me/share/url`) e **Copiar link** (com aviso "Link copiado!").
6. **Capa** em tamanho grande; clicar abre o lightbox com a imagem original.
7. **Resumo**, com o rótulo "Resumo" se existir.
8. Linha de metadados: **Categoria**, **Coleção** e **Palavras-chave** (`#tag1, #tag2`).
9. **Conteúdo** (HTML).
10. **Galeria de imagens** — grade com legenda e botões "Ver" e "Baixar".
11. **Vídeos** — grade de players do YouTube embutidos, com descrição.
12. **Áudios** — lista com título, tamanho, mime, descrição, botão "Baixar" e player (Plyr).
13. **Anexos PDF** — lista com nome, tamanho, descrição e os botões "Ver" (nova aba) e "Baixar".
14. **"Sugeridos para você!"** — carrossel de relacionados por categoria ([§10.2](#102-carrossel-de-artigos-widgets_artigos), modo `related_category`).
15. **Aviso de responsabilidade:** "Os conteúdos disponibilizados neste site são de uso exclusivo para fins informativos e pessoais."

**Lightbox da galeria:**
- Abre ao clicar ou dar Enter numa imagem; navega entre as imagens com as setas da tela e do teclado; Esc fecha.
- Contador "N / total", legenda, zoom (+/− de 0,2 a 4×) e download.
- Clicar fora da imagem fecha.

**Regras:**
- Cada seção de mídia só aparece se tiver itens.
- O vídeo aceita os formatos `youtu.be/ID`, `youtube.com/watch?v=ID`, `/embed/ID`, `/shorts/ID` e `/v/ID`.
- Artigo pendente: hoje fica acessível pela URL. **Recomendação:** permitir apenas ao autor e aos admins.

### 5.4 Coleções

- **Lista de coleções** (componente disponível, não usado numa página hoje): cards com nome, contador "N artigo(s)", descrição e "Ver artigos →"; opções de colunas e de contagem mínima.
- **Artigos de uma coleção:** cabeçalho "Coleção: {nome}" e a descrição, grade de cards (capa ou "Sem capa", título, coleções), paginação de 9 por página via `?pg=N`.

### 5.5 Apoie (`/apoie`)

Página de doação com dados **configuráveis** (hoje vêm de parâmetros; no Laravel, use config/settings):

| Campo | Valor atual |
|---|---|
| Chave PIX | `museudapessoadefranca@gmail.com` (botão **Copiar chave**) |
| QR Code PIX | imagem `/uploads/2025/09/pix_musseupessoa.png` |
| Banco / Agência / Conta | Banco do Brasil / 0053-1 / 103.585-1 |
| CNPJ | 07.464.521-0001/49 |
| E-mail para comprovante | `museudapessoadefranca@gmail.com` |

Conteúdo: hero ("Contribua para o desenvolvimento de um museu cada vez mais acessível, diverso e
plural"), cards de impacto (+ Inclusão, + Memória, + Educação), "Por que seu apoio é importante?",
bloco de **Transparência** (infraestrutura, preservação, acessibilidade) e texto institucional sobre o IPRA.

---

## 6. Autenticação e conta

### 6.1 Cadastro (`/minha-conta/cadastro`)
- Campos: **Nome de usuário**, **E-mail** e **Senha** (mín. 6).
- Validações: usuário ou e-mail já existentes → "Usuário ou e-mail já está cadastrado."; senha curta → "A senha deve ter no mínimo 6 caracteres."
- Cria o usuário com `role = user` e redireciona para o **login** (não loga automaticamente).
- Logado acessando a página → redireciona para `/minha-conta`.
- Sem confirmação de e-mail.

### 6.2 Login (`/minha-conta/login`)
- Formulário usuário/e-mail + senha. **Sem** "esqueci minha senha" e **sem** link de cadastro no formulário.
- Após o login → `/minha-conta`.
- **Login com Google** (botão "Acesse com Google"):
  - Se a conta Google já está vinculada → entra.
  - Senão, se existe um usuário com o **mesmo e-mail** → vincula automaticamente e entra.
  - Senão → **cria a conta** (`role = user`) com nome, e-mail e avatar do Google. Isso depende do cadastro estar aberto.
  - Após o login → `/minha-conta`.
- Logado acessando a página → redireciona para `/minha-conta`.
- Cada login grava `last_login_at`.

> Laravel: Socialite (Google), com a URL de retorno `https://museudapessoadefranca.org.br/...`.
> Para reaproveitar as 5 contas já vinculadas, migre os `google_id` existentes ([§14](#14-migração-de-dados)).

### 6.3 Logout
"Sair" no cabeçalho → encerra a sessão e volta para a home.

### 6.4 Barra de administração
Usuários não administradores nunca veem a barra/admin do framework.

---

## 7. Painel do usuário (Minha Conta)

URL: `/minha-conta?aba={aba}`. Exige login; um visitante é redirecionado para o login.
Layout com **menu lateral** (recolhível no mobile, com botão "Fechar menu"; Esc fecha) e área de conteúdo.

| Aba (`aba=`) | Rótulo no menu | Quem vê | Conteúdo |
|---|---|---|---|
| `publicar` *(padrão)* | Publicar História | todos | Formulário de novo artigo ([§8.1](#81-publicar-novo-artigo)) |
| `revisao` | Publicações Para Revisão | admin | Cards dos artigos **pendentes** |
| `todos_artigos` | Todas Publicações | admin | Cards de **todos os publicados** |
| `usuarios` | Usuários | admin | Lista de usuários ([§9.5](#95-lista-de-usuários)) |
| `favoritos` | Favoritos | todos | Cards dos favoritos do usuário (sem editar/excluir) |
| `historico` | Minhas Publicações | todos | Cards dos artigos do usuário (publicados e pendentes) |
| `meus-dados` | Meus Dados | todos | Mostra nome e e-mail (somente leitura) |
| `editar` | (sem item no menu) | autor/admin | Formulário de edição ([§8.2](#82-editar-artigo)) — `&post_id=N` |

As listas usam o componente **Card de gestão** ([§10.1](#101-card-de-gestão-de-artigos)).
Favoritos vazios: "Você ainda não favoritou nenhum conteúdo."

---

## 8. Publicação e edição de artigos

### 8.1 Publicar novo artigo

Título da tela: **"Publique sua História, Documentos, Fotos ou Vídeos"**.

| Campo | Obrigatório | Regras |
|---|:-:|---|
| Título da Publicação | ✅ | máx. 100, contador ao vivo |
| Resumo | | máx. 250, contador ao vivo |
| Conteúdo | | Editor rico (Quill): títulos H1–H3, negrito, itálico, sublinhado, link, citação, bloco de código, listas |
| Foto de Capa | ✅ | imagem; área de arrastar ou clicar, com pré-visualização |
| Categoria | ✅ | select hierárquico (subcategorias com prefixo `-- `) |
| Coleções | | **só admin**: checkboxes |
| Palavras-chave | | texto separado por vírgulas |
| Galeria de Imagens | | começa com 2 campos e o botão "Adicionar outra imagem" adiciona mais; cada imagem tem uma descrição opcional, que aparece após escolher o arquivo |
| PDFs | | começa com 2 campos e adiciona mais; cada um com descrição opcional |
| Áudios | | começa com 2 campos e adiciona mais; mp3, m4a, wav, ogg, aac, opus, flac, webm; **máx. 5 MB** cada; descrição opcional |
| Vídeos do YouTube | | começa com 2 campos e adiciona mais; URL + descrição; **pré-visualização** do player ao colar a URL |
| Termo de autorização | ✅ | checkbox: "Autorizo a utilização da minha imagem, voz, fotografias, vídeos e materiais fornecidos no âmbito da Plataforma Museu da Pessoa Franca." |

**Envio:**
- Validação no navegador: capa, campos obrigatórios, categoria e tamanho/formato dos áudios.
- O envio é **assíncrono**, com **barra de progresso (%)** em overlay ("Enviando sua publicação…").
- No servidor: valida a categoria e os áudios (tamanho e formato), cria o artigo e define o **status**:
  - Admin → **publicado** imediatamente.
  - Colaborador → **pendente** (aguarda revisão).
- Associa a categoria, as tags (criando as novas) e as coleções (se admin).
- Salva a capa, as imagens, os PDFs e os áudios na ordem dos campos, com suas descrições.
- Vídeos: salva a URL, o `youtube_id` extraído e a descrição; URLs vazias são ignoradas.
- Ao terminar → redireciona para `/minha-conta?aba=historico&enviado=1`.
- Em caso de erro: caixa "Corrija os itens abaixo:" com a lista dos erros, mantendo o conteúdo digitado.

> **Validar também no servidor** no Laravel: título, capa e aceite do termo hoje só são validados
> no navegador ([§13](#13-bugs-e-inconsistências-atuais-não-replicar)).

### 8.2 Editar artigo

Acesso: `/minha-conta?aba=editar&post_id=N`. Permitido ao **autor** ou ao **admin**.
Mensagens: "Artigo não encontrado." / "Artigo inválido." / "Você não tem permissão para editar este artigo."

Mesmos campos da publicação, pré-preenchidos, com:
- **Capa:** mostra a atual; enviar uma nova substitui. É obrigatória (atual **ou** nova).
- **Imagens, PDFs e áudios existentes:** cada um com descrição editável e checkbox de **remover**, e campos para adicionar novos.
- **Vídeos existentes:** URL e descrição editáveis, checkbox de remover e pré-visualização; mais campos para vídeos novos. Ao salvar, a lista de vídeos é **reconstruída**: os existentes não removidos e com URL, depois os novos.
- **Coleções:** só para admin.
- Validações no servidor: título obrigatório, resumo ≤ 250, categoria obrigatória, capa (atual ou nova), áudios (formato e **máx. 50 MB** — diferente do limite de 5 MB da criação).
- **Não altera o status**: um artigo publicado continua publicado depois da edição feita pelo autor.
- Ao terminar → `/minha-conta?aba=historico&edit=1`.

> Decisão de negócio para a migração: definir se a edição de um artigo **publicado** feita por um
> colaborador deve voltar para revisão (hoje não volta).

### 8.3 Excluir artigo (autor)
Pelo card de gestão, com o botão "Deletar" e a confirmação "Tem certeza que deseja excluir esta publicação?".
- O autor exclui o próprio artigo; o admin exclui qualquer um.
- A exclusão é **permanente** e **apaga também os arquivos** (capa, imagens, PDFs, áudios).
- Mensagem: "Artigo excluído com sucesso."

---

## 9. Moderação e gestão (administradores)

### 9.1 Fila de revisão
Aba "Publicações Para Revisão" com os artigos de status pendente, em cards (Ver / Editar / Deletar).

### 9.2 Publicar agora
Na página de um artigo pendente, o admin vê **"Publicar agora"** → status publicado → redireciona para a fila de revisão.

### 9.3 Painel de gestão no artigo
Recolhível ("Abrir painel" / "Fechar painel"). O estado aberto/fechado é lembrado **por artigo** no navegador.

Cabeçalho com chips: **Autor**, **Status**, **Visualizações**, "Fixado" e "Destaque".

| Ação | Efeito |
|---|---|
| Editar artigo | Leva ao formulário de edição |
| Tornar / Remover Destaque | Alterna `is_featured` |
| Lixeira | Envia para a lixeira (soft delete), com confirmação |
| Excluir permanente | Exclusão definitiva com arquivos, com confirmação forte → volta para Minha Conta |
| Status | Select Publicado / Revisão Pendente → atualizar |
| Coleções | Checkboxes de todas as coleções → salvar (substitui) |
| Categoria principal | Select hierárquico → salvar (substitui) |
| Zerar visualizações | Põe o contador em 0 (existe no back-end, sem botão na UI atual) |
| Fixar no topo | Alterna "fixado" (existe no back-end, sem botão na UI e sem efeito em nenhuma listagem) |

Cada ação mostra uma mensagem de retorno (ex.: "Coleções atualizadas.").

### 9.4 Todas as publicações
Aba com todos os artigos publicados em cards de gestão (12 por página).

### 9.5 Lista de usuários
Grade de cards, 24 por página, ordenada por nome:
- Avatar, nome (ou login), e-mail.
- 📝 Número de **publicações** (artigos publicados do usuário).
- ⏱️ **Último acesso** (data e hora).
- 👤 **Função** (papel principal).

### 9.6 Cadastros auxiliares (hoje no admin do WordPress)
CRUD de **Categorias** (nome, slug, pai, descrição), **Coleções** (nome, slug, descrição) e
**Palavras-chave**. No Laravel, crie telas de admin para isso.

---

## 10. Componentes reutilizáveis

### 10.1 Card de gestão de artigos
Usado em Minhas Publicações, Revisão, Todas as publicações e Favoritos.
- Título da seção com ícone.
- Grade de 12 por página, ordenada por data desc; os links preservam `aba`.
- Cada card tem: capa (ou o logo como padrão), selos de mídia (YouTube, PDF, galeria), título, **selo de status** (Publicado / Pendente / Rascunho), chips de categoria `Pai > Filha` e autor.
- Botões: **Ver**; **Editar** e **Deletar** (escondidos em Favoritos; Deletar só para o autor ou um admin).
- Lista vazia: "Não há artigos para exibir no momento."

### 10.2 Carrossel de artigos (`widgets_artigos`)
Faixa horizontal com rolagem (setas anterior/próximo que se desabilitam no fim; roda do mouse rola na horizontal).
Título opcional. **Máximo de 20 itens.** Vários carrosséis podem existir na mesma página.

Card: capa, ícones de mídia (imagem, vídeo, PDF, áudio), título e uma linha de meta (**primeira
coleção**; se não houver, **primeira categoria**).

**Modos de seleção:**

| Modo | Regra |
|---|---|
| `recent` *(padrão)* | Publicados por data desc |
| `most_viewed` | Por `views_count` desc |
| `featured` | `is_featured = true` (filtro opcional por categoria ou coleção) |
| `category` | De uma categoria (slug ou id) |
| `tag` | De uma tag |
| `collection` | De uma coleção |
| `related_category` | Mesma(s) categoria(s) do artigo base |
| `related_parent` | Categoria pai do artigo base, incluindo as filhas |
| `related_tags` | Mesmas tags |
| `related_collections` | Mesmas coleções |
| `related_mix` | Primeiro as interseções (categoria∩tag, categoria∩coleção, tag∩coleção), depois em cascata: categoria → pai → tags → coleções |

Os modos `related_*` **excluem o próprio artigo**, não repetem itens e **completam com os recentes** quando faltam itens.
Só para artigos publicados.

### 10.3 Grade de destaques
Até N artigos com `is_featured = true`, ordenados por **visualizações** desc, depois número de comentários e depois data.
Card: capa 16:9 (ou "Sem imagem") e chips de tipos de mídia.

### 10.4 Botão de favoritar
Duas variantes: **ícone** (nos cards) e **pílula com texto** ("Favoritar" / "Favorito", no artigo).
- Visitante: o botão é um link para o login ("Entre para favoritar").
- Logado: alterna via requisição assíncrona; **sincroniza todos os botões do mesmo artigo** na página (coração cheio ou vazio, título, aria-pressed).
- Só aceita artigo publicado. Em caso de erro, mostra um alerta.

### 10.5 Player de áudios (componente avulso)
Grade ou lista de cards de áudio (capa do artigo com o selo "Áudio", título, descrição, player Plyr e "Baixar" opcional).

---

## 11. Contador de visualizações

Reformulado em 21/10/2026 (os contadores foram zerados nessa data). Regras:

1. A visita é registrada pelo **navegador** depois que a página carrega (requisição assíncrona); robôs que não executam JS ficam de fora, e a contagem funciona com a página em cache.
2. Só conta depois de **3 segundos com a aba visível**. Se a aba for escondida antes, o envio é cancelado.
3. Ignora navegadores automatizados (`navigator.webdriver`).
4. No servidor, descarta user-agents vazios ou de robôs (bot, crawl, spider, curl, python, headless, Googlebot, Ahrefs, Semrush, meta-webindexer, GPTBot, Claude, Bytespider etc.).
5. **No máximo 1 visita por IP + artigo a cada 24h**. Também há uma trava local no navegador.
6. Visitas de administradores não contam.
7. Só conta para artigos publicados.

> Laravel: endpoint `POST /artigos/{id}/view` sem CSRF (a página pode estar em cache); deduplicação
> em uma tabela `article_views (article_id, ip_hash, viewed_at)` ou em cache/Redis com TTL de 24h; incremento atômico em `articles.views_count`.

---

## 12. Integrações externas

| Integração | Uso |
|---|---|
| **Google OAuth** | Login social ([§6.2](#62-login-minha-contalogin)) |
| **YouTube** | Embeds `https://www.youtube.com/embed/{id}` |
| **VLibras** (`vlibras.gov.br/app/vlibras-plugin.js`) | Tradutor de Libras flutuante em todas as páginas — **acessibilidade, manter** |
| **Quill 1.3.6** | Editor rico dos formulários |
| **Plyr 3** | Player de áudio |
| **Font Awesome 6.5** | Ícones |
| **WhatsApp / Telegram** | Links de compartilhamento |
| **Google Analytics (Site Kit)** | Métricas do site — recriar a tag GA4 |
| **Gravatar** | Avatar padrão dos usuários |

Segurança e infra que hoje vêm de plugins e devem ter equivalentes: limite de tentativas de login,
firewall/WAF, HTTPS forçado, cache de página, banner de consentimento de cookies (LGPD) e SEO
(meta tags, sitemap).

---

## 13. Bugs e inconsistências atuais (não replicar)

| # | Problema | Recomendação |
|---|---|---|
| 1 | A lista de usuários lê "último acesso" de `last_login`, mas o login grava em `mp_last_login` → dados defasados | Um único `users.last_login_at` |
| 2 | Limite de áudio: **5 MB** na criação × **50 MB** na edição | Definir um limite único |
| 3 | Capa, título e aceite do termo só são validados no navegador | Form Request no servidor |
| 4 | Admin edita um artigo e desmarca **todas** as coleções → as coleções **não** são removidas | Sincronizar sempre (`sync([])`) |
| 5 | "Excluir permanente" redireciona para `?aba=artigos`, que não existe | Redirecionar para `historico` ou `todos_artigos` |
| 6 | "Fixar no topo" existe sem botão e sem efeito | Remover ou implementar |
| 7 | Artigo pendente fica visível publicamente pela URL | Policy: só autor e admins |
| 8 | O selo "Coleção" junta os nomes sem separador ("DOPSMemórias…") | `join(', ')` |
| 9 | O placeholder de capa usa `via.placeholder.com` (serviço fora do ar) | Imagem local |
| 10 | A detecção de mídia difere entre componentes (metadados × anexos × links no conteúdo) | Usar as relações `article_media` / `article_videos` |
| 11 | Formatos misturados de vídeo (URL simples × objeto) | Tabela própria com `url` e `youtube_id` |
| 12 | Os nomes de metadados mudaram numa refatoração sem migrar os dados (mídias sumiram até 21/10/2026) | Migrations versionadas |
| 13 | Pasta `widgets/` antiga duplicada (não carregada) | Ignorar na migração |
| 14 | Comentários habilitados no modelo, sem UI, 0 comentários | Não migrar (ou decidir) |
| 15 | Login sem "esqueci minha senha" | Implementar reset de senha |
| 16 | Cadastro sem confirmação de e-mail | Considerar `MustVerifyEmail` |
| 17 | Um colaborador pode excluir permanentemente um artigo **já publicado** | Decidir: soft delete, ou só enquanto pendente |

---

## 14. Migração de dados

Tabelas de origem (prefixo `wp_`) e mapeamento:

| Destino | Origem |
|---|---|
| `users` | `wp_users` (`ID, user_login, user_email, user_pass, display_name, user_registered`). O papel vem de `wp_usermeta.wp_capabilities` (`administrator` → admin, demais → user). O último acesso vem de `mp_last_login` ou `last_login` (o mais recente) |
| `social_accounts` | `wp_social_users` (`ID`→user_id, `type`=google, `identifier`=google_id) |
| `articles` | `wp_posts` com `post_type='artigo'`: `post_title, post_name (slug), post_excerpt, post_content, post_status (publish/pending/trash), post_author, post_date (published_at), post_modified` |
| `articles.cover_media_id` | `wp_postmeta._thumbnail_id` |
| `articles.is_featured` | `wp_postmeta.mp_featured = '1'` |
| `articles.views_count` | `wp_postmeta._views` |
| `categories` / `collections` / `tags` | `wp_terms` + `wp_term_taxonomy` (`taxonomy` = `category` / `colecao` / `post_tag`; `parent`, `description`) |
| pivôs | `wp_term_relationships` (`object_id`=artigo, `term_taxonomy_id`) |
| `article_media` (image) | `wp_postmeta.imagem_adicional` (1 linha por imagem, valor = ID do anexo; ordem = `meta_id`) + descrição em `imagem_adicional_descricao_{idAnexo}` |
| `article_media` (pdf) | `wp_postmeta.pdf` + `pdf_descricao_{idAnexo}` |
| `article_media` (audio) | `wp_postmeta.audio` + `audio_descricao_{idAnexo}` |
| `article_videos` | `wp_postmeta.youtube_link`: **dois formatos** — string com a URL ou array serializado PHP `{url, desc, video_id}`. Desserializar e extrair o id da URL quando faltar |
| arquivos | Anexos = `wp_posts` com `post_type='attachment'` (`guid`, `post_mime_type`, `post_title`); caminho em `wp_postmeta._wp_attached_file` (relativo a `wp-content/uploads/`); alt em `_wp_attachment_image_alt` |
| `favorites` | `wp_usermeta.mp_favoritos` (array serializado PHP de IDs de artigos) |

Cuidados:
- Preservar os **IDs** dos artigos, ou criar uma tabela de redirecionamento `?p=ID` → slug.
- Preservar os **slugs** dos artigos, categorias e coleções (SEO e links das páginas).
- As 14 senhas estão no formato do WordPress 6.8+: `$wp$2y$…`, ou seja, bcrypt sobre um HMAC-SHA384 da senha. Elas **não** são aceitas diretamente pelo `Hash::check` do Laravel. Use um *hasher* compatível na transição, que verifica no formato WP e regrava em bcrypt no primeiro login, ou force o reset de senha.
- Copiar `wp-content/uploads/` (≈ 527 arquivos) e gerar as miniaturas.
- Ignorar `_views` antigos/`_mp_views` (já unificados), `_elementor_*`, `_edit_lock`, `_oembed_*`.

---

## 15. Sugestão de arquitetura Laravel + Inertia

**Models:** `User`, `Article`, `Category` (self-referencing), `Collection`, `Tag`, `ArticleMedia`, `ArticleVideo`, `Favorite`, `SocialAccount`, `ArticleView`.

**Policies:** `ArticlePolicy` (view pendente, update/delete: autor ou admin; publish/feature/moderate: admin).

**Controllers / rotas principais:**
```
GET   /                           HomeController@index
GET   /acervo                     AcervoController@index     (?q, ?categoria, ?colecao, ?page)
GET   /artigos/{article:slug}     ArticleController@show
POST  /artigos/{article}/view     ArticleViewController@store   (sem CSRF, throttle)
POST  /artigos/{article}/favorite FavoriteController@toggle      (auth)
GET   /colecoes/{collection:slug} CollectionController@show
GET   /apoie | /quem-somos | ...  PageController

GET/POST /login, /cadastro, /logout, /auth/google, /auth/google/callback

prefix /minha-conta (auth):
  GET  /publicar              Conta\ArticleController@create
  POST /artigos               Conta\ArticleController@store       (multipart; progresso de upload)
  GET  /artigos/{id}/editar   Conta\ArticleController@edit
  PUT  /artigos/{id}          Conta\ArticleController@update
  DEL  /artigos/{id}          Conta\ArticleController@destroy
  GET  /historico             minhas publicações
  GET  /favoritos
  GET  /meus-dados

prefix /minha-conta (admin):
  GET  /revisao               pendentes
  GET  /todos-artigos
  GET  /usuarios
  POST /artigos/{id}/publicar | /destaque | /status | /colecoes | /categoria | /lixeira | /zerar-views
  CRUD /categorias, /colecoes, /tags
```

**Páginas Inertia (Vue/React):** `Home`, `Acervo/Index`, `Artigos/Show`, `Colecoes/Show`,
`Paginas/Apoie`, `Auth/Login`, `Auth/Register`, `Conta/Layout` (menu lateral), `Conta/Publicar`,
`Conta/Editar`, `Conta/Historico`, `Conta/Favoritos`, `Conta/MeusDados`, `Admin/Revisao`,
`Admin/TodosArtigos`, `Admin/Usuarios`, `Admin/Categorias`, `Admin/Colecoes`.

**Componentes de front:** `ArticleCard`, `ManageArticleCard`, `ArticleCarousel`, `FeaturedGrid`,
`FavoriteButton`, `MediaBadges`, `CategoryMenu` (árvore com contadores), `CollectionMenu`,
`SearchBar`, `Gallery` + `Lightbox`, `YouTubeEmbed`, `AudioPlayer`, `PdfList`, `ShareBar`,
`RichTextEditor`, `FileDropZone`, `RepeatableMediaField`, `UploadProgressOverlay`, `AdminArticlePanel`, `VLibras`.

**Serviços:** `RelatedArticlesService` (algoritmo do [§10.2](#102-carrossel-de-artigos-widgets_artigos)),
`YoutubeIdExtractor`, `BotDetector` + `ViewCounter` ([§11](#11-contador-de-visualizações)),
`MediaService` (upload, miniaturas, exclusão em cascata), cache dos contadores de categoria e coleção.
