<?php

namespace Drupal\adyax_test\Tests;

use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\simpletest\WebTestBase;

/**
 * Provides automated tests for the adyax_test module.
 *
 * @group adyax_test
 */
class AdyaxWSControllerTest extends WebTestBase {

  /**
   * @var NodeStorageInterface
   */
  protected $storage;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = array('adyax_test', 'entity_test', 'node');

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // Create a test content type for node testing.
    $this->drupalCreateContentType(array('name' => 'Adyax rest test content type', 'type' => 'adyax_rest_test'));
    $this->storage = $this->container->get('entity_type.manager')->getStorage('node');
  }

  /**
   * Create request and get response data.
   *
   * @param $method
   *   HTTP request method.
   * @param $nid
   *   The Node id.
   * @param array $data
   *   Post data.
   * @return array
   */
  protected function handleRequest($method, $nid = NULL, array $data = []) {
    $httpClient = \Drupal::httpClient();
    $options = [];
    if ($nid) {
      $options['query'] = ['nid' => $nid];
    }
    if (!empty($data)) {
      $options['json'] = $data;
    }
    $response = $httpClient->request($method, $this->getAbsoluteUrl('/adyax_ws'), $options);
    $this->assertEqual($response->getStatusCode(), 200, 'Return status 200 OK');
    $this->assertEqual('application/json', $response->getHeader('Content-type')[0], 'Returns Content-type: application/json');
    return json_decode($response->getBody(), TRUE);
  }

  /**
   * Returns test data for node.
   *
   * @return array
   */
  protected function nodeTestData() {
    return [
      'title' => $this->randomString(),
      'type' => 'adyax_rest_test',
      'body' => $this->randomString(),
    ];
  }

  /**
   * Create test node.
   *
   * @return NodeInterface
   */
  protected function createTestNode() {
    $data = $this->nodeTestData();
    $node = $this->storage->create($data);
    $node->save();
    return $node;
  }

  /**
   * Test GET REST call.
   */
  public function testGetNode() {
    $node = $this->createTestNode();
    $response_data = $this->handleRequest('GET', $node->id());
    $this->assertEqual($response_data['nid'][0]['value'], $node->id(), 'Return the right node id');
    $this->assertEqual($response_data['title'][0]['value'], $node->title->value, 'Title is equal');
    $this->assertEqual($response_data['type'][0]['target_id'], $node->bundle(), 'Type is equal');
    $this->assertEqual($response_data['body'][0]['value'], $node->body->value, 'Body is equal');
  }

  /**
   * Test GET POST call.
   */
  public function testPostNode() {
    $data = $this->nodeTestData();
    $response_data = $this->handleRequest('POST', NULL, $data);
    /** @var NodeInterface $node */
    $node = $this->storage->load($response_data['nid']);
    $this->assertEqual($response_data['message'], t('Node successfully saved.'), 'Node saved message returns.');
    $this->assertNotNull($node, 'Node exists');
    $node_copy = $this->storage->create($data);
    $this->assertEqual($node_copy->title->value, $data['title'], 'Title is equal');
    $this->assertEqual($node_copy->bundle(), $data['type'], 'Type is equal');
    $this->assertEqual($node_copy->body->value, $data['body'], 'Body is equal');
  }

  /**
   * Test GET PUT call.
   */
  public function testPutNode() {
    $node = $this->createTestNode();
    $data = $this->nodeTestData();
    $response_data = $this->handleRequest('PUT', $node->id(), $data);
    $this->assertEqual($response_data['message'], t('Node successfully updated.'), 'Node updated message returns.');
    /** @var NodeInterface $node */
    $node_copy = $this->storage->loadUnchanged($node->id());
    $this->assertNotNull($node_copy, 'Node exists');
    $this->assertEqual($node_copy->title->value, $data['title'], 'Title is equal');
    $this->assertEqual($node_copy->bundle(), $data['type'], 'Type is equal');
    $this->assertEqual($node_copy->body->value, $data['body'], 'Body is equal');
  }

  /**
   * Test DELETE REST call.
   */
  public function testDeleteNode() {
    $node = $this->createTestNode();
    $nid = $node->id();
    $response_data = $this->handleRequest('DELETE', $node->id());
    $this->assertEqual($response_data['message'], t('Node successfully deleted.'), 'Node deletion message returns.');
    $node_copy = $this->storage->load($nid);
    $this->assertNull($node_copy, 'Node is deleted');
  }
}
