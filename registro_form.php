<?php
// ===================================================================
//  registro_form.php  —  Escrever no prontuário
//  Módulo 4 · Sprint 6 · perfis MÉDICO e TÉCNICO
//
//  Um campo de texto, e nada mais. Repare no que NÃO existe nesta
//  tela:
//
//    · nenhum <select> de tipo — o tipo vem do perfil da sessão
//    · nenhum campo de data ou hora — vem do relógio do servidor
//    · nenhum campo de autor — vem da sessão
//
//  Sobrou só o que a pessoa realmente tem para dizer. Todo o resto o
//  sistema sabe sozinho, e é justamente o que ele não deixa ninguém
//  escolher.
//
//  A mesma tela serve aos dois perfis e muda de nome conforme quem
//  abre: anotação de enfermagem para o técnico, evolução médica para o
//  médico. Uma tela, dois comportamentos, decididos pelo login.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('medico', 'tecnico'));

require 'config/conexao.php';
require 'includes/alergia.php';
require 'includes/registros.php';

$meu_perfil = $_SESSION['usuario_perfil'];
$meu_tipo   = tipoDoPerfil($meu_perfil);

$paciente_id = isset($_GET['paciente_id']) ? (int) $_GET['paciente_id'] : 0;

if ($paciente_id == 0) {
    header('Location: registro_listar.php?erro=nao_encontrado');
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
    header('Location: registro_listar.php?erro=nao_internado');
    exit;
}

// -------------------------------------------------------------------
//  Os três últimos registros, para quem escreve saber o que já foi
//  dito antes de repetir ou contradizer.
// -------------------------------------------------------------------
$sql = "SELECT r.tipo, r.data_hora, r.texto,
               COALESCE(u.nome, '') AS autor
        FROM registros r
        LEFT JOIN usuarios u ON u.id = r.usuario_id
        WHERE r.paciente_id = ?
        ORDER BY r.data_hora DESC
        LIMIT 3";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 'i', $paciente_id);
mysqli_stmt_execute($stmt);
$ultimos = mysqli_stmt_get_result($stmt);

// Se o salvar recusou, devolve o texto para não perder o que já foi
// escrito. Um registro clínico pode ter muitas linhas — perder isso
// por causa de uma validação seria imperdoável.
$texto = isset($_GET['texto']) ? $_GET['texto'] : '';

$titulo    = nomeDoTipo($meu_tipo);
$subtitulo = $paciente['nome'];
require 'includes/cabecalho.php';
?>

<?php
$avisos_erro = array(
    'vazio' => 'Escreva o registro antes de salvar.',
    'curto' => 'O registro está curto demais. Descreva o que foi observado ou feito.'
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

<div class="row">
  <div class="col-lg-8">

    <form action="registro_salvar.php" method="post">
      <input type="hidden" name="paciente_id" value="<?php echo $paciente['id']; ?>">

      <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
          <span class="selo-perfil perfil-<?php echo $meu_perfil; ?>">
            <?php echo nomeCurtoDoTipo($meu_tipo); ?>
          </span>
          <h5 class="mb-0"><?php echo nomeDoTipo($meu_tipo); ?></h5>
        </div>

        <div class="card-body">
          <div class="mb-0">
            <label class="form-label" for="texto">
              <?php
              // O rótulo muda com o perfil: o técnico descreve o que
              // observou e fez; o médico registra avaliação e conduta.
              if ($meu_tipo == 'anotacao') {
                  echo 'O que foi observado e o que foi feito *';
              } else {
                  echo 'Avaliação e conduta *';
              }
              ?>
            </label>
            <textarea class="form-control" id="texto" name="texto" rows="9" required
                      placeholder="<?php echo ($meu_tipo == 'anotacao'
                            ? 'Ex.: Paciente acordada, orientada, em ar ambiente. Refere tosse produtiva. Aceitou dieta parcialmente.'
                            : 'Ex.: Paciente com melhora do padrão respiratório. Mantida antibioticoterapia. Reavaliar febre no turno da tarde.'); ?>"><?php echo htmlspecialchars($texto); ?></textarea>
            <div class="form-text">
              Mínimo de <?php echo minimoDoTexto(); ?> caracteres.
              A data, a hora e o seu nome são gravados automaticamente.
            </div>
          </div>
        </div>

        <div class="card-body border-top">
          <div class="alert alert-warning mb-3" role="alert">
            <i class="icon-base bx bx-lock-alt me-1"></i>
            <strong>Registro salvo não pode ser corrigido nem apagado.</strong>
            Se precisar corrigir algo depois, escreva um registro novo.
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="icon-base bx bx-save me-1"></i> Salvar registro
            </button>
            <a href="registro_listar.php" class="btn btn-outline-secondary">Cancelar</a>
          </div>
        </div>
      </div>
    </form>

  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h6 class="mb-0">Últimos registros</h6>
      </div>
      <div class="card-body">
        <?php
        if (mysqli_num_rows($ultimos) == 0) {
        ?>
          <p class="text-body-secondary mb-0 small">
            Este paciente ainda não tem registro.
          </p>
        <?php
        } else {
        ?>
          <div class="linha-tempo">
          <?php while ($r = mysqli_fetch_assoc($ultimos)) { ?>
            <div class="registro-item <?php echo $r['tipo']; ?>">
              <div class="registro-cabeca">
                <span class="registro-hora">
                  <?php echo date('d/m H:i', strtotime($r['data_hora'])); ?>
                </span>
                <span class="selo-perfil perfil-<?php echo ($r['tipo'] == 'evolucao' ? 'medico' : 'tecnico'); ?>">
                  <?php echo nomeCurtoDoTipo($r['tipo']); ?>
                </span>
              </div>
              <div class="small text-body-secondary mb-1">
                <?php echo htmlspecialchars($r['autor']); ?>
              </div>
              <p class="registro-texto small mb-0"><?php
                $curto = trim(preg_replace('/\s+/', ' ', $r['texto']));
                if (strlen($curto) > 150) {
                    $curto = substr($curto, 0, 150) . '…';
                }
                echo htmlspecialchars($curto);
              ?></p>
            </div>
          <?php } ?>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<?php
mysqli_stmt_close($stmt);
mysqli_close($conexao);
require 'includes/rodape.php';
?>
