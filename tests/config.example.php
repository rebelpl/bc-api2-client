<?php
// learn how to set up the app:
// https://github.com/rebelpl/oauth2-businesscentral?tab=readme-ov-file#pre-requisites

return [
    'tenantId'                  => getenv('TENANT_ID') ?: '',
    'clientId'                  => getenv('CLIENT_ID') ?: '',
    'clientSecret'              => getenv('CLIENT_SECRET') ?: '',
    'environment'               => getenv('ENVIRONMENT') ?: 'sandbox',
    'companyId'                 => getenv('COMPANY_ID') ?: '',

    // only for Authorization Code Grant
    'redirectUri'               => getenv('REDIRECT_URI') ?: 'http://localhost',
    'tokenFilename'             => getenv('TOKEN_FILENAME') ?: 'tmp/token.json',
];