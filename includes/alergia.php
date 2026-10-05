<?php
// ===================================================================
//  includes/alergia.php
//  Prontuário Eletrônico TDS
//
//  A pergunta "este paciente nega alergias?", escrita UMA vez só.
//
//  ┌──────────────────────────────────────────────────────────────┐
//  │  ANTES DESTE ARQUIVO                                         │
//  │  A mesma linha estava copiada em 20 lugares, em 17 telas:      │
//  │                                                               │
//  │    $nega = (strtolower($texto_alergia) == 'nega alergias'      │
//  │             || $texto_alergia == '');                          │
//  └──────────────────────────────────────────────────────────────┘
//
//  E cada uma das 20 cópias reconhecia SÓ a frase exata. Quem
//  cadastrasse "Nenhuma", "Não tem" ou "-" aparecia com tarja
//  vermelha de alergia em todas as telas do sistema.
//
//  POR QUE ISSO É GRAVE, e não um detalhe de estilo:
//
//  O erro é "para o lado seguro" — o sistema grita alergia onde não
//  há, nunca o contrário. Só que o custo é o ALERTA QUE NINGUÉM LÊ.
//  Se metade dos pacientes aparece com tarja vermelha, quem trabalha
//  aprende a passar o olho por cima da tarja — e no dia em que ela for
//  verdadeira, também não vai ser lida. Alerta que dispara errado
//  treina a pessoa a ignorar o alerta.
//
//  Mesma ideia do includes/perfis.php e do includes/faixas.php:
//  UMA VERDADE, UM LUGAR. Redação nova para aceitar? Uma linha aqui,
//  e as 17 telas passam a aceitar juntas.
//
//  E POR QUE ELE CONTINUA EXISTINDO
//
//  O paciente_form.php não aceita mais texto livre para essa pergunta:
//  são duas escolhas — "Nega alergias" ou "Tem alergia" — e nenhuma
//  vem pré-marcada. Então texto ambíguo não entra mais pela tela.
//
//  Mesmo assim esta função fica, e o motivo é o que vale a aula:
//
//    O FORMULÁRIO PREVINE, A FUNÇÃO PERDOA.
//
//  O formulário só governa o que entra a partir de agora. O banco da
//  escola já tem redação antiga, ninguém vai reescrever prontuário, e
//  um professor mexendo no Workbench pode digitar qualquer coisa. Duas
//  camadas com papéis diferentes: uma barra na entrada, a outra tolera
//  o que já está dentro.
//
//  Tirar a função porque "agora o formulário garante" seria confiar
//  numa garantia que só vale para o futuro.
//
//  E ela ganhou um terceiro uso: o paciente_salvar.php a chama para
//  pegar a CONTRADIÇÃO — quem marca "Tem alergia" e escreve "nenhuma"
//  é recusado, porque a função reconhece o texto como negação.
// ===================================================================


// -------------------------------------------------------------------
//  formasDeNegarAlergia()
//
//  As redações que significam "não tem alergia". Todas em minúsculo e
//  sem espaço nas pontas, porque é assim que negaAlergias() compara.
//
//  A lista é curta de propósito: cada item aqui é uma redação que
//  alguém REALMENTE digitou ou provavelmente vai digitar. Não vale
//  encher de variação inventada — quanto mais longa a lista, maior a
//  chance de aceitar por engano uma frase que descreve uma alergia.
// -------------------------------------------------------------------
function formasDeNegarAlergia()
{
    return array(
        '',
        'nega alergias',
        'nega alergia',
        'nega',
        'nenhuma',
        'nenhum',
        'nada',
        'não tem',
        'nao tem',
        'não',
        '-',
        'nao',
        'sem alergias',
        'sem alergia',
        'n/a',
        '--'
    );
}


// -------------------------------------------------------------------
//  negaAlergias()
//
//  Devolve true quando o texto do cadastro significa "não tem".
//
//  Os dois cuidados da comparação:
//
//    trim()       tira espaço e ENTER das pontas. Sem isso, "Nenhuma "
//                 com um espaço sobrando não casaria com "nenhuma".
//
//    strtolower() deixa tudo minúsculo. Sem isso, "NENHUMA",
//                 "Nenhuma" e "nenhuma" seriam três respostas
//                 diferentes para a mesma coisa.
//
//  A ordem importa: minúsculo DEPOIS do trim não muda nada, mas
//  comparar sem os dois muda tudo.
// -------------------------------------------------------------------
function negaAlergias($texto){

$limpo = trim(strtolower($texto));
return in_array($limpo, formasDeNegarAlergia());
}

    // in_array() compara com cada item da lista e devolve true no
    // primeiro que bater. É a mesma coisa que escrever um || para cada
    // redação, e cabe numa linha.
    

