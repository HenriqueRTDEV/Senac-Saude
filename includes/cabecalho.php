<?php
// ===================================================================
//  includes/cabecalho.php
//  Prontuário Eletrônico TDS
//
//  Desenha tudo o que se repete em toda página: o menu lateral, a
//  barra de cima e a abertura da área de conteúdo.
//
//  Toda página interna tem a mesma forma: primeiro o protege.php,
//  depois o título, depois este arquivo, depois só o conteúdo da tela,
//  e por último o rodape.php. Veja o exemplo pronto em painel.php.
//
//  CUIDADO: nunca escreva a marca de fechar PHP dentro de um comentário
//  aqui. Mesmo depois de duas barras, ela fecha o bloco PHP de verdade
//  e o resto do arquivo vira texto solto na tela.
//
//  REGRA DA TURMA: este arquivo é escrito uma vez e ninguém mexe depois.
// ===================================================================

// Se a página não definiu um título, usa um padrão.
if (!isset($titulo)) {
    $titulo = 'Prontuário TDS';
}
if (!isset($subtitulo)) {
    $subtitulo = '';
}

// Descobre qual arquivo está aberto agora (ex.: "painel.php").
// Serve para deixar aceso o item certo do menu lateral.
$pagina_atual = basename($_SERVER['PHP_SELF']);

// -------------------------------------------------------------------
//  QUEM ESTÁ LOGADO
//  O protege.php já garantiu que a sessão existe.
// -------------------------------------------------------------------
$usuario_nome   = $_SESSION['usuario_nome'];
$usuario_perfil = $_SESSION['usuario_perfil'];

// O banco guarda o perfil sem acento e em minúsculas ('recepcao').
// Na tela queremos a versão bonita. A tradução mora em um arquivo só.
require_once 'includes/perfis.php';
$perfil_na_tela = nomeDoPerfil($usuario_perfil);

// -------------------------------------------------------------------
//  O PAPEL EMPRESTADO
//
//  O administrador pode ver o sistema como outro perfil sem sair e
//  entrar de novo. Serve para testar o caminho inteiro numa sessão só,
//  e para apresentar o sistema sem trocar de login na frente da turma.
//
//  Quando isso acontece, o $usuario_perfil lá em cima JÁ É o papel
//  assumido — e é assim que tem que ser. O menu, o selo, o avatar e as
//  telas precisam ficar idênticos ao que aquele perfil vê; se qualquer
//  pedaço continuasse dizendo "administrador", você não estaria vendo
//  o que aquele perfil vê.
//
//  Quem guarda a verdade é a chave separada, e o único lugar do
//  cabeçalho que a mostra é o menu do avatar, lá embaixo. O isset()
//  cobre quem já estava logado quando esta parte passou a existir: a
//  sessão dessa pessoa ainda não tem a chave nova.
// -------------------------------------------------------------------
require_once 'config/papel.php';

$usuario_perfil_real = (isset($_SESSION['usuario_perfil_real'])
                        ? $_SESSION['usuario_perfil_real']
                        : $usuario_perfil);

$pode_assumir_papel = (ADMIN_TROCA_PAPEL
                       && $usuario_perfil_real == 'administrador');

// Está com um papel emprestado agora? Serve para o menu do avatar
// dizer a verdade sem repetir a comparação em três lugares.
$papel_emprestado = ($usuario_perfil != $usuario_perfil_real);

// Iniciais para o avatar: primeira letra das duas primeiras palavras,
// pulando abreviações como "Dr." e "Téc." e apelidos entre parênteses.
// "Téc. Carlos Menezes" vira CM.
//
// Usamos preg_match com a marca /u no lugar de substr(). Em UTF-8 uma
// letra acentuada ocupa dois bytes, e substr() cortaria essa letra ao
// meio, mandando lixo para a tela. O /u faz o PHP contar letras em vez
// de bytes — e, ao contrário das funções mb_, não exige nenhuma
// extensão instalada, então funciona em qualquer PHP.
$iniciais = '';
$quantas  = 0;

