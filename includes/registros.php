<?php
// ===================================================================
//  includes/registros.php
//  Prontuário Eletrônico TDS
//
//  Os dois tipos de registro do prontuário, e — mais importante — a
//  regra de QUEM escreve QUAL.
//
//  Mesma ideia do perfis.php e do faixas.php: escrito uma vez, num
//  lugar só. Sem isso, o mapeamento perfil → tipo estaria copiado na
//  tela que lista, na que escreve, na que grava e no histórico.
//
//  ┌──────────────────────────────────────────────────────────────┐
//  │  O TIPO NÃO É ESCOLHIDO. ELE É CONSEQUÊNCIA DE QUEM ESTÁ     │
//  │  LOGADO.                                                     │
//  └──────────────────────────────────────────────────────────────┘
//
//  Não existe <select> de tipo em lugar nenhum do sistema, e isso é
//  de propósito:
//
//    · o TÉCNICO escreve ANOTAÇÃO DE ENFERMAGEM — descreve o que
//      observou e o que fez
//    · o MÉDICO escreve EVOLUÇÃO MÉDICA — registra a avaliação
//      clínica e a conduta
//
//  Um técnico não pode escrever uma evolução médica, nem por engano
//  nem de propósito. Se o tipo viesse do formulário, bastaria trocar
//  uma palavra no que é enviado para uma anotação de enfermagem virar
//  evolução médica assinada por quem não é médico. Vindo do perfil da
//  sessão, isso é impossível — não há o que trocar.
//
//  No banco os dois são a MESMA tabela, com a coluna `tipo`. O que os
//  separa é a autoria, não a estrutura.
// ===================================================================


// -------------------------------------------------------------------
//  tipoDoPerfil($perfil)
//  Qual tipo de registro este perfil escreve. Devolve null para quem
//  não escreve nenhum — administrador e recepção.
// -------------------------------------------------------------------
function tipoDoPerfil($perfil){
    if($perfil == 'tecnico'){
        return 'anotacao';
    }

    if($perfil == 'medico'){
        return 'evolucao';
    }
}


// -------------------------------------------------------------------
//  perfilEscreveRegistro($perfil)
//  Este perfil escreve registro? Só técnico e médico.
// -------------------------------------------------------------------
function perfilEscreveRegistro($perfil){
    return (tipoDoPerfil($perfil) !== null);
}


// -------------------------------------------------------------------
//  nomeDoTipo($tipo)
//  O nome que aparece na tela.
// -------------------------------------------------------------------
function nomeDoTipo($tipo){
    $nomes = array(
        'anotacao' => 'Anotação de Enfermagem',
        'evolucao' => 'Evolução Médica'
    );

    if(isset($nomes[$tipo])){
        return $nomes[$tipo];
    }
    return $tipo;
}


// -------------------------------------------------------------------
//  nomeCurtoDoTipo($tipo)
//  Versão curta, para caber no selo colorido da linha do tempo.
// -------------------------------------------------------------------
function nomeCurtoDoTipo($tipo){
$nomes = array(
        'anotacao' => 'Anotação',
        'evolucao' => 'Evolução'
    );

    if(isset($nomes[$tipo])){
        return $nomes[$tipo];
    }
    return $tipo;

}


// -------------------------------------------------------------------
//  TAMANHO MÍNIMO DO TEXTO
//
//  Um registro clínico é uma frase, não uma letra. O mínimo existe
//  para recusar o clique acidental — não para julgar o conteúdo.
// -------------------------------------------------------------------
function minimoDoTexto(){
    return 15;
}

// Sem a marca de fechar o PHP: espaço solto depois dela iria para o
// navegador e quebraria o header('Location: ...') de quem inclui.
