<?php

namespace Drupal\breezy\Controller;

use Drupal\breezy\BreezyApiManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides route response for Breezy position details.
 */
class BreezyPositionController extends ControllerBase {

  /**
   * The Breezy API manager service.
   *
   * @var \Drupal\breezy\BreezyApiManager
   */
  protected $breezyApiManager;

  /**
   * A Breezy position object.
   *
   * @var object
   */
  protected $position;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('breezy.api_manager')
    );
  }

  /**
   * Creates a Breezy positions list controller.
   *
   * @param \Drupal\breezy\BreezyApiManager $breezy_api_manager
   *   The Breezy API manager service.
   */
  public function __construct(BreezyApiManager $breezy_api_manager) {
    $this->breezyApiManager = $breezy_api_manager;
  }

  /**
   * Returns details of a single Breezy position.
   *
   * @var string
   *   A Breezy position id.
   *
   * @return array
   *   A renderable array.
   */
  public function positionDetail($position_id) {
    if (!$this->position) {
      if (!$this->position = $this->breezyApiManager->getPositionData($position_id)) {
        throw new NotFoundHttpException();;
      }
    }

    $position_application_url = $this->breezyApiManager->getPositionApplicationUrl($position_id);

    return [
      '#theme' => 'breezy_position',
      '#name' => $this->position->name,
      '#description' => $this->position->description,
      '#application_link' => Link::fromTextAndUrl('Apply now', $position_application_url),
      '#application_url' => $position_application_url,
    ];
  }

  /**
   * Get position title.
   *
   * @param string $position_id
   *   A Breezy position id.
   *
   * @return string
   *   The position title provided by Breezy.
   */
  public function getPositionTitle($position_id) {
    if (!$this->position) {
      $this->position = $this->breezyApiManager->getPositionData($position_id);
    }
    return $this->position->name ?? NULL;
  }

}
