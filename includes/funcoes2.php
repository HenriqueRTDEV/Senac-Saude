<?php
// ===================================================================
//  includes/funcoes2.php   —   versão de estudo, sem CSS
//
//  AS FUNCTIONS DAS TELAS 2
//
//  Este arquivo NÃO TEM TELA. Se você abrir ele no navegador, a
//  página sai em branco — e está certo. Ele só guarda functions.
//  Quem tem tela é quem chama.
//
//
//  O QUE É UMA FUNCTION
//
//  É um pedaço de código com nome. Escrito UMA vez aqui, e qualquer
//  página chama pelo nome, quantas vezes quiser:
//
//      function nomeDoSexo($letra)      <- a receita, mora aqui
//      echo nomeDoSexo('F');            <- o pedido, lá na página
//
//  Entra alguma coisa pelo parêntese. Sai alguma coisa pelo return.
//  A página que chama não precisa saber como a function faz.
//
//
//  POR QUE NÃO ESCREVER DIRETO NA PÁGINA
//
//  Porque a mesma pergunta se repete em várias telas. "Como a data
//  aparece na tela?" vale para a lista, para o formulário e para o
//  histórico. Se a resposta estiver copiada nos três lugares, basta
//  alguém corrigir num deles para o sistema falar duas línguas.
//
//      UMA VERDADE, UM LUGAR.
//
//  É o que o includes/perfis.php e o includes/faixas.php fazem no
//  sistema de verdade. Este arquivo é a versão pequena deles, e cada
//  function abaixo diz qual function real ela está imitando.
//
//
//  COMO USAR NUMA PÁGINA — uma linha, lá em cima:
//
//      require 'includes/funcoes2.php';
//
//  Só inclui quem usa. O paciente_inativar2.php, por exemplo, não
//  chama nenhuma destas — então não inclui este arquivo.
// ===================================================================


// -------------------------------------------------------------------
//  1. listaDeSexos()   —   A LISTA ÚNICA
//
//  Devolve a tabela de tradução: de um lado a letra que o banco
//  guarda, do outro a palavra que aparece na tela.
//
//  Repare que ela não recebe nada pelo parêntese. Só devolve.
//
//  Por que as letras são F, M e O: a coluna sexo no banco é
//  ENUM('F','M','O') e não aceita outra coisa. A lista aqui tem que
//  bater com a lista de lá.
//
//  No sistema de verdade: listaDePerfis() do includes/perfis.php e
//  listaDeSinais() do includes/faixas.php são esta mesma function,
//  com outro assunto.
// -------------------------------------------------------------------
function listaDeSexos() {
    return [
        'F' => 'Feminino',
        'M' => 'Masculino',
        'O' => 'Outro',
    ];
}

// -------------------------------------------------------------------
//  2. nomeDoSexo()   —   O TRADUTOR
//
//  Entra a letra, sai a palavra:   'F'  ->  'Feminino'
//
//  Os três passos:
//    a) pega a lista chamando a function de cima — uma function pode
//       chamar outra, e é assim que a lista fica num lugar só;
//    b) isset() pergunta se essa letra existe na lista;
//    c) existe? devolve a palavra. Não existe? devolve a letra como
//       veio.
//
//  O passo (c) é o mais importante e o menos óbvio. Se alguém mexer
//  no banco pelo Workbench e gravar um 'X', esta function devolve
//  'X' — feio, mas a tela abre. Sem ele a página quebraria, e uma
//  tela de prontuário não pode quebrar por causa de um rótulo.
//
//  No sistema de verdade: nomeDoPerfil(), nomeDoTipo(), nomeDaVia()
//  e nomeDoStatus() são todas exatamente este mesmo desenho.
// -------------------------------------------------------------------
function nomeDoSexo($letra) {
    $lista = listaDeSexos();          // a) pega a lista
    if (isset($lista[$letra])) {      // b) existe na lista?
        return $lista[$letra];        // c) sim, devolve a palavra
    } else {
        return $letra;                 // c) não, devolve a letra
    }
}
// -------------------------------------------------------------------
//  3. sexoExiste()   —   A PERGUNTA DE SIM OU NÃO
//
//  Devolve true ou false. Serve para VALIDAR: o paciente_salvar2.php
//  usa esta function antes de gravar, para recusar uma letra que não
//  está na lista.
//
//  Por que precisa, se o formulário já tem só três opções: porque o
//  formulário pode ser contornado. Quem grava é o salvar2, e é lá que
//  o estrago aconteceria. Nunca confie no que chega do navegador.
//
//  Repare que ela usa a MESMA lista do tradutor. Acrescentar um sexo
//  na listaDeSexos() faz a tradução e a validação aprenderem juntas.
//
//  No sistema de verdade: perfilExiste() e viaExiste().
// -------------------------------------------------------------------