foreach (explode(' ', $usuario_nome) as $pedaco) {

    // pula "Dr.", "Téc." e "(TI)"
    if ($pedaco == '' || strpos($pedaco, '.') !== false || strpos($pedaco, '(') !== false) {
        continue;
    }

    if (preg_match('/^./u', $pedaco, $achado)) {
        $iniciais = $iniciais . strtoupper($achado[0]);
        $quantas  = $quantas + 1;
    }

    if ($quantas == 2) {
        break;
    }
}
?>
<!doctype html>
<html
  lang="pt-BR"
  class="layout-menu-fixed layout-compact"
  data-assets-path="assets/"
  data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title><?php echo $titulo; ?> · Prontuário TDS</title>

  <!-- O ícone da aba. Três declarações, e cada uma tem um motivo:
         .ico    o navegador procura /favicon.ico sozinho, mesmo sem
                 link nenhum — sem este, ele acharia o do Sneat
         .png    32px, o que a aba realmente mostra
         180px   o que o iPhone usa quando alguém salva na tela inicial -->
  <link rel="icon" type="image/x-icon" href="assets/img/favicon/prontus.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/img/prontus-32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/img/prontus-180.png">

  <!-- Fonte e ícones -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="assets/vendor/fonts/iconify-icons.css">

  <!-- CSS do template -->
  <link rel="stylesheet" href="assets/vendor/css/core.css">
  <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css">

  <!-- CSS nosso (cor verde, destaque de alergia, impressão) -->
  <link rel="stylesheet" href="assets/css/prontuario.css">

  <!-- Precisa vir no <head>, antes de tudo -->
  <script src="assets/vendor/js/helpers.js"></script>
</head>

<body>

