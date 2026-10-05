<?php
// ===================================================================
//  paciente_ver.php  —  A ficha do paciente
//  Módulo 6 · Sprint 8 · TODOS os perfis, cada um vendo o seu
//
//  A tela para onde o painel de leitos aponta quando se clica no
//  cartão de um paciente. É o prontuário reunido num lugar só.
//
//  ┌──────────────────────────────────────────────────────────────┐
//  │  UMA TELA, QUATRO COMPORTAMENTOS                             │
//  │    recepção      vê SÓ os dados cadastrais                    │
//  │    administrador vê tudo, e não tem botão nenhum              │
//  │    médico        vê tudo, com os botões de prescrever e alta   │
//  │    técnico       vê tudo, com os botões de aferir e registrar  │
//  └──────────────────────────────────────────────────────────────┘
//
//  É aqui que a matriz de permissões do escopo deixa de ser bloqueio
//  e passa a ser COMPORTAMENTO: ninguém é barrado na porta, mas cada
//  um recebe uma tela diferente.
//
//  BLOCOS, NÃO ABAS. O escopo permitia os dois. Escolhemos blocos
//  empilhados por um motivo prático: aba escondida NÃO SAI NA
//  IMPRESSÃO. Com blocos, o botão Imprimir entrega o prontuário
//  inteiro, que é justamente o que se quer levar para a visita.
//  E de quebra a tela funciona sem depender de JavaScript.
//
//  UM EPISÓDIO POR VEZ. A ficha mostra UMA internação — a atual, ou a
//  escolhida no seletor quando o paciente já esteve aqui antes. Os
//  dados clínicos são filtrados por `internacao_id`, então nada de uma
//  estadia aparece dentro da outra. Isso só é possível por causa das
//  migrações v3 e v4.
// ===================================================================

require 'includes/protege.php';
require 'config/conexao.php';
require 'includes/alergia.php';
require 'includes/perfis.php';
require 'includes/faixas.php';
require 'includes/registros.php';
require 'includes/prescricoes.php';

$meu_perfil = $_SESSION['usuario_perfil'];

// A recepção cadastra o paciente e não vê dado clínico. Mesma regra
// das telas de sinais vitais, registros e prescrições — aqui ela não
// barra a tela, apenas esconde os blocos.
$ve_clinico = ($meu_perfil != 'recepcao');

$eh_medico  = ($meu_perfil == 'medico');
$eh_tecnico = ($meu_perfil == 'tecnico');
$eh_recepcao = ($meu_perfil == 'recepcao');

$paciente_id = isset($_GET['paciente_id']) ? (int) $_GET['paciente_id'] : 0;

if ($paciente_id == 0) {
    header('Location: painel.php');
    exit;
}

// -------------------------------------------------------------------
//  1. O PACIENTE — o que vale para a vida inteira dele
// -------------------------------------------------------------------
$sql = "SELECT p.id, p.nome, p.data_nascimento, p.sexo,
               COALESCE(p.cartao_sus, '')  AS cartao_sus,
               COALESCE(p.telefone, '')    AS telefone,
               COALESCE(p.endereco, '')    AS endereco,
               COALESCE(p.responsavel, '') AS responsavel,
               COALESCE(p.alergias, '')    AS alergias,
               TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
               COALESCE(u.nome, '') AS cadastrado_por
        FROM pacientes p
        LEFT JOIN usuarios u ON u.id = p.cadastrado_por
        WHERE p.id = ? AND p.ativo = 1";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
