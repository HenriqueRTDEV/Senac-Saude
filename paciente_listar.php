<?php
// ===================================================================
//  paciente_listar.php  —  Lista de pacientes cadastrados
//  Módulo 2 · Sprint 3
//
//  ATENÇÃO À PERMISSÃO: esta tela NÃO chama exigirPerfil().
//  Pela matriz do escopo, "ver dados cadastrais do paciente" é dos
//  quatro perfis. O que muda são os BOTÕES: só a recepção cadastra
//  e corrige.
//
//  Depois da migração v2, a informação está em três tabelas:
//    pacientes    -> quem a pessoa é
//    internacoes  -> se está internada agora, desde quando
//    leitos       -> em que cama
// ===================================================================

require 'includes/protege.php';
require 'config/conexao.php';


// -------------------------------------------------------------------
//  BUSCA E FILTRO
// -------------------------------------------------------------------


// O filtro entra no meio do SQL, então ele NÃO pode vir solto do
// navegador. Passa antes por esta lista de valores permitidos —
// qualquer outra coisa é ignorada.


$curinga = '%' . $busca . '%';

// -------------------------------------------------------------------
//  A CONSULTA
//
//  Repare que a condição da internação está no ON, não no WHERE.
//  Isso é proposital: no ON, o LEFT JOIN traz o paciente mesmo sem
//  internação em andamento (as colunas vêm nulas). Se estivesse no
//  WHERE, todo paciente não internado sumiria da lista — e é
//  justamente ele que a recepção precisa achar para reinternar.
// -------------------------------------------------------------------
$sql = "select p.id, p.nome, p.data_nascimento, p.sexo, p.alergias, p.ativo,
               i.id as internacao_id, i.data_admissao, i.diagnostico,
               l.numero as leito, l.setor
        from pacientes p
        left join internacoes i on (i.paciente_id = p.id and i.data_alta is null)
        left join leitos l on (l.id = i.leito_id)
        where p.nome like ?
        order by p.ativo desc, p.nome";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 's', $curinga);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$quantos = mysqli_num_rows($resultado);

$titulo    = 'Pacientes';
$subtitulo = 'Cadastro';
require 'includes/cabecalho.php';
?>

<?php
$avisos_ok = array(
    'cadastrado' => 'Paciente cadastrado. Para interná-lo, use a tela de Movimentação.',
    'atualizado' => 'Cadastro do paciente atualizado.',
    'inativado'  => 'Cadastro inativado. O paciente continua no banco, com todo o histórico.',
    'reativado'  => 'Cadastro reativado.'
);

$avisos_erro = array(
    'campos'         => 'Preencha todos os campos obrigatórios.',
    'data_futura'    => 'A data de nascimento não pode estar no futuro.',
    'data_invalida'  => 'Data de nascimento inválida.',
    'sexo_invalido'  => 'Sexo inválido.',
    'nao_encontrado' => 'Paciente não encontrado.',
    'tem_internacao' => 'Não dá para inativar o cadastro de um paciente internado. Dê alta antes.'
);

if (isset($_GET['ok']) && isset($avisos_ok[$_GET['ok']])) {
?>
  <div class="alert alert-success d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-check-circle me-2"></i>
    <div><?php echo $avisos_ok[$_GET['ok']]; ?></div>
  </div>
<?php
}

