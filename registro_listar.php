<?php
// ===================================================================
//  registro_listar.php  —  Registros da ala
//  Módulo 4 · Sprint 6
//
//  Uma linha por internado, com o último registro escrito e quantos
//  cada paciente tem. Serve para ver de relance quem está sem registro
//  no plantão.
//
//  PERMISSÃO: administrador, médico e técnico. A recepção não —
//  registro é dado clínico, mesma regra do módulo 3.
//
//  Quem ESCREVE, porém, são só dois: técnico e médico. E cada um
//  escreve um tipo diferente, que ele não escolhe. Ver
//  includes/registros.php.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('administrador', 'medico', 'tecnico'));

require 'config/conexao.php';
require 'includes/alergia.php';
require 'includes/registros.php';

$meu_perfil = $_SESSION['usuario_perfil'];
$eu_escrevo = perfilEscreveRegistro($meu_perfil);
$meu_tipo   = tipoDoPerfil($meu_perfil);

// -------------------------------------------------------------------
//  O QUE FOI DIGITADO NA BUSCA
//
//  Com um valor vindo de fora, a consulta passou dos mysqli_query()
//  para os quatro passos do mysqli_prepare. Mesma razão do
//  sinal_listar.php: texto que o usuário digita nunca entra no SQL
//  por concatenação.
// -------------------------------------------------------------------
$busca = '';
if (isset($_GET['busca'])) {
    $busca = trim($_GET['busca']);
}

$curinga = '%' . $busca . '%';

// -------------------------------------------------------------------
//  OS INTERNADOS COM O ÚLTIMO REGISTRO
//
//  Mesma técnica do sinal_listar.php: um LEFT JOIN que só casa com a
//  linha mais recente, usando NOT EXISTS para dizer "não existe
//  registro mais novo deste paciente".
//
//  A contagem total vem de um SELECT dentro do SELECT, porque é um
//  número por paciente e não daria para trazer junto do JOIN sem
//  multiplicar as linhas.
// -------------------------------------------------------------------
$sql = "SELECT p.id AS paciente_id, p.nome,
               COALESCE(p.alergias, '') AS alergias,
               TIMESTAMPDIFF(YEAR, p.data_nascimento, CURDATE()) AS idade,
               COALESCE(l.identificacao, '') AS leito,
               COALESCE(s.nome, '')         AS setor,
               (SELECT COUNT(*) FROM registros
                 WHERE paciente_id = p.id) AS quantos,
               r.id AS registro_id, r.tipo, r.data_hora,
               TIMESTAMPDIFF(HOUR, r.data_hora, NOW()) AS horas_atras,
               COALESCE(r.texto, '') AS texto,
               COALESCE(u.nome, '')  AS autor
        FROM internacoes i
        JOIN pacientes p ON p.id = i.paciente_id
        LEFT JOIN leitos  l ON l.id = i.leito_id
        LEFT JOIN setores s ON s.id = l.setor_id
        LEFT JOIN registros r
               ON r.paciente_id = p.id
              AND NOT EXISTS (
                  SELECT 1 FROM registros mais_novo
                   WHERE mais_novo.paciente_id = p.id
                     AND mais_novo.data_hora > r.data_hora
              )
        LEFT JOIN usuarios u ON u.id = r.usuario_id
        WHERE i.situacao = 'internado' AND i.ativo = 1
          AND p.nome LIKE ?
        ORDER BY l.identificacao";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, 's', $curinga);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$lista = array();
while ($linha = mysqli_fetch_assoc($resultado)) {
    $lista[] = $linha;
}

$sem_registro = 0;
foreach ($lista as $linha) {
    if ($linha['registro_id'] === null) {
        $sem_registro = $sem_registro + 1;
    }
}

$titulo    = 'Registros';
$subtitulo = 'Anotações de enfermagem e evoluções médicas';
require 'includes/cabecalho.php';
?>

<?php
$avisos_ok = array(
    'gravado' => 'Registro gravado.'
);

