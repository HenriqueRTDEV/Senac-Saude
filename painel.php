<?php
// ===================================================================
//  painel.php  —  Painel de leitos
//  Módulo 2 · Sprint 4 · TODOS os perfis
//
//  A primeira tela depois do login. Responde de um olhar a pergunta
//  que se faz ao chegar no plantão: como está a ala agora?
//
//  Um cartão por cama. Ocupada mostra quem está nela; livre aparece
//  tracejada; fora de uso aparece apagada.
//
//  ESTA TELA NÃO CHAMA exigirPerfil(). É de propósito, e é a única
//  exceção do sistema: o painel é justamente o lugar para onde o
//  permissao.php manda quem tentou abrir uma tela proibida. Se ele
//  também exigisse perfil, a pessoa seria mandada de volta para uma
//  página que a manda de volta — e o navegador giraria em círculos
//  até dar erro. Por isso o painel aceita os quatro perfis.
// ===================================================================

require 'includes/protege.php';
require 'config/conexao.php';
require 'includes/alergia.php';

// -------------------------------------------------------------------
//  OS DOIS FILTROS
//
//  Eles não fazem a mesma coisa, e a diferença importa:
//
//    SETOR     recorta a ala. Continua sendo um painel: mostra as
//              camas daquele setor, ocupadas E livres.
//
//    PACIENTE  procura uma pessoa. Cama livre não tem nome, então
//              nenhuma casa com a busca — o painel deixa de ser
//              painel e passa a ser resultado de busca.
//
//  Por isso OS QUATRO NÚMEROS DO TOPO NÃO SEGUEM A BUSCA POR NOME.
//  Eles descrevem a ala (ou o setor escolhido), que é o que a
//  pergunta 'como está a unidade?' pede. Se seguissem, buscar um
//  nome mostraria '0 leitos livres' numa ala com dez vagas — um
//  número verdadeiro respondendo à pergunta errada.
// -------------------------------------------------------------------
$busca = '';
if (isset($_GET['busca'])) {
    $busca = trim($_GET['busca']);
}
$curinga = '%' . $busca . '%';

// O setor entra na consulta como número, então é convertido com
// (int) e conferido contra a lista de setores ativos mais abaixo.
// Zero significa 'todos'.
$setor_id = 0;
if (isset($_GET['setor_id'])) {
    $setor_id = (int) $_GET['setor_id'];
}

// A lista do <select>, que também serve para conferir o que chegou.
$res_setores = mysqli_query($conexao,
    "SELECT id, nome FROM setores WHERE ativo = 1 ORDER BY nome");

$setores = array();
while ($s = mysqli_fetch_assoc($res_setores)) {
    $setores[] = $s;
}

// Setor que não existe na lista vira 'todos'. Convenção 14: valor de
// <select> se reconfere, mesmo quando ele só filtra e não grava.
$setor_existe = false;
$nome_do_setor = '';

foreach ($setores as $s) {
    if ($s['id'] == $setor_id) {
        $setor_existe  = true;
        $nome_do_setor = $s['nome'];
    }
}

if (!$setor_existe) {
    $setor_id = 0;
}