if (isset($_GET['erro']) && isset($avisos_erro[$_GET['erro']])) {
?>
  <div class="alert alert-danger d-flex align-items-center" role="alert">
    <i class="icon-base bx bx-error-circle me-2"></i>
    <div><?php echo $avisos_erro[$_GET['erro']]; ?></div>
  </div>
<?php
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h5 class="mb-0">Pacientes cadastrados</h5>
    <small class="text-body-secondary">
      <?php echo $quantos; ?>
      <?php echo ($quantos == 1 ? 'encontrado' : 'encontrados'); ?>
    </small>
  </div>

  <?php  ?>
    <div class="d-flex gap-2">
      <a href="internacao_movimentar.php" class="btn btn-outline-primary">
        <i class="icon-base bx bx-transfer me-1"></i> Movimentação
      </a>
      <a href="paciente_form.php" class="btn btn-primary">
        <i class="icon-base bx bx-plus me-1"></i> Cadastrar paciente
      </a>
    </div>
  <?php  ?>
</div>

<form method="get" action="paciente_listar.php" class="mb-4">
  <div class="row g-2">
    <div class="col-md-7">
      <input type="text" name="busca" class="form-control"
             placeholder="Buscar por nome…"
             value="<?php echo htmlspecialchars($busca); ?>">
    </div>
    <div class="col-md-3">
      <select name="situacao" class="form-select">
        <option value="internado"     <?php  ?>>Internados agora</option>
        <option value="nao_internado" <?php  ?>>Não internados</option>
        <option value="todos"         <?php ?>>Todos</option>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-outline-primary w-100" type="submit">Filtrar</button>
    </div>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Leito</th>
          <th>Paciente</th>
          <th>Idade</th>
          <th>Diagnóstico</th>
          <th>Alergias</th>
          <th>Situação</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>

      <?php
      if ($quantos == 0) {
      ?>
        <tr>
          <td colspan="7" class="text-center text-body-secondary py-4">
            Nenhum paciente encontrado com esse filtro.
          </td>
        </tr>
      <?php
      }

      while ($p = mysqli_fetch_assoc($resultado)) {

          // internacao_id nulo = não está internado agora
          $internado = ($p['internacao_id'] !== null);
      ?>
        <tr class="<?php echo ($p['ativo'] ? '' : 'opacity-50'); ?>">

          <td>
            <?php if ($internado && $p['leito'] != '') { ?>
              <strong><?php echo htmlspecialchars($p['leito']); ?></strong><br>
              <small class="text-body-secondary"><?php echo htmlspecialchars($p['setor']); ?></small>
            <?php } else { ?>
              <span class="text-body-secondary">—</span>
            <?php } ?>
          </td>

          <td>
            <strong><?php echo htmlspecialchars($p['nome']); ?></strong>
            <?php if ($internado) { ?>
              <br><small class="text-body-secondary">
                Internado em <?php echo date('d/m/Y', strtotime($p['data_admissao'])); ?>
              </small>
            <?php } ?>
          </td>

          <td><?php echo $p['idade']; ?> anos</td>

          <td>
            <?php
            if (!$internado) {
                echo '<span class="text-body-secondary">—</span>';
            } else if ($p['diagnostico'] == '') {
                echo '<span class="text-body-secondary">aguardando avaliação</span>';
            } else {
                echo htmlspecialchars($p['diagnostico']);
            }
            ?>
          </td>

          <td>
            <?php
            // REGRA DE OURO Nº 4 — alergia sempre em destaque.
            // "Nega alergias" é informação, mas não é alerta.
            $texto_alergia = trim($p['alergias']);
            $nega = negaAlergias($texto_alergia);

            if ($nega) {
                echo '<span class="text-body-secondary">Nega alergias</span>';
            } else {
            ?>
              <span class="valor-alterado">
                <i class="icon-base bx bx-error-circle"></i>
                <?php echo htmlspecialchars($texto_alergia); ?>
              </span>
            <?php
            }
            ?>
          </td>

          <td>
            <?php if (!$p['ativo']) { ?>
              <span class="badge bg-label-secondary">Cadastro inativo</span>
            <?php } else if ($internado) { ?>
              <span class="badge bg-label-primary">Internado</span>
            <?php } else { ?>
              <span class="badge bg-label-secondary">Não internado</span>
            <?php } ?>
          </td>

          <td>
            <?php if ($eh_recepcao) { ?>
              <a href="paciente_form.php?id=<?php echo $p['id']; ?>"
                 class="btn btn-sm btn-outline-primary">Editar</a>

              <?php if ($p['ativo']) { ?>
                <a href="paciente_inativar.php?id=<?php echo $p['id']; ?>"
                   class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Inativar o cadastro de <?php echo htmlspecialchars($p['nome']); ?>? O histórico continua guardado.');">
                  Inativar</a>
              <?php } else { ?>
                <a href="paciente_inativar.php?id=<?php echo $p['id']; ?>"
                   class="btn btn-sm btn-outline-primary">Reativar</a>
              <?php } ?>

            <?php } else { ?>
              <span class="text-body-secondary small">—</span>
            <?php } ?>
          </td>

        </tr>
      <?php
      } // fim do while
      ?>

      </tbody>
    </table>
  </div>
</div>

<?php
mysqli_stmt_close($stmt);
mysqli_close($conexao);
require 'includes/rodape.php';
?>
