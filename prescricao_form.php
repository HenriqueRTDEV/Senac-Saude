<?php
// ===================================================================
//  prescricao_form.php  —  Nova prescrição
//  Módulo 5 · Sprint 7 · perfil MÉDICO
//
//  O médico escolhe o medicamento de uma lista — a mesma lista da
//  farmácia, que ele não edita — e define dose, via, frequência e
//  horários.
//
//  Não existe campo de autor nem de data de lançamento: os dois saem
//  da sessão e do relógio no prescricao_salvar.php.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('medico'));

require 'config/conexao.php';
require 'includes/alergia.php';
require 'includes/prescricoes.php';

$paciente_id = isset($_GET['paciente_id']) ? (int) $_GET['paciente_id'] : 0;

if ($paciente_id == 0) {
    header('Location: prescricao_listar.php?erro=nao_encontrado');
    exit;
}

// -------------------------------------------------------------------
//  O paciente existe E está internado?
// -------------------------------------------------------------------
$sql = "SELECT p.id, p.nome,
               COALESCE(p.alergias, '') AS alergias,
               TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
               COALESCE(l.identificacao, '') AS leito,
               COALESCE(s.nome, '') AS setor
        FROM pacientes p
        JOIN internacoes i ON i.paciente_id = p.id
                          AND i.situacao = 'internado'
                          AND i.ativo = 1
        LEFT JOIN leitos  l ON l.id = i.leito_id
        LEFT JOIN setores s ON s.id = l.setor_id
        WHERE p.id = ? AND p.ativo = 1";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
mysqli_stmt_execute($stmt);
$paciente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$paciente) {
    mysqli_close($conexao);
    header('Location: prescricao_listar.php?erro=nao_internado');
    exit;
}

