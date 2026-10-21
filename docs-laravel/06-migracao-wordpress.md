# 06 — Importação do WordPress

Comando: `php artisan museu:import-wordpress {--fresh} {--only=users,taxonomies,articles,media,favorites,redirects} {--dry-run}`

- Conexão extra `wordpress` em `config/database.php` (credenciais `WP_DB_*`, prefixo `WP_DB_PREFIX`), **somente leitura**.
- Os arquivos são lidos de `WP_UPLOADS_PATH`.
- **Idempotente:** usa `updateOrCreate` pelos IDs ou pelo `legacy_wp_id`; pode rodar várias vezes (ensaio + virada).
- Cada etapa roda numa transação e imprime um relatório (criados / atualizados / ignorados / erros) em `storage/logs/import-*.log`.
- `--dry-run` só lê e reporta.
- Ordem: users → social → taxonomies → articles → pivots → media → videos → favorites → redirects.

## 1. Usuários

Origem: `wp_users` + `wp_usermeta`.

| Destino | Origem |
|---|---|
| `id` | `ID` |
| `username` | `user_login` |
| `name` | `display_name` (fallback: `user_login`) |
| `email` | `user_email` (lowercase) |
| `password` | `user_pass` (mantém o hash) + `password_legacy = true` |
| `email_verified_at` | `user_registered` (contas antigas são consideradas verificadas) |
| `role` | `wp_capabilities` contém `administrator` → `admin`; senão → `user` |
| `last_login_at` | o maior valor entre `mp_last_login` e `last_login` (timestamp Unix) |
| `created_at` | `user_registered` |

> ⚠️ **Revisão de segurança antes da virada:** a conta `admin` (ID 2, e-mail `admin@thgfghfgvh.com`)
> é administradora, tem e-mail sem sentido e nenhum login registrado. **Não importar como admin**
> sem confirmação do responsável. O importador deve listar todos os admins e pedir confirmação
> (`--confirm-admins=1,3,15,21`); os não confirmados entram como `user`.

## 2. Contas sociais
`wp_social_users (ID, type, identifier)` → `social_accounts (user_id = ID, provider = type ['google'], provider_id = identifier)`.
Hoje são 5 vínculos Google.

## 3. Taxonomias
`wp_terms t JOIN wp_term_taxonomy tt ON tt.term_id = t.term_id`

| `tt.taxonomy` | Destino | Campos |
|---|---|---|
| `category` | `categories` | `id = term_id`, `name`, `slug`, `description`, `parent_id = parent ?: null` (inserir os pais primeiro ou desativar a FK temporariamente) |
| `colecao` | `collections` | `id = term_id`, `name`, `slug`, `description` |
| `post_tag` | `tags` | `id = term_id`, `name`, `slug` |

Decodificar as entidades HTML dos nomes (`html_entity_decode`). Ignorar a categoria "Sem categoria" (id 1) se ninguém a usar.

## 4. Artigos
`wp_posts WHERE post_type = 'artigo' AND post_status IN ('publish','pending','draft','trash')` (hoje: 66 `publish`).

| Destino | Origem |
|---|---|
| `id`, `legacy_wp_id` | `ID` |
| `user_id` | `post_author` (se o usuário não existir → admin principal + log) |
| `title` | `post_title` (cortar em 100 e logar se for maior) |
| `slug` | `post_name` (**preservar**) |
| `summary` | `post_excerpt` (cortar em 250 e logar) |
| `content` | `post_content` → passar pelo Purifier; converter os shortcodes residuais em texto |
| `status` | `publish`→published, `pending`→pending, `draft`→draft, `trash`→published + `deleted_at` |
| `published_at` | `post_date_gmt` (se for `0000-…`, usar `post_date` convertido de America/Sao_Paulo) |
| `created_at` / `updated_at` | `post_date_gmt` / `post_modified_gmt` |
| `is_featured` | `wp_postmeta` `mp_featured = '1'` |
| `featured_at` | `post_modified_gmt` quando for destaque |
| `views_count` | `wp_postmeta` `_views` (inteiro). Os valores foram zerados em 21/10/2026 com o novo contador; importar como estiverem |
| `category_id` | primeiro termo `category` do artigo (`wp_term_relationships`); se faltar → "Sem categoria" + log |
| `consent_accepted_at` | `post_date_gmt` (aceite implícito no envio legado) |

**Pivôs:** `wp_term_relationships tr JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = artigo`
→ `colecao` → `article_collection`; `post_tag` → `article_tag`.

## 5. Senhas (`WordpressHasher`)

Formato atual em produção: **todas** as 14 senhas começam com `$wp$2y$` (WordPress 6.8+).