// -------------------------------------------------------------------
//  1. OS NÚMEROS DO TOPO
//
//  Uma consulta só, com um SELECT dentro de outro para cada número.
//  Sai mais barato do que quatro consultas separadas, e os quatro
//  números ficam do mesmo instante — se fossem consultas separadas,
//  alguém poderia internar um paciente no meio do caminho e os
//  números não fechariam entre si.
//
//  Repare que "leitos ocupados" conta INTERNAÇÕES que têm leito, não
//  leitos. É o mesmo número visto do outro lado, e é justamente isso
//  que permite a conferência do item 3 mais abaixo.
// -------------------------------------------------------------------
// O filtro de setor entra como 'ou o setor bate, ou nenhum setor foi
// escolhido'. Assim a mesma consulta serve para os dois casos, sem
// dois SQL diferentes para manter.
$sql = "SELECT
          (SELECT COUNT(*) FROM leitos
            WHERE ativo = 1 AND (? = 0 OR setor_id = ?)) AS leitos_ativos,
          (SELECT COUNT(*) FROM leitos
            WHERE ativo = 0 AND (? = 0 OR setor_id = ?)) AS fora_de_uso,
          (SELECT COUNT(*) FROM internacoes i
             LEFT JOIN leitos l ON l.id = i.leito_id
            WHERE i.situacao = 'internado' AND i.ativo = 1
              AND (? = 0 OR l.setor_id = ?)) AS internados,
          (SELECT COUNT(*) FROM internacoes i
             JOIN leitos l ON l.id = i.leito_id
            WHERE i.situacao = 'internado' AND i.ativo = 1
              AND (? = 0 OR l.setor_id = ?)) AS leitos_ocupados";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'iiiiiiii',
    $setor_id, $setor_id, $setor_id, $setor_id,
    $setor_id, $setor_id, $setor_id, $setor_id);
mysqli_stmt_execute($stmt);
$resumo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$leitos_livres = $resumo['leitos_ativos'] - $resumo['leitos_ocupados'];

// Divisão por zero derruba a página. Se ainda não há leito cadastrado,
// a taxa é zero — não é erro, é um hospital vazio.
$taxa = 0;
if ($resumo['leitos_ativos'] > 0) {
    $taxa = round($resumo['leitos_ocupados'] * 100 / $resumo['leitos_ativos']);
}

// -------------------------------------------------------------------
//  2. TODOS OS LEITOS, COM QUEM ESTÁ NELES
//
//  O painel começa a consulta em `leitos`, não em `pacientes`. Isso
//  não é detalhe: um leito VAZIO não tem paciente nenhum, então ele
//  jamais apareceria numa consulta que partisse de `pacientes`. E o
//  leito vazio é metade da informação que o painel precisa dar.
//
//  Daí os LEFT JOIN: "traga o leito de qualquer jeito, e se por acaso
//  houver internação em andamento, traga o paciente e o CID também".
//
//  ESTA CONSULTA JÁ USOU mysqli_query(), e a história vale a aula:
//  enquanto o painel não tinha filtro nenhum, ela não recebia nada de
//  fora — e sem dado de fora não há o que injetar. No dia em que
//  ganhou os dois filtros, passou a receber o nome digitado, e teve de
//  virar mysqli_prepare com os quatro passos.
//
//  Não foi troca de estilo: foi o problema mudando. A técnica segue o
//  problema, não o gosto de quem escreve.
// -------------------------------------------------------------------
$sql = "SELECT l.id, l.identificacao, l.ativo,
               COALESCE(s.nome, 'Sem setor')  AS setor,
               COALESCE(l.observacao, '')     AS observacao,
               p.id                           AS paciente_id,
               COALESCE(p.nome, '')           AS paciente,
               COALESCE(p.sexo, '')           AS sexo,
               COALESCE(p.alergias, '')       AS alergias,
               TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
               COALESCE(c.codigo, '')         AS cid_codigo,
               COALESCE(c.descricao, '')      AS cid_descricao,
               i.data_admissao,
               TIMESTAMPDIFF(DAY, i.data_admissao, NOW()) AS dias
        FROM leitos l
        LEFT JOIN setores s ON s.id = l.setor_id
        LEFT JOIN internacoes i
               ON i.leito_id = l.id
              AND i.situacao = 'internado'
              AND i.ativo = 1
        LEFT JOIN pacientes p ON p.id = i.paciente_id
        LEFT JOIN cids      c ON c.id = i.cid_id
        WHERE (? = 0 OR l.setor_id = ?)
          AND (? = '' OR p.nome LIKE ?)
        ORDER BY setor, l.identificacao";

// A partir daqui a consulta recebe valor de fora, então ela usa os
// quatro passos do mysqli_prepare. Antes usava mysqli_query, porque
// não recebia nada — ver o passo do painel no diário.
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'iiss',
    $setor_id, $setor_id, $busca, $curinga);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

