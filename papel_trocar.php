<?php
// ===================================================================
//  papel_trocar.php  —  Empresta um papel ao administrador
//  Recurso permanente do perfil administrador. Ver config/papel.php.
//
//  Recebe papel_trocar.php?papel=medico e passa a tratar quem está
//  logado como médico: o menu lateral, os botões das telas, as trancas
//  de permissão, tudo.
//
//  A troca acontece em UMA linha — a que reescreve
//  $_SESSION['usuario_perfil'] lá embaixo. As 60 verificações de
//  permissão do sistema (40 exigirPerfil() e 20 comparações diretas)
//  leem essa mesma chave, então todas obedecem de uma vez, sem que
//  nenhuma delas precise saber que este arquivo existe.
//
//  É a lição de "uma verdade, um lugar" cobrando o prêmio: se a
//  verificação estivesse copiada dentro de cada tela, este arquivo
//  seria impossível de escrever.
// ===================================================================

require 'includes/protege.php';
require_once 'includes/perfis.php';
require_once 'config/papel.php';


// -------------------------------------------------------------------
//  TRANCA 1 — o recurso está ligado, e quem pede é administrador DE
//  VERDADE.
//
//  Repare que a comparação é com usuario_perfil_real, nunca com
//  usuario_perfil. Se fosse com o assumido, o administrador que
//  assumisse "técnico" ficaria preso: dali em diante a comparação
//  diria tecnico != administrador e ele não conseguiria mais voltar.
//  Porta de mão única.
//
//  O usuario_perfil_real é escrito UMA vez, no autenticar.php, a
//  partir da tabela usuarios. Não vem de formulário nem de URL, e
//  nenhuma outra tela o reescreve. É isso que dá a garantia de
//  verdade: um técnico que digitar este endereço na barra do
//  navegador tem perfil real 'tecnico' e leva o mesmo negado=1 que
//  levaria ao tentar abrir usuario_listar.php.
//
//  A SESSÃO ANTIGA — aberta antes de esta parte do sistema existir —
//  não tem a chave nova. E aqui a gente GRAVA em vez de só tolerar a
//  falta, porque tolerar tem um defeito, e ele foi medido:
//
//    a pessoa assumia médico, a chave continuava sem existir, e na
//    requisição seguinte a conta caía no perfil ASSUMIDO. Dali em
//    diante 'medico' != 'administrador', e ela ficava presa no papel
//    até sair e entrar de novo — a porta de mão única que o parágrafo
//    acima promete evitar, entrando pelos fundos.
//
//  Gravar é seguro: se nenhum papel foi emprestado ainda, o perfil de
//  agora É o verdadeiro. Depois desta linha a chave existe, e a partir
//  do próximo login o autenticar.php cuida dela.
// -------------------------------------------------------------------
if(!isset($_SESSION['usuario_perfil_real'])){
    $_SESSION['usuario_perfil_real'] = $_SESSION['usuario_perfil'];
}



// -------------------------------------------------------------------
//  TRANCA 2 — nunca confie no que chega pela URL.
//
//  Sem isto, papel_trocar.php?papel=qualquercoisa gravaria
//  "qualquercoisa" na sessão, e esse valor passaria a ser comparado
//  nas 60 verificações do sistema. Nenhuma daria erro: todas
//  simplesmente diriam "não é este perfil", e a pessoa ficaria num
//  limbo, sem menu e sem tela nenhuma para abrir.
//
//  perfilExiste() é a mesma função que o usuario_salvar.php usa para
//  validar o campo do formulário. A regra mora num lugar só.
// -------------------------------------------------------------------

$perfil_real = $_SESSION['usuario_perfil_real'];

if(!ADMIN_TROCA_PAPEL || $perfil_real != 'administrador'){
    header('Location: painel.php?negado=1');
}

// -------------------------------------------------------------------
//  A TROCA
// -------------------------------------------------------------------
$_SESSION['usuario_perfil'] = $papel;


// -------------------------------------------------------------------
//  Volta sempre ao painel, nunca para a página de onde a pessoa veio.
//
//  Dois motivos. O de segurança: HTTP_REFERER vem do navegador, então
//  mandar a pessoa "de volta" para o que ele diz é abrir a porta para
//  jogá-la em qualquer endereço.
//
//  O prático: a tela onde você estava provavelmente não é permitida no
//  papel novo. Assumir médico estando em usuario_listar.php daria um
//  negado=1 no mesmo instante. O painel é a única tela que todo perfil
//  pode abrir — está escrito no comentário do próprio painel.php.
// -------------------------------------------------------------------
header('Location: painel.php');
exit;
