<?php

namespace Drupal\tabledrag_example\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Table drag example reset form.
 *
 * @package Drupal\tabledrag_example\Form
 */
class TableDragExampleResetForm extends ConfirmFormBase {

  /**
   * The ID of the item to delete.
   *
   * @var int
   */
  protected $id;

  /**
   * The name of the item to delete.
   *
   * @var string
   */
  protected $name;

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  /**
   * Construct a form.
   *
   * @param Drupal\Core\Database\Connection $database
   *   The database.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tabledrag_example_reset';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Reset demo data for TableDrag Example');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('tabledrag_example.description');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Are you sure you want to reset demo data?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Yes, Reset It!');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $db_connection = \Drupal::database();
    // Load tabledrag_example.install so that we can call
    // tabledrag_example_data().
    module_load_include('inc', 'tabledrag_example', 'tabledrag_example.data');
    $data = tabledrag_example_data();
    foreach ($data as $id => $item) {
      // Add 1 to each array key to match ID.
      $id++;
      $db_connection->update('tabledrag_example')
        ->fields([
          'weight' => 0,
          'pid' => 0,
          'description' => $item['description'],
          'itemgroup' => $item['itemgroup'],
        ])
        ->condition('id', $id, '=')
        ->execute();
    }
    $this->messenger()->addMessage($this->t('Data for TableDrag Example has been reset.'), 'status');
    $form_state->setRedirect('tabledrag_example.description');
  }

}
