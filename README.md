# BC API

## Installation

To install, use composer:

```
composer require rebelpl/bc-api2-client
```

to install common entities, use:
```
composer require rebelpl/bc-api2-common
```

## Usage

To use the client, you need to obtain a valid Access Token.
Use an OAuth client to obtain it (for example [rebelpl/oauth2-businesscentral](https://github.com/rebelpl/oauth2-businesscentral)).

```php
$provider = new Rebel\OAuth2\Client\Provider\BusinessCentral([
    // Required
    'tenantId'                  => 'mydomain.com',
    'clientId'                  => 'xxxxx-yyyy-zzzz-xxxx-yyyyyyyyyyyy',
    'clientSecret'              => '*************************',
]);

$token = $provider->getAccessToken('client_credentials', [
    'scope' => Rebel\OAuth2\Client\Provider\BusinessCentral::CLIENT_CREDENTIALS_SCOPE
]);

$this->client = new Rebel\BCApi2\Client(
    accessToken: $token->getToken(),
    environment: 'sandbox',
    companyId: null,
);

foreach ($client->getCompanies() as $company) {
    echo " - {$company->getName()}:\t{$company->id()}\n";
}
```

# Tests
```
./vendor/bin/phpunit
```