$avisos_erro = array(
    'nao_internado'  => 'Só se registra em paciente internado.',
    'nao_encontrado' => 'Paciente não encontrado.',
    'vazio'          => 'Escreva o registro antes de salvar.',
    'curto'          => 'O registro está curto demais. Descreva o que foi observado ou feito.',
    'sem_permissao'  => 'Seu perfil não escreve registro no prontuário.'
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
    <h5 class="mb-0">Internados</h5>
    <small class="text-body-secondary">
      <?php echo count($lista); ?>
      <?php echo (count($lista) == 1 ? 'paciente' : 'pacientes'); ?>
      <?php if ($sem_registro > 0) { ?>
        · <span class="fw-bold"><?php echo $sem_registro; ?> sem registro</span>
      <?php } ?>
    </small>
  </div>
  <?php if ($eu_escrevo) { ?>
    <small class="text-body-secondary">
      Seu perfil escreve: <strong><?php echo nomeDoTipo($meu_tipo); ?></strong>
    </small>
  <?php } ?>
</div>

<form method="get" action="registro_listar.php" class="mb-3">
  <div class="input-group">
    <input type="text" name="busca" class="form-control"
           placeholder="Buscar por nome…"
           value="<?php echo htmlspecialchars($busca); ?>">
    <button class="btn btn-outline-primary" type="submit">Buscar</button>
    <?php if ($busca != '') { ?>
      <a href="registro_listar.php" class="btn btn-outline-secondary">Limpar</a>
    <?php } ?>
  </div>
</form>

<div class="mb-2">
  <small class="text-body-secondary">
    <span class="selo-perfil perfil-tecnico">Anotação</span> escrita pelo técnico de enfermagem
    &nbsp;·&nbsp;
    <span class="selo-perfil perfil-medico">Evolução</span> escrita pelo médico
  </small>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Leito</th>
          <th>Paciente</th>
          <th>Último registro</th>
          <th></th>
        </tr>
      </thead>
      <tbody>

      <?php
      if (count($lista) == 0) {
      ?>
        <tr><td colspan="4" class="text-center text-body-secondary py-4">
          <?php
          echo ($busca == ''
                ? 'Nenhum paciente internado no momento.'
                : 'Nenhum internado com esse nome.');
          ?>
        </td></tr>
      <?php
      }

      foreach ($lista as $linha) {

          $sem = ($linha['registro_id'] === null);
      ?>
        <tr>
          <td>
            <?php if ($linha['leito'] == '') { ?>
              <span class="text-body-secondary">sem leito</span>
            <?php } else { ?>
              <strong><?php echo htmlspecialchars($linha['leito']); ?></strong><br>
              <small class="text-body-secondary"><?php echo htmlspecialchars($linha['setor']); ?></small>
            <?php } ?>
          </td>

          <td>
            <strong><?php echo htmlspecialchars($linha['nome']); ?></strong>,
            <?php echo $linha['idade']; ?> anos
            <?php
            // REGRA DE OURO Nº 4 — alergia sempre em destaque.
            $texto_alergia = trim($linha['alergias']);
            $nega = negaAlergias($texto_alergia);
            if (!$nega) {
            ?>
              <br><span class="valor-alterado small">
                <i class="icon-base bx bx-error-circle"></i>
                <?php echo htmlspecialchars($texto_alergia); ?>
              </span>
            <?php } ?>
            <br><small class="text-body-secondary">
              <?php echo $linha['quantos']; ?>
              <?php echo ($linha['quantos'] == 1 ? 'registro' : 'registros'); ?>
            </small>
          </td>

          <td>
            <?php
            if ($sem) {
            ?>
              <span class="badge bg-label-secondary">Nenhum registro</span>
            <?php
            } else {

                // A hora, com a unidade acompanhando o tamanho do
                // intervalo — "há 109 horas" obriga quem lê a dividir
                // por 24 de cabeça.
                $horas = $linha['horas_atras'];

                if ($horas < 1)       { $quando = 'há menos de 1 hora'; }
                else if ($horas == 1) { $quando = 'há 1 hora'; }
                else if ($horas < 24) { $quando = 'há ' . $horas . ' horas'; }
                else {
                    $dias = (int) ($horas / 24);
                    $quando = 'há ' . $dias . ($dias == 1 ? ' dia' : ' dias');
                }

                // O texto inteiro pode ser longo; na lista entra só o
                // começo. O histórico mostra completo.
                $resumo = trim(preg_replace('/\s+/', ' ', $linha['texto']));
                if (strlen($resumo) > 110) {
                    $resumo = substr($resumo, 0, 110) . '…';
                }
            ?>
              <span class="selo-perfil perfil-<?php echo ($linha['tipo'] == 'evolucao' ? 'medico' : 'tecnico'); ?>">
                <?php echo nomeCurtoDoTipo($linha['tipo']); ?>
              </span>
              <small class="text-body-secondary">
                <?php echo date('d/m H:i', strtotime($linha['data_hora'])); ?>
                · <?php echo $quando; ?>
                · <?php echo htmlspecialchars($linha['autor']); ?>
              </small>
              <div class="small mt-1"><?php echo htmlspecialchars($resumo); ?></div>
            <?php
            }
            ?>
          </td>

          <td class="text-nowrap">
            <?php if ($eu_escrevo) { ?>
              <a href="registro_form.php?paciente_id=<?php echo $linha['paciente_id']; ?>"
                 class="btn btn-sm btn-primary">Escrever</a>
            <?php } ?>
            <a href="registro_historico.php?paciente_id=<?php echo $linha['paciente_id']; ?>"
               class="btn btn-sm btn-outline-primary">Ver todos</a>
          </td>
        </tr>
      <?php } ?>

      </tbody>
    </table>
  </div>
</div>

<?php
mysqli_stmt_close($stmt);
mysqli_close($conexao);
require 'includes/rodape.php';
?>