// Agrupa os leitos por setor ANTES de desenhar.
//
// Daria para desenhar direto no while, mas então teríamos que
// adivinhar, a cada volta, se aquela linha é a primeira de um setor
// novo, para abrir o título e a grade na hora certa. Separando em
// duas etapas — primeiro organizo, depois desenho — cada parte faz
// uma coisa só.
//
// O resultado é um array de arrays: a chave é o nome do setor, o
// valor é a lista dos leitos daquele setor.
$por_setor = array();

while ($l = mysqli_fetch_assoc($resultado)) {
    $por_setor[$l['setor']][] = $l;
}

// -------------------------------------------------------------------
//  3. CONFERÊNCIA: internado sem leito
//
//  Pelas telas isso não acontece — a movimentação exige escolher um
//  leito livre. Mas o banco aceita `leito_id` nulo, e quem mexe
//  direto no SQL pode criar essa situação. Se acontecesse, o paciente
//  simplesmente não apareceria no painel, porque o painel percorre
//  leitos: sem leito, sem cartão.
//
//  Paciente invisível no painel é o pior defeito possível numa tela
//  de plantão. Então, em vez de confiar, o painel confere: se os dois
//  números do item 1 não fecharem, ele vai buscar quem está sobrando
//  e mostra um aviso.
// -------------------------------------------------------------------
$sem_leito = null;

if ($resumo['internados'] > $resumo['leitos_ocupados']) {

    $sql = "SELECT p.nome,
                   TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
                   COALESCE(c.codigo, '') AS cid_codigo
            FROM internacoes i
            JOIN pacientes p ON p.id = i.paciente_id
            LEFT JOIN cids  c ON c.id = i.cid_id
            WHERE i.situacao = 'internado' AND i.ativo = 1
              AND i.leito_id IS NULL
            ORDER BY p.nome";

    $sem_leito = mysqli_query($conexao, $sql);
}

$titulo    = 'Painel de leitos';
$subtitulo = 'A ala agora';
require 'includes/cabecalho.php';
?>

<?php
// Quem tentou abrir uma tela sem permissão chega aqui com ?negado=1.
if (isset($_GET['negado'])) {
?>
  <div class="alert alert-danger d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-lock-alt me-2"></i>
    <div>Você não tem permissão para abrir essa tela.</div>
  </div>
<?php
}
?>

<!-- ================================================================
     LEGENDA E ATALHO
     Vêm antes de tudo: quem chega no plantão precisa saber o que cada
     cor quer dizer ANTES de olhar a grade, sem rolar a página.
     ================================================================ -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">

  <div class="small text-body-secondary">
    <span class="me-3">
      <span class="legenda-ponto" style="background:#0a7c74;"></span> Ocupado
    </span>
    <span class="me-3">
      <span class="legenda-ponto" style="background:#d9dee3;"></span> Livre
    </span>
    <span>
      <span class="legenda-ponto" style="background:#a8adb2;"></span> Fora de uso
    </span>
  </div>

  <?php
  // O atalho só aparece para quem tem o que fazer com ele. Mostrar um
  // botão que leva a uma tela proibida é ensinar a bater na porta
  // trancada.
  if ($usuario_perfil == 'recepcao') {
  ?>
    <a href="internacao_movimentar.php" class="btn btn-primary btn-sm">
      <i class="icon-base bx bx-transfer me-1"></i> Internar ou transferir
    </a>
  <?php } else if ($usuario_perfil == 'medico') { ?>
    <a href="internacao_alta.php" class="btn btn-primary btn-sm">
      <i class="icon-base bx bx-user-check me-1"></i> Diagnóstico e alta
    </a>
  <?php } else if ($usuario_perfil == 'tecnico') { ?>
    <a href="sinal_listar.php" class="btn btn-primary btn-sm">
      <i class="icon-base bx bx-pulse me-1"></i> Aferir sinais vitais
    </a>
  <?php } ?>

</div>

