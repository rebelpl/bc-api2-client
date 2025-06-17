<?php
namespace Rebel\BCApi2\Client;

use Rebel\BCApi2\Client;
use Rebel\BCApi2\Exception;
use Rebel\OAuth2\Client\Provider;
use League\OAuth2\Client\Token;

class Factory
{
    public static function useClientCredentials(
        string $tenantId,
        string $clientId,
        string $clientSecret,
        string $environment,
        ?string $companyId = null): Client
    {
        $provider = new Provider\BusinessCentral([
            'tenantId' => $tenantId,
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
        ]);

        $token = $provider->getAccessToken('client_credentials', [
            'scope' => Provider\BusinessCentral::CLIENT_CREDENTIALS_SCOPE
        ]);

        return new Client($token->getToken(), environment: $environment, companyId: $companyId);
    }

    public static function useAuthorizationCode(
        string $tenantId,
        string $clientId,
        string $clientSecret,
        string $environment,
        string $redirectUri,
        ?string $companyId = null,
        ?string $tokenFilename = null): Client
    {
        $provider = new Provider\BusinessCentral([
            'tenantId' => $tenantId,
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri,
        ]);

        // load existing tokens from storage (refresh if expired)
        if ($tokenFilename && file_exists($tokenFilename)) {
            $token = new Token\AccessToken(json_decode(file_get_contents($tokenFilename), true));
            if ($token->hasExpired() && $token->getRefreshToken()) {
                $token = $provider->getAccessToken('refresh_token', [
                    'refresh_token' => $token->getRefreshToken()
                ]);

                file_put_contents($tokenFilename, json_encode($token->jsonSerialize(), JSON_PRETTY_PRINT));
                return new Client($token->getToken(), environment: $environment, companyId: $companyId);
            }
        }

        // get the authorization code using CLI or HTTP flow
        $code = self::isCommandLineInterface()
            ? self::cliAuthorizationCode($provider)
            : self::httpAuthorizationCode($provider);

        // Get access token using authorization code
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $code
        ]);

        // save the token for later use
        if ($tokenFilename && is_dir(dirname($tokenFilename))) {
            file_put_contents($tokenFilename, json_encode($token->jsonSerialize(), JSON_PRETTY_PRINT));
        }

        return new Client($token->getToken(), environment: $environment, companyId: $companyId);
   }

    private static function isCommandLineInterface(): bool
    {
        return php_sapi_name() === 'cli';
    }

    private static function cliAuthorizationCode(Provider\BusinessCentral $provider): string
    {
        echo "Open this URL in your browser:\n";
        echo $provider->getAuthorizationUrl() . "\n\n";

        echo "then paste the Authorization Code here (the `code` URL param):\n";
        return trim(fgets(STDIN));
    }

    private static function httpAuthorizationCode(Provider\BusinessCentral $provider): string
    {
        // Handle OAuth error message
        if (isset($_GET['error'])) {
            throw new Exception($_GET['error'] . ' / ' . $_GET['error_description']);
        }

        // CSRF protection
        session_start();

        // If we don't have an authorization code then get one
        if (!isset($_GET['code'])) {

            $authorizationUrl = $provider->getAuthorizationUrl();
            $_SESSION['oauth2state'] = $provider->getState();
            header('Location: ' . $authorizationUrl);
            exit;

        // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

            if (isset($_SESSION['oauth2state'])) {
                unset($_SESSION['oauth2state']);
            }

            throw new Exception('Invalid oauth state.');

        }

        return $_GET['code'];
    }
}