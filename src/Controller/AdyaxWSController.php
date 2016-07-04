<?php

namespace Drupal\adyax_test\Controller;

use Drupal\Core\Controller\ControllerBase;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Serializer;

/**
 * Class AdyaxWSController.
 *
 * @package Drupal\adyax_test\Controller
 */
class AdyaxWSController extends ControllerBase {

  /**
   * The node storage service.
   *
   * @var \Drupal\node\NodeStorageInterface
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
  protected $apiRequiredFields = ['title', 'type', 'body'];

  /**
   * Constructs a AdyaxWSController object.
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param Serializer $serializer
   *   Serializer component.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Serializer $serializer) {
    $this->storage = $entityTypeManager->getStorage('node');
    $this->serializer = $serializer;
  }

  /**
   * Return the node object from request.
   *
   * @param Request $request
   *   Site request.
   *
   * @return NodeInterface
   *   Node loaded from requested 'nid' query param.
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
   *   Site request.
   *
   * @return array
   *   Filtered and valid data from request.
   *
   * @throws AdyaxWSValidationErrorException
   */
  protected function validateDataFromRequest(Request $request) {
    try {
      $data = (array) $this->serializer->decode($request->getContent(), 'json', ['json_decode_associative' => TRUE]);
    }
    catch (\UnexpectedValueException $e) {
      throw new AdyaxWSValidationErrorException($this->t('Please, provide a valid json data.'));
    }
    $data = array_intersect_key($data, array_flip($this->apiRequiredFields));
    foreach ($this->apiRequiredFields as $field) {
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
   *   Node for validation.
   *
   * @return NodeInterface
   *   Node with valid data for saving.
   *
   * @throws AdyaxWSValidationErrorException
   */
  protected function validateNode(NodeInterface $node) {
    $constraints = $node->validate();
    $constraints->filterByFields($this->apiRequiredFields);
    $violations = $constraints->getEntityViolations();
    if ($violations->count()) {
      $error = new AdyaxWSValidationErrorException();
      foreach ($violations as $violation) {
        /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
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
   *   Exceptions linked list.
   *
   * @return array
   *   List of error messages.
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
   *   Site request.
   *
   * @return JsonResponse
   *   Json response with serialized node data or error messages.
   */
  public function getNode(Request $request) {
    try {
      $node = $this->getNodeFromRequest($request);
    }
    catch (AdyaxWSValidationErrorException $e) {
      return new JsonResponse(['errors' => $this->getErrorMessages($e)]);
    }
    return new JsonResponse($this->serializer->normalize($node, 'json', ['plugin_id' => 'entity']));
  }

  /**
   * Insert node API callback.
   *
   * @param Request $request
   *   Site request.
   *
   * @return JsonResponse
   *   Json response with success or error messages.
   */
  public function postNode(Request $request) {
    try {
      $data = $this->validateDataFromRequest($request);
      /** @var NodeInterface $node */
      $node = $this->storage->create($data);
      $this->validateNode($node);
      $node->save();
    }
    catch (AdyaxWSValidationErrorException $e) {
      return new JsonResponse(['errors' => $this->getErrorMessages($e)]);
    }
    return new JsonResponse(['message' => $this->t('Node successfully saved.'), 'nid' => $node->id()]);
  }

  /**
   * Update node API callback.
   *
   * @param Request $request
   *   Site request.
   *
   * @return JsonResponse
   *   Json response with success or error messages.
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
    }
    catch (AdyaxWSValidationErrorException $e) {
      return new JsonResponse(['errors' => $this->getErrorMessages($e)]);
    }
    return new JsonResponse(['message' => $this->t('Node successfully updated.')]);
  }

  /**
   * Delete node API callback.
   *
   * @param Request $request
   *   Site request.
   *
   * @return JsonResponse
   *   Json response with success or error messages.
   */
  public function deleteNode(Request $request) {
    try {
      $node = $this->getNodeFromRequest($request);
      $node->delete();
    }
    catch (AdyaxWSValidationErrorException $e) {
      return new JsonResponse(['errors' => $this->getErrorMessages($e)]);
    }
    return new JsonResponse(['message' => $this->t('Node successfully deleted.')]);
  }

}