mysqli_stmt_execute($stmt);
$paciente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$paciente) {
    mysqli_close($conexao);
    header('Location: painel.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  2. AS INTERNAÇÕES DELE
//     A mais recente primeiro. É a lista do seletor de episódio.
// -------------------------------------------------------------------
$sql = "SELECT i.id, i.data_admissao, i.data_alta, i.situacao,
               TIMESTAMPDIFF(DAY, i.data_admissao,
                             COALESCE(i.data_alta, NOW())) AS dias,
               COALESCE(l.identificacao, '') AS leito,
               COALESCE(s.nome, '')          AS setor,
               COALESCE(c.codigo, '')        AS cid_codigo,
               COALESCE(c.descricao, '')     AS cid_descricao,
               COALESCE(ua.nome, '') AS admitido_por,
               COALESCE(ul.nome, '') AS alta_por
        FROM internacoes i
        LEFT JOIN leitos   l  ON l.id = i.leito_id
        LEFT JOIN setores  s  ON s.id = l.setor_id
        LEFT JOIN cids     c  ON c.id = i.cid_id
        LEFT JOIN usuarios ua ON ua.id = i.admitido_por
        LEFT JOIN usuarios ul ON ul.id = i.alta_usuario_id
        WHERE i.paciente_id = ? AND i.ativo = 1
        ORDER BY i.data_admissao DESC";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$internacoes = array();
while ($linha = mysqli_fetch_assoc($res)) {
    $internacoes[] = $linha;
}
mysqli_stmt_close($stmt);

// -------------------------------------------------------------------
//  Qual episódio está aberto na ficha
//
//  Sem escolha explícita, abre a primeira da lista — que é a mais
//  recente, e é a internação em curso quando existe uma.
//
//  O id que chega pela URL é conferido contra as internações DESTE
//  paciente. Sem isso, trocar o número na barra de endereço mostraria
//  o episódio de outra pessoa dentro da ficha desta.
// -------------------------------------------------------------------
$internacao = null;
$pedido = isset($_GET['internacao_id']) ? (int) $_GET['internacao_id'] : 0;

foreach ($internacoes as $i) {
    if ($i['id'] == $pedido) {
        $internacao = $i;
    }
}

if (!$internacao && count($internacoes) > 0) {
    $internacao = $internacoes[0];
}

$internacao_id  = ($internacao ? $internacao['id'] : 0);
// ATENÇÃO AO QUE ESTA LINHA MEDE.
//
// Ela pergunta se O EPISÓDIO ABERTO NA TELA está em andamento — não se
// o paciente está internado hoje. As duas coisas parecem a mesma e não
// são: um paciente que teve alta e voltou está internado AGORA, mas se
// quem abriu a ficha escolheu no seletor a internação antiga, o que está
// na tela é um episódio encerrado.
//
// É esta variável que decide se os botões de escrever aparecem. Se ela
// medisse o paciente em vez do episódio, o botão apareceria olhando a
// internação de março e o registro seria gravado na de agosto — porque o
// registro_salvar.php procura a internação EM ANDAMENTO. A pessoa veria
// o texto sumir da tela onde escreveu e aparecer em outra.
//
// Daí a regra: EPISÓDIO ENCERRADO, FICHA SÓ DE LEITURA.
$esta_internado = ($internacao && $internacao['situacao'] == 'internado');

// Numeração de leitura: a lista vem da mais nova para a mais velha,
// então a "internação 1" é a última do array.
$numero_do_episodio = 0;
foreach ($internacoes as $posicao => $i) {
    if ($i['id'] == $internacao_id) {
        $numero_do_episodio = count($internacoes) - $posicao;
    }
}

// -------------------------------------------------------------------
//  3. OS DADOS CLÍNICOS DO EPISÓDIO
//     Só para quem pode ver, e só se houver episódio.
// -------------------------------------------------------------------
$sinais     = null;
$registros  = null;
$prescricoes = null;

if ($ve_clinico && $internacao_id > 0) {

    // 3a. Sinais vitais, do mais recente para o mais antigo
    $sql = "SELECT sv.data_hora,
                   sv.pa_sistolica, sv.pa_diastolica,
                   sv.frequencia_cardiaca, sv.frequencia_respiratoria,
                   sv.temperatura, sv.saturacao, sv.glicemia, sv.escala_dor,
                   COALESCE(sv.observacao, '') AS observacao,
                   COALESCE(u.nome, '') AS aferido_por
            FROM sinais_vitais sv
            LEFT JOIN usuarios u ON u.id = sv.usuario_id
            WHERE sv.internacao_id = ?
            ORDER BY sv.data_hora DESC";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $internacao_id);
    mysqli_stmt_execute($stmt);
    $sinais = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    // 3b. Registros — anotações e evoluções, intercaladas
    $sql = "SELECT r.tipo, r.data_hora, r.texto,
                   COALESCE(u.nome, '') AS autor,
                   COALESCE(u.perfil, '') AS perfil_autor,
                   COALESCE(u.registro_profissional, '') AS registro
            FROM registros r
            LEFT JOIN usuarios u ON u.id = r.usuario_id
            WHERE r.internacao_id = ?
            ORDER BY r.data_hora DESC";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $internacao_id);
    mysqli_stmt_execute($stmt);
    $registros = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    // 3c. Prescrições, com a contagem de doses de cada uma
    $sql = "SELECT pr.id, pr.dose, pr.via, pr.frequencia,
                   COALESCE(pr.horarios, '')   AS horarios,
                   pr.data_inicio,
                   COALESCE(pr.data_fim, '')   AS data_fim,
                   COALESCE(pr.observacao, '') AS observacao,
                   pr.ativo,
                   m.nome AS medicamento,
                   COALESCE(m.apresentacao, '') AS apresentacao,
                   COALESCE(u.nome, '') AS prescrito_por,
                   (SELECT COUNT(*) FROM administracoes
                     WHERE prescricao_id = pr.id AND status = 'administrado') AS dadas,
                   (SELECT COUNT(*) FROM administracoes
                     WHERE prescricao_id = pr.id AND status = 'nao_administrado') AS nao_dadas,
                   (SELECT COUNT(*) FROM administracoes
                     WHERE prescricao_id = pr.id AND status = 'pendente') AS pendentes
            FROM prescricoes pr
            JOIN medicamentos m ON m.id = pr.medicamento_id
            LEFT JOIN usuarios u ON u.id = pr.usuario_id
            WHERE pr.internacao_id = ?
            ORDER BY pr.ativo DESC, pr.criado_em DESC";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $internacao_id);
    mysqli_stmt_execute($stmt);
    $prescricoes = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
}

$titulo    = 'Ficha do paciente';
$subtitulo = $paciente['nome'];
require 'includes/cabecalho.php';

// A alergia é calculada uma vez e usada em vários blocos da ficha.
$texto_alergia = trim($paciente['alergias']);
$nega_alergia  = negaAlergias($texto_alergia);
?>

<?php
// ================================================================
//  A TARJA QUE SÓ EXISTE NO PAPEL
//
//  Na tela ela não aparece; ao imprimir, vira a primeira linha da
//  folha. É a classe `d-none d-print-block` do Bootstrap fazendo isso.
//
//  Por que ela existe: uma folha impressa daqui sai andando pela
//  escola sem contexto nenhum. Sem essa tarja, um papel com nome,
//  idade, diagnóstico e medicação é indistinguível de um prontuário
//  de verdade. A tarja diz, na própria folha, que aquilo é exercício
//  e que a pessoa não existe.
//
//  A data e a hora completam: prontuário impresso sem o momento da
//  impressão é armadilha, porque quem lê semana que vem acha que está
//  vendo a situação de hoje.
// ================================================================
?>
<div class="d-none d-print-block tarja-treino">
  DOCUMENTO DE TREINAMENTO — DADOS FICTÍCIOS — NÃO É PRONTUÁRIO DE PACIENTE REAL
  <span class="tarja-quando">
    Impresso em <?php echo date('d/m/Y \à\s H:i'); ?>
    por <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>
  </span>
</div>

<!-- ================================================================
     CABEÇALHO DA FICHA — aparece na impressão
     ================================================================ -->
<div class="card mb-3">
  <div class="card-body">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h4 class="mb-1"><?php echo htmlspecialchars($paciente['nome']); ?></h4>
        <div class="text-body-secondary">
          <?php echo $paciente['idade']; ?> anos
          · <?php
             if ($paciente['sexo'] == 'F')      { echo 'Feminino'; }
             else if ($paciente['sexo'] == 'M') { echo 'Masculino'; }
             else                               { echo 'Outro'; }
             ?>
          · nasceu em <?php echo date('d/m/Y', strtotime($paciente['data_nascimento'])); ?>
          <?php if ($internacao && $internacao['leito'] != '') { ?>
            · Leito <strong><?php echo htmlspecialchars($internacao['leito']); ?></strong>
          <?php } ?>
        </div>
      </div>

      <div class="d-flex gap-2 flex-wrap">
        <a href="painel.php" class="btn btn-outline-secondary btn-sm">Voltar ao painel</a>
        <?php
        // O botão de imprimir usa só o @media print do nosso CSS, que
        // esconde menu, barra e botões. Não gera PDF nem depende de
        // biblioteca: o navegador faz o trabalho.
        //
        // QUEM IMPRIME: só o médico e o técnico.
        //
        // A recepção não, porque não vê dado clínico — o que ela
        // imprimiria seria só a ficha cadastral.
        //
        // O administrador também não, e este é o caso que vale comentar:
        // ele VÊ o prontuário inteiro nesta tela e não recebe botão
        // nenhum. É o papel dele no sistema — existe para cuidar de
        // usuários e leitos, não para levar prontuário debaixo do braço.
        //
        // E vale dizer com clareza: esconder o botão NÃO IMPEDE de
        // imprimir. O Ctrl+P do navegador continua funcionando para quem
        // já está com a tela aberta. Isto é uma declaração de papel, não
        // um controle de segurança. Quem protege dado é o exigirPerfil()
        // do servidor, nunca o que a tela mostra ou esconde.
        if ($eh_medico || $eh_tecnico) {
        ?>
          <button type="button" class="btn btn-outline-primary btn-sm"
                  onclick="window.print()">
            <i class="icon-base bx bx-printer me-1"></i> Imprimir
          </button>
        <?php } ?>
      </div>
    </div>

    <?php if (!$nega_alergia) { ?>
      <div class="alerta-alergia mt-3">
        <i class="icon-base bx bx-error-circle"></i>
        <span>ALERGIA: <?php echo htmlspecialchars($texto_alergia); ?></span>
      </div>
    <?php } ?>

  </div>
</div>

<?php
// ================================================================
//  O SELETOR DE EPISÓDIO
//  Só aparece quando há mais de uma internação — numa só, seria uma
//  escolha sem alternativa.
// ================================================================
if (count($internacoes) > 1) {
?>
  <div class="card mb-3">
    <div class="card-body py-3">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <small class="text-body-secondary me-1">
          <?php echo count($internacoes); ?> internações — escolha o episódio:
        </small>
        <?php
        foreach ($internacoes as $posicao => $i) {
            $ordem  = count($internacoes) - $posicao;
            $aberta = ($i['id'] == $internacao_id);
        ?>
          <a href="paciente_ver.php?paciente_id=<?php echo $paciente['id']; ?>&internacao_id=<?php echo $i['id']; ?>"
             class="btn btn-sm <?php echo ($aberta ? 'btn-primary' : 'btn-outline-primary'); ?>">
            <?php echo $ordem; ?>ª ·
            <?php echo date('d/m/y', strtotime($i['data_admissao'])); ?>
            <?php if ($i['data_alta'] === null) { ?>
              → hoje
            <?php } else { ?>
              → <?php echo date('d/m/y', strtotime($i['data_alta'])); ?>
            <?php } ?>
          </a>
        <?php } ?>
      </div>
    </div>
  </div>
<?php
}
?>

<!-- ================================================================
     BLOCO 1 — DADOS DO PACIENTE
     O único que a recepção vê.
     ================================================================ -->
<div class="card mb-3">
  <div class="card-header">
    <h5 class="mb-0">Dados do paciente</h5>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <small class="text-body-secondary d-block">Cartão SUS</small>
        <?php echo ($paciente['cartao_sus'] == '' ? '—' : htmlspecialchars($paciente['cartao_sus'])); ?>
      </div>
      <div class="col-md-4">
        <small class="text-body-secondary d-block">Telefone</small>
        <?php echo ($paciente['telefone'] == '' ? '—' : htmlspecialchars($paciente['telefone'])); ?>
      </div>
      <div class="col-md-4">
        <small class="text-body-secondary d-block">Acompanhante</small>
        <?php echo ($paciente['responsavel'] == '' ? '—' : htmlspecialchars($paciente['responsavel'])); ?>
      </div>
      <div class="col-md-8">
        <small class="text-body-secondary d-block">Endereço</small>
        <?php echo ($paciente['endereco'] == '' ? '—' : htmlspecialchars($paciente['endereco'])); ?>
      </div>
      <div class="col-md-4">
        <small class="text-body-secondary d-block">Alergias</small>
        <?php
        if ($nega_alergia) {
            echo '<span class="text-body-secondary">Nega alergias</span>';
        } else {
            echo '<span class="valor-alterado">' . htmlspecialchars($texto_alergia) . '</span>';
        }
        ?>
      </div>
    </div>

    <?php if ($paciente['cadastrado_por'] != '') { ?>
      <div class="mt-3">
        <small class="text-body-secondary">
          Cadastro feito por <?php echo htmlspecialchars($paciente['cadastrado_por']); ?>
        </small>
      </div>
    <?php } ?>

    <?php
    // A recepção é a única que edita o cadastro — e é a única que não
    // vê o resto da ficha.
    if ($eh_recepcao) {
    ?>
      <div class="mt-3">
        <a href="paciente_form.php?id=<?php echo $paciente['id']; ?>"
           class="btn btn-sm btn-primary">Corrigir cadastro</a>
      </div>
    <?php } ?>
  </div>
</div>

<!-- ================================================================
     BLOCO 2 — A INTERNAÇÃO ABERTA NA FICHA
     ================================================================ -->
<?php if ($internacao) { ?>
  <div class="card mb-3">
    <div class="card-header">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <h5 class="mb-0">
          Internação <?php echo $numero_do_episodio; ?>
          <?php if ($esta_internado) { ?>
            <span class="badge bg-label-primary">internado</span>
          <?php } else { ?>
            <span class="badge bg-label-secondary">alta</span>
          <?php } ?>
        </h5>
        <?php if ($eh_medico && $esta_internado) { ?>
          <a href="internacao_alta.php" class="btn btn-sm btn-outline-primary">
            Diagnóstico e alta
          </a>
        <?php } ?>
      </div>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <small class="text-body-secondary d-block">Admissão</small>
          <?php echo date('d/m/Y H:i', strtotime($internacao['data_admissao'])); ?>
        </div>
        <div class="col-md-3">
          <small class="text-body-secondary d-block">Alta</small>
          <?php
          echo ($internacao['data_alta'] === null
                ? '<span class="text-body-secondary">ainda internado</span>'
                : date('d/m/Y H:i', strtotime($internacao['data_alta'])));
          ?>
        </div>
        <div class="col-md-2">
          <small class="text-body-secondary d-block">Tempo</small>
          <?php echo $internacao['dias']; ?>
          <?php echo ($internacao['dias'] == 1 ? 'dia' : 'dias'); ?>
        </div>
        <div class="col-md-4">
          <small class="text-body-secondary d-block">Leito</small>
          <?php
          if ($internacao['leito'] == '') {
              echo '<span class="text-body-secondary">sem leito</span>';
          } else {
              echo '<strong>' . htmlspecialchars($internacao['leito']) . '</strong>';
              if ($internacao['setor'] != '') {
                  echo ' · ' . htmlspecialchars($internacao['setor']);
              }
          }
          ?>
        </div>
        <div class="col-12">
          <small class="text-body-secondary d-block">Diagnóstico</small>
          <?php if ($internacao['cid_codigo'] == '') { ?>
            <span class="text-body-secondary fst-italic">ainda não definido</span>
          <?php } else { ?>
            <span class="badge bg-label-info"><?php echo htmlspecialchars($internacao['cid_codigo']); ?></span>
            <?php echo htmlspecialchars($internacao['cid_descricao']); ?>
          <?php } ?>
        </div>
      </div>

      <div class="mt-3">
        <small class="text-body-secondary">
          Admitido por <?php echo htmlspecialchars($internacao['admitido_por']); ?>
          <?php if ($internacao['alta_por'] != '') { ?>
            · alta por <?php echo htmlspecialchars($internacao['alta_por']); ?>
          <?php } ?>
        </small>
      </div>
    </div>
  </div>
<?php } else { ?>
  <div class="card mb-3">
    <div class="card-body text-body-secondary">
      Este paciente ainda não foi internado.
      <?php if ($ve_clinico) { ?>
        Sinais vitais, registros e medicações pertencem a uma internação,
        então não há o que mostrar.
      <?php } ?>
    </div>
  </div>
<?php
    // Sem episódio, a ficha acaba aqui. Mostrar os três blocos clínicos
    // dizendo "nada nesta internação" seria pior que não mostrar: dá a
    // entender que existe uma internação vazia, quando não existe
    // internação nenhuma.
    mysqli_close($conexao);
    require 'includes/rodape.php';
    exit;
} ?>

<?php
// ================================================================
//  DAQUI PARA BAIXO É DADO CLÍNICO
//
//  A recepção para aqui. Não é um bloqueio de tela: ela chegou, viu o
//  que lhe cabe, e o resto simplesmente não existe para ela. Acesso
//  mínimo necessário — a linha da matriz do escopo que o próprio
//  documento chama de a mais importante.
// ================================================================
if (!$ve_clinico) {
?>
  <div class="card">
    <div class="card-body text-body-secondary">
      <i class="icon-base bx bx-lock-alt me-1"></i>
      Sinais vitais, registros e prescrições são dados clínicos.
      Seu perfil trabalha com o cadastro e a movimentação.
    </div>
  </div>
<?php
    mysqli_close($conexao);
    require 'includes/rodape.php';
    exit;
}
?>

<!-- ================================================================
     BLOCO 3 — SINAIS VITAIS
     ================================================================ -->
<div class="card mb-3">
  <div class="card-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <h5 class="mb-0">Sinais vitais</h5>
        <small class="text-body-secondary">
          <?php echo ($sinais ? mysqli_num_rows($sinais) : 0); ?>
          <?php echo (($sinais ? mysqli_num_rows($sinais) : 0) == 1 ? 'aferição' : 'aferições'); ?>
          desta internação · <span class="valor-alterado">em vermelho</span>, fora da faixa
        </small>
      </div>
      <?php if ($eh_tecnico && $esta_internado) { ?>
        <a href="sinal_form.php?paciente_id=<?php echo $paciente['id']; ?>"
           class="btn btn-sm btn-primary">
          <i class="icon-base bx bx-pulse me-1"></i> Aferir
        </a>
      <?php } ?>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Data e hora</th>
          <?php foreach (listaDeSinais() as $campo => $faixa) { ?>
            <th class="text-nowrap" title="Normal: <?php echo textoDaFaixa($campo); ?>">
              <?php echo $faixa['curto']; ?>
            </th>
          <?php } ?>
          <th>Aferido por</th>
        </tr>
      </thead>
      <tbody>
      <?php
      if (!$sinais || mysqli_num_rows($sinais) == 0) {
      ?>
        <tr><td colspan="10" class="text-center text-body-secondary py-4">
          Nenhuma aferição nesta internação.
        </td></tr>
      <?php
      } else {
          while ($a = mysqli_fetch_assoc($sinais)) {
      ?>
        <tr>
          <td class="text-nowrap">
            <?php echo date('d/m/Y', strtotime($a['data_hora'])); ?><br>
            <strong><?php echo date('H:i', strtotime($a['data_hora'])); ?></strong>
          </td>
          <?php
          foreach (listaDeSinais() as $campo => $faixa) {
              $valor    = $a[$campo];
              $alterado = estaAlterado($campo, $valor);
          ?>
            <td class="text-nowrap <?php echo ($alterado ? 'valor-alterado' : ''); ?>">
              <?php
              if ($valor === null) {
                  echo '<span class="text-body-secondary">—</span>';
              } else {
                  echo number_format($valor, $faixa['decimais'], ',', '');
              }
              ?>
            </td>
          <?php } ?>
          <td class="text-nowrap"><small><?php echo htmlspecialchars($a['aferido_por']); ?></small></td>
        </tr>
        <?php if ($a['observacao'] != '') { ?>
          <tr>
            <td colspan="10" class="pt-0 text-body-secondary small">
              <i class="icon-base bx bx-message-square-dots me-1"></i>
              <?php echo htmlspecialchars($a['observacao']); ?>
            </td>
          </tr>
        <?php } ?>
      <?php
          }
      }
      ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================================================================
     BLOCO 4 — REGISTROS
     ================================================================ -->
<div class="card mb-3">
  <div class="card-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <h5 class="mb-0">Registros</h5>
        <small class="text-body-secondary">
          <span class="selo-perfil perfil-tecnico">Anotação</span> do técnico
          &nbsp;·&nbsp;
          <span class="selo-perfil perfil-medico">Evolução</span> do médico
        </small>
      </div>
      <?php
      // O tipo vem do perfil: o técnico escreve anotação, o médico
      // escreve evolução. Ninguém escolhe. Ver includes/registros.php.
      if (perfilEscreveRegistro($meu_perfil) && $esta_internado) {
      ?>
        <a href="registro_form.php?paciente_id=<?php echo $paciente['id']; ?>"
           class="btn btn-sm btn-primary">
          <i class="icon-base bx bx-plus me-1"></i>
          <?php echo nomeDoTipo(tipoDoPerfil($meu_perfil)); ?>
        </a>
      <?php } ?>
    </div>
  </div>

  <div class="card-body">
    <?php
    if (!$registros || mysqli_num_rows($registros) == 0) {
    ?>
      <p class="text-body-secondary mb-0 text-center py-3">
        Nenhum registro nesta internação.
      </p>
    <?php
    } else {
    ?>
      <div class="linha-tempo">
      <?php
      $dia_anterior = '';

      while ($r = mysqli_fetch_assoc($registros)) {

          $dia = date('d/m/Y', strtotime($r['data_hora']));

          if ($dia != $dia_anterior) {
              $dia_anterior = $dia;
      ?>
          <div class="text-uppercase text-body-secondary fw-bold mb-2"
               style="font-size:.72rem; letter-spacing:.06em;">
            <?php echo $dia; ?>
          </div>
      <?php
          }
      ?>
        <div class="registro-item <?php echo $r['tipo']; ?>">
          <div class="registro-cabeca">
            <span class="registro-hora"><?php echo date('H:i', strtotime($r['data_hora'])); ?></span>
            <span class="selo-perfil perfil-<?php echo ($r['tipo'] == 'evolucao' ? 'medico' : 'tecnico'); ?>">
              <?php echo nomeCurtoDoTipo($r['tipo']); ?>
            </span>
            <span class="small text-body-secondary">
              <?php echo htmlspecialchars($r['autor']); ?>
              <?php if ($r['perfil_autor'] != '') { ?>
                · <?php echo nomeDoPerfil($r['perfil_autor']); ?>
              <?php } ?>
              <?php if ($r['registro'] != '') { ?>
                · <?php echo htmlspecialchars($r['registro']); ?>
              <?php } ?>
            </span>
          </div>
          <p class="registro-texto"><?php echo htmlspecialchars($r['texto']); ?></p>
        </div>
      <?php } ?>
      </div>
    <?php } ?>
  </div>
</div>

<!-- ================================================================
     BLOCO 5 — MEDICAÇÕES
     ================================================================ -->
<div class="card mb-3">
  <div class="card-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <h5 class="mb-0">Medicações</h5>
        <small class="text-body-secondary">
          <?php echo ($prescricoes ? mysqli_num_rows($prescricoes) : 0); ?>
          <?php echo (($prescricoes ? mysqli_num_rows($prescricoes) : 0) == 1 ? 'prescrição' : 'prescrições'); ?>
          desta internação · ativas primeiro
        </small>
      </div>
      <div class="d-flex gap-2">
        <?php if ($eh_medico && $esta_internado) { ?>
          <a href="prescricao_form.php?paciente_id=<?php echo $paciente['id']; ?>"
             class="btn btn-sm btn-primary">
            <i class="icon-base bx bx-capsule me-1"></i> Prescrever
          </a>
        <?php } ?>
        <?php
        // O atalho para o turno só faz sentido enquanto o paciente
        // está aqui. Na ficha de quem já recebeu alta não há dose
        // para checar, e o botão viraria um convite para nada.
        //
        // Com isso a regra da ficha fica inteira e fácil de dizer:
        // PACIENTE COM ALTA, FICHA SÓ DE LEITURA. Nenhum botão de
        // ação aparece, para nenhum perfil.
        if ($eh_tecnico && $esta_internado) {
        ?>
          <a href="medicacao_turno.php" class="btn btn-sm btn-outline-primary">
            Doses a checar
          </a>
        <?php } ?>
      </div>
    </div>
  </div>

  <div class="card-body">
    <?php
    if (!$prescricoes || mysqli_num_rows($prescricoes) == 0) {
    ?>
      <p class="text-body-secondary mb-0 text-center py-3">
        Nenhuma prescrição nesta internação.
      </p>
    <?php
    } else {
        while ($p = mysqli_fetch_assoc($prescricoes)) {
            $sos = ($p['horarios'] == '');
    ?>
      <div class="border-bottom pb-3 mb-3 <?php echo ($p['ativo'] ? '' : 'opacity-75'); ?>">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <strong><?php echo htmlspecialchars($p['medicamento']); ?></strong>
            <?php if ($p['apresentacao'] != '') { ?>
              <small class="text-body-secondary"><?php echo htmlspecialchars($p['apresentacao']); ?></small>
            <?php } ?>
            <div class="small">
              <?php echo htmlspecialchars($p['dose']); ?>
              · <?php echo htmlspecialchars($p['via']); ?>
              <span class="text-body-secondary">(<?php echo nomeDaVia($p['via']); ?>)</span>
              · <?php echo htmlspecialchars($p['frequencia']); ?>
              <?php if (!$sos) { ?>
                · <?php echo htmlspecialchars(str_replace(',', '  ', $p['horarios'])); ?>
              <?php } ?>
            </div>
            <div class="small text-body-secondary">
              <?php
              // O período em que a prescrição vale. Na suspensa é a
              // informação mais útil do bloco: diz até quando aquele
              // medicamento estava valendo.
              echo 'de ' . date('d/m/Y', strtotime($p['data_inicio']));
              if ($p['data_fim'] == '') {
                  echo ' · sem data de término';
              } else {
                  echo ' até ' . date('d/m/Y', strtotime($p['data_fim']));
              }
              ?>
            </div>
            <?php if ($p['observacao'] != '') { ?>
              <div class="small text-body-secondary">
                <?php echo htmlspecialchars($p['observacao']); ?>
              </div>
            <?php } ?>
          </div>

          <div class="text-end">
            <?php if (!$p['ativo']) { ?>
              <span class="badge bg-label-secondary">Suspensa</span>
            <?php } else if ($sos) { ?>
              <span class="badge bg-label-info">Se necessário</span>
            <?php } else { ?>
              <span class="badge bg-label-primary">Ativa</span>
            <?php } ?>
            <div class="mt-1">
              <?php
              // CONCORDÂNCIA. "1 doses não dadas" é o tipo de erro que a
              // gente só vê depois de a tela estar no ar, porque durante o
              // teste o número quase sempre é maior que um.
              if ($p['dadas'] > 0) {
                  echo '<span class="badge bg-label-success">' . $p['dadas']
                     . ($p['dadas'] == 1 ? ' dada' : ' dadas') . '</span> ';
              }
              if ($p['nao_dadas'] > 0) {
                  echo '<span class="badge bg-label-danger">' . $p['nao_dadas']
                     . ($p['nao_dadas'] == 1 ? ' não dada' : ' não dadas') . '</span> ';
              }
              if ($p['pendentes'] > 0) {
                  echo '<span class="badge bg-label-warning">' . $p['pendentes']
                     . ($p['pendentes'] == 1 ? ' pendente' : ' pendentes') . '</span>';
              }
              ?>
            </div>
            <small class="text-body-secondary d-block mt-1">
              <?php echo htmlspecialchars($p['prescrito_por']); ?>
            </small>
          </div>
        </div>
      </div>
    <?php
        }
    }
    ?>
  </div>
</div>

<?php
mysqli_close($conexao);
require 'includes/rodape.php';
?>
