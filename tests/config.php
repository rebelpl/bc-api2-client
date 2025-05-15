<?php
return [
    'access_token' => getenv('BCAPI2_ACCESS_TOKEN') ?: '',
    'environment' => getenv('BCAPI2_ENVIRONMENT') ?: 'sandbox',
    'api_path' => getenv('BCAPI2_API_PATH') ?: '/v2.0',
    'company_id' => getenv('BCAPI2_COMPANY_ID') ?: null,
];