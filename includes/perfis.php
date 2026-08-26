<?php
// ===================================================================
//  includes/perfis.php
//  Prontuário Eletrônico TDS
//
//  A lista dos quatro perfis, escrita UMA vez só.
//
//  Por que um arquivo só para isso: o nome bonito de cada perfil
//  aparece na barra de cima, na lista de usuários e no formulário de
//  cadastro. Se estivesse copiado nos três lugares, bastaria alguém
//  corrigir num deles para o sistema ficar falando duas línguas.
//
//  É a mesma ideia do includes/faixas.php previsto no escopo para as
//  faixas de referência dos sinais vitais: uma verdade, um lugar.
// ===================================================================


// -------------------------------------------------------------------
//  listaDePerfis()
//  Devolve os quatro perfis. A chave é o que o banco guarda (sem
//  acento, minúsculo); o valor é o que aparece na tela.
// -------------------------------------------------------------------
function listaDePerfis()
{
    return array(
        'administrador' => 'Administrador',
        'recepcao'      => 'Recepção',
        'medico'        => 'Médico',
        'tecnico'       => 'Técnico'
    );
}


// -------------------------------------------------------------------
//  nomeDoPerfil()
//  Traduz 'recepcao' para 'Recepção'.
// -------------------------------------------------------------------
function nomeDoPerfil($chave)
{
    $perfis = listaDePerfis();

    if (isset($perfis[$chave])) {
        return $perfis[$chave];
    }

    // Se vier algo que não está na lista, devolve como veio em vez de
    // dar erro. Assim uma página nunca quebra por causa de um rótulo.
    return $chave;
}


// -------------------------------------------------------------------
//  perfilExiste()
//  Usado na validação: garante que ninguém mande um perfil inventado
//  pelo formulário. Nunca confie no que chega do navegador.
// -------------------------------------------------------------------
function perfilExiste($chave)
{
    $perfis = listaDePerfis();
    return isset($perfis[$chave]);
}