```php
final class WordpressHasher
{
    public static function check(string $plain, string $hash): bool
    {
        if (str_starts_with($hash, '$wp')) {                     // WP 6.8+: bcrypt(base64(hmac-sha384))
            // wp_hash_password() aplica trim() antes de gerar o hash
            foreach (array_unique([$plain, trim($plain)]) as $candidate) {
                $pre = base64_encode(hash_hmac('sha384', $candidate, 'wp-sha384', true));
                if (password_verify($pre, substr($hash, 3))) return true;
            }
            return false;
        }
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {  // phpass portable
            return (new \Hautelook\Phpass\PasswordHash(8, true))->CheckPassword($plain, $hash);
        }
        if (str_starts_with($hash, '$2y$')) return password_verify($plain, $hash);
        if (strlen($hash) === 32 && ctype_xdigit($hash)) return hash_equals($hash, md5($plain)); // muito antigo
        return false;
    }
}
```

No `Fortify::authenticateUsing`: buscar o usuário por e-mail ou username. Se `password_legacy`, verificar com o `WordpressHasher::check` e, se der certo, fazer `forceFill(['password' => Hash::make($plain), 'password_legacy' => false])->save()`. Senão, usar `Hash::check`.
Teste unitário com hashes reais gerados por `wp_hash_password()` (gere-os numa instalação WP de teste; não use hashes de produção nos testes).

## 6. Mídias

Anexo WP = `wp_posts` com `post_type = 'attachment'`: `ID`, `post_title`, `post_mime_type`.
Caminho: `wp_postmeta._wp_attached_file` (relativo a `uploads/`, ex.: `2025/08/ficha-01.jpg`). Alt: `_wp_attachment_image_alt`.

| Coleção destino | Origem (no artigo) | Descrição |
|---|---|---|
| `cover` | `_thumbnail_id` | — |
| `gallery` | `imagem_adicional` (1 linha por imagem; valor = ID do anexo, às vezes array serializado → usar o 1º elemento) | `imagem_adicional_descricao_{idAnexo}` |
| `pdfs` | `pdf` | `pdf_descricao_{idAnexo}` |
| `audios` | `audio` | `audio_descricao_{idAnexo}` |

- **Ordem** = ordem de `meta_id` crescente.
- Importar com `$article->addMedia($fullPath)->preservingOriginal()->usingName($title)->withCustomProperties(['description' => $desc, 'alt' => $alt, 'legacy_attachment_id' => $id])->toMediaCollection($col)`.
- Arquivo inexistente no disco → log e segue. Deduplicar pelo `legacy_attachment_id` (idempotência).
- Volume atual: ~527 arquivos (513 imagens, 5 PDFs). Rodar com `--only=media` em lotes (`chunkById(10)`); as conversões vão para a fila.

## 7. Vídeos
`wp_postmeta.meta_key = 'youtube_link'` tem **dois formatos** (38 registros):
1. String com a URL (4 registros), ex.: `https://youtu.be/BeFEo4vb_9c?si=...`
2. Array PHP serializado (34 registros): `a:3:{s:3:"url";…;s:4:"desc";…;s:8:"video_id";…}`

```php
$data = @unserialize($value, ['allowed_classes' => false]);
$url  = is_array($data) ? ($data['url'] ?? '') : $value;
$desc = is_array($data) ? ($data['desc'] ?? null) : null;
$id   = (is_array($data) && !empty($data['video_id'])) ? $data['video_id'] : YoutubeId::extract($url);
```
Sem ID válido → log. `position` = ordem de `meta_id`.

## 8. Favoritos
`wp_usermeta.meta_key = 'mp_favoritos'` = array PHP serializado de IDs → `favorites (user_id, article_id)`. Só os artigos importados; ignorar os IDs inexistentes.

## 9. Redirects (tabela `redirects`)
Para cada artigo importado:
- `/?p={ID}` → `/artigos/{slug}`
- `/publicacoes/artigo/?id={ID}` → `/artigos/{slug}`
- `_wp_old_slug` (se existir) `/artigos/{old}` → `/artigos/{slug}`

Para cada coleção: `/colecao/{slug}` → `/colecoes/{slug}`.
Os demais (`/minha-conta/?aba=…`, `/minha-conta/login`, `/wp-login.php`…) ficam no middleware `LegacyRedirects` (04 §1).

## 10. Validação pós-importação (`php artisan museu:import-wordpress --verify`)
Comparar com o WP e falhar se houver divergência:
- contagem de artigos por status, de usuários, de categorias, coleções e tags, de vínculos;
- para cada artigo: nº de imagens de galeria, PDFs, áudios e vídeos igual ao do WP; capa presente;
- 20 artigos aleatórios: título, slug e categoria iguais;
- todas as URLs `/artigos/{slug}` do sitemap antigo respondem 200 no novo.

## 11. Roteiro de virada (go-live)
1. Ensaio completo em homologação (`--fresh`) + `--verify` + teste manual das páginas DOPS (galerias grandes: artigo 684 com 143 imagens).
2. Cadastrar o novo redirect URI do Google OAuth.
3. Congelar o WP (modo manutenção ou só leitura) → importação final (incremental) → `--verify`.
4. Trocar o DNS/vhost para o Laravel; manter o WP antigo acessível por um subdomínio interno por 30 dias.
5. Monitorar os 404 (logar os paths sem rota para criar redirects) e o login legado (taxa de rehash).
