<?php
// ===== Página "Apoie" — usar via [pagina_apoie]
add_shortcode('pagina_apoie', function ($atts = []) {
    $a = shortcode_atts([
            'pix_key' => 'institutopraxis@yahoo.com.br',
            'banco' => 'Banco do Brasil',
            'agencia' => '0053-1',
            'conta' => '44-479-0',
            'cnpj' => '07.464.521-0001/49',
            'qr' => '/wp-content/uploads/2025/08/qrcode_pix.png', // URL do QR Code do PIX
            'contato' => 'ipra@institutopraxis.org.br',
            'meta_percent' => '56'
    ], $atts, 'pagina_apoie');

    ob_start(); ?>

    <section class="apoie-wrap" aria-labelledby="apoie-title">
        <style>
            .apoie-wrap {
                --brand: #9E2B19;
                --brand-weak: #EED2CD;
                --text: #1C1C1C;
                --muted: #6B6B6B;
                --card-bg: #ffffff;
                --card-bd: #eee;
                --shadow: 0 2px 8px rgba(0, 0, 0, .06);
                background: #fff;
                color: var(--text);
                padding: 40px 16px;
                font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Arial, sans-serif
            }

            .apoie-container {
                max-width: 1120px;
                margin: 0 auto
            }

            .apoie-title {
                font-size: clamp(22px, 4.2vw, 32px);
                line-height: 1.18;
                margin: 6px 0 10px
            }

            .apoie-sub {
                color: var(--muted);
                font-size: clamp(15px, 2.2vw, 18px)
            }

            .apoie-cta {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 18px
            }

            .apoie-btn {
                border-radius: 10px;
                padding: 12px 16px;
                font-weight: 700;
                border: 2px solid transparent;
                cursor: pointer;
                transition: .18s
            }

            .apoie-btn--solid {
                background: var(--brand);
                color: #fff
            }

            .apoie-btn--solid:hover {
                filter: brightness(0.98)
            }

            .apoie-btn--ghost {
                background: #fff;
                color: var(--brand);
                border-color: var(--brand)
            }

            .apoie-btn--ghost:hover {
                background: var(--brand-weak)
            }

            .apoie-card {
                background: var(--card-bg);
                border: 1px solid var(--card-bd);
                border-radius: 16px;
                padding: 18px;
                box-shadow: var(--shadow)
            }

            .hr {
                height: 1px;
                background: #f0f0f0;
                margin: 18px 0
            }

            .apoie-hero {
                display: grid;
                gap: 18px;
                grid-template-columns:1fr;
                align-items: center
            }

            .apoie-badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                color: var(--brand);
                background: white;
                border-radius: 999px;
            }

            .impact {
                display: grid;
                gap: 14px;
                grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
                align-items: stretch
            }

            .impact .card {
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                min-height: 128px
            }

            .impact .title {
                font-weight: 800;
                font-size: 20px;
                margin: 0 0 6px
            }

            .impact .desc {
                color: var(--muted);
                margin: 0
            }

            .bar {
                height: 8px;
                background: #f1e5e3;
                border-radius: 999px;
                overflow: hidden;
                margin-top: 8px
            }

            .bar > span {
                display: block;
                height: 100%;
                background: linear-gradient(90deg, var(--brand), #B94631)
            }

            .apoie-grid {
                display: grid;
                gap: 18px;
                grid-template-columns:2fr 1fr
            }

            @media (max-width: 980px) {
                .apoie-grid {
                    grid-template-columns:1fr
                }
            }

            .pay h3, .why h3 {
                color: var(--brand);
                margin: 0 0 10px
            }

            .pix-key {
                font-family: ui-monospace, SFMono-Regular, Consolas, Menlo, monospace;
                background: #fafafa;
                border: 1px dashed #cfcfcf;
                border-radius: 8px;
                padding: 8px 12px
            }

            .copy-btn {
                border: 0;
                background: var(--brand);
                color: #fff;
                border-radius: 8px;
                padding: 9px 12px;
                cursor: pointer
            }

            .qr-box img {
                width: 150px;
                height: 150px;
                border: 1px solid #e6e6e6;
                border-radius: 10px
            }

            .why-list {
                display: grid;
                gap: 10px
            }

            .why-item {
                display: flex;
                gap: 10px
            }

            .why-dot {
                width: 10px;
                height: 10px;
                border-radius: 50%;
                margin-top: 8px;
                background: var(--brand)
            }

            .note {
                background: #fff;
                border: 1px solid var(--card-bd);
                border-radius: 14px;
                padding: 14px;
                box-shadow: var(--shadow)
            }

            .note .tag {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background: var(--brand-weak);
                color: #3b2a28;
                font-weight: 600;
                border-radius: 999px;
                padding: 6px 10px;
                margin-bottom: 8px
            }
            .transparencia-item {
                display: block;
                margin-bottom: 10px;

            }
        </style>

        <div class="apoie-container">
            <!-- HERO -->
            <div class="apoie-card apoie-hero">
                <div>
                    <span class="apoie-badge"><i class="fa-solid fa-heart"></i> Apoie o Museu da Pessoa de Franca</span>
                    <h1 id="apoie-title" class="apoie-title">Contribua para o desenvolvimento de um museu cada vez mais
                        acessível, diverso e plural</h1>
                    <p class="apoie-sub">
                        Com um gesto simples, você ajuda a sustentar a preservação de memórias, a digitalização de
                        acervos
                        e a continuidade de um museu plural, inclusivo e gratuito. Cada contribuição faz diferença.
                    </p>
                    <div class="apoie-cta">
                        <a class="apoie-btn apoie-btn--ghost" href="#formas"><i class="fa-solid fa-heart"></i> Quero
                            contribuir agora</a>
                    </div>

                    <div class="hr"></div>

                    <!-- IMPACTO -->
                    <div class="impact" aria-label="Impactos do apoio">
                        <div class="apoie-card card">
                            <div>
                                <div class="title">+ Inclusão</div>
                                <p class="desc">Conteúdos gratuitos, acessíveis e representativos.</p>
                            </div>
                        </div>

                        <div class="apoie-card card">
                            <div>
                                <div class="title">+ Memória</div>
                                <p class="desc">Coleta, cuidado e difusão de histórias da comunidade.</p>
                            </div>
                        </div>

                        <div class="apoie-card card">
                            <div>
                                <div class="title">+ Educação</div>
                                <p class="desc">Materiais e ações para escolas e iniciativas locais.</p>
                            </div>
                        </div>
                    </div>

                    <div class="note" style="margin-top:16px">
                        <div class="tag"><i class="fa-regular fa-lightbulb"></i> Por que seu apoio é importante?</div>
                        <div>
                            A existência do Museu da Pessoa de Franca depende do apoio coletivo de pessoas como você.
                            Com sua doação, mantemos o acervo no ar, ampliamos o acesso e seguimos eternizando histórias
                            de vida.
                        </div>
                    </div>
                </div>
            </div>

            <!-- FORMAS DE APOIO -->
            <div id="formas" class="apoie-grid" style="margin-top:18px">
                <div class="apoie-card pay">
                    <h3><i class="fa-brands fa-pix"></i> PIX</h3>
                    <p>Use a chave ou o QR Code para doar o valor que desejar:</p>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:6px 0 12px">
                        <span class="pix-key" id="pixKey"><?= esc_html($a['pix_key']); ?></span>
                        <button class="copy-btn" type="button" data-copy-target="#pixKey"><i
                                    class="fa-regular fa-copy"></i> Copiar chave
                        </button>
                    </div>
                    <?php if (!empty($a['qr'])): ?>
                        <div class="qr-box"><img src="<?= esc_url($a['qr']); ?>" alt="QR Code PIX" loading="lazy"
                                                 decoding="async"></div>
                    <?php endif; ?>

                    <div class="hr"></div>

                    <h3><i class="fa-solid fa-building-columns"></i> Depósito/Transferência</h3>
                    <p>
                        <strong>Banco:</strong> <?= esc_html($a['banco']); ?><br>
                        <strong>Agência:</strong> <?= esc_html($a['agencia']); ?><br>
                        <strong>Conta Corrente:</strong> <?= esc_html($a['conta']); ?><br>
                        <strong>CNPJ:</strong> <?= esc_html($a['cnpj']); ?>
                    </p>
                    <p style="color:var(--muted);font-size:14px;margin-top:8px">
                        Se desejar, envie o comprovante para <a
                                href="mailto:<?= esc_attr($a['contato']); ?>"><?= esc_html($a['contato']); ?></a>.
                    </p>
                </div>

                <aside class="apoie-card why" id="transparencia">
                    <h3><i class="fa-regular fa-circle-check"></i> Transparência</h3>
                    <span class="transparencia-item"><strong>Transparência no uso dos recursos</strong></span>

                    <span class="transparencia-item">As doações recebidas ajudam a manter o Museu da Pessoa de Franca no ar, garantindo:</span>

                    <span class="transparencia-item">Infraestrutura digital (servidores, hospedagem, domínio e segurança).</span>

                    <span class="transparencia-item">Preservação do acervo (digitalização, organização e publicação de histórias).</span>

                    <span class="transparencia-item">Acessibilidade e conteúdos (legendas, transcrições, materiais educativos e exposições online).</span>

                    <span class="transparencia-item">Assim, cada contribuição fortalece nossa missão de preservar memórias e compartilhar histórias de forma aberta e gratuita com toda a comunidade.</span>
                </aside>
            </div>

            <!-- Bloco INSTITUTO PRÁXIS DE EDUCAÇÃO E CULTURA – IPRA -->
            <div class="apoie-card" style="margin-top:18px">
                <!--                <h3 style="color:var(--brand);margin:0 0 8px"><i class="fa-solid fa-users"></i> INSTITUTO PRÁXIS DE EDUCAÇÃO E CULTURA – IPRA</h3>-->
                <p>
                    O <strong>Museu da Pessoa de Franca</strong> é uma iniciativa cultural mantida pelo
                    <strong>INSTITUTO PRÁXIS DE EDUCAÇÃO E CULTURA – IPRA</strong>, entidade sem fins lucrativos
                    responsável pela
                    gestão administrativa e financeira do projeto.
                </p>
                <p>
                    Por isso, todas as contribuições realizadas para apoiar o Museu são recebidas diretamente
                    na conta do IPRA, garantindo segurança, transparência e a correta
                    aplicação dos recursos.
                </p>
                <p>
                    Cada valor doado é destinado integralmente à manutenção do Museu, cobrindo custos essenciais
                    como servidores, domínio, digitalização do acervo, preservação de memórias e produção de
                    conteúdos educativos acessíveis à comunidade.
                </p>
            </div>
        </div>

        <script>
            (function () {
                document.querySelectorAll('.copy-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const el = document.querySelector(this.dataset.copyTarget);
                        if (!el) return;
                        navigator.clipboard.writeText((el.textContent || '').trim()).then(() => {
                            this.innerHTML = '<i class="fa-solid fa-check"></i> Copiado!';
                            setTimeout(() => {
                                this.innerHTML = '<i class="fa-regular fa-copy"></i> Copiar chave'
                            }, 2000);
                        });
                    });
                });
            })();
        </script>
    </section>

    <?php
    return ob_get_clean();
});
