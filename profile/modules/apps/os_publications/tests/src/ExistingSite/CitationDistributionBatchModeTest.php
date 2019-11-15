<?php

namespace Drupal\Tests\os_publications\ExistingSite;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;
use Drupal\bibcite_entity\Entity\ReferenceInterface;
use Drupal\Component\Serialization\Json;
use Drupal\os_publications\CitationDistributionModes;

/**
 * CitationDistributionBatchModeTest.
 *
 * @group kernel
 * @group publications-2
 */
class CitationDistributionBatchModeTest extends TestBase {

  /**
   * Repec service.
   *
   * @var \Drupal\repec\Repec
   */
  protected $repec;

  /**
   * Config service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Default publications settings.
   *
   * @var array
   */
  protected $defaultPublicationsSettings;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $databaseConnection;

  /**
   * Queue that would store the jobs.
   *
   * Required for cleanup.
   *
   * @var \Drupal\advancedqueue\Entity\QueueInterface
   */
  protected $queue;

  /**
   * The processor being tested.
   *
   * @var \Drupal\advancedqueue\ProcessorInterface
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->configFactory = $this->container->get('config.factory');
    $this->repec = $this->container->get('repec');
    $this->defaultPublicationsSettings = $this->configFactory->get('os_publications.settings')->getRawData();
    $this->databaseConnection = $this->container->get('database');
    $this->queue = Queue::load('publications');
    $this->processor = $this->container->get('advancedqueue.processor');

    /** @var \Drupal\Core\Config\Config $publications_settings_mut */
    $publications_settings_mut = $this->configFactory->getEditable('os_publications.settings');
    $publications_settings_mut->set('citation_distribute_module_mode', CitationDistributionModes::BATCH);
    $publications_settings_mut->save();
  }

  /**
   * Tests behavior of batch mode for repec.
   *
   * @covers \Drupal\os_publications\Plugin\CitationDistribution\CitationDistributePluginManager::distribute
   * @covers \Drupal\os_publications\Plugin\CitationDistribution\CitationDistributePluginManager::conceal
   * @covers \Drupal\os_publications\Plugin\AdvancedQueue\JobType\CitationDistribute::process
   * @covers \Drupal\os_publications\Plugin\AdvancedQueue\JobType\CitationConceal::process
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRepec() {
    // Test creation.
    $reference = $this->createReference();
    $template_path = $this->getRepecTemplatePath($reference);

    $this->assertFileNotExists($template_path);
    $this->assertItemAsDistributionJob($reference);

    $this->processor->processQueue($this->queue);

    $this->assertFileExists($template_path);
    $content = file_get_contents($template_path);
    $this->assertTemplateContent($reference, $content);

    // Test updation.
    $reference->set('bibcite_abst_e', [
      'value' => 'Test abstract',
    ]);
    $reference->save();

    $this->assertItemAsDistributionJob($reference);

    $this->processor->processQueue($this->queue);

    $this->assertFileExists($template_path);
    $content = file_get_contents($template_path);
    $this->assertTemplateContent($reference, $content);

    // Test deletion.
    $reference->delete();

    $this->assertFileExists($template_path);
    $this->assertItemAsConcealmentJob($reference);

    $this->processor->processQueue($this->queue);

    $this->assertFileNotExists($template_path);
  }

  /**
   * Asserts whether the item has been converted to a distribution job.
   *
   * @param \Drupal\bibcite_entity\Entity\ReferenceInterface $item
   *   The item.
   */
  protected function assertItemAsDistributionJob(ReferenceInterface $item) {
    $raw_jobs = $this->databaseConnection->query('SELECT * FROM {advancedqueue} WHERE {queue_id} = :queue_id AND {type} = :type AND {state} = :state', [
      ':queue_id' => $this->queue->id(),
      ':type' => 'os_publications_citation_distribute',
      ':state' => Job::STATE_QUEUED,
    ])->fetchAllAssoc('job_id', \PDO::FETCH_ASSOC);

    $this->assertCount(1, $raw_jobs);

    $job = reset($raw_jobs);
    $payload = Json::decode($job['payload']);

    $this->assertEquals($item->id(), $payload['id']);
  }

  /**
   * Asserts whether the item has been converted to a concealment job.
   *
   * @param \Drupal\bibcite_entity\Entity\ReferenceInterface $item
   *   The item.
   */
  protected function assertItemAsConcealmentJob(ReferenceInterface $item) {
    $raw_jobs = $this->databaseConnection->query('SELECT * FROM {advancedqueue} WHERE {queue_id} = :queue_id AND {type} = :type AND {state} = :state', [
      ':queue_id' => $this->queue->id(),
      ':type' => 'os_publications_citation_conceal',
      ':state' => Job::STATE_QUEUED,
    ])->fetchAllAssoc('job_id', \PDO::FETCH_ASSOC);

    $this->assertCount(1, $raw_jobs);

    $job = reset($raw_jobs);
    $payload = Json::decode($job['payload']);

    $this->assertEquals($item->id(), $payload['id']);
    $this->assertEquals($item->getEntityTypeId(), $payload['type']);
    $this->assertEquals($item->bundle(), $payload['bundle']);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    /** @var \Drupal\Core\Config\Config $publications_settings_mut */
    $publications_settings_mut = $this->configFactory->getEditable('os_publications.settings');
    $publications_settings_mut->setData($this->defaultPublicationsSettings);
    $publications_settings_mut->save(TRUE);

    parent::tearDown();

    $this->queue->getBackend()->deleteQueue();
  }

}