<!-- ================================================================
     OS DOIS FILTROS
     ================================================================ -->
<form method="get" action="painel.php" class="mb-3">
  <div class="row g-2">
    <div class="col-md-6">
      <input type="text" name="busca" class="form-control"
             placeholder="Buscar por nome…"
             value="<?php echo htmlspecialchars($busca); ?>">
    </div>
    <div class="col-md-4">
      <select name="setor_id" class="form-select">
        <option value="0">Todos os setores</option>
        <?php foreach ($setores as $s) { ?>
          <option value="<?php echo $s['id']; ?>"
            <?php if ($setor_id == $s['id']) { echo 'selected'; } ?>>
            <?php echo htmlspecialchars($s['nome']); ?>
          </option>
        <?php } ?>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-outline-primary flex-grow-1" type="submit">Filtrar</button>
      <?php if ($busca != '' || $setor_id != 0) { ?>
        <a href="painel.php" class="btn btn-outline-secondary">Limpar</a>
      <?php } ?>
    </div>
  </div>
</form>

<!-- ================================================================
     OS QUATRO NÚMEROS
     ================================================================ -->
<div class="row g-3 mb-2">

  <div class="col-6 col-lg-3">
    <div class="card h-100">
      <div class="card-body py-3">
        <small class="text-body-secondary d-block mb-1">Internados</small>
        <h3 class="mb-0 text-primary"><?php echo $resumo['internados']; ?></h3>
      </div>
    </div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="card h-100">
      <div class="card-body py-3">
        <small class="text-body-secondary d-block mb-1">Leitos livres</small>
        <h3 class="mb-0"><?php echo $leitos_livres; ?></h3>
      </div>
    </div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="card h-100">
      <div class="card-body py-3">
        <small class="text-body-secondary d-block mb-1">Ocupação</small>
        <h3 class="mb-0"><?php echo $taxa; ?>%</h3>
        <div class="progress mt-2" style="height: 5px;">
          <div class="progress-bar" style="width: <?php echo $taxa; ?>%"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="card h-100">
      <div class="card-body py-3">
        <small class="text-body-secondary d-block mb-1">Fora de uso</small>
        <h3 class="mb-0 text-body-secondary"><?php echo $resumo['fora_de_uso']; ?></h3>
      </div>
    </div>
  </div>

</div>

<?php
// -------------------------------------------------------------------
//  O aviso da conferência só aparece quando há algo errado.
// -------------------------------------------------------------------
if ($sem_leito && mysqli_num_rows($sem_leito) > 0) {
?>
  <div class="alert alert-warning mt-3" role="alert">
    <div class="d-flex align-items-center mb-1">
      <i class="icon-base bx bx-error me-2"></i>
      <strong>Internado sem leito</strong>
    </div>
    <div class="small mb-2">
      Estes pacientes estão internados mas não têm leito atribuído, por isso
      não aparecem na grade abaixo. A recepção precisa alocá-los.
    </div>
    <ul class="mb-0 small">
      <?php while ($s = mysqli_fetch_assoc($sem_leito)) { ?>
        <li>
          <?php echo htmlspecialchars($s['nome']); ?>
          · <?php echo $s['idade']; ?> anos
          <?php if ($s['cid_codigo'] != '') { ?>
            · <?php echo htmlspecialchars($s['cid_codigo']); ?>
          <?php } ?>
        </li>
      <?php } ?>
    </ul>
  </div>
<?php
}
?>

<!-- ================================================================
     A GRADE DE LEITOS, UM BLOCO POR SETOR
     ================================================================ -->
<?php
// Quantos leitos a grade está mostrando, somando todos os setores.
// Quando há filtro, este número é menor que o total da ala — e o aviso
// abaixo diz isso, para ninguém achar que os leitos desapareceram.
$leitos_mostrados = 0;
foreach ($por_setor as $do_setor) {
    $leitos_mostrados = $leitos_mostrados + count($do_setor);
}

