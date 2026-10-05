<?php
// ===================================================================
//  registro_historico.php  —  A linha do tempo do prontuário
//  Módulo 4 · Sprint 6
//
//  Todos os registros do paciente em ordem de hora, com nome, perfil e
//  registro profissional de quem escreveu cada um.
//
//  Anotação de enfermagem e evolução médica aparecem na MESMA coluna,
//  intercaladas. É assim que se lê um prontuário: a conduta do médico
//  às 9h só faz sentido depois da febre que o técnico registrou às 6h.
//  Separar em duas listas esconderia justamente essa relação.
//
//  PERMISSÃO: administrador, médico e técnico. A recepção não.
//
//  ORDEM: do MAIS RECENTE para o mais antigo, igual aos sinais vitais.
//  Quem abre o prontuário no plantão quer saber o que aconteceu agora,
//  não o que aconteceu na admissão — e num paciente de duas semanas o
//  registro de hoje estaria no fim de uma página longa. O sistema
//  inteiro usa a mesma ordem, então ninguém precisa lembrar qual tela
//  inverte.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('administrador', 'medico', 'tecnico'));

require 'config/conexao.php';
require 'includes/alergia.php';
require 'includes/registros.php';
require 'includes/perfis.php';

$meu_perfil = $_SESSION['usuario_perfil'];
$eu_escrevo = perfilEscreveRegistro($meu_perfil);
$meu_tipo   = tipoDoPerfil($meu_perfil);

$paciente_id = isset($_GET['paciente_id']) ? (int) $_GET['paciente_id'] : 0;

if ($paciente_id == 0) {
    header('Location: registro_listar.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  O paciente. Não se exige internação: prontuário de quem teve alta
//  continua existindo e continua podendo ser lido. A internação decide
//  só se o botão de escrever aparece.
// -------------------------------------------------------------------
$sql = "SELECT p.id, p.nome,
               COALESCE(p.alergias, '') AS alergias,
               TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
               COALESCE(l.identificacao, '') AS leito,
               i.id AS internacao_id
        FROM pacientes p
        LEFT JOIN internacoes i ON i.paciente_id = p.id
                               AND i.situacao = 'internado'
                               AND i.ativo = 1
        LEFT JOIN leitos l ON l.id = i.leito_id
        WHERE p.id = ? AND p.ativo = 1";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
mysqli_stmt_execute($stmt);
$paciente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$paciente) {
    mysqli_close($conexao);
    header('Location: registro_listar.php?erro=nao_encontrado');
    exit;
}

$esta_internado = ($paciente['internacao_id'] !== null);

// -------------------------------------------------------------------
//  Os registros, do mais recente para o mais antigo.
// -------------------------------------------------------------------
$sql = "SELECT r.id, r.tipo, r.data_hora, r.texto,
               COALESCE(u.nome, '') AS autor,
               COALESCE(u.perfil, '') AS perfil_autor,
               COALESCE(u.registro_profissional, '') AS registro
        FROM registros r
        LEFT JOIN usuarios u ON u.id = r.usuario_id
        WHERE r.paciente_id = ?
        ORDER BY r.data_hora DESC";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
mysqli_stmt_execute($stmt);
$registros = mysqli_stmt_get_result($stmt);
$quantos   = mysqli_num_rows($registros);

$titulo    = 'Registros';
$subtitulo = $paciente['nome'];
require 'includes/cabecalho.php';
?>

<?php
if (isset($_GET['ok']) && $_GET['ok'] == 'gravado') {
?>
  <div class="alert alert-success d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-check-circle me-2"></i>
    <div>Registro gravado.</div>
  </div>
<?php
}
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="row g-3 align-items-center">
      <div class="col-md-7">
        <h5 class="mb-1"><?php echo htmlspecialchars($paciente['nome']); ?></h5>
        <span class="text-body-secondary">
          <?php echo $paciente['idade']; ?> anos
          <?php if ($esta_internado && $paciente['leito'] != '') { ?>
            · Leito <strong><?php echo htmlspecialchars($paciente['leito']); ?></strong>
          <?php } else if (!$esta_internado) { ?>
            · <span class="badge bg-label-secondary">Não internado</span>
          <?php } ?>
        </span>
      </div>
      <div class="col-md-5">
        <?php
        $texto_alergia = trim($paciente['alergias']);
        $nega = negaAlergias($texto_alergia);
        if (!$nega) {
        ?>
          <div class="alerta-alergia">
            <i class="icon-base bx bx-error-circle"></i>
            <span><?php echo htmlspecialchars($texto_alergia); ?></span>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h5 class="mb-0">Prontuário</h5>
    <small class="text-body-secondary">
      <?php echo $quantos; ?>
      <?php echo ($quantos == 1 ? 'registro' : 'registros'); ?>
      · do mais recente para o mais antigo
    </small>
  </div>
  <div class="d-flex gap-2">
    <a href="registro_listar.php" class="btn btn-outline-secondary btn-sm">Voltar</a>
    <?php if ($eu_escrevo && $esta_internado) { ?>
      <a href="registro_form.php?paciente_id=<?php echo $paciente['id']; ?>"
         class="btn btn-primary btn-sm">
        <i class="icon-base bx bx-plus me-1"></i> <?php echo nomeDoTipo($meu_tipo); ?>
      </a>
    <?php } ?>
  </div>
</div>

<div class="mb-2">
  <small class="text-body-secondary">
    <span class="selo-perfil perfil-tecnico">Anotação</span> do técnico de enfermagem
    &nbsp;·&nbsp;
    <span class="selo-perfil perfil-medico">Evolução</span> do médico
  </small>
</div>

<div class="card">
  <div class="card-body">

    <?php
    if ($quantos == 0) {
    ?>
      <p class="text-body-secondary mb-0 text-center py-4">
        Nenhum registro no prontuário deste paciente.
      </p>
    <?php
    } else {
    ?>
      <div class="linha-tempo">
      <?php
      $dia_anterior = '';

      while ($r = mysqli_fetch_assoc($registros)) {

          // Quebra por dia: um separador aparece quando a data muda.
          // Num prontuário de vários dias, sem isso todas as horas
          // viram uma lista sem começo de plantão.
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
            <span class="registro-hora">
              <?php echo date('H:i', strtotime($r['data_hora'])); ?>
            </span>
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

<?php
mysqli_stmt_close($stmt);
mysqli_close($conexao);
require 'includes/rodape.php';
?>
