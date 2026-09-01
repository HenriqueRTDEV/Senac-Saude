<?php
// ===================================================================
//  usuario_salvar.php  —  Grava o formulário no banco
//  Módulo 1 · Sprint 2 · perfil administrador
//
//  É o "C" e o "U" do CRUD. Esta página não desenha nada: recebe o
//  POST, confere, grava e manda o navegador para outro lugar.
//
//  Sem id  -> INSERT (usuário novo)
//  Com id  -> UPDATE (usuário que já existe)
//
//  ATENÇÃO — a verificação de permissão se repete AQUI.
//  É neste arquivo que o estrago aconteceria: quem conseguisse enviar
//  um formulário direto para cá criaria usuários à vontade, mesmo sem
//  nunca ter aberto a tela. Esconder o botão não protege nada.
// ===================================================================

require 'includes/protege.php';
require 'includes/permissao.php';
exigirPerfil(array('administrador'));



// -------------------------------------------------------------------
//  Manda de volta ao formulário levando o erro e o que foi digitado,
//  para a pessoa não ter que preencher tudo outra vez.
//  A senha NUNCA vai junto — senha não anda na barra de endereço.
// -------------------------------------------------------------------

    // urlencode troca espaços e acentos por um código que a URL aceita.


// Ninguém deve abrir este arquivo digitando o endereço no navegador.





// ===================================================================
//  VALIDAÇÃO
//  O required do HTML ajuda o usuário distraído, mas não protege o
//  sistema: qualquer um envia um formulário sem passar pela tela.
//  A validação que vale é esta, no PHP.
// ===================================================================



// Nunca confie no que chega do navegador: o select tinha quatro
// opções, mas nada impede alguém de mandar uma quinta.

// Senha: obrigatória ao cadastrar; ao editar, em branco significa
// "não quero trocar".


// ===================================================================
//  O LOGIN NÃO PODE REPETIR
//  A coluna é UNIQUE no banco, então ele recusaria de qualquer jeito.
//  Mas o erro do banco é feio e técnico. Conferir antes deixa a
//  mensagem clara para quem está usando o sistema.
//
//  Ao editar, o próprio usuário não conta: ele pode manter o login
//  dele. Por isso o "AND id <> ?".
// ===================================================================


// ===================================================================
//  GRAVAÇÃO
// ===================================================================



    // Se o administrador editou o próprio nome, a barra de cima
    // continuaria mostrando o nome antigo até ele sair e entrar de
    // novo. Atualizamos a sessão junto.
   

        // As duas chaves de perfil andam juntas AQUI, e só aqui.
        //
        // Este é o único lugar do sistema onde o perfil de alguém muda
        // de verdade. O outro lugar que escreve usuario_perfil — o
        // papel_trocar.php — está apenas emprestando um papel.
        //
        // Sem esta linha, o administrador que editasse a si mesmo
        // acabaria com as duas chaves discordando, e o empréstimo de
        // papel terminaria em silêncio no meio de um teste.
        

    // ------------------------------------------------------------
    //  INSERT — usuário novo, sempre nasce ativo
    // ------------------------------------------------------------
   


mysqli_close($conexao);

header('Location: usuario_listar.php?ok=' . $aviso);
exit;