if ($busca != '' || $setor_id != 0) {
?>
  <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
    <i class="icon-base bx bx-filter-alt me-2"></i>
    <div>
      Mostrando <strong><?php echo $leitos_mostrados; ?></strong>
      <?php echo ($leitos_mostrados == 1 ? 'leito' : 'leitos'); ?>
      <?php if ($setor_id != 0) { ?>
        em <strong><?php echo htmlspecialchars($nome_do_setor); ?></strong>
      <?php } ?>
      <?php if ($busca != '') { ?>
        com <strong><?php echo htmlspecialchars($busca); ?></strong> no nome.
        Os números acima continuam descrevendo
        <?php echo ($setor_id != 0 ? 'o setor inteiro' : 'a ala inteira'); ?>.
      <?php } ?>
    </div>
  </div>
<?php
}

if (count($por_setor) == 0) {
?>
  <div class="card mt-3">
    <div class="card-body text-center text-body-secondary py-5">
      <?php
      // Três situações diferentes, três frases. Dizer "nenhum leito
      // cadastrado" a quem acabou de buscar um nome faria a pessoa
      // procurar defeito onde não há.
      if ($busca != '') {
          echo 'Nenhum leito com paciente que atenda a essa busca.';
      } else if ($setor_id != 0) {
          echo 'Este setor não tem leito cadastrado.';
      } else {
          echo 'Nenhum leito cadastrado ainda.';
      }
      ?>
      <?php if ($usuario_perfil == 'recepcao' && $busca == '' && $setor_id == 0) { ?>
        <div class="mt-2">
          <a href="leito_form.php" class="btn btn-primary btn-sm">Cadastrar o primeiro leito</a>
        </div>
      <?php } ?>
    </div>
  </div>
<?php
}

