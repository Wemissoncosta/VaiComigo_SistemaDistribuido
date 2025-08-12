<?php
// Define application routes as [URL path => Controller@method]
return [
    // Rotas de autenticação
    '/' => 'AuthController@login',
    '/login' => 'AuthController@login',
    '/logout' => 'AuthController@logout',
    
    // Rotas de recuperação de senha
    '/forgot-password' => 'AuthController@forgotPassword',
    '/reset-password' => 'AuthController@resetPassword',
    '/verify-code' => 'AuthController@verifyCode',
    
    // Rota do gestor
    '/gestor' => 'GestorController@index',
];
