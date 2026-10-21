# Pacote de especificação — Museu da Pessoa de Franca (Laravel + Inertia + MUI)

Este pacote foi escrito para que o **Claude Code** consiga recriar a plataforma do zero,
**corrigindo os bugs** da versão WordPress e incluindo **melhorias**.

## Como usar

1. Crie o repositório novo e copie para ele:
   - `CLAUDE.md` → **raiz** do repositório (o Claude Code lê automaticamente);
   - todos os arquivos `0*.md` → pasta `docs/` do repositório;
   - `../DOCUMENTACAO-FUNCIONAL.md` → `docs/00-documentacao-funcional-legado.md` (referência de comportamento do sistema atual).
2. No Claude Code, peça uma fase por vez, por exemplo:
   > "Leia docs/07-plano-de-implementacao.md e execute a Fase 1. Ao final, rode os testes e me mostre o checklist de aceite."
3. Valide o checklist de aceite de cada fase antes de seguir para a próxima.

## Índice

| Arquivo | Conteúdo |
|---|---|
| `CLAUDE.md` | Regras do projeto para a IA: stack, convenções, comandos, definição de pronto |
| `01-stack-e-setup.md` | Versões, pacotes, configuração do ambiente, `.env`, estrutura de pastas |
| `02-banco-de-dados.md` | Esquema completo (migrations), enums, índices, models e relações |
| `03-regras-de-negocio.md` | Todas as regras: papéis, policies, fluxo editorial, validações, contador de views, bugs corrigidos |
| `04-rotas-e-backend.md` | Rotas, controllers, Form Requests, serviços, jobs, notificações, cache |
| `05-frontend-mui.md` | Tema MUI, layouts, páginas Inertia, componentes, acessibilidade e SEO |
| `06-migracao-wordpress.md` | Importador `php artisan museu:import-wordpress` (dados, arquivos, senhas, redirects) |
| `07-plano-de-implementacao.md` | Fases ordenadas com tarefas, testes e checklist de aceite |
| `08-arquitetura-recomendada.md` | **Arquitetura oficial**: monólito modular, revisões editoriais, mídia como entidade de acervo, Redis/Meilisearch/S3, design system, testes, CI/CD. **Prevalece** sobre 01–05 nas questões de estrutura (ver a §9 dele) |

## Decisões tomadas (podem ser revistas)

| Tema | Decisão padrão | Onde |
|---|---|---|
| Edição de artigo publicado por colaborador | Volta para **revisão** (configurável em `config/museu.php`) | 03 §4 |
| Exclusão por colaborador | Só **soft delete** (lixeira); o admin restaura ou exclui de vez | 03 §4 |
| Rejeição de artigo | Novo status `rejected`, com motivo e notificação ao autor | 03 §4 |
| Filtros do acervo | Categoria **e** coleção combináveis (antes eram exclusivos) | 03 §6 |
| Front-end | React + TypeScript + MUI v7, Inertia v2 com **SSR** para SEO | 01 |
| Editor de texto | Tiptap via `mui-tiptap` (substitui o Quill) | 05 |
| Mídias | `spatie/laravel-medialibrary`, com conversões WebP em fila | 02/04 |