// A chave do array é o nome do setor; o valor é a lista de leitos dele.
foreach ($por_setor as $nome_setor => $leitos_do_setor) {

    // Conta os leitos deste setor. Percorrer o array de novo é de
    // graça — o banco não é consultado outra vez.
    $ocupados_aqui = 0;
    $ativos_aqui   = 0;

    foreach ($leitos_do_setor as $l) {
        if ($l['ativo']) {
            $ativos_aqui = $ativos_aqui + 1;
        }
        if ($l['paciente_id'] !== null) {
            $ocupados_aqui = $ocupados_aqui + 1;
        }
    }
?>

  <div class="d-flex align-items-center justify-content-between mb-2 mt-4 flex-wrap gap-2">
    <h6 class="mb-0 text-uppercase text-body-secondary" style="letter-spacing:.04em;">
      <?php echo htmlspecialchars($nome_setor); ?>
    </h6>
    <small class="text-body-secondary">
      <?php echo $ocupados_aqui; ?> de <?php echo $ativos_aqui; ?> ocupados
    </small>
  </div>

  <div class="row g-3">

    <?php
    foreach ($leitos_do_setor as $l) {

        // Três situações possíveis, nesta ordem: fora de uso vence
        // tudo (para a ala, a cama não existe), depois ocupado, depois
        // livre. O paciente_id vem nulo quando o LEFT JOIN não achou
        // internação em andamento — é assim que se sabe que a cama
        // está vazia.
        $ocupado = ($l['paciente_id'] !== null);

        if (!$l['ativo']) {
            $situacao = 'inativo';
        } else if ($ocupado) {
            $situacao = 'ocupado';
        } else {
            $situacao = 'livre';
        }
    ?>

      <div class="col-12 col-sm-6 col-xl-4 col-xxl-3">
        <?php
        // O CARTÃO OCUPADO É UM LINK; o livre e o fora de uso, não.
        //
        // A razão é simples: o link leva à ficha de um paciente, e cama
        // vazia não tem paciente para abrir. Um cartão clicável que não
        // leva a nada é pior que um cartão que não convida ao clique.
        //
        // O <a> envolve o cartão inteiro em vez de só o nome, porque a
        // área de clique confortável é o cartão todo — quem está no
        // plantão não quer acertar um alvo de duas palavras.
        //
        // As classes de cor e estado continuam no mesmo elemento; o que
        // muda é a etiqueta, de div para a. O CSS não precisou saber.
        if ($situacao == 'ocupado') {
        ?>
          <a href="paciente_ver.php?paciente_id=<?php echo $l['paciente_id']; ?>"
             class="leito-cartao <?php echo $situacao; ?> leito-link">
        <?php } else { ?>
          <div class="leito-cartao <?php echo $situacao; ?>">
        <?php } ?>

          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="leito-numero"><?php echo htmlspecialchars($l['identificacao']); ?></span>

            <?php if ($situacao == 'inativo') { ?>
              <span class="badge bg-label-secondary">Fora de uso</span>
            <?php } else if ($situacao == 'ocupado') { ?>
              <span class="badge bg-label-primary">Ocupado</span>
            <?php } else { ?>
              <span class="badge bg-label-success">Livre</span>
            <?php } ?>
          </div>

          <?php
          if ($situacao == 'ocupado') {

              // REGRA DE OURO Nº 4 — alergia sempre em destaque.
              // "Nega alergias" é informação registrada, não é alerta:
              // pintar de vermelho quem não tem alergia ensinaria a
              // turma a ignorar o vermelho.
              $texto_alergia = trim($l['alergias']);
              $nega = negaAlergias($texto_alergia);

              // Quantos dias de internação, escrito como se fala.
              if ($l['dias'] == 0) {
                  $tempo = 'admitido hoje';
              } else if ($l['dias'] == 1) {
                  $tempo = 'internado há 1 dia';
              } else {
                  $tempo = 'internado há ' . $l['dias'] . ' dias';
              }
          ?>

            <div class="leito-paciente"><?php echo htmlspecialchars($l['paciente']); ?></div>

            <div class="small text-body-secondary mb-2">
              <?php echo $l['idade']; ?> anos
              <?php if ($l['sexo'] == 'F') { ?>
                · Feminino
              <?php } else if ($l['sexo'] == 'M') { ?>
                · Masculino
              <?php } ?>
            </div>

            <?php if ($l['cid_codigo'] != '') { ?>
              <div class="leito-cid mb-2">
                <span class="badge bg-label-info"><?php echo htmlspecialchars($l['cid_codigo']); ?></span>
                <span class="text-body-secondary">
                  <?php echo htmlspecialchars($l['cid_descricao']); ?>
                </span>
              </div>
            <?php } else { ?>
              <div class="leito-cid mb-2 text-body-secondary fst-italic">
                Diagnóstico ainda não definido
              </div>
            <?php } ?>

            <?php if (!$nega) { ?>
              <div class="alerta-alergia alerta-mini mb-2">
                <i class="icon-base bx bx-error-circle"></i>
                <span><?php echo htmlspecialchars($texto_alergia); ?></span>
              </div>
            <?php } ?>

            <div class="small text-body-secondary">
              <?php echo $tempo; ?>
              · desde <?php echo date('d/m/Y', strtotime($l['data_admissao'])); ?>
            </div>

          <?php
          } else if ($situacao == 'inativo') {
          ?>
            <div class="small text-body-secondary">
              <?php
              echo ($l['observacao'] == ''
                    ? 'Sem observação registrada.'
                    : htmlspecialchars($l['observacao']));
              ?>
            </div>
          <?php
          } else {
          ?>
            <div class="small text-body-secondary">
              Cama disponível para internação.
            </div>
          <?php
          }
          ?>

        <?php
        // Fecha com a mesma etiqueta que abriu. Se as duas nao
        // combinarem, o navegador conserta do jeito dele — e o que
        // ele escolhe nao e o que a gente quis.
        if ($situacao == 'ocupado') {
        ?>
          </a>
        <?php } else { ?>
          </div>
        <?php } ?>
      </div>

    <?php } ?>

  </div>

<?php } ?>

<?php
mysqli_stmt_close($stmt);
mysqli_close($conexao);
require 'includes/rodape.php';
?>
