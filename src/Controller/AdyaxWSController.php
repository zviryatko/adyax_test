<?php
/**
 * @file
 * Contains \Drupal\adyax_test\Controller\AdyaxWSController.
 */

namespace Drupal\adyax_test\Controller;

use Drupal\Core\Controller\ControllerBase;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Class AdyaxWSController.
 *
 * @package Drupal\adyax_test\Controller
 */
class AdyaxWSController extends ControllerBase {

  /**
   * The node storage service.
   *
   * @var NodeStorageInterface
   */
  protected $storage;

  /**
   * The serializer service.
   *
   * @var Serializer
   */
  protected $serializer;

  /**
   * Required fields for Adyax Web Service API.
   *
   * @var array
   */
  protected $api_required_fields = ['title', 'type', 'body'];

  /**
   * Constructs a AdyaxWSController object.
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   *
   * @param Serializer $serializer
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Serializer $serializer) {
    $this->storage = $entityTypeManager->getStorage('node');
    $this->serializer = $serializer;
  }

  /**
   * Return the node object from request.
   *
   * @param Request $request
   *
   * @return NodeInterface
   *
   * @throws AdyaxWSValidationErrorException
   */
  protected function getNodeFromRequest(Request $request) {
    $nid = $request->query->get('nid');
    if (empty($nid)) {
      throw new AdyaxWSValidationErrorException($this->t('Please, provide a valid node id.'));
    }
    $node = $this->storage->load($nid);
    if (!$node instanceof NodeInterface) {
      throw new AdyaxWSValidationErrorException($this->t('Node does not exists.'));
    }
    return $node;
  }

  /**
   * Get required fields from request.
   *
   * @param Request $request
   *
   * @return array
   *
   * @throws AdyaxWSValidationErrorException
   */
  protected function validateDataFromRequest(Request $request) {
    try {
      $data = $this->serializer->decode($request->getContent(), 'json');
    } catch (\UnexpectedValueException $e) {
      throw new AdyaxWSValidationErrorException($this->t('Please, provide a valid json data.'));
    }
    $data = array_intersect_key($data, array_flip($this->api_required_fields));
    foreach ($this->api_required_fields as $field) {
      if (!array_key_exists($field, $data)) {
        throw new AdyaxWSValidationErrorException($this->t('Please, provide required fields: title, type and body.'));
      }
    }
    return $data;
  }

  /**
   * Validate data in node, throws on validation error.
   *
   * @param NodeInterface $node
   *
   * @return NodeInterface
   *
   * @throws AdyaxWSValidationErrorException
   */
  protected function validateNode(NodeInterface $node) {
    $constraints = $node->validate();
    $constraints->filterByFields($this->api_required_fields);
    $violations = $constraints->getEntityViolations();
    if ($violations->count()) {
      $error = new AdyaxWSValidationErrorException();
      foreach ($violations as $violation) {
        /** @var ConstraintViolationInterface $violation */
        $error = new AdyaxWSValidationErrorException($violation->getMessage(), 0, isset($error) ? $error : NULL);
      }
      throw new $error;
    }
    return $node;
  }

  /**
   * Collect exceptions to error messages array.
   *
   * @param AdyaxWSValidationErrorException $exception
   *
   * @return array
   */
  protected function getErrorMessages(AdyaxWSValidationErrorException $exception) {
    $errors = [];
    do {
      $errors[] = $exception->getMessage();
    } while ($exception = $exception->getPrevious());

    return array_filter($errors);
  }

  /**
   * Get node API callback.
   *
   * @param Request $request
   *
   * @return JsonResponse
   */
  public function getNode(Request $request) {
    try {
      $node = $this->getNodeFromRequest($request);
    } catch (AdyaxWSValidationErrorException $e) {
      return new JsonResponse(['errors' => $this->getErrorMessages($e)]);
    }
    return new JsonResponse($this->serializer->normalize($node, 'json', ['plugin_id' => 'entity']));
  }

  /**
   * Insert node API callback.
   *
   * @param Request $request
   *
   * @return JsonResponse
   */
  public function postNode(Request $request) {
    try {
      $data = $this->validateDataFromRequest($request);
      /** @var NodeInterface $node */
      $node = $this->storage->create($data);
      $this->validateNode($node);
      $node->save();
    } catch (AdyaxWSValidationErrorException $e) {
      return new JsonResponse(['errors' => $this->getErrorMessages($e)]);
    }
    return new JsonResponse(['message' => $this->t('Node successfully saved.'), 'nid' => $node->id()]);
  }

  /**
   * Update node API callback.
   *
   * @param Request $request
   *
   * @return JsonResponse
   */
  public function putNode(Request $request) {
    try {
      $node = $this->getNodeFromRequest($request);
      $data = $this->validateDataFromRequest($request);
      foreach ($data as $field => $value) {
        $node->set($field, $value);
      }
      $this->validateNode($node);
      $node->save();
    } catch (AdyaxWSValidationErrorException $e) {
      return new JsonResponse(['errors' => $this->getErrorMessages($e)]);
    }
    return new JsonResponse(['message' => $this->t('Node successfully updated.')]);
  }

  /**
   * Delete node API callback.
   *
   * @param Request $request
   *
   * @return JsonResponse
   */
  public function deleteNode(Request $request) {
    try {
      $node = $this->getNodeFromRequest($request);
      $node->delete();
    } catch (AdyaxWSValidationErrorException $e) {
      return new JsonResponse(['errors' => $this->getErrorMessages($e)]);
    }
    return new JsonResponse(['message' => $this->t('Node successfully deleted.')]);
  }

}
