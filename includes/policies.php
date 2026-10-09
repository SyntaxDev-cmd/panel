<?php
// ============================================================
//  MODELOS DE POLITICA DE PRIVACIDADE E TERMOS DE USO
//  Escritos para atender as regras das lojas (Google Play, Amazon Appstore,
//  Roku Channel Store, LG Content Store, Samsung Apps/Tizen, Apple) para
//  apps que sao APENAS REPRODUTORES DE MIDIA (sem conteudo proprio).
//  Marcadores trocados na exibicao: {APP_NAME} {EMAIL} {WEBSITE} {DELETE_URL} {DATE}
// ============================================================
if (count(get_included_files()) == 1) exit('No direct script access allowed');

// texto curto exibido na landing page, nos rodapes e nas politicas
function lf_disclaimer_text() {
    return 'Somos apenas um reprodutor de midia. Nao fornecemos, vendemos, hospedamos nem distribuimos nenhum conteudo, canal, filme, serie ou lista. '
         . 'O usuario utiliza somente conteudos proprios ou que tem direito legal de acessar, fornecidos por terceiros de sua escolha.';
}

// a politica gravada ainda e o texto de exemplo do instalador (ou esta vazia)?
function lf_policy_is_placeholder($html) {
    $t = trim(strip_tags((string)$html));
    if ($t === '') return true;
    return strpos($t, 'What personal data we collect and why we collect it') === 0
        || strpos($t, 'IntroductionThis document (the') === 0
        || strpos($t, 'Introduction') === 0 && strpos($t, 'digital music services') !== false;
}

function lf_policy_render($html) {
    global $settings_details;
    $s = is_array($settings_details) ? $settings_details : array();
    $base = function_exists('getBaseUrl') ? getBaseUrl() : '';
    $email = !empty($s['app_email']) ? $s['app_email'] : '';
    $site = !empty($s['app_website']) && filter_var($s['app_website'], FILTER_VALIDATE_URL) ? $s['app_website'] : $base;
    $map = array(
        '{APP_NAME}' => htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'App', ENT_QUOTES, 'UTF-8'),
        '{EMAIL}' => htmlspecialchars($email !== '' ? $email : 'o e-mail de suporte informado na loja de aplicativos', ENT_QUOTES, 'UTF-8'),
        '{WEBSITE}' => htmlspecialchars($site, ENT_QUOTES, 'UTF-8'),
        '{DELETE_URL}' => htmlspecialchars($base . 'policy/account_delete_request.php', ENT_QUOTES, 'UTF-8'),
        '{DATE}' => date('d/m/Y'),
    );
    return strtr((string)$html, $map);
}

