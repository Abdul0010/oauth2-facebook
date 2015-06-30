<?php

namespace League\OAuth2\Client\Provider;

use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Provider\Exception\FacebookProviderException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Http\Message\ResponseInterface;

class Facebook extends AbstractProvider
{
    /**
     * Production Graph API URL.
     *
     * @const string
     */
    const BASE_FACEBOOK_URL = 'https://www.facebook.com/';

    /**
     * Beta tier URL of the Graph API.
     *
     * @const string
     */
    const BASE_FACEBOOK_URL_BETA = 'https://www.beta.facebook.com/';

    /**
     * Production Graph API URL.
     *
     * @const string
     */
    const BASE_GRAPH_URL = 'https://graph.facebook.com/';

    /**
     * Beta tier URL of the Graph API.
     *
     * @const string
     */
    const BASE_GRAPH_URL_BETA = 'https://graph.beta.facebook.com/';

    /**
     * The Graph API version to use for requests.
     *
     * @var string
     */
    protected $graphApiVersion;

    /**
     * A toggle to enable the beta tier URL's.
     *
     * @var boolean
     */
    private $enableBetaMode = false;

    /**
     * @param array $options
     * @param array $collaborators
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($options = [], array $collaborators = [])
    {
        parent::__construct($options, $collaborators);

        if (empty($options['graphApiVersion'])) {
            $message = 'The "graphApiVersion" option not set. Please set a default Graph API version.';
            throw new \InvalidArgumentException($message);
        }

        $this->graphApiVersion = $options['graphApiVersion'];

        if (!empty($options['enableBetaTier']) && $options['enableBetaTier'] === true) {
            $this->enableBetaMode = true;
        }
    }

    public function getBaseAuthorizationUrl()
    {
        return $this->getBaseFacebookUrl().$this->graphApiVersion.'/dialog/oauth';
    }

    public function getBaseAccessTokenUrl()
    {
        return $this->getBaseGraphUrl().$this->graphApiVersion.'/oauth/access_token';
    }

    public function getDefaultScopes()
    {
        return ['public_profile', 'email'];
    }

    public function getUserDetailsUrl(AccessToken $token)
    {
        $fields = implode(',', [
            'id', 'name', 'first_name', 'last_name',
            'email', 'hometown', 'bio', 'picture.type(large){url}',
            'gender', 'locale', 'link',
        ]);

        return $this->getBaseGraphUrl().$this->graphApiVersion.'/me?fields='.$fields.'&access_token='.$token;
    }

    public function getAccessToken($grant = 'authorization_code', array $params = [])
    {
        if (isset($params['refresh_token'])) {
            throw new FacebookProviderException('Facebook does not support token refreshing.');
        }

        return parent::getAccessToken($grant, $params);
    }

    /**
     * Exchanges a short-lived access token with a long-lived access-token.
     *
     * @param string $accessToken
     *
     * @return \League\OAuth2\Client\Token\AccessToken
     *
     * @throws FacebookProviderException
     */
    public function getLongLivedAccessToken($accessToken)
    {
        $params = [
            'fb_exchange_token' => (string) $accessToken,
        ];

        return $this->getAccessToken('fb_exchange_token', $params);
    }

    protected function createUser(array $response, AccessToken $token)
    {
        return new FacebookUser($response);
    }

    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (!empty($data['error'])) {
            $message = $data['error']['type'].': '.$data['error']['message'];
            throw new IdentityProviderException($message, $data['error']['code'], $data);
        }
    }

    /**
     * @inheritdoc
     */
    protected function getContentType(ResponseInterface $response)
    {
        $type = parent::getContentType($response);

        // Fix for Facebook's pseudo-JSONP support
        if (strpos($type, 'javascript') !== false) {
            return 'application/json';
        }

        // Fix for Facebook's pseudo-urlencoded support
        if (strpos($type, 'plain') !== false) {
            return 'application/x-www-form-urlencoded';
        }

        return $type;
    }

    /**
     * Get the base Facebook URL.
     *
     * @return string
     */
    private function getBaseFacebookUrl()
    {
        return $this->enableBetaMode ? static::BASE_FACEBOOK_URL_BETA : static::BASE_FACEBOOK_URL;
    }

    /**
     * Get the base Graph API URL.
     *
     * @return string
     */
    private function getBaseGraphUrl()
    {
        return $this->enableBetaMode ? static::BASE_GRAPH_URL_BETA : static::BASE_GRAPH_URL;
    }
}
