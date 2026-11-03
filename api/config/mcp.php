<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | Domínios aceitos como redirect_uri no registro dinâmico de clientes OAuth
    | (/oauth/register). Restringe a conectores conhecidos para evitar que um
    | cliente arbitrário capture códigos de autorização via phishing.
    | "http://localhost" libera qualquer porta local (Claude Code, Inspector).
    | Valores extras via MCP_REDIRECT_DOMAINS (separados por vírgula).
    |
    */

    'redirect_domains' => array_values(array_filter([
        'https://claude.ai',
        'https://claude.com',
        'http://localhost',
        ...explode(',', (string) env('MCP_REDIRECT_DOMAINS', '')),
    ])),

    'custom_schemes' => [],

    'authorization_server' => null,

];