function lf_policy_template($which) {
    if ($which === 'terms') return <<<'HTML'
<h2>Termos de Uso</h2>
<p>Ao instalar, acessar ou usar o aplicativo <b>{APP_NAME}</b> (o "Aplicativo") e o site de ativacao, voce concorda com estes Termos de Uso. Se nao concordar, nao use o Aplicativo.</p>

<h3>1. O que e o Aplicativo</h3>
<p>O {APP_NAME} e <b>exclusivamente um reprodutor de midia</b> (media player). Ele permite que o usuario reproduza listas e transmissoes (por exemplo M3U, M3U8/HLS, MPEG-TS e Xtream Codes) <b>fornecidas pelo proprio usuario</b>.</p>
<p><b>Nao fornecemos, vendemos, hospedamos, transmitimos nem distribuimos nenhum conteudo</b>, canal de TV, filme, serie, evento, lista ou assinatura. O Aplicativo e entregue sem nenhum conteudo. Nao temos relacao com os fornecedores de conteudo que o usuario escolhe usar e nao recomendamos nenhum deles.</p>

<h3>2. Responsabilidade do usuario pelo conteudo</h3>
<ul>
<li>Voce declara que somente usara conteudos proprios ou que tem <b>direito legal</b> de acessar e reproduzir.</li>
<li>E proibido usar o Aplicativo para acessar, reproduzir ou compartilhar conteudo pirata, sem licenca ou que viole direitos autorais, marcas ou qualquer lei.</li>
<li>Voce e o unico responsavel pelas listas, links, servidores, usuarios e senhas que informa no Aplicativo ou no site de ativacao.</li>
</ul>

<h3>3. Ativacao e pagamentos</h3>
<p>Valores eventualmente cobrados no site de ativacao referem-se <b>somente a licenca de uso do software reprodutor</b> no aparelho informado (identificado pelo MAC/ID do aparelho), pelo periodo do plano escolhido. Nenhum valor corresponde a conteudo.</p>
<p>Os pagamentos sao processados pelo Mercado Pago; nao armazenamos dados de cartao. Compras feitas pela internet podem ser canceladas em ate 7 (sete) dias, conforme o art. 49 do Codigo de Defesa do Consumidor, pelo contato abaixo. Planos de teste gratuitos podem ser limitados a um por aparelho.</p>

<h3>4. Uso permitido</h3>
<ul>
<li>Nao copie, modifique, descompile ou revenda o Aplicativo sem autorizacao.</li>
<li>Nao use o Aplicativo para fins ilegais nem para prejudicar terceiros, servidores ou redes.</li>
<li>Podemos bloquear aparelhos ou ativacoes usados em desacordo com estes Termos ou com a lei.</li>
</ul>

<h3>5. Direitos autorais (notificacao e remocao)</h3>
<p>Respeitamos a propriedade intelectual. Como nao hospedamos conteudo, nao podemos remove-lo da origem, mas bloqueamos o acesso pelo Aplicativo quando recebemos uma notificacao valida. Envie para {EMAIL}: identificacao da obra, o link/servidor envolvido, seus dados de contato e uma declaracao de que voce e o titular ou representante autorizado.</p>

<h3>6. Isencao de garantias e limitacao de responsabilidade</h3>
<p>O Aplicativo e fornecido "no estado em que se encontra". Nao garantimos a disponibilidade, qualidade ou legalidade de conteudos de terceiros, nem o funcionamento de servidores que nao sao nossos. Na maxima extensao permitida pela lei, nao respondemos por danos decorrentes do uso de conteudos de terceiros.</p>

<h3>7. Lojas de aplicativos</h3>
<p>Estes Termos sao entre voce e o responsavel pelo {APP_NAME}, e nao com Google, Amazon, Apple, Roku, LG ou Samsung, que nao respondem pelo Aplicativo nem por seu suporte.</p>

<h3>8. Alteracoes e lei aplicavel</h3>
<p>Podemos atualizar estes Termos; a versao vigente fica sempre nesta pagina. Aplicam-se as leis da Republica Federativa do Brasil.</p>

<h3>9. Contato</h3>
<p>{EMAIL} &middot; {WEBSITE}</p>
<p><small>Atualizado em {DATE}.</small></p>
HTML;

    return <<<'HTML'
<h2>Politica de Privacidade</h2>
<p>Esta Politica explica como o aplicativo <b>{APP_NAME}</b> (o "Aplicativo") e o site de ativacao tratam dados pessoais, de acordo com a LGPD (Lei 13.709/2018), o GDPR e as regras das lojas Google Play, Amazon Appstore, Roku, LG, Samsung e Apple.</p>

<h3>1. Somos apenas um reprodutor de midia</h3>
<p>O {APP_NAME} <b>nao fornece, vende, hospeda nem distribui nenhum conteudo</b>. O usuario informa as proprias listas ou servidores (M3U, M3U8/HLS, MPEG-TS, Xtream Codes). Nao temos acesso ao que voce assiste e nao registramos historico de reproducao.</p>

<h3>2. Dados que coletamos</h3>
<ul>
<li><b>Identificacao do aparelho:</b> MAC ou ID do aparelho, plataforma (Android, Roku, LG, Samsung, etc.), modelo e versao do app - para ativar o aparelho e limitar o numero de aparelhos.</li>
<li><b>Dados tecnicos:</b> endereco IP e data/hora do ultimo acesso - para seguranca e prevencao de abuso.</li>
<li><b>Dados de acesso que voce informa:</b> endereco do servidor, usuario e senha da sua lista - usados somente para conectar o Aplicativo ao servidor que voce escolheu.</li>
<li><b>Site de ativacao:</b> e-mail e dados do pedido. O pagamento e feito no Mercado Pago, que trata os dados financeiros conforme a politica dele; <b>nao recebemos nem armazenamos dados de cartao</b>.</li>
</ul>
<p>Nao coletamos localizacao precisa, contatos, fotos, microfone ou camera. Nao vendemos dados pessoais e nao usamos dados para publicidade personalizada.</p>

<h3>3. Para que usamos</h3>
<ul>
<li>Ativar e manter o Aplicativo funcionando no seu aparelho;</li>
<li>Processar pagamentos e enviar comprovantes;</li>
<li>Seguranca, prevencao de fraude e cumprimento de obrigacoes legais;</li>
<li>Suporte ao usuario.</li>
</ul>
<p>Bases legais (LGPD art. 7): execucao de contrato, cumprimento de obrigacao legal e legitimo interesse.</p>

<h3>4. Compartilhamento</h3>
<p>Somente com prestadores necessarios ao servico: hospedagem do servidor, processador de pagamento (Mercado Pago) e, se ativado, servico de notificacoes (OneSignal). Tambem quando exigido por lei ou ordem judicial.</p>

<h3>5. Armazenamento e seguranca</h3>
<p>Os dados ficam em servidores protegidos, com acesso restrito. Guardamos os dados enquanto a ativacao estiver em uso e pelo prazo exigido por lei (por exemplo, registros de acesso por 6 meses, Marco Civil da Internet); depois sao apagados ou anonimizados.</p>

<h3>6. Seus direitos</h3>
<p>Voce pode pedir acesso, correcao, portabilidade ou <b>exclusao dos seus dados</b> a qualquer momento, sem custo:</p>
<ul>
<li>pelo formulario: <a href="{DELETE_URL}">{DELETE_URL}</a></li>
<li>ou pelo e-mail {EMAIL}</li>
</ul>
<p>A exclusao remove a ativacao do aparelho e os dados associados em ate 30 dias, exceto o que a lei obrigar a manter.</p>

<h3>7. Criancas</h3>
<p>O Aplicativo nao e direcionado a menores de 13 anos e nao coleta conscientemente dados de criancas. Se identificarmos esse caso, os dados serao apagados.</p>

<h3>8. Alteracoes</h3>
<p>Esta Politica pode ser atualizada. A versao vigente fica sempre nesta pagina.</p>

<h3>9. Contato e encarregado (DPO)</h3>
<p>{EMAIL} &middot; {WEBSITE}</p>
<p><small>Atualizado em {DATE}.</small></p>
HTML;
}