// -------------------------------------------------------------------
//  O catálogo da farmácia. Como os CIDs: existe no banco, e nenhuma
//  tela do sistema o cadastra — manter medicamento é trabalho da
//  farmácia, e o sistema cobre enfermagem.
// -------------------------------------------------------------------
$res = mysqli_query($conexao,
    "SELECT id, nome, COALESCE(apresentacao, '') AS apresentacao,
            COALESCE(via_padrao, '') AS via_padrao
     FROM medicamentos WHERE ativo = 1 ORDER BY nome");

$medicamentos = array();
while ($m = mysqli_fetch_assoc($res)) {
    $medicamentos[] = $m;
}

// Se o salvar recusou, devolve o que foi digitado.
$campos = array('medicamento_id', 'dose', 'via', 'frequencia',
                'horarios', 'data_inicio', 'data_fim', 'observacao');
$digitado = array();
foreach ($campos as $campo) {
    $digitado[$campo] = isset($_GET[$campo]) ? $_GET[$campo] : '';
}

if ($digitado['data_inicio'] == '') {
    $digitado['data_inicio'] = date('Y-m-d');
}

$titulo    = 'Nova prescrição';
$subtitulo = $paciente['nome'];
require 'includes/cabecalho.php';
?>

<?php
$avisos_erro = array(
    'campos'          => 'Preencha medicamento, dose, via e frequência.',
    'medicamento'     => 'Medicamento inválido ou fora de uso.',
    'via'             => 'Via de administração inválida.',
    'horarios'        => 'Horários inválidos. Escreva no formato 06:00, separando por vírgula — ou deixe em branco para "se necessário".',
    'data'            => 'Data de início inválida.',
    'data_fim_antes'  => 'A data de fim não pode ser anterior à de início.'
);

if (isset($_GET['erro']) && isset($avisos_erro[$_GET['erro']])) {
?>
  <div class="alert alert-danger d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-error-circle me-2"></i>
    <div><?php echo $avisos_erro[$_GET['erro']]; ?></div>
  </div>
<?php
}
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="row g-3 align-items-center">
      <div class="col-md-8">
        <h5 class="mb-1"><?php echo htmlspecialchars($paciente['nome']); ?></h5>
        <span class="text-body-secondary">
          <?php echo $paciente['idade']; ?> anos
          <?php if ($paciente['leito'] != '') { ?>
            · Leito <strong><?php echo htmlspecialchars($paciente['leito']); ?></strong>
            · <?php echo htmlspecialchars($paciente['setor']); ?>
          <?php } ?>
        </span>
      </div>
      <div class="col-md-4">
        <?php
        // REGRA DE OURO Nº 4. Nesta tela ela é mais importante do que
        // em qualquer outra: é aqui que se escolhe o medicamento.
        $texto_alergia = trim($paciente['alergias']);
        $nega = negaAlergias($texto_alergia);
        if (!$nega) {
        ?>
          <div class="alerta-alergia">
            <i class="icon-base bx bx-error-circle"></i>
            <span><?php echo htmlspecialchars($texto_alergia); ?></span>
          </div>
        <?php } else { ?>
          <span class="text-body-secondary small">Nega alergias</span>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-9">

    <form action="prescricao_salvar.php" method="post">
      <input type="hidden" name="paciente_id" value="<?php echo $paciente['id']; ?>">

      <div class="card">
        <div class="card-body">

          <div class="row g-3">

            <div class="col-md-8">
              <label class="form-label" for="medicamento_id">Medicamento *</label>
              <select class="form-select" id="medicamento_id" name="medicamento_id" required>
                <option value="">Escolha o medicamento…</option>
                <?php foreach ($medicamentos as $m) { ?>
                  <option value="<?php echo $m['id']; ?>"
                    <?php if ($digitado['medicamento_id'] == $m['id']) { echo 'selected'; } ?>>
                    <?php echo htmlspecialchars($m['nome']); ?>
                    <?php if ($m['apresentacao'] != '') { ?>
                      — <?php echo htmlspecialchars($m['apresentacao']); ?>
                    <?php } ?>
                  </option>
                <?php } ?>
              </select>
              <div class="form-text">
                A lista é o catálogo da farmácia. Falta algum? Fale com a farmácia.
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label" for="dose">Dose *</label>
              <input type="text" class="form-control" id="dose" name="dose" required
                     maxlength="50" placeholder="Ex.: 500 mg"
                     value="<?php echo htmlspecialchars($digitado['dose']); ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label" for="via">Via *</label>
              <select class="form-select" id="via" name="via" required>
                <option value="">Escolha a via…</option>
                <?php foreach (listaDeVias() as $sigla => $nome) { ?>
                  <option value="<?php echo $sigla; ?>"
                    <?php if ($digitado['via'] == $sigla) { echo 'selected'; } ?>>
                    <?php echo $sigla; ?> — <?php echo $nome; ?>
                  </option>
                <?php } ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label" for="frequencia">Frequência *</label>
              <input type="text" class="form-control" id="frequencia" name="frequencia" required
                     maxlength="50" placeholder="Ex.: 8/8h"
                     value="<?php echo htmlspecialchars($digitado['frequencia']); ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label" for="horarios">Horários</label>
              <input type="text" class="form-control" id="horarios" name="horarios"
                     maxlength="100" placeholder="Ex.: 06:00, 14:00, 22:00"
                     value="<?php echo htmlspecialchars($digitado['horarios']); ?>">
              <div class="form-text">
                Separe por vírgula. <strong>Deixe em branco para "se necessário"</strong>.
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="data_inicio">Início *</label>
              <input type="date" class="form-control" id="data_inicio" name="data_inicio" required
                     value="<?php echo htmlspecialchars($digitado['data_inicio']); ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label" for="data_fim">Fim</label>
              <input type="date" class="form-control" id="data_fim" name="data_fim"
                     value="<?php echo htmlspecialchars($digitado['data_fim']); ?>">
              <div class="form-text">
                Em branco: segue até ser suspensa. Os horários são criados
                para <?php echo diasParaGerar(); ?> dias.
              </div>
            </div>

            <div class="col-12">
              <label class="form-label" for="observacao">Observação</label>
              <textarea class="form-control" id="observacao" name="observacao" rows="2"
                        maxlength="255"
                        placeholder="Ex.: administrar após alimentação"><?php echo htmlspecialchars($digitado['observacao']); ?></textarea>
            </div>

          </div>
        </div>

        <div class="card-body border-top d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="icon-base bx bx-capsule me-1"></i> Lançar prescrição
          </button>
          <a href="prescricao_historico.php?paciente_id=<?php echo $paciente['id']; ?>"
             class="btn btn-outline-secondary">Cancelar</a>
        </div>
      </div>
    </form>

  </div>
</div>

<?php
mysqli_close($conexao);
require 'includes/rodape.php';
?>
