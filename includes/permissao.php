<?php
// ===================================================================
//  includes/permissao.php
//  Prontuário Eletrônico TDS
//
//  A SEGUNDA verificação de permissão — a que realmente vale.
//
//  A primeira está no cabecalho.php: ela esconde do menu os itens que
//  a pessoa não pode usar. Mas esconder um link NÃO é segurança —
//  qualquer um digita o endereço da página direto na barra do
//  navegador. É esta função aqui que tranca a porta.
//
//  COMO USAR — duas linhas no topo da página, nesta ordem:
//
//      require 'includes/protege.php';     (já entrou no sistema?)
//      require 'includes/permissao.php';   (carrega a função)
//      exigirPerfil(array('administrador'));
//
//  A ordem importa: o protege.php é quem garante que a sessão existe.
//  Sem ele, esta função não teria o perfil para comparar.
//
//  Vale também para os arquivos que RECEBEM formulário (os que fazem
//  INSERT e UPDATE). É lá que o estrago aconteceria de verdade, então
//  é lá que a verificação mais importa.
// ===================================================================


// -------------------------------------------------------------------
//  exigirPerfil()
//
//  Recebe a lista de perfis que podem abrir a página. Se o perfil de
//  quem está logado não estiver na lista, a pessoa é mandada de volta
//  ao painel com um aviso — e o script para aqui.
//
//  Exemplos:
//      exigirPerfil(array('administrador'));
//      exigirPerfil(array('medico', 'tecnico'));
// -------------------------------------------------------------------
function exigirPerfil($perfis_permitidos)
{
    // Trava contra erro de programação.
    // O painel é para onde todo mundo é mandado quando não tem
    // permissão. Se ele próprio exigisse um perfil, o redirecionamento
    // chamaria a si mesmo para sempre e o navegador travaria com
    // "too many redirects". Melhor avisar do que travar.
    if (basename($_SERVER['PHP_SELF']) == 'painel.php') {
        die('Erro de programação: painel.php é a tela para onde todos '
          . 'são mandados quando falta permissão, então ela mesma não '
          . 'pode chamar exigirPerfil().');
    }

    $perfil_de_quem_entrou = $_SESSION['usuario_perfil'];

    // in_array() procura um valor dentro de um array e devolve
    // verdadeiro ou falso. O ponto de exclamação inverte: "se NÃO
    // estiver na lista...".
    if (!in_array($perfil_de_quem_entrou, $perfis_permitidos)) {
        header('Location: painel.php?negado=1');
        exit;   // sem o exit o resto da página continuaria rodando
    }

    // Se chegou até aqui, o perfil está na lista e a página segue
    // normalmente.
}
