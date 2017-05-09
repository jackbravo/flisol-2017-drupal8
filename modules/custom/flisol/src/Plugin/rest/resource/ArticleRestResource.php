<?php

namespace Drupal\flisol\Plugin\rest\resource;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "articles",
 *   label = @Translation("Article rest resource"),
 *   uri_paths = {
 *     "canonical" = "/rest/articles"
 *   }
 * )
 */
class ArticleRestResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The database connection
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The file system stream wrapper
   *
   * @var string
   */
  protected $fileDir;

  /**
   * Constructs a new ArticleRestResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   * @param \Drupal\Core\Database\Connection $database
   *   A connection to the drupal database.
   * @param \Drupal\Core\StreamWrapper\PublicStream $stream
   *   A wrapper for the public file system.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    Connection $database,
    PublicStream $stream) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->database = $database;
    $this->fileDir = $stream->getDirectoryPath();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('flisol'),
      $container->get('current_user'),
      $container->get('database'),
      $container->get('stream_wrapper.public')
    );
  }

  /**
   * Responds to GET requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get() {

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    try {
      $query = $this->database->select('node_field_data', 'nfd');
      $query->condition('nfd.type', 'article');

      $query->join('node__field_image', 'n_fi', 'n_fi.entity_id = nfd.nid');
      $query->join('file_managed', 'f', 'f.fid = n_fi.field_image_target_id');

      $query->addField('nfd', 'title');
      $query->addExpression("REPLACE(f.uri, 'public:/', :base_path)", 'image', [':base_path' => $this->fileDir]);

      // $string = $query->execute()->getQueryString();

      $articles = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

      $response = new ResourceResponse(['articles' => $articles]);
      $response->setMaxAge(strtotime('1 day', 0));

      return $response;
    }
    catch (\Exception $e) {
      throw new BadRequestHttpException($this->t('Could not find articles'), $e);
    }
  }

}
