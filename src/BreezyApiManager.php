<?php

namespace Drupal\breezy;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Link;
use GuzzleHttp\ClientInterface;

/**
 * BreezyApiManager.
 */
class BreezyApiManager {

  /**
   * Breezy API base URL.
   */
  const BREEZY_API_BASE_URL = 'https://breezy.hr/public/api/v3';

  /**
   * Number of days to cache the Breezy access token.
   */
  const BREEZY_TOKEN_CACHE_DAYS = 1;

  /**
   * Breezy module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $breezyConfig;

  /**
   * Guzzle http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * CacheBackendInterface object.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Access token retrieved via the Breezy API.
   *
   * @var string
   */
  protected $breezyAccessToken;

  /**
   * Constructs the Breezy API Manager.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   Guzzle HTTP client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, CacheBackendInterface $cache) {
    $this->breezyConfig = $config_factory->get('breezy.settings');
    $this->httpClient = $http_client;
    $this->cache = $cache;

    $this->breezyAccessToken = $this->getBreezyAccessToken();
  }

  /**
   * Retrieves Breezy access token.
   *
   * The Breezy access token is valid for 30 days.
   *
   * @param bool $reset
   *   If TRUE, cached values will be ignored.
   *
   * @return string
   *   A Breezy access token string.
   *
   * @see https://developer.breezy.hr/docs/signin
   */
  protected function getBreezyAccessToken($reset = FALSE) {
    if (!$reset && $cache = $this->cache->get('breezy.breezy_access_token')) {
      $access_token = $cache->data;
    }
    else {
      try {
        $response = $this->httpClient->post(self::BREEZY_API_BASE_URL . '/signin', [
          'form_params' => [
            'email' => $this->breezyConfig->get('breezy_email'),
            'password' => $this->breezyConfig->get('breezy_password'),
          ],
        ]);
        $response = json_decode($response->getBody());
        $access_token = $response->access_token;
        $this->cache->set('breezy.breezy_access_token', $access_token, $this->breezyCacheExpirationTimestamp());
      }
      catch (Exception $e) {
        $this->getLogger('breezy')->error('Unable to retrieve Breezy access token: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }
    return $access_token;
  }

  /**
   * Get Breezy positions.
   *
   * @param bool $reset
   *   If TRUE, cached values will be ignored.
   *
   * @return array
   *   An array of Breezy positions.
   */
  public function getPositions($reset = FALSE) {
    if (!$reset && $cache = $this->cache->get('breezy.breezy_positions')) {
      $positions = $cache->data;
    }
    else {
      try {
        $positions_url = self::BREEZY_API_BASE_URL . '/company/' . $this->breezyConfig->get('breezy_company_id') . '/positions?state=published';
        $response = $this->httpClient->get($positions_url, $this->breezyGetRequestHeaders());
        $positions = json_decode($response->getBody());
        $this->cache->set('breezy.breezy_positions', $positions, time() + 120);
      }
      catch (Exception $e) {
        $this->getLogger('breezy')->error('Error accessing Breezy API: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }
    return $positions;
  }

  /**
   * Get position data.
   *
   * @param string $position_id
   *   A Breezy position id.
   * @param bool $reset
   *   If TRUE, cached values will be ignored.
   *
   * @return object
   *   The entire Breezy position object.
   */
  public function getPositionData($position_id, $reset = FALSE) {
    if (!$reset && $cache = $this->cache->get('breezy.breezy_position_' . $position_id)) {
      $position = $cache->data;
    }
    else {
      try {
        $positions_url = self::BREEZY_API_BASE_URL . '/company/' . $this->breezyConfig->get('breezy_company_id') . '/position/' . $position_id;
        $response = $this->httpClient->get($positions_url, $this->breezyGetRequestHeaders());
        $position = json_decode($response->getBody());
        $this->cache->set('breezy.breezy_position_' . $position_id, $position, time() + 120);
      }
      catch (Exception $e) {
        $this->getLogger('breezy')->error('Error accessing Breezy API: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }
    return $position;
  }

  /**
   * Get position links.
   *
   * @return array
   *   An array of positions names linked to their detail pages.
   */
  public function getPositionDetailLinks() {
    foreach ($this->getPositions() as $position) {
      $position_links[] = Link::createFromRoute($position->name, 'breezy.position_detail', [
        'position_id' => $position->_id,
      ]);
    }
    return $position_links;
  }

  /**
   * Generates a cache expiration timestamp for the access token.
   *
   * @return string
   *   A timestamp string.
   */
  protected function breezyCacheExpirationTimestamp() {
    return time() + (self::BREEZY_TOKEN_CACHE_DAYS * 24 * 60 * 60);
  }

  /**
   * Generates standard Breezy GET request headers.
   *
   * @return array
   *   An array of headers.
   */
  protected function breezyGetRequestHeaders() {
    return [
      'headers' => [
        'Accept' => 'application/json',
        'Authorization' => $this->breezyAccessToken,
      ],
    ];
  }

}
