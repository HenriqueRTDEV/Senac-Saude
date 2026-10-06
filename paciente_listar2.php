<?php


// Passo 1 - Conectar no Banco
require 'config/conexao.php';
require 'includes/funcoes2.php'; // para usar nomeDoSexo() e listaDeSexos()

// Passo 2 - Escrever a Consulta que vai buscar no banco os pacientes
// O Texto da consulta fica guardada na variavel, para ser usada depois

$sql = "SELECT id, nome, data_nascimento, sexo, ativo
        FROM pacientes
        ORDER BY nome";

// Passo 3 - Mandar pedido, enviar a consulta para o banco
// $resultado é o monte de linhas que voltou
// Não dá para imprimir $resultado direto: as linhas saem uma por vez.

$resultado = mysqli_query($conexao, $sql);

// Passo 4 - Perguntar quantas linhas vieram
// Banco nos devolve quantas linhas

$quantos = mysqli_num_rows($resultado);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listar Pacientes</title>
</head>
<body>
    <h1>Listar pacientes - o R do CRUD</h1>
    <fieldset>
        <legend><b>O que esta página faz</b></legend>
        <p>
            Lê a tabela <code>pacientes</code> e mostra na tela. 
        </p>
        <p>
            <b>Passo 1</b> Conectar no banco.
            <b>Passo 2</b> Escreve o SELECT.
            <b>Passo 3</b> manda o SELECT para o banco.
            <b>Passo 4</b> pergunta quantas linhas vieram.
            <b>Passo 5</b> o <code> while</code> imprime uma linha por paciente.
</p>
</fieldset>
<p><b>Este foi o SELECT que o PHP mandou para o banco:</b></p>
<pre><?php echo $sql ?></pre>
<?php 
// -------------------------------------------------------------------
//  RECADO DA PÁGINA ANTERIOR
//
//  As outras telas terminam com header('Location: ...?ok=alguma_coisa')
//  e esse pedaço da URL chega aqui dentro de $_GET. É assim que uma
//  página avisa a outra que deu certo — sem guardar nada em lugar
//  nenhum, só pela barra de endereço.
//
//  A URL traz só uma palavra-código. A frase que a pessoa lê está
//  escrita AQUI, nesta lista. Assim ninguém consegue fazer a tela
//  exibir o texto que quiser trocando a URL na mão.
// -------------------------------------------------------------------
$frases = array(
    'cadastrado' => 'Paciente cadastrado',
    'atualizado' => 'Cadastro atualizado',
    'inativado'  => 'Cadastro inativado',
    'reativado'  => 'Cadastro reativado',
    'campos'     => 'Faltou preencher nome, nascimento ou sexo',
    'nao_encontrado' => 'Esse paciente não existe no banco'
);

if(isset($_GET['ok']) && isset($frases[$_GET['ok']])){
    echo '<p><b>Deu certo:</b> ' . $frases[$_GET['ok']] . '</p>';
}
if(isset($_GET['erro']) && isset($frases[$_GET['erro']])){
    echo '<p><b>Deu erro:</b> ' . $frases[$_GET['erro']] . '</p>';
}

?>
<p>
    <a href="paciente_form2.php">Cadastrar novo paciente</a>
    « 👈 este link leva ao <b>C</b> do CRUD (create)

</p>
<table border="1" cellpadding="4" cellspacing="0">
    <tr> <!-- significa Table Row, LInha da tabela -->
        <th>ID</th>
        <th>Nome</th> <!-- Significa Table header, célula do cabeçalho -->
        <th>Nascimento</th>
        <th>Idade</th>
        <th>Sexo</th>
        <th>Ativo</th>
        <th>Ações</th>
    </tr>

<?php 
// Passo 5 - 
// mysqli_fetch_assoc() tira uma linha da pilha e devolve num array.
// Na próxima volta tira a seguinte. Quando a pilha acaba, devolve nulo
// o while entende como falso e o laço para sozinho.

// Dentro do laço, $p é UM paciente. $p['nome'] é o nome dele.
while ($p = mysqli_fetch_assoc($resultado)){
?>
<tr>
    <td><?php echo $p['id']; ?></td> <!-- Table Data, dados da tabela -->
<!-- htmlspecialchars() é obrigatorio em tudo que vem do banco
    se alguem cadastrar um nome com sinal de menor (<b>), sem ele o 
        navegador tentaria entender aquilo como tag HTML -->
    <td><?php echo htmlspecialchars($p['nome']); ?></td>
    <td><?php echo dataNaTela($p['data_nascimento']); ?></td>
    <td><?php echo calcularIdade($p['data_nascimento']); ?></td>
    <td><?php echo htmlspecialchars(nomeDoSexo($p['sexo'])); ?></td>
    <td><?php echo ($p['ativo'] == 1 ? 'Sim' : 'Não'); ?></td>
    <td>
        <a href="paciente_form2.php?id=<?php echo $p['id']; ?>">Editar</a>
        <a href="paciente_inativar2.php=<?php echo $p['id']; ?>">
            <?php echo($p['ativo'] == 1 ? 'Inativar' : 'Reativar'); ?>
        </a>
    </td>
</tr>
<?php } ?>
</table>

</body>
</html>