<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">

    <!-- ============================================================
         MENU LATERAL

         Repare nos if: cada item só é escrito no HTML se o perfil
         de quem está logado tiver direito a ele. É a matriz de
         permissões do documento de escopo virando código.

         MAS ATENÇÃO: esconder o item do menu NÃO é segurança.
         Qualquer pessoa consegue digitar o endereço da página no
         navegador. A verificação precisa se repetir DENTRO de cada
         página, no PHP. Esconder aqui é só para não confundir quem
         não pode usar.
         ============================================================ -->
    <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

      <div class="app-brand">
        <a href="painel.php" class="app-brand-link">
          <span class="app-brand-logo">
            <?php
            // O logotipo é uma imagem, não um ícone de fonte. A altura
            // vem do CSS (.marca-prontus) e não de atributo aqui —
            // assim o tamanho muda num lugar só.
            //
            // O alt fica vazio de propósito: o nome do sistema já está
            // escrito ao lado, em texto. Repetir "Prontus" no alt faria
            // o leitor de tela dizer o nome duas vezes.
            ?>
            <img src="assets/img/prontus-64.png" alt="" class="marca-prontus">
          </span>
          <span class="app-brand-text menu-text fw-bold ms-2">Prontuário TDS</span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
          <i class="bx bx-chevron-left d-block d-xl-none align-middle"></i>
        </a>
      </div>

      <div class="menu-divider mt-0"></div>
      <div class="menu-inner-shadow"></div>

      <ul class="menu-inner py-1">

        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">Assistencial</span>
        </li>

        <!-- Painel de leitos: todos os perfis -->
        <li class="menu-item <?php if ($pagina_atual == 'painel.php') { echo 'active'; } ?>">
          <a href="painel.php" class="menu-link">
            <i class="menu-icon tf-icons bx bx-bed"></i>
            <div class="text-truncate">Painel de leitos</div>
          </a>
        </li>

        <!-- Pacientes: todos veem os dados cadastrais -->
        <li class="menu-item <?php if ($pagina_atual == 'paciente_listar.php') { echo 'active'; } ?>">
          <a href="paciente_listar.php" class="menu-link">
            <i class="menu-icon tf-icons bx bx-user"></i>
            <div class="text-truncate">Pacientes</div>
          </a>
        </li>

        <!-- Movimentação e leitos: só a recepção -->
        <?php if ($usuario_perfil == 'recepcao') { ?>
          <li class="menu-item <?php if ($pagina_atual == 'internacao_movimentar.php') { echo 'active'; } ?>">
            <a href="internacao_movimentar.php" class="menu-link">
              <i class="menu-icon tf-icons bx bx-transfer"></i>
              <div class="text-truncate">Movimentação</div>
            </a>
          </li>
          <li class="menu-item <?php if ($pagina_atual == 'leito_listar.php' || $pagina_atual == 'leito_form.php') { echo 'active'; } ?>">
            <a href="leito_listar.php" class="menu-link">
              <i class="menu-icon tf-icons bx bx-grid-alt"></i>
              <div class="text-truncate">Leitos</div>
            </a>
          </li>
        <?php } ?>

        <!-- Sinais vitais: TODOS MENOS A RECEPÇÃO.
             É a linha mais importante da matriz de permissões do
             escopo: a recepção cadastra o paciente e escolhe o leito,
             mas não vê um dado clínico dele. Acesso mínimo necessário,
             que a LGPD exige de sistema de saúde.
             Só o técnico afere; médico e administrador leem. -->
        <?php if ($usuario_perfil != 'recepcao') { ?>
          <li class="menu-item <?php if ($pagina_atual == 'sinal_listar.php' || $pagina_atual == 'sinal_form.php' || $pagina_atual == 'sinal_historico.php') { echo 'active'; } ?>">
            <a href="sinal_listar.php" class="menu-link">
              <i class="menu-icon tf-icons bx bx-pulse"></i>
              <div class="text-truncate">Sinais vitais</div>
            </a>
          </li>
        <?php } ?>

        <!-- Registros: também todos menos a recepção.
             Mas ESCREVER é só de dois: o técnico escreve anotação de
             enfermagem, o médico escreve evolução médica. O tipo vem do
             perfil, nunca de um <select>. -->
        <?php if ($usuario_perfil != 'recepcao') { ?>
          <li class="menu-item <?php if ($pagina_atual == 'registro_listar.php' || $pagina_atual == 'registro_form.php' || $pagina_atual == 'registro_historico.php') { echo 'active'; } ?>">
            <a href="registro_listar.php" class="menu-link">
              <i class="menu-icon tf-icons bx bx-notepad"></i>
              <div class="text-truncate">Registros</div>
            </a>
          </li>
        <?php } ?>

        <!-- Alta médica: só o médico define diagnóstico e dá alta -->
        <?php if ($usuario_perfil == 'medico') { ?>
          <li class="menu-item <?php if ($pagina_atual == 'internacao_alta.php') { echo 'active'; } ?>">
            <a href="internacao_alta.php" class="menu-link">
              <i class="menu-icon tf-icons bx bx-user-check"></i>
              <div class="text-truncate">Alta médica</div>
            </a>
          </li>
        <?php } ?>

        <!-- Prescrições: também todos menos a recepção.
             Prescrever e suspender é só do médico; o técnico lê aqui e
             executa na tela do turno. -->
        <?php if ($usuario_perfil != 'recepcao') { ?>
          <li class="menu-item <?php if ($pagina_atual == 'prescricao_listar.php' || $pagina_atual == 'prescricao_form.php' || $pagina_atual == 'prescricao_historico.php') { echo 'active'; } ?>">
            <a href="prescricao_listar.php" class="menu-link">
              <i class="menu-icon tf-icons bx bx-capsule"></i>
              <div class="text-truncate">Prescrições</div>
            </a>
          </li>
        <?php } ?>

        <!-- Medicações: só o técnico de enfermagem checa.
             Este é o ÚNICO item de menu com submenu, e tem um motivo:
             a tela respondia duas perguntas diferentes ao mesmo tempo
             — "o que eu tenho que fazer agora?" e "o que já foi
             feito?". A primeira é urgente, a segunda é consulta, e a
             segunda cresce todo dia até empurrar a primeira para fora
             da tela.

             O menu-toggle e o menu-sub são do próprio Sneat; a única
             coisa nossa é abrir o submenu quando uma das duas telas
             está aberta. -->
        <?php if ($usuario_perfil == 'tecnico') { ?>
          <?php
          // As duas telas do submenu. Se estamos numa delas, o pai
          // fica aceso e o submenu já abre.
          $telas_medicacao = array('medicacao_turno.php', 'medicacao_historico.php');
          $no_medicacao = in_array($pagina_atual, $telas_medicacao);

          // Quantas doses estão atrasadas agora. O número aparece no
          // menu para o técnico saber que tem coisa vencida sem
          // precisar abrir a tela.
          //
          // A consulta mora aqui porque o cabeçalho é a única parte do
          // sistema que aparece em toda página — e o aviso só serve se
          // aparecer em todas.
          $atrasadas_no_menu = 0;

          if (isset($conexao)) {

              $sql_atraso = "SELECT COUNT(*) AS quantas
                             FROM administracoes a
                             JOIN prescricoes  pr ON pr.id = a.prescricao_id AND pr.ativo = 1
                             JOIN internacoes  i  ON i.id = pr.internacao_id
                                                 AND i.situacao = 'internado'
                                                 AND i.ativo = 1
                             WHERE a.status = 'pendente'
                               AND a.horario_previsto < NOW()";

              $res_atraso = mysqli_query($conexao, $sql_atraso);

              if ($res_atraso) {
                  $linha_atraso = mysqli_fetch_assoc($res_atraso);
                  $atrasadas_no_menu = (int) $linha_atraso['quantas'];
              }
          }
          ?>
          <li class="menu-item <?php if ($no_medicacao) { echo 'active open'; } ?>">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-list-check"></i>
              <div class="text-truncate">Medicações</div>
              <?php if ($atrasadas_no_menu > 0) { ?>
                <span class="badge bg-label-danger rounded-pill ms-auto">
                  <?php echo $atrasadas_no_menu; ?>
                </span>
              <?php } ?>
            </a>

            <ul class="menu-sub">
              <li class="menu-item <?php if ($pagina_atual == 'medicacao_turno.php') { echo 'active'; } ?>">
                <a href="medicacao_turno.php" class="menu-link">
                  <div class="text-truncate">Doses a checar</div>
                </a>
              </li>
              <li class="menu-item <?php if ($pagina_atual == 'medicacao_historico.php') { echo 'active'; } ?>">
                <a href="medicacao_historico.php" class="menu-link">
                  <div class="text-truncate">Histórico checado</div>
                </a>
              </li>
            </ul>
          </li>
        <?php } ?>

        <!-- Administração: só o administrador -->
        <?php if ($usuario_perfil == 'administrador') { ?>
          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Administração</span>
          </li>
          <li class="menu-item <?php if ($pagina_atual == 'usuario_listar.php' || $pagina_atual == 'usuario_form.php') { echo 'active'; } ?>">
            <a href="usuario_listar.php" class="menu-link">
              <i class="menu-icon tf-icons bx bx-group"></i>
              <div class="text-truncate">Usuários</div>
            </a>
          </li>
          <li class="menu-item <?php if ($pagina_atual == 'setor_listar.php' || $pagina_atual == 'setor_form.php') { echo 'active'; } ?>">
            <a href="setor_listar.php" class="menu-link">
              <i class="menu-icon tf-icons bx bx-buildings"></i>
              <div class="text-truncate">Setores</div>
            </a>
          </li>
        <?php } ?>

      </ul>
    </aside>
    <!-- / MENU LATERAL -->

    <div class="layout-page">

      <!-- ============================================================
           BARRA DE CIMA
           ============================================================ -->
      <nav
        class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
        id="layout-navbar">

        <!-- Botão que abre o menu no celular -->
        <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
          <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
            <i class="icon-base bx bx-menu icon-md"></i>
          </a>
        </div>

        <div class="navbar-nav-right d-flex align-items-center justify-content-end w-100">

          <div class="navbar-nav align-items-center me-auto">
            <div class="nav-item">
              <h5 class="mb-0"><?php echo $titulo; ?></h5>
              <?php if ($subtitulo != '') { ?>
                <small class="text-body-secondary"><?php echo $subtitulo; ?></small>
              <?php } ?>
            </div>
          </div>

          <ul class="navbar-nav flex-row align-items-center ms-auto">

            <li class="nav-item me-3 d-none d-md-block">
              <span class="selo-perfil perfil-<?php echo $usuario_perfil; ?>">
                <?php echo $perfil_na_tela; ?>
              </span>
            </li>

            <li class="nav-item navbar-dropdown dropdown-user dropdown">
              <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                <div class="avatar">
                  <span class="avatar-initial rounded-circle perfil-<?php echo $usuario_perfil; ?>">
                    <?php echo $iniciais; ?>
                  </span>
                </div>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <div class="dropdown-item-text">
                    <h6 class="mb-0"><?php echo $usuario_nome; ?></h6>
                    <small class="text-body-secondary"><?php
                      // AQUI MORA A VERDADE.
                      //
                      // O selo e o avatar na barra mostram o papel
                      // ASSUMIDO, de propósito: a tela tem que ficar
                      // idêntica à daquele perfil. Esta linha é o único
                      // lugar do cabeçalho que diz quem a pessoa é de
                      // fato — e por isso ela mostra o perfil REAL.
                      //
                      // Para quem não emprestou papel nenhum, os dois
                      // são o mesmo e a linha sai igual a sempre.
                      echo nomeDoPerfil($usuario_perfil_real);

                      if ($papel_emprestado) {
                          echo ' · vendo como <strong>'
                             . $perfil_na_tela . '</strong>';
                      }
                    ?></small>
                  </div>
                </li>

                <?php
                // ========================================================
                //  VER O SISTEMA COMO OUTRO PERFIL
                //
                //  Só para o administrador, e só com o recurso ligado no
                //  config/papel.php. Para todos os outros, este bloco não
                //  chega ao navegador e o menu fica idêntico ao que era
                //  antes de ele existir.
                //
                //  Esconder o item NÃO é segurança — é a mesma observação
                //  que está no menu lateral. Quem tranca é o
                //  papel_trocar.php.
                // ========================================================
                if ($pode_assumir_papel) {
                ?>
                  <li><div class="dropdown-divider my-1"></div></li>
                  <li><h6 class="dropdown-header py-1">Ver o sistema como</h6></li>
                  <?php
                  // A lista vem do includes/perfis.php, não escrita à
                  // mão. Se um quinto perfil nascer, ele aparece aqui
                  // sozinho.
                  foreach (listaDePerfis() as $chave => $nome_do_perfil) {

                      // O papel de agora não é link: já está nele.
                      $eh_o_atual = ($chave == $usuario_perfil);
                  ?>
                    <li>
                      <?php if ($eh_o_atual) { ?>
                        <span class="dropdown-item active d-flex align-items-center">
                          <?php echo $nome_do_perfil; ?>
                          <i class="icon-base bx bx-check icon-md ms-auto"></i>
                        </span>
                      <?php } else { ?>
                        <a class="dropdown-item"
                           href="papel_trocar.php?papel=<?php echo $chave; ?>"><?php echo $nome_do_perfil; ?></a>
                      <?php } ?>
                    </li>
                  <?php } ?>
                <?php } ?>

                <li><div class="dropdown-divider my-1"></div></li>
                <li>
                  <a class="dropdown-item" href="sair.php">
                    <i class="icon-base bx bx-power-off icon-md me-3"></i><span>Sair</span>
                  </a>
                </li>
              </ul>
            </li>
          </ul>

        </div>
      </nav>
      <!-- / BARRA DE CIMA -->

      <!-- ============================================================
           AQUI COMEÇA O CONTEÚDO DA TELA
           ============================================================ -->
      <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">