function sexoExiste($letra) {
    $sexos = listaDeSexos();          // pega a lista
    return isset($sexos[$letra]);     // devolve true ou false
}
// -------------------------------------------------------------------
//  4. dataNaTela()   —   O FORMATADOR
//
//  Entra a data do jeito do banco, sai do jeito que a gente lê:
//
//      '1952-03-14'  ->  '14/03/1952'
//
//  São dois formatos para a MESMA data, e cada um tem a sua razão:
//    aaaa-mm-dd  é como o MySQL guarda e como ele ordena certo;
//    dd/mm/aaaa  é como se escreve data no Brasil.
//
//  strtotime() transforma o texto da data num número.
//  date() pega esse número e escreve de volta no formato pedido:
//      d = dia com dois dígitos, m = mês, Y = ano com quatro.
//
//  O if em cima é para data vazia ou nula: sem ele, strtotime()
//  devolveria lixo e a tela mostraria 01/01/1970.
//
//  No sistema de verdade esta linha está escrita solta, repetida em
//  várias telas: date('d/m/Y', strtotime($p['data_admissao'])).
//  É um bom candidato a virar function um dia.
// -------------------------------------------------------------------
function dataNaTela($data) {
    if ($data === null || $data === '') {
        return '';
    } else {
        return date('d/m/Y', strtotime($data));
    }
}
// -------------------------------------------------------------------
//  5. calcularIdade()   —   O CÁLCULO
//
//  Entra a data de nascimento, sai o número de anos.
//
//  Esta é a única das cinco que faz CONTA, e por isso é a que mais
//  ensina: o resultado muda sozinho com o passar do tempo, sem
//  ninguém editar o banco. Idade não se guarda, se calcula.
//
//  A conta tem duas partes:
//    a) ano de hoje menos ano de nascimento;
//    b) se a pessoa ainda não fez aniversário este ano, tira um.
//
//  A parte (b) é a que todo mundo esquece. Sem ela, quem nasceu em
//  dezembro fica um ano mais velho durante onze meses.
//
//  No sistema de verdade quem faz esta conta é o banco, dentro do
//  SELECT, com TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()).
//  Mesmo resultado, outro lugar: lá é o MySQL, aqui é o PHP.
// -------------------------------------------------------------------
function calcularIdade($nascimento) {
 if($nascimento === null || $nascimento === '') {
    return '';
}

//Quebra a data de nascimento em três numeros.

$segundos = strtotime($nascimento);
$ano_nasc = (int) date('Y', $segundos);
$mes_nasc = (int) date('M', $segundos);
$dia_nasc = (int) date('D', $segundos);

//date () sem o segundo parametro devolve a data atual
$ano_hoje = (int) date('Y');
$mes_hoje = (int) date('m');
$dia_hoje = (int) date('d');


$idade = $ano_hoje - $ano_nasc;
//Se a pessoa ainda não fez aniversario este ano, tira um.

// (b) ainda não fez aniversario este ano? tira um.
if ($mes_hoje < $mes_nasc){
    $idade = $idade -1;
} else if ($mes_hoje == $mes_nasc && $dia_hoje < $dia_nasc){
    $idade = $idade -1;
}
return $idade;
